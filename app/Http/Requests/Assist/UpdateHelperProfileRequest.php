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
            'expertise_ids.*'   => ['numeric', 'exists:expertise_types,id'],
            'country_id'        => ['nullable', 'numeric', 'exists:countries,id'],
            'city_id'           => ['nullable', 'numeric', 'exists:cities,id'],
            'terms_accepted'    => ['nullable', 'boolean'],
            // Notification preferences
            'notify_push'       => ['nullable', 'boolean'],
            'notify_whatsapp'   => ['nullable', 'boolean'],
            'notify_email'      => ['nullable', 'boolean'],
            // Verification / social links
            'instagram_url'     => ['nullable', 'url', 'max:500'],
            'facebook_url'      => ['nullable', 'url', 'max:500'],
            'linkedin_url'      => ['nullable', 'url', 'max:500'],
        ];
    }
}
