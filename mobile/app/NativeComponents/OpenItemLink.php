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

    public function openInBrowser(): void
    {
        $baseUrl = app(SunnyConnector::class)->resolveBaseUrl();
        $url = preg_replace('~/api$~', '', $baseUrl).'/i/'.(int) $this->param('id');
        $this->error = Browser::open($url) ? '' : 'Unable to open Sunny in the browser. Please try again.';
    }

    public function render(): View
    {
        return view('native.open-item-link');
    }
}
