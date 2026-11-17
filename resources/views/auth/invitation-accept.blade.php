<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation to {{ $invitation->organization->name }} — ServiceFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Instrument Sans', sans-serif; }</style>
</head>
<body class="h-full flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 text-slate-800 dark:text-slate-200">
    <div class="fixed inset-0 pointer-events-none -z-10 flex justify-center items-center">
        <div class="w-[600px] h-[600px] rounded-full bg-blue-500/10 dark:bg-blue-600/15 blur-3xl"></div>
    </div>

    <div class="w-full max-w-md space-y-6">
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 text-white shadow-lg shadow-blue-500/25 mb-2">
                <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                Team Invitation
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                You have been invited to join <strong class="text-slate-800 dark:text-slate-200">{{ $invitation->organization->name }}</strong> as a <strong class="text-slate-800 dark:text-slate-200">{{ $invitation->role->label() }}</strong>.
            </p>
        </div>

        <div class="bg-white dark:bg-slate-900 shadow-xl border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8 space-y-6">
            @if ($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-900/60 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (auth()->check())
                <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="space-y-4">
                    @csrf
                    <div class="p-4 rounded-xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200/60 dark:border-blue-900/40 text-xs space-y-1 text-slate-700 dark:text-slate-300">
                        <div>Signed in as: <span class="font-semibold text-slate-900 dark:text-white">{{ auth()->user()->email }}</span></div>
                        @if (! $isEmailMatch)
                            <div class="text-amber-600 dark:text-amber-400 font-medium pt-1">
                                Notice: You are accepting an invitation addressed to {{ $invitation->email }} with this signed-in account.
                            </div>
                        @endif
                    </div>

                    <button type="submit" class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-500/25 transition">
                        Accept Invitation & Enter Workspace
                    </button>
                </form>
            @elseif (isset($existingUser) && $existingUser)
                <div class="space-y-4 text-center">
                    <div class="p-4 rounded-xl bg-slate-100/80 dark:bg-slate-800/80 text-xs text-slate-600 dark:text-slate-300">
                        An account for <strong class="text-slate-900 dark:text-white">{{ $invitation->email }}</strong> already exists. Please sign in to accept your invitation.
                    </div>

                    <a href="/admin/login" class="w-full inline-flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-500/25 transition">
                        Sign In to Accept
                    </a>
                </div>
            @else
                <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                        <input id="email" type="email" value="{{ $invitation->email }}" disabled class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 text-slate-500 text-sm">
                    </div>

                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name') }}" placeholder="e.g. Jane Doe" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password</label>
                        <input id="password" name="password" type="password" required placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="Re-enter password" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit" class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-500/25 transition">
                        Create Account & Join Team
                    </button>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
