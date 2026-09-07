<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Domain\Products\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ProductStatus::class)], 'company_id' => ['prohibited'], 'tenant_id' => ['prohibited']];
    }
}
