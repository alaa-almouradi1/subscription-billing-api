<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'scope',
    'idempotency_key',
    'fingerprint',
    'status',
    'response_status',
    'response_body',
    'expires_at',
])]
class IdempotencyKey extends Model
{
    use MassPrunable;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Removed by the scheduled `model:prune` command once expired.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
