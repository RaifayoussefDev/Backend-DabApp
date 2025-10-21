<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventTicketRequest extends FormRequest
{
    public function authorize()
    {
        $event = $this->route('event');
        return $this->user()->id === $event->organizer_id;
    }

    public function rules()
    {
        return [
            'ticket_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity_available' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'ticket_type.required' => 'The ticket type is required',
            'price.required' => 'The price is required',
            'price.min' => 'The price must be positive',
        ];
    }
}
