@use('App\Enums\TeamRole')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\TeamSettings')
@use('App\Ui\Card')

@php
    $team = $this->team();
    $invitations = $this->invitations();
    $canInvite = $this->canInvite();
@endphp

<native:top-bar title="Invite your family" back />

@if ($team !== null && $canInvite)
    <x-ui.submit-bar
        ref="invite-send"
        :disabled="$this->disconnected"
        tap="send"
    >
        {{ $offline && ! $this->disconnected ? 'Try again' : 'Send invitation' }}
    </x-ui.submit-bar>
@endif

<scroll-view ref="invite-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    @if ($team === null)
        <column class="w-full items-center gap-2 px-6 py-12">
            <x-ui.heading size="lg" class="text-center">No team yet</x-ui.heading>
            <x-ui.text class="text-center">Pull down on Summary to download your teams.</x-ui.text>
        </column>
    @else
        <column class="w-full gap-6 px-4 py-6">
            <row class="w-full items-center gap-4 px-2">
                <column class="min-h-12 min-w-12 items-center justify-center rounded-full bg-theme-primary">
                    <text font="semibold" class="text-lg text-theme-on-primary">{{ mb_strtoupper(mb_substr($team->name, 0, 1)) }}</text>
                </column>
                <column class="flex-1 gap-0.5">
                    <x-ui.heading size="xl">{{ $team->name }}</x-ui.heading>
                    <x-ui.text>Share recipes, inventory, routines and lists with the people you live with.</x-ui.text>
                </column>
            </row>

            @if ($canInvite)
                @if ($sent)
                    <x-ui.alert
                        ref="invite-sent"
                        :ios="Ios::CheckmarkCircle"
                        :android="Android::CheckCircle"
                        title="Invitation sent"
                        :message="$sent['email'].' can join as '.($sent['role'] === 'admin' ? 'an admin' : 'a member').'.'"
                    />
                @endif

                <column class="w-full gap-4">
                    <outlined-text-input
                        ref="invite-email"
                        label="Email address"
                        placeholder="name@example.com"
                        keyboard="email"
                        content-type="email"
                        :error="$emailError !== ''"
                        :supporting="$emailError !== '' ? $emailError : 'We’ll email them a link that lasts 3 days.'"
                        native:model="email"
                    />

                    @include('native.role-options', ['ref' => 'invite-role', 'roles' => TeamRole::assignable(), 'selected' => TeamRole::tryFrom($role) ?? TeamRole::Member, 'action' => 'chooseRole'])

                    @if ($offline || $this->disconnected)
                        <x-ui.offline ref="invite-offline" :extra="$this->disconnected ? null : 'Nothing’s been sent'.(trim($email) !== '' ? ' to '.trim($email) : '').' yet.'" />
                    @endif

                    @if ($error !== '')
                        <text ref="invite-error" class="text-sm text-theme-destructive">{{ $error }}</text>
                    @endif
                </column>
            @else
                <column ref="invite-ask" class="{{ Card::classes() }} w-full">
                    <row class="w-full items-start gap-3 p-4">
                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                            <icon :ios="Ios::Lock" :android="Android::Lock" :size="18" class="text-theme-primary-text" />
                        </column>
                        <column class="flex-1 gap-1">
                            <x-ui.heading>Ask {{ TeamSettings::listNames(collect($this->inviters())->pluck('name')->map(TeamSettings::firstName(...))->all(), 'or') }}</x-ui.heading>
                            <x-ui.text size="sm">Only the owner and admins can invite people to {{ $team->name }} or cancel invitations.</x-ui.text>
                        </column>
                    </row>
                    @foreach ($this->inviters() as $inviter)
                        <divider />
                        <row class="min-h-12 w-full items-center gap-3 px-4 py-2">
                            <x-ui.avatar circle :initials="$inviter['initials']" />
                            <x-ui.heading class="flex-1">{{ $inviter['name'] }}</x-ui.heading>
                            <x-ui.badge size="sm" class="shrink-0">{{ $inviter['role']->label() }}</x-ui.badge>
                        </row>
                    @endforeach
                </column>
            @endif

            <column class="w-full gap-2">
                <x-ui.subheading class="px-2">Waiting to join</x-ui.subheading>
                <column ref="invite-waiting" class="{{ Card::classes() }} w-full">
                    @forelse ($invitations as $invitation)
                        <x-ui.row
                            :ref="'waiting-'.$invitation['code']"
                            :headline="$invitation['email']"
                            plain
                            :max-lines="1"
                            :badge="$invitation['role']->label()"
                            :supporting="$invitation['expires']"
                            :strong="$invitation['soon']"
                            :ios="Ios::Envelope"
                            :android="Android::Email"
                            icon-background="theme-primary/15"
                            icon-color="theme-primary-text"
                            :highlighted="$invitation['highlighted']"
                            :action="$canInvite ? ['ref' => 'cancel-invitation-'.$invitation['code'], 'ios' => Ios::Xmark, 'android' => Android::Close, 'label' => 'Cancel the invitation to '.$invitation['email'], 'tap' => 'confirmCancel(\''.$invitation['code'].'\')'] : null"
                            :last="$loop->last"
                        />
                    @empty
                        <x-ui.text ref="invite-none-waiting" class="px-4 py-4">No one is waiting to join.</x-ui.text>
                    @endforelse
                </column>
                <x-ui.text size="sm" class="px-2">
                    @if ($offline && $this->updatedAt())
                        Last updated at {{ $this->updatedAt() }}.
                    @elseif ($canInvite)
                        Cancel an invitation and its link stops working.
                    @else
                        They’ll join the team once they accept.
                    @endif
                </x-ui.text>
            </column>
        </column>
    @endif
</scroll-view>
