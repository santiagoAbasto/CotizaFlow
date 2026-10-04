<?php

declare(strict_types=1);

namespace App\Http\Requests\FollowUps;

use App\Domain\FollowUps\FollowUpOutcome;
use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('followUp');

        return $followUp instanceof FollowUp && ($this->user()?->can('complete', $followUp) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $nextAssignedTo = $this->input('next_assigned_to');

        $this->merge([
            'schedule_next' => $this->boolean('schedule_next'),
            'outcome_note' => is_string($this->input('outcome_note')) ? trim($this->input('outcome_note')) : $this->input('outcome_note'),
            'next_note' => is_string($this->input('next_note')) ? trim($this->input('next_note')) : $this->input('next_note'),
            'next_assigned_to' => $nextAssignedTo === '' ? null : (is_string($nextAssignedTo) && ctype_digit($nextAssignedTo) ? (int) $nextAssignedTo : $nextAssignedTo),
        ]);
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(FollowUpOutcome::class)],
            'outcome_note' => ['nullable', 'string', 'max:2000'],
            'schedule_next' => ['nullable', 'boolean'],
            'next_scheduled_at' => [Rule::requiredIf(fn (): bool => $this->boolean('schedule_next')), 'nullable', 'date_format:Y-m-d\\TH:i'],
            'next_assigned_to' => ['nullable', 'integer', 'min:1'],
            'next_note' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'quote_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'completed_by' => ['prohibited'],
            'status' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'pending_quote_id' => ['prohibited'],
        ];
    }
}
