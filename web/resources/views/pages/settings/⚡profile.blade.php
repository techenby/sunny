<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(UpdateUserProfileInformation $updateProfile): void
    {
        $user = Auth::user();
        $validated = $this->validate($this->profileRules($user->id));

        if ($validated['email'] === $user->email) {
            $updateProfile->handle($user, $validated);
        } elseif (! $this->sendWithinEmailLimits($user, fn () => $updateProfile->handle($user, $validated))) {
            $this->addError('email', $this->tooManyEmailsMessage($user));

            return;
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        if (! $this->sendWithinEmailLimits($user, fn () => $user->sendEmailVerificationNotification())) {
            Flux::toast(variant: 'danger', text: $this->tooManyEmailsMessage($user));

            return;
        }

        Session::flash('status', 'verification-link-sent');
    }

    private function emailLimitKey(User $user): string
    {
        return "verification-email-web:{$user->id}";
    }

    private function sendWithinEmailLimits(User $user, Closure $send): bool
    {
        if (RateLimiter::tooManyAttempts($this->dailyEmailLimitKey($user), User::VERIFICATION_EMAILS_PER_DAY)) {
            return false;
        }

        if (RateLimiter::attempt($this->emailLimitKey($user), 6, $send) === false) {
            return false;
        }

        RateLimiter::hit($this->dailyEmailLimitKey($user), 86400);

        return true;
    }

    private function dailyEmailLimitKey(User $user): string
    {
        return "{$this->emailLimitKey($user)}:day";
    }

    private function tooManyEmailsMessage(User $user): string
    {
        if (RateLimiter::tooManyAttempts($this->dailyEmailLimitKey($user), User::VERIFICATION_EMAILS_PER_DAY)) {
            return __('Too many verification emails today. Try again tomorrow.');
        }

        return __('Too many verification emails. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($this->emailLimitKey($user))]);
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
