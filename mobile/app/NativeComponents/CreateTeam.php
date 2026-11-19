<?php

namespace App\NativeComponents;

use App\Concerns\CallsSunnyOnline;
use App\Http\Integrations\Sunny\SunnyTeams;
use App\Support\Toast;
use Illuminate\View\View;
use Saloon\Http\Response;

class CreateTeam extends Component
{
    use CallsSunnyOnline;

    public string $name = '';

    public string $nameError = '';

    public string $error = '';

    public bool $offline = false;

    public bool $creating = false;

    public function create(): void
    {
        $name = trim($this->name);
        $this->nameError = '';
        $this->error = '';
        $this->offline = false;

        if ($name === '') {
            $this->nameError = 'Enter a name for the team.';

            return;
        }

        $this->creating = true;

        $created = $this->callSunny(
            fn () => app(SunnyTeams::class)->create($name),
            function (string $problem, ?Response $response): void {
                if ($problem === 'offline') {
                    $this->offline = true;
                } elseif (($message = self::firstError($response, 'name')) !== null) {
                    $this->nameError = $message;
                } else {
                    $this->error = self::failureMessage($problem, $response, 'Sunny couldn’t create the team right now.');
                }
            },
        );

        $this->creating = false;

        if ($created) {
            Toast::success('Created '.$name);
            $this->replace('/preferences/team');
        }
    }

    public function render(): View
    {
        return view('native.create-team');
    }
}
