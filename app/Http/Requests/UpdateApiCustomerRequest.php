<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        if (! $this->has('active') && $customer !== null) {
            $this->merge(['active' => (bool) $customer->active]);
        }
    }

    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')->ignore($customer->id),
            ],
            'active' => ['required', 'boolean'],
        ];
    }
}
