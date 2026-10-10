<?php

namespace App\NativeComponents;

use App\Actions\GenerateItemQrCode;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Share;
use NativePHP\Clipboard\Facades\Clipboard;

class InventoryItemQrCode extends NativeComponent
{
    public ?string $name = null;

    public ?string $path = null;

    public ?string $url = null;

    public ?string $copied = null;

    public string $error = '';

    public function mount(): void
    {
        $item = Inventory::find((int) $this->param('id'));

        if ($item === null) {
            return;
        }

        $this->name = $item['name'];
        ['path' => $this->path, 'url' => $this->url] = app(GenerateItemQrCode::class)->handle($item['id'], $item['name']);
    }

    public function share(): void
    {
        if ($this->path !== null) {
            Share::file('', '', $this->path);
        }
    }

    public function copyName(): void
    {
        $this->copy('name', $this->name, 'name');
    }

    public function copyUrl(): void
    {
        $this->copy('url', $this->url, 'link');
    }

    private function copy(string $field, ?string $text, string $label): void
    {
        if ($text === null) {
            return;
        }

        $copied = Clipboard::writeText($text);
        $this->copied = $copied ? $field : null;
        $this->error = $copied ? '' : 'Unable to copy the '.$label.'. Please try again.';
    }

    public function render(): View
    {
        return view('native.inventory-item-qr-code');
    }
}
