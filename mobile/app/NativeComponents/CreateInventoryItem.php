<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesInventoryItemForm;
use App\Enums\ItemType;
use App\Layouts\HidesTabBar;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;

class CreateInventoryItem extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesInventoryItemForm;

    public function mount(): void
    {
        $this->initializeTeam();
        $this->chooseType((ItemType::tryFrom((string) $this->data('type')) ?? ItemType::Item)->value);

        $parent = $this->parentChoice((int) $this->data('parent'));
        if ($parent !== null) {
            $this->parentId = $parent['id'];
        }
    }

    #[Computed]
    public function title(): string
    {
        return $this->type->addLabel();
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveItem();
    }

    public function render(): View
    {
        return view('native.create-inventory-item');
    }
}
