<?php

namespace App\Http\Requests\Assist;

use Illuminate\Validation\Rule;

class UpdateExpertiseTypeRequest extends AssistFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100',
                Rule::unique('expertise_types', 'name')->ignore($this->route('id'))],
            'icon' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}
