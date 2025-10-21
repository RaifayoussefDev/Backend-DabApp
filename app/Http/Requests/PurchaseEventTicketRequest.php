<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseEventTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quantity' => 'required|integer|min:1|max:10',
        ];
    }

    public function messages()
    {
        return [
            'quantity.required' => 'The quantity is required',
            'quantity.min' => 'You must purchase at least 1 ticket',
            'quantity.max' => 'You cannot purchase more than 10 tickets at once',
        ];
    }
}
