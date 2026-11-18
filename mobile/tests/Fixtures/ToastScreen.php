<?php

namespace Tests\Fixtures;

use App\Support\Toast;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class ToastScreen extends NativeComponent
{
    public ?int $opened = null;

    #[On(Toast::ACTION_PRESSED, when: ['actionId' => 'open-item'])]
    public function openItem(array $data): void
    {
        $this->opened = $data['item'];
    }

    public function render(): View
    {
        return view('toast-screen');
    }
}
