<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array returned by AnalyticsService::getAnalytics().
 * top_tickers is OMITTED (not null) from the response when the value is null.
 * Instantiated as: new AnalyticsResource($data)
 */
class AnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'avg_return_pct'       => $this->resource['avg_return_pct'],
            'median_return_pct'    => $this->resource['median_return_pct'],
            'positive_count'       => $this->resource['positive_count'],
            'negative_count'       => $this->resource['negative_count'],
            'returns_distribution' => $this->resource['returns_distribution'],
            'avg_diversification'  => $this->resource['avg_diversification'],
            'total_trades'         => $this->resource['total_trades'],
            'trades_per_day'       => $this->resource['trades_per_day'],
        ];

        // Only include top_tickers when it is not null (finished league)
        if ($this->resource['top_tickers'] !== null) {
            $data['top_tickers'] = $this->resource['top_tickers'];
        }

        return $data;
    }
}
