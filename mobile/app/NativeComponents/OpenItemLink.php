<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Team;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;

class OpenItemLink extends Component
{
    public string $error = '';

    /** @var array{name: string, place: array{name: string, type: string}|null}|null */
    public ?array $deleted = null;

    public function mount(): void
    {
        if (app(SunnyStore::class)->lastSyncedAt() === null) {
            $this->replaceStack('/');

            return;
        }

        $item = $this->linkedItem();

        if ($item?->trashed()) {
            $this->deleted = $this->describeDeleted($item);

            return;
        }

        if ($item !== null) {
            $this->openItem($item);
        }
    }

    public function openRecentlyDeleted(): void
    {
        $item = $this->linkedItem();

        if ($item === null) {
            $this->deleted = null;

            return;
        }

        if (! $item->trashed()) {
            $this->openItem($item);

            return;
        }

        if (! $this->selectTeamOf($item)) {
            $this->deleted = null;

            return;
        }

        $this->replace('/inventory/deleted');
    }

    private function openItem(Item $item): void
    {
        if (! $this->selectTeamOf($item)) {
            $this->deleted = null;

            return;
        }

        $this->replace('/inventory/'.$item->id);
    }

    private function linkedItem(): ?Item
    {
        return Item::withTrashed()->forCurrentServer()->find((int) $this->param('id'));
    }

    private function selectTeamOf(Item $item): bool
    {
        if ($item->team_id === app(SunnyTeam::class)->current()?->id) {
            return true;
        }

        if (! Team::query()->where('server', SunnyStore::server())->whereKey($item->team_id)->exists()) {
            return false;
        }

        app(SunnyTeam::class)->select($item->team_id);

        return true;
    }

    /**
     * @return array{name: string, place: array{name: string, type: string}|null}
     */
    private function describeDeleted(Item $item): array
    {
        $place = $item->parent_id === null ? null : Item::withTrashed()->forCurrentServer()->find($item->parent_id);

        return [
            'name' => $item->name,
            'place' => $place === null ? null : ['name' => $place->name, 'type' => $place->type->value],
        ];
    }

    /**
     * The app path for a scanned Sunny item URL, or null when it isn't one.
     */
    public static function pathFor(string $url): ?string
    {
        if (! str_starts_with($url, self::siteUrl().'/')) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $id = match (true) {
            preg_match('~^/i/(\d+)$~', $path, $matches) === 1 => $matches[1],
            preg_match('~^/[^/]+/inventory/(\d+)$~', $path, $matches) === 1 => $matches[1],
            preg_match('~^/[^/]+/inventory/?$~', $path) === 1 => $query['parentId'] ?? null,
            default => null,
        };

        return is_string($id) && ctype_digit($id) ? '/i/'.(int) $id : null;
    }

    public static function urlFor(int $id): string
    {
        return self::siteUrl().'/i/'.$id;
    }

    public function openInBrowser(): void
    {
        $url = self::urlFor((int) $this->param('id'));
        $this->error = Browser::open($url) ? '' : 'Unable to open the website. Please try again.';
    }

    protected static function siteUrl(): string
    {
        return app(SunnyConnector::class)->siteUrl();
    }

    public function render(): View
    {
        return view('native.open-item-link');
    }
}
