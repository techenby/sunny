<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class Preferences extends NativeComponent
{
    public ?int $activeTeamId = null;

    public function mount(): void
    {
        $this->activeTeamId = app(SunnyTeam::class)->current()?->id;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function teamOptions(): array
    {
        return app(SunnyTeam::class)->choices();
    }

    public function selectTeam(int $id): void
    {
        if (array_key_exists($id, $this->teamOptions)) {
            app(SunnyTeam::class)->select($id);
            $this->activeTeamId = $id;
        }
    }

    public function confirmLogOut(): void
    {
        Dialog::alert('Log out?', 'Recipes, inventory, and routines downloaded to this device will be removed.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Log out', 'style' => 'destructive'],
        ])->id('log-out')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'log-out' && $label === 'Log out') {
            $this->logOut();
        }
    }

    public function logOut(): void
    {
        try {
            app(SunnyAuth::class)->logout();
        } catch (RequestException|FatalRequestException) {
            Dialog::toast('Signed out on this device. Sunny could not revoke the remote session.');
        } catch (RuntimeException) {
            Dialog::toast('Unable to clear your saved login. Unlock your device and try again.');

            return;
        }

        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.preferences');
    }
}
