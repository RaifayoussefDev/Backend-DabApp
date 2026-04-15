<?php

namespace App\Http\Requests\Assist;

class CreateAssistanceRequestRequest extends AssistFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expertise_type_ids'   => ['required', 'array', 'min:1'],
            'expertise_type_ids.*' => ['integer', 'exists:expertise_types,id'],
            'latitude'          => ['required', 'numeric', 'between:-90,90'],
            'longitude'         => ['required', 'numeric', 'between:-180,180'],
            'location_label'    => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'motorcycle_id'     => ['nullable', 'numeric', 'exists:assist_motorcycles,id'],
            'photo_urls'        => ['nullable', 'array', 'max:5'],
            'photo_urls.*'      => ['string', 'url'],
        ];
    }
}
