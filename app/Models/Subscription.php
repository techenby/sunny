<?php

namespace App\Models;

use App\BillingFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $table = 'berries_subscriptions';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'frequency' => BillingFrequency::class,
            'billed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
