<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'legal_name', 'tax_id', 'email', 'phone', 'whatsapp', 'commercial_condition', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'email' ? mb_strtolower($value) : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'commercial_condition' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del cliente.',
            'email.email' => 'Ingresa un email válido.',
            '*.max' => 'El texto supera la longitud permitida.',
            '*.string' => 'Ingresa un texto válido.',
            '*.prohibited' => 'Este campo no se puede modificar desde este formulario.',
        ];
    }
}
