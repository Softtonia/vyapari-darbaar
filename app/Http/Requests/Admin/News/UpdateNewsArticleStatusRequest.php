<?php

namespace App\Http\Requests\Admin\News;

use App\Enums\NewsStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNewsArticleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge([
                'status' => strtolower(trim((string) $this->input('status'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(NewsStatus::class)],
            'scheduled_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $status = $this->input('status');
            if ($status === NewsStatus::SCHEDULED->value) {
                $scheduledAt = $this->input('scheduled_at');
                if (empty($scheduledAt)) {
                    $v->errors()->add('scheduled_at', 'The scheduled_at field is required when transitioning to scheduled status.');
                } elseif (strtotime($scheduledAt) <= time()) {
                    $v->errors()->add('scheduled_at', 'The scheduled_at date must be in the future.');
                }
            }
        });
    }
}
