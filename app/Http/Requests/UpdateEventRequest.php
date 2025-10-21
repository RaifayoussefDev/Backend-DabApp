<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize()
    {
        $event = $this->route('event');
        return $this->user()->id === $event->organizer_id;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'short_description' => 'nullable|string|max:500',
            'event_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'nullable',
            'venue_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'max_participants' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
            'featured_image' => 'nullable|string',
        ];
    }
}
