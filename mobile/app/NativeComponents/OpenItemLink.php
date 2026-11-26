<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;

class OpenItemLink extends Component
{
    public string $error = '';

    /** @var array{name: string, team_id: int, place: array{name: string, type: string}|null}|null */
    public ?array $deleted = null;

    public function mount(): void
    {
        if (app(SunnyStore::class)->lastSyncedAt() === null) {
            $this->replace('/');

            return;
        }

        $item = Item::forCurrentServer()->find((int) $this->param('id'));

        if ($item === null) {
            $this->deleted = $this->deletedItem((int) $this->param('id'));

            return;
        }

        if ($item->team_id !== app(SunnyTeam::class)->current()?->id) {
            app(SunnyTeam::class)->select($item->team_id);
        }

        $this->replace('/inventory/'.$item->id);
    }

    public function openRecentlyDeleted(): void
    {
        if ($this->deleted !== null && $this->deleted['team_id'] !== app(SunnyTeam::class)->current()?->id) {
            app(SunnyTeam::class)->select($this->deleted['team_id']);
        }

        $this->replace('/inventory/deleted');
    }

    /**
     * @return array{name: string, team_id: int, place: array{name: string, type: string}|null}|null
     */
    private function deletedItem(int $id): ?array
    {
        $item = Item::onlyTrashed()->forCurrentServer()->find($id);

        if ($item === null) {
            return null;
        }

        $place = $item->parent_id === null ? null : Item::withTrashed()->forCurrentServer()->find($item->parent_id);

        return [
            'name' => $item->name,
            'team_id' => $item->team_id,
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
