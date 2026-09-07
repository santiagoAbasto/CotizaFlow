<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Domain\Customers\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('customer')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CustomerStatus::class)], 'company_id' => ['prohibited'], 'tenant_id' => ['prohibited']];
    }
}
