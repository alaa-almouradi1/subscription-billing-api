<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'description', 'amount', 'period_start', 'period_end'])]
class InvoiceLine extends Model
{
    use HasUuids;

    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_PRORATION_CREDIT = 'proration_credit';

    public const TYPE_PRORATION_CHARGE = 'proration_charge';

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
        ];
    }
}
