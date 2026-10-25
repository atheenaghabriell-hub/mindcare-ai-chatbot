<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoodLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mood' => [
                'required',
                'string',
                Rule::in(['very_low', 'low', 'neutral', 'good', 'very_good']),
            ],
            'intensity' => ['nullable', 'integer', 'between:1,5'],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
