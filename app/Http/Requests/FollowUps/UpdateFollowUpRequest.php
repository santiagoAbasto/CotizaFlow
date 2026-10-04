<?php

declare(strict_types=1);

namespace App\Http\Requests\FollowUps;

use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('followUp');

        return $followUp instanceof FollowUp && ($this->user()?->can('update', $followUp) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $assignedTo = $this->input('assigned_to');

        $this->merge([
            'note' => is_string($this->input('note')) ? trim($this->input('note')) : $this->input('note'),
            'assigned_to' => $assignedTo === '' ? null : (is_string($assignedTo) && ctype_digit($assignedTo) ? (int) $assignedTo : $assignedTo),
        ]);
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'quote_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'completed_by' => ['prohibited'],
            'status' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
            'outcome' => ['prohibited'],
            'pending_quote_id' => ['prohibited'],
        ];
    }
}
