<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuideCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('guide_categories', 'name')->ignore($this->route('id'))
            ],
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Cette catégorie existe déjà',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
        ];
    }
}
