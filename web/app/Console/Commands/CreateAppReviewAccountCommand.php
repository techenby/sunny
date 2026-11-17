<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\FillDemoHousehold;
use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;

#[Description('Create or rebuild the App Review demo account, with a household of sample content that only it belongs to')]
#[Signature('demo:app-review {--email=appreview@sunnyhome.app : Email address to sign in with} {--password= : Password to sign in with (falls back to APP_REVIEW_PASSWORD, then a generated one)} {--timezone=America/Chicago : Household timezone} {--force : Rebuild an existing account without asking}')]
class CreateAppReviewAccountCommand extends Command
{
    public function handle(CreateTeam $createTeam, FillDemoHousehold $fillHousehold): int
    {
        $email = (string) $this->option('email');
        $existing = User::firstWhere('email', $email);

        if ($existing !== null && $existing->teamsBlockingDeletion()->isNotEmpty()) {
            $this->components->error("{$email} owns a team other people use, so it can't be rebuilt.");

            return self::FAILURE;
        }

        if ($existing !== null && ! $this->option('force') && ! confirm("Delete {$email} and all of its data, then build it again?", default: false)) {
            return self::FAILURE;
        }

        $configuredPassword = $this->option('password') ?? config('services.app_review.password');
        $password = filled($configuredPassword) ? (string) $configuredPassword : Str::password(16, symbols: false);

        $existing?->purge();

        DB::transaction(function () use ($email, $password, $createTeam, $fillHousehold): void {
            $user = User::create(['name' => 'Alex Rivera', 'email' => $email, 'password' => $password, 'onboarded_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $team = $createTeam->handle($user, "{$user->name}'s Team", isPersonal: true);
            $team->update(['timezone' => $this->option('timezone')]);

            $fillHousehold->handle($team, $user);
        });

        $this->components->info($existing === null ? "Created the App Review account {$email}." : "Rebuilt the App Review account {$email}.");

        if (blank($configuredPassword)) {
            $this->components->twoColumnDetail('Password', $password);
        }

        return self::SUCCESS;
    }
}
