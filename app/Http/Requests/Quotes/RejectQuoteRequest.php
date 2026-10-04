<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

final class RejectQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote && ($this->user()?->can('reject', $quote) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rejection_reason'))) {
            $this->merge(['rejection_reason' => trim($this->input('rejection_reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
            'company_id' => ['prohibited'],
            'sent_at' => ['prohibited'],
            'accepted_at' => ['prohibited'],
            'rejected_at' => ['prohibited'],
            'expired_at' => ['prohibited'],
        ];
    }
}
