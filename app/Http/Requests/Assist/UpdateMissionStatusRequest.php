<?php

namespace App\Http\Requests\Assist;

class UpdateMissionStatusRequest extends AssistFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:en_route,arrived,completed'],
        ];
    }
}
