<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['bail', 'required', 'uuid', 'exists:customers,id'],
            'plan_id' => ['bail', 'required', 'uuid', 'exists:plans,id'],
        ];
    }
}
