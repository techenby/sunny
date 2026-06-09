<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KioskDevice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KioskDevice>
 */
class KioskDeviceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'pairing_code' => KioskDevice::generatePairingCode(),
            'user_agent' => fake()->userAgent(),
            'last_ip' => fake()->ipv4(),
            'expires_at' => now()->addMinutes(KioskDevice::PAIRING_TTL_MINUTES),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'paired_at' => null,
            'user_id' => null,
            'team_id' => null,
            'expires_at' => now()->addMinutes(KioskDevice::PAIRING_TTL_MINUTES),
        ]);
    }

    public function paired(?User $user = null, ?Team $team = null): self
    {
        return $this->state(fn (): array => [
            'user_id' => $user ?? User::factory(),
            'team_id' => $team ?? Team::factory(),
            'paired_at' => now(),
            'expires_at' => null,
            'pairing_code' => null,
            'name' => fake()->words(2, true),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'paired_at' => null,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
