<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class Dashboard extends NativeComponent
{
    /**
     * @return list<array{id: int, name: string, supporting: string, url: string}>
     */
    #[Computed]
    public function recentRecipes(): array
    {
        return collect(Recipes::recent())
            ->map(fn (array $recipe): array => [
                'id' => $recipe['id'],
                'name' => $recipe['name'],
                'supporting' => $this->activity($recipe),
                'url' => '/recipes/'.$recipe['id'],
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, type: ItemType, supporting: string, url: string}>
     */
    #[Computed]
    public function recentItems(): array
    {
        return collect(Inventory::recent())
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'supporting' => $item['type']->label().' · '.$this->activity($item),
                'url' => '/inventory/'.$item['id'],
            ])
            ->all();
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
        return view('native.dashboard');
    }

    /**
     * "Added …" for a record that has never changed since it was created, "Updated …" otherwise.
     *
     * @param  array{created_at: string, updated_at: string}  $record
     */
    private function activity(array $record): string
    {
        $verb = $record['created_at'] === $record['updated_at'] ? 'Added' : 'Updated';

        return $verb.' '.Carbon::parse($record['updated_at'])->diffForHumans();
    }
}
