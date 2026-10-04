<?php

namespace App\Http\Requests;

use App\Domain\Billing\BillingInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
