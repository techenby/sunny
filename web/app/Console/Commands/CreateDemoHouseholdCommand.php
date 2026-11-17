<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\FillDemoHousehold;
use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

#[Description('Create a demo account with a household full of sample recipes, inventory, lists and routines')]
#[Signature('demo:household {email : Email address to sign in with} {--password= : Password to sign in with (prompted for when omitted)} {--timezone=America/Chicago : Household timezone}')]
class CreateDemoHouseholdCommand extends Command
{
    public function handle(CreateTeam $createTeam, FillDemoHousehold $fillHousehold): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->components->error("A user with the email {$email} already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? password('Password for the demo account', required: true);

        DB::transaction(function () use ($email, $password, $createTeam, $fillHousehold): void {
            $user = $this->member('Alex Rivera', $email, $password);

            $team = $createTeam->handle($user, 'The Rivera Family');
            $team->update(['timezone' => $this->option('timezone')]);

            $createTeam->handle($user, "{$user->name}'s Team", isPersonal: true);
            $user->switchTeam($team);

            $family = collect(['Jordan', 'Maya', 'Leo'])->mapWithKeys(function (string $name) use ($email, $team): array {
                $member = $this->member("{$name} Rivera", Str::replaceFirst('@', '+' . Str::lower($name) . '@', $email), Str::password());
                $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);
                $member->switchTeam($team);

                return [$name => $member];
            });

            $fillHousehold->handle($team, $user, $family->all());
        });

        $this->components->info("Created the demo household for {$email}.");

        return self::SUCCESS;
    }

    private function member(string $name, string $email, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
