<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'ticker'       => $this->ticker,
            'action'       => $this->action,
            'quantity'     => $this->quantity,
            'price'        => $this->price,
            'total_amount' => $this->total_amount,
            'executed_at'  => $this->executed_at,
        ];
    }
}
