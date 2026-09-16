<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostToKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => 'required|in:cash,rek',
            'account_id' => 'required|exists:accounts,id'
        ];
    }
}
