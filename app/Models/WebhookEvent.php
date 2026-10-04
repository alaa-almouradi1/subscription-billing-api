<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment-provider event that has been applied (see WebhookProcessor).
 * Kept long enough to recognise redeliveries, then pruned.
 */
class WebhookEvent extends Model
{
    use MassPrunable;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'received_at',
            '<=',
            now()->subDays((int) config('billing.retention.webhook_event_days')),
        );
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }
}
