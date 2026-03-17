<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array returned by CompareController.
 * Instantiated as: new CompareResource($data)
 */
class CompareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user1'          => $this->resource['user1'],
            'user2'          => $this->resource['user2'],
            'shared_tickers' => $this->resource['shared_tickers'],
            'evolution'      => $this->resource['evolution'],
        ];
    }
}
