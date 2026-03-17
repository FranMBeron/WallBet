<?php

namespace Database\Factories;

use App\Enums\TradeAction;
use App\Models\TradeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TradeLog>
 */
class TradeLogFactory extends Factory
{
    protected $model = TradeLog::class;

    public function definition(): array
    {
        $quantity    = fake()->randomFloat(6, 0.01, 10);
        $price       = fake()->randomFloat(2, 50, 500);
        $totalAmount = round($quantity * $price, 2);

        return [
            // league_id and user_id must be provided by tests — no default to avoid stale UUIDs
            'ticker'       => fake()->randomElement(['AAPL', 'GOOGL', 'MSFT', 'TSLA']),
            'action'       => TradeAction::Buy,
            'quantity'     => $quantity,
            'price'        => $price,
            'total_amount' => $totalAmount,
            'executed_at'  => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /** State: BUY action */
    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => TradeAction::Buy,
        ]);
    }

    /** State: SELL action */
    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => TradeAction::Sell,
        ]);
    }
}
