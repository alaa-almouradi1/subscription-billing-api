<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'event_type', 'partition_key', 'payload', 'occurred_at'])]
class OutboxMessage extends Model
{
    use MassPrunable;

    public const SCHEMA_VERSION = 1;

    public $timestamps = false;

    protected $attributes = [
        'attempts' => 0,
    ];

    /**
     * Published messages are only kept for troubleshooting; unpublished ones
     * are never pruned.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->subDays((int) config('billing.retention.outbox_days')));
    }

    /**
     * The message as consumers see it (the event contract, see docs/events.md).
     */
    public function toWireFormat(): string
    {
        return json_encode([
            'id' => $this->event_id,
            'type' => $this->event_type,
            'version' => self::SCHEMA_VERSION,
            'source' => 'subscription-billing-api',
            'occurred_at' => $this->occurred_at->format('Y-m-d\TH:i:s.uP'),
            'data' => $this->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
