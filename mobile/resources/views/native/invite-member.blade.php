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
                    <row ref="invite-sent" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                        <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="20" class="text-theme-primary-text" />
                        <column class="flex-1 gap-0.5">
                            <text font="semibold" class="text-base text-theme-primary-text">Invitation sent</text>
                            <text class="text-base text-theme-on-surface">{{ $sent['email'] }} can join as {{ $sent['role'] === 'admin' ? 'an admin' : 'a member' }}.</text>
                        </column>
                    </row>
                @endif

                <column class="w-full gap-2">
                    <column class="{{ Card::classes() }} w-full gap-4 p-4">
                        <outlined-text-input
                            ref="invite-email"
                            label="Email address"
                            placeholder="name@example.com"
                            keyboard="email"
                            content-type="email"
                            :ios-leading-icon="Ios::Envelope"
                            :android-leading-icon="Android::Email"
                            :error="$emailError !== ''"
                            :supporting="$emailError !== '' ? $emailError : null"
                            native:model="email"
                        />

                        @include('native.role-options', ['ref' => 'invite-role', 'roles' => TeamRole::assignable(), 'selected' => TeamRole::tryFrom($role) ?? TeamRole::Member, 'action' => 'chooseRole'])

                        @if ($offline)
                            <row ref="invite-offline" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                                <native:icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-primary-text" />
                                <column class="flex-1 gap-0.5">
                                    <text font="semibold" class="text-base text-theme-primary-text">You’re offline</text>
                                    <text class="text-base text-theme-on-surface">Sending an invitation needs a connection, since Sunny emails it right away. Nothing’s been sent{{ trim($email) !== '' ? ' to '.trim($email) : '' }} yet.</text>
                                </column>
                            </row>
                        @endif

                        @if ($error !== '')
                            <text ref="invite-error" class="text-sm text-theme-destructive">{{ $error }}</text>
                        @endif

                        <x-ui.button
                            ref="invite-send"
                            class="w-full"
                            tap="send"
                        >
                            {{ $offline ? 'Try again' : 'Send invitation' }}
                        </x-ui.button>
                    </column>
                    <x-ui.text size="sm" class="px-2">We’ll email them a link that lasts 3 days.</x-ui.text>
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
                            <x-ui.badge size="sm" class="shrink-0" :color="$inviter['role'] === TeamRole::Admin ? 'red' : null">{{ $inviter['role']->label() }}</x-ui.badge>
                        </row>
                    @endforeach
                </column>
            @endif

            <column class="w-full gap-2">
                <x-ui.subheading class="px-2">Waiting to join</x-ui.subheading>
                <column ref="invite-waiting" class="{{ Card::classes() }} w-full">
                    @forelse ($invitations as $invitation)
                        <row ref="waiting-{{ $invitation['code'] }}" class="min-h-12 w-full items-center gap-3 px-4 py-2 {{ $invitation['highlighted'] ? 'bg-theme-primary/10' : '' }}">
                            <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                                <icon :ios="Ios::Envelope" :android="Android::Email" :size="16" class="text-theme-primary-text" />
                            </column>
                            <column class="flex-1 gap-1">
                                <text max-lines="1" class="text-base text-theme-on-surface">{{ $invitation['email'] }}</text>
                                <x-ui.badge size="sm" class="self-start" :color="$invitation['role'] === TeamRole::Admin ? 'red' : null">{{ $invitation['role']->label() }}</x-ui.badge>
                                <text class="text-sm {{ $invitation['soon'] ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ $invitation['expires'] }}</text>
                            </column>
                            @if ($canInvite)
                                <x-ui.button
                                    ref="cancel-invitation-{{ $invitation['code'] }}"
                                    variant="secondary"
                                    :ios="Ios::Xmark"
                                    :android="Android::Close"
                                    :a11y-label="'Cancel the invitation to '.$invitation['email']"
                                    tap="confirmCancel('{{ $invitation['code'] }}')"
                                />
                            @endif
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-16" />
                        @endunless
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
