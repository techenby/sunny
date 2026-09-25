<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Actions\Inventory\ImportItemsFromAmazonAction;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ImportItemsForm extends Form
{
    #[Validate('required|file|mimes:csv,txt')]
    public $file = null;

    public bool $filterGifts = true;

    public bool $filterConsumables = true;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function process($parentId)
    {
        $this->validateOnly('file');

        $result = resolve(ImportItemsFromAmazonAction::class)->handle(
            $this->file,
            Auth::user()->currentTeam,
            $parentId,
            [
                'filterGifts' => $this->filterGifts,
                'filterConsumables' => $this->filterConsumables,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
            ],
        );

        $this->reset('file', 'filterGifts', 'filterConsumables', 'startDate', 'endDate');

        Flux::toast(
            text: __('Imported :imported items, skipped :skipped.', [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]),
            heading: __('Import complete'),
            variant: 'success',
        );
    }
}
