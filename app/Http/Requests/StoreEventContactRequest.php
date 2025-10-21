<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventContactRequest extends FormRequest
{
    public function authorize()
    {
        $event = $this->route('event');
        return $this->user()->id === $event->organizer_id;
    }

    public function rules()
    {
        return [
            'contact_type' => 'required|in:organizer,support,emergency',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function messages()
    {
        return [
            'contact_type.required' => 'The contact type is required',
            'contact_type.in' => 'Invalid contact type',
            'email.email' => 'The email must be valid',
        ];
    }
}
