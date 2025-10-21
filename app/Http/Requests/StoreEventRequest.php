<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'required|exists:event_categories,id',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'nullable|after:start_time',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'venue_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'max_participants' => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before:event_date',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'featured_image' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'The event title is required',
            'description.required' => 'The description is required',
            'category_id.required' => 'The category is required',
            'category_id.exists' => 'The selected category does not exist',
            'event_date.required' => 'The event date is required',
            'event_date.after_or_equal' => 'The date must be today or in the future',
            'start_time.required' => 'The start time is required',
            'end_time.after' => 'The end time must be after the start time',
            'registration_deadline.before' => 'The registration deadline must be before the event',
        ];
    }
}
