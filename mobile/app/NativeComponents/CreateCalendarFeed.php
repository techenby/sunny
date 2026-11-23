<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesCalendarFeedForm;
use App\Layouts\HidesTabBar;
use Illuminate\View\View;

class CreateCalendarFeed extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesCalendarFeedForm;

    public function save(): void
    {
        $this->submitFeed();
    }

    public function render(): View
    {
        return view('native.create-calendar-feed');
    }
}
