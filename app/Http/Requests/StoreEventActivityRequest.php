<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventActivityRequest extends FormRequest
{
    public function authorize()
    {
        $event = $this->route('event');
        return $this->user()->id === $event->organizer_id;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'nullable',
            'end_time' => 'nullable|after:start_time',
            'location' => 'nullable|string|max:255',
            'order_position' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'The activity title is required',
            'end_time.after' => 'The end time must be after the start time',
        ];
    }
}
