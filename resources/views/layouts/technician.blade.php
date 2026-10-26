<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ServiceFlow — Technician Workspace</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="h-full antialiased font-sans text-slate-800">
    <div class="min-h-full flex flex-col">
        <!-- Top Mobile-Friendly Header -->
        <header class="bg-slate-900 text-white shadow-md sticky top-0 z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-sm shadow-blue-500/50">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                <path d="M5 19l4-4"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-base font-semibold tracking-tight text-white block leading-none">ServiceFlow</span>
                            <span class="text-xs text-blue-400 font-medium">Technician Workspace</span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="hidden sm:block text-right">
                            <div class="text-sm font-medium text-white">{{ auth()->user()?->name }}</div>
                            <div class="text-xs text-slate-400">{{ auth()->user()?->currentMembership()?->organization?->name ?? 'Service Team' }}</div>
                        </div>

                        <!-- Sign Out Form -->
                        <form method="POST" action="/admin/logout" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition">
                                <svg class="w-4 h-4 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
