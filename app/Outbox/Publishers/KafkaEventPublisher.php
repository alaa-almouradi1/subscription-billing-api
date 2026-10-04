<?php

namespace App\Outbox\Publishers;

use App\Models\OutboxMessage;
use App\Outbox\EventPublisher;
use RdKafka\Conf;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RuntimeException;

/**
 * Publishes to Kafka through ext-rdkafka (librdkafka).
 *
 * The producer is idempotent with acks=all, and every message is flushed
 * and confirmed before the relay marks it as published.
 */
final class KafkaEventPublisher implements EventPublisher
{
    private ?Producer $producer = null;

    private ?ProducerTopic $topic = null;

    /** @var list<string> */
    private array $deliveryErrors = [];

    public function __construct(
        private readonly string $brokers,
        private readonly string $topicName,
        private readonly int $flushTimeoutMs = 10_000,
    ) {}

    public function publish(OutboxMessage $message): void
    {
        $this->deliveryErrors = [];

        $this->topic()->producev(
            RD_KAFKA_PARTITION_UA,
            0,
            $message->toWireFormat(),
            $message->partition_key,
            [
                'event-id' => $message->event_id,
                'event-type' => $message->event_type,
            ],
        );

        $result = $this->producer()->flush($this->flushTimeoutMs);

        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new RuntimeException('Kafka did not confirm delivery: '.rd_kafka_err2str($result));
        }

        if ($this->deliveryErrors !== []) {
            throw new RuntimeException('Kafka delivery failed: '.implode('; ', $this->deliveryErrors));
        }
    }

    private function producer(): Producer
    {
        if ($this->producer !== null) {
            return $this->producer;
        }

        if (! extension_loaded('rdkafka')) {
            throw new RuntimeException('The rdkafka PHP extension is required for BILLING_EVENTS_DRIVER=kafka.');
        }

        $conf = new Conf;
        $conf->set('bootstrap.servers', $this->brokers);
        $conf->set('enable.idempotence', 'true');
        $conf->set('acks', 'all');
        $conf->setDrMsgCb(function ($kafka, Message $message): void {
            if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->deliveryErrors[] = $message->errstr();
            }
        });

        return $this->producer = new Producer($conf);
    }

    private function topic(): ProducerTopic
    {
        return $this->topic ??= $this->producer()->newTopic($this->topicName);
    }
}
