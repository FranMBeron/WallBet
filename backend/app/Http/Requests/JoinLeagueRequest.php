<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string'],
        ];
    }
}
