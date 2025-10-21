<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventFaqRequest extends FormRequest
{
    public function authorize()
    {
        $event = $this->route('event');
        return $this->user()->id === $event->organizer_id;
    }

    public function rules()
    {
        return [
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:2000',
            'order_position' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'question.required' => 'The question is required',
            'answer.required' => 'The answer is required',
        ];
    }
}
