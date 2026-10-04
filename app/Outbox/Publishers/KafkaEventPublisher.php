<?php

namespace App\Outbox\Publishers;

use App\Outbox\EventPublisher;
use RdKafka\Conf;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RuntimeException;

/**
 * Publishes to Kafka through ext-rdkafka (librdkafka).
 *
 * The producer is idempotent with acks=all (no duplicates or reordering
 * from internal retries), and a batch is only reported as published once
 * every message in it has been acknowledged.
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

    public function publishBatch(array $messages): void
    {
        $this->deliveryErrors = [];

        // Queue the whole batch locally; librdkafka groups it into a few
        // compressed requests per partition.
        foreach ($messages as $message) {
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
            $this->producer()->poll(0);
        }

        // One wait for all acknowledgements (and delivery reports).
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
        $conf->set('compression.type', 'lz4');
        $conf->set('linger.ms', '5');
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
