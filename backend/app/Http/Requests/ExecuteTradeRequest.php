<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol'     => ['required', 'string'],
            'direction'  => ['required', 'in:BUY,SELL'],
            'order_type' => ['required', 'in:MARKET,LIMIT,STOP,STOP_LIMIT'],
            'amount'     => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
