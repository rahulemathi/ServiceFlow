@php
    $user = filament()->auth()->user();
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <div class="flex items-center justify-between gap-4 w-full">
            <div class="flex items-center gap-3.5 min-w-0">
                <x-filament-panels::avatar.user
                    size="lg"
                    :user="$user"
                    loading="lazy"
                />

                <div class="fi-account-widget-main min-w-0">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
                    </h2>

                    <p class="text-base font-bold text-slate-900 dark:text-white truncate">
                        {{ filament()->getUserName($user) }}
                    </p>
                </div>
            </div>

            <form
                action="{{ filament()->getLogoutUrl() }}"
                method="post"
                class="fi-account-widget-logout-form shrink-0"
            >
                @csrf

                <button
                    type="submit"
                    title="{{ __('filament-panels::widgets/account-widget.actions.logout.label') }}"
                    aria-label="{{ __('filament-panels::widgets/account-widget.actions.logout.label') }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 hover:text-rose-800 border border-rose-200/80 shadow-2xs dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/60 dark:hover:bg-rose-900/50 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-rose-500/50 focus:ring-offset-1 active:scale-[0.98] cursor-pointer"
                >
                    <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    <span>{{ __('filament-panels::widgets/account-widget.actions.logout.label') }}</span>
                </button>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
