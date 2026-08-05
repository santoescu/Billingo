<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text"  autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email"  autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
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
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <flux:separator variant="subtle" class="my-6" />

        <div class="space-y-4">
            <div>
                <flux:heading>{{ __('My companies') }}</flux:heading>
                <flux:subheading>{{ __('Companies you belong to and your role in each one.') }}</flux:subheading>
            </div>

            @php $companies = $this->companies(); @endphp

            @if ($companies->isEmpty())
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('You have no registered companies yet.') }}</p>
            @else
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-neutral-700 dark:border-neutral-700">
                    @foreach ($companies as $company)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $company->name }}</span>
                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">
                                {{ $company->membership->role ? __(ucfirst($company->membership->role)) : __('Member') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <flux:separator variant="subtle" class="my-6" />

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
