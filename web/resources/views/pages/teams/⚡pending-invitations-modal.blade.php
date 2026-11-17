<?php

use App\Actions\Teams\AcceptTeamInvitation;
use App\Actions\Teams\DeclineTeamInvitation;
use App\Models\TeamInvitation;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showPendingInvitationsModal = true;

    public function mount(): void
    {
        if (session()->pull('team-invitation-accepted')) {
            Flux::toast(variant: 'success', text: __('Invitation accepted.'));
        }
    }

    /** @return Collection<int, array{code: string, inviter_name: string, team_name: string}> */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->pendingFor(Auth::user())
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviter_name' => $invitation->inviter->name,
                'team_name' => $invitation->team->name,
            ]);
    }

    public function acceptInvitation(AcceptTeamInvitation $acceptInvitation, string $code): void
    {
        $user = Auth::user();

        $user->switchTeam($acceptInvitation->handle($user, $this->findPendingInvitation($code)));

        session()->flash('team-invitation-accepted', true);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function declineInvitation(DeclineTeamInvitation $declineInvitation, string $code): void
    {
        $declineInvitation->handle(Auth::user(), $this->findPendingInvitation($code));

        Flux::toast(variant: 'success', text: __('Invitation declined.'));
    }

    private function findPendingInvitation(string $code): TeamInvitation
    {
        return TeamInvitation::query()
            ->where('code', $code)
            ->whereNull('accepted_at')
            ->firstOrFail();
    }
}; ?>

<div>
    @if ($this->pendingInvitations->isNotEmpty())
        <flux:modal name="pending-invitations" wire:model="showPendingInvitationsModal" focusable class="max-w-lg">
            <div data-test="pending-invitations-modal" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Pending team invitations') }}</flux:heading>
                    <flux:subheading>{{ __('Accept or decline the teams you have been invited to join.') }}</flux:subheading>
                </div>

                <div class="grid gap-4">
                    @foreach ($this->pendingInvitations as $invitation)
                        <div data-test="pending-invitation-row" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="space-y-1">
                                <p class="font-medium">{{ $invitation['team_name'] }}</p>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __(':inviter invited you to join this team.', ['inviter' => $invitation['inviter_name']]) }}
                                </flux:text>
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <flux:button
                                    variant="filled"
                                    wire:click="declineInvitation('{{ $invitation['code'] }}')"
                                    wire:loading.attr="disabled"
                                    data-test="pending-invitation-decline"
                                >
                                    {{ __('Decline') }}
                                </flux:button>

                                <flux:button
                                    variant="primary"
                                    wire:click="acceptInvitation('{{ $invitation['code'] }}')"
                                    wire:loading.attr="disabled"
                                    data-test="pending-invitation-accept"
                                >
                                    {{ __('Accept') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:modal>
    @endif
</div>
