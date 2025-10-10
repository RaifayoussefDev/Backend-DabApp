<?php

// app/Http/Requests/UpdateGuideRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published,archived',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas',
            'tags.*.exists' => 'Un ou plusieurs tags n\'existent pas',
            'status.in' => 'Le statut doit être: draft, published ou archived',
        ];
    }
}
