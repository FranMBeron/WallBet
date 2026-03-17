<?php

namespace App\Http\Requests;

use App\Enums\LeagueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:100'],
            'type'             => ['required', Rule::enum(LeagueType::class)],
            'buy_in'           => ['required', 'numeric', 'min:30'],
            'max_participants' => ['required', 'integer', 'min:2'],
            'starts_at'        => ['required', 'date', 'after:now'],
            'ends_at'          => ['required', 'date', 'after:starts_at'],
            'is_public'        => ['required', 'boolean'],
            'description'      => ['nullable', 'string'],
            'password'         => ['nullable', 'string', Rule::requiredIf(fn () => $this->boolean('is_public') === false)],
        ];
    }
}
