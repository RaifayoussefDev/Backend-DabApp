<?php

namespace App\Http\Requests\Assist;

class CreateExpertiseTypeRequest extends AssistFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:100', 'unique:expertise_types,name'],
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'icon'    => ['required', 'string', 'max:100'],
        ];
    }
}
