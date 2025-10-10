<?php

// app/Http/Requests/StoreGuideCategoryRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuideCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Géré par le middleware dans les routes
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:guide_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la catégorie est obligatoire',
            'name.unique' => 'Cette catégorie existe déjà',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
        ];
    }
}
