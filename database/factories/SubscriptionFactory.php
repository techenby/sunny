<?php

namespace Database\Factories;

use App\BillingFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Forge',
            'amount' => 19.99,
            'frequency' => BillingFrequency::MONTHLY,
            'billed_at' => now(),
        ];
    }
}
