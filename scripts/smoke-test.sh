#!/usr/bin/env bash
# End-to-end smoke test against a running `docker compose up` stack:
# signs up a customer, checks the first invoice was charged, and checks the
# resulting domain events reached Kafka through the outbox relay.
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
API_KEY="${API_KEY:-local-dev-key}"
RUN_ID="$(date +%s)-$RANDOM"

api() {
  local method="$1" path="$2" body="${3:-}"
  curl -fsS -X "$method" "$BASE_URL/api/v1$path" \
    -H "X-Api-Key: $API_KEY" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Idempotency-Key: smoke-$RUN_ID-$method-${path//\//-}" \
    ${body:+-d "$body"}
}

echo "Waiting for the API to become ready..."
for _ in $(seq 1 90); do
  if curl -fsS "$BASE_URL/api/health/ready" > /dev/null 2>&1; then break; fi
  sleep 2
done
curl -fsS "$BASE_URL/api/health/ready"
echo

customer_id=$(api POST /customers "{\"name\":\"Smoke Test\",\"email\":\"smoke-$RUN_ID@example.com\",\"payment_method\":\"tok_visa\"}" | jq -r '.data.id')
plan_id=$(api POST /plans "{\"code\":\"smoke-$RUN_ID\",\"name\":\"Smoke\",\"amount\":1999,\"currency\":\"EUR\",\"interval\":\"month\"}" | jq -r '.data.id')
subscription=$(api POST /subscriptions "{\"customer_id\":\"$customer_id\",\"plan_id\":\"$plan_id\"}")

status=$(echo "$subscription" | jq -r '.data.latest_invoice.status')
subscription_id=$(echo "$subscription" | jq -r '.data.id')
echo "Subscription $subscription_id, first invoice: $status"
[ "$status" = "paid" ] || { echo "Expected the first invoice to be paid"; exit 1; }

echo "Checking that events for this subscription reached Kafka..."
events=$(docker compose exec -T kafka /opt/kafka/bin/kafka-console-consumer.sh \
  --bootstrap-server kafka:9092 --topic billing.events --from-beginning \
  --property print.key=true --timeout-ms 20000 2>/dev/null | grep "$subscription_id" || true)

for type in subscription.created invoice.issued payment.succeeded invoice.paid; do
  echo "$events" | grep -q "\"type\":\"$type\"" || { echo "Missing event $type"; echo "$events"; exit 1; }
  echo "  ✓ $type"
done

echo "Smoke test passed."
