<?php

namespace App\Http\Requests\Assist;

class UpdateHelperProfileRequest extends AssistFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:100'],
            'level'             => ['nullable', 'string', 'in:standard,elite,vanguard'],
            'expertise_ids'     => ['nullable', 'array'],
            'expertise_ids.*'   => ['integer', 'exists:expertise_types,id'],
            'country_id'        => ['nullable', 'integer', 'exists:countries,id'],
            'city_id'           => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }
}
