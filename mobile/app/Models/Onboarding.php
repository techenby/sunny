<?php

namespace App\Models;

use App\Http\Integrations\Sunny\Requests\MarkOnboardedRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyWrites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class Onboarding extends Model
{
    protected $table = 'sunny_onboarding';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'set_up_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
            'reported_at' => 'immutable_datetime',
        ];
    }

    public static function current(): ?self
    {
        $userId = DB::table('sunny_accounts')->where('server', SunnyStore::server())->value('user_id');

        return $userId === null ? null : self::query()->where('server', SunnyStore::server())->where('user_id', $userId)->first();
    }

    public static function begin(int $userId, ?string $name = null): self
    {
        return self::query()->firstOrCreate(
            ['server' => SunnyStore::server(), 'user_id' => $userId],
            ['first_name' => $name === null ? null : (string) str($name)->trim()->before(' ')],
        );
    }

    public static function needsSetUp(): bool
    {
        $onboarding = self::current();

        return $onboarding !== null && $onboarding->set_up_at === null && $onboarding->skipped_at === null;
    }

    public function skip(): void
    {
        $this->update(['skipped_at' => now()]);
        $this->report();
        app(SunnySyncCoordinator::class)->dispatch();
    }

    public function report(): void
    {
        if ($this->reported_at !== null || ($this->set_up_at === null && $this->skipped_at === null)) {
            return;
        }

        try {
            app(SunnyWrites::class)->push();

            if (app(SunnyOutbox::class)->hasPending()) {
                return;
            }

            app(SunnyAuth::class)->authenticatedConnector()->send(new MarkOnboardedRequest);
        } catch (Throwable) {
            return;
        }

        $this->update(['reported_at' => now()]);
    }
}
