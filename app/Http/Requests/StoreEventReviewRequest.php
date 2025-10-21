<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventReviewRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages()
    {
        return [
            'rating.required' => 'The rating is required',
            'rating.min' => 'The minimum rating is 1',
            'rating.max' => 'The maximum rating is 5',
        ];
    }
}
