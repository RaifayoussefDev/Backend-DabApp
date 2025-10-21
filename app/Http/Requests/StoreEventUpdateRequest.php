<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventUpdateRequest extends FormRequest
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
            'content' => 'required|string',
            'is_important' => 'boolean',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'The title is required',
            'content.required' => 'The content is required',
        ];
    }
}
