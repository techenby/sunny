<?php

use App\Actions\InviteTeamMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Collection;

new class extends Component {
    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->ownsTeam($this->team), 403);

        $this->name = $this->team->name;
    }

    #[Computed]
    public function invitations(): Collection
    {
        return $this->team->invitations;
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->team->users;
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function inviteMember(): void
    {
        $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, $fail): void {
                    $existingUser = User::query()->where('email', $value)->first();

                    if ($existingUser && $this->team->hasUser($existingUser)) {
                        $fail(__('This user is already a team member.'));
                    }
                },
                Rule::unique('team_invitations', 'email')
                    ->where(fn ($query) => $query->where('team_id', $this->team->id)),
            ],
        ], [
            'email.unique' => __('This email has already been invited.'),
        ]);

        InviteTeamMember::handle($this->team, $this->email);

        $this->modal('invite-member')->close();
        $this->reset('email');

        unset($this->invitations);
    }

    public function updateTeamName(): void
    {
        abort_unless(Auth::user()->ownsTeam($this->team), 403);

        $this->validate(['name' => ['required', 'string', 'max:255']]);

        $this->team->update(['name' => $this->name]);

        $this->dispatch('team-updated');
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading class="sr-only">{{ __('Team Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Team')" :subheading="__('Update your team\'s information')">
        <form wire:submit="updateTeamName" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Team Name')" type="text" required autofocus autocomplete="off" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="team-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <flux:separator class="my-10" />

        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <flux:heading size="lg">{{ __('Team Members') }}</flux:heading>
                <flux:subheading>{{ __('Manage your team\'s members and invitations') }}</flux:subheading>
            </div>
            <div>
                <flux:modal.trigger name="invite-member">
                    <flux:button variant="primary" size="sm">{{ __('Invite') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->members as $member)
                    <flux:table.row :wire:key="'member-'.$member->id">
                        <flux:table.cell>{{ $member->name }}</flux:table.cell>
                        <flux:table.cell>{{ $member->email }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($this->team->user_id === $member->id)
                                <flux:badge size="sm" color="blue">{{ __('Owner') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">{{ __('Member') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach

                @foreach ($this->invitations as $invitation)
                    <flux:table.row :wire:key="'invitation-'.$invitation->id">
                        <flux:table.cell>&mdash;</flux:table.cell>
                        <flux:table.cell>{{ $invitation->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="yellow">{{ __('Invited') }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-pages::settings.layout>

    @teleport('body')
    <flux:modal name="invite-member" class="md:w-96">
        <form wire:submit="inviteMember" class="space-y-6">
            <flux:heading size="lg">{{ __('Invite member') }}</flux:heading>

            <flux:input wire:model="email" :label="__('Email')" type="email" />

            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Send invite') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
