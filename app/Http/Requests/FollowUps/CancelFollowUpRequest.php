<?php

declare(strict_types=1);

namespace App\Http\Requests\FollowUps;

use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;

final class CancelFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('followUp');

        return $followUp instanceof FollowUp && ($this->user()?->can('cancel', $followUp) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancellation_reason' => is_string($this->input('cancellation_reason')) ? trim($this->input('cancellation_reason')) : $this->input('cancellation_reason'),
        ]);
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'completed_by' => ['prohibited'],
            'cancelled_by' => ['prohibited'],
            'pending_quote_id' => ['prohibited'],
        ];
    }
}
