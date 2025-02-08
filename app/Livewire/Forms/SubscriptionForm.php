<?php

namespace App\Livewire\Forms;

use App\BillingFrequency;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SubscriptionForm extends Form
{
    public ?Subscription $subscription;

    public $name;
    public $frequency = '';
    public $amount;
    public $billed_at;
    public $due_at;
    public $notes;

    public function set(Subscription $subscription): void
    {
        $this->subscription = $subscription;
        $this->name = $subscription->name;
        $this->frequency = $subscription->frequency;
        $this->amount = $subscription->amount;
        $this->billed_at = $subscription->billed_at->format('Y-m-d');
        $this->due_at = $subscription->due_at?->format('Y-m-d');
        $this->notes = $subscription->notes;
    }

    public function store(): void
    {
        $validated = $this->validate();

        Subscription::create($validated);

        $this->reset();
    }

    public function update(): void
    {
        $validated = $this->validate();

        $this->subscription->update($validated);

        $this->reset();
    }

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'amount' => ['required', 'numeric'],
            'billed_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable'],
        ];
    }
}
