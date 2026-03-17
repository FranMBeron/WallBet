<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array returned by PortfolioService::buildPortfolio().
 * Instantiated as: new PortfolioResource($portfolioArray)
 */
class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'positions'      => $this->resource['positions'],
            'cash_available' => $this->resource['cash_available'],
            'total_value'    => $this->resource['total_value'],
            'return_pct'     => $this->resource['return_pct'],
        ];
    }
}
