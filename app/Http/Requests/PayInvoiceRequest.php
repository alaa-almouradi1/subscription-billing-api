<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional override; defaults to the customer's saved method.
            'payment_method' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
