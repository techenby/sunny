<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class OpenItemLink extends NativeComponent
{
    public string $error = '';

    public function mount(): void
    {
        if (app(SunnyStore::class)->lastSyncedAt() === null) {
            $this->replace('/');

            return;
        }

        $item = Item::forCurrentServer()->find((int) $this->param('id'));

        if ($item === null) {
            return;
        }

        if ($item->team_id !== app(SunnyTeam::class)->current()?->id) {
            app(SunnyTeam::class)->select($item->team_id);
        }

        $this->replace('/inventory/'.$item->id);
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
        $this->error = Browser::open($url) ? '' : 'Unable to open Sunny in the browser. Please try again.';
    }

    protected static function siteUrl(): string
    {
        return preg_replace('~/api$~', '', app(SunnyConnector::class)->resolveBaseUrl());
    }

    public function render(): View
    {
        return view('native.open-item-link');
    }
}
