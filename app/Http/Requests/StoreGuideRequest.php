<?php

// app/Http/Requests/StoreGuideRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères',
            'content.required' => 'Le contenu est obligatoire',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas',
            'tags.*.exists' => 'Un ou plusieurs tags n\'existent pas',
        ];
    }
}
