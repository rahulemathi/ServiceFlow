<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — ServiceFlow</title>
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

    <div class="w-full max-w-md space-y-6 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-500 text-white shadow-lg shadow-amber-500/25 mb-2">
            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ $title }}
        </h1>

        <div class="bg-white dark:bg-slate-900 shadow-xl border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8 space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                {{ $message }}
            </p>

            <div class="pt-4">
                <a href="/admin/login" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-xl text-white bg-blue-600 hover:bg-blue-500 shadow-sm transition">
                    Return to Sign In
                </a>
            </div>
        </div>
    </div>
</body>
</html>
