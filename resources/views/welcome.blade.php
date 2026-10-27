<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ServiceFlow — Field Service & Maintenance Management SaaS</title>
        <meta name="description" content="Manage customer requests, work orders, technicians, maintenance contracts, and billing in one centralized platform.">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 font-sans antialiased selection:bg-blue-600 selection:text-white">
        <!-- Accessibility Skip Link -->
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-blue-600 focus:text-white focus:rounded-lg focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Skip to main content
        </a>

        <!-- 3. Navigation Bar -->
        <header class="sticky top-0 z-40 w-full bg-white/95 backdrop-blur-md border-b border-slate-200/80 transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    <!-- Left: Brand Logo & Name -->
                    <div class="flex items-center gap-3">
                        <a href="#home" class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-xl p-1" aria-label="ServiceFlow Home">
                            <!-- Logo Brand Mark -->
                            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-600/25 group-hover:bg-blue-700 transition-colors">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                    <path d="M5 19l4-4" stroke-width="2"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold tracking-tight text-slate-900 group-hover:text-blue-600 transition-colors">
                                Service<span class="text-blue-600">Flow</span>
                            </span>
                        </a>
                    </div>

                    <!-- Center: Navigation Links -->
                    <nav class="hidden md:flex items-center gap-8" aria-label="Main Navigation">
                        <a href="#home" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Home</a>
                        <a href="#features" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Features</a>
                        <a href="#how-it-works" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">How It Works</a>
                        <a href="#pricing" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Pricing</a>
                        <a href="#about" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">About</a>
                        <a href="#contact" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Contact</a>
                    </nav>

                    <!-- Right: Auth & Primary Action -->
                    <div class="hidden sm:flex items-center gap-3">
                        @auth
                            <a href="{{ url('/admin') }}" class="text-sm font-semibold text-slate-700 hover:text-blue-600 px-4 py-2.5 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Dashboard
                            </a>
                            <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm shadow-blue-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:scale-[0.98]">
                                Open Portal
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-700 hover:text-blue-600 px-4 py-2.5 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Log In
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm shadow-blue-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:scale-[0.98]">
                                Get Started
                            </a>
                        @endauth
                    </div>

                    <!-- Mobile Hamburger Button -->
                    <div class="flex sm:hidden">
                        <button type="button" id="mobile-menu-toggle" class="p-2.5 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-controls="mobile-menu" aria-expanded="false" aria-label="Toggle navigation menu">
                            <svg id="hamburger-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div id="mobile-menu" class="hidden sm:hidden border-t border-slate-200/80 bg-white px-4 pt-3 pb-6 space-y-3 shadow-xl">
                <nav class="flex flex-col space-y-1">
                    <a href="#home" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">Home</a>
                    <a href="#features" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">Features</a>
                    <a href="#how-it-works" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">How It Works</a>
                    <a href="#pricing" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">Pricing</a>
                    <a href="#about" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">About</a>
                    <a href="#contact" class="mobile-nav-link block px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">Contact</a>
                </nav>
                <div class="pt-4 border-t border-slate-100 flex flex-col gap-2.5">
                    @auth
                        <a href="{{ url('/admin') }}" class="w-full text-center px-4 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            Dashboard
                        </a>
                        <a href="{{ url('/admin') }}" class="w-full text-center px-5 py-3 rounded-xl text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-600/30 transition-all">
                            Open Portal
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full text-center px-4 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            Log In
                        </a>
                        <a href="{{ route('login') }}" class="w-full text-center px-5 py-3 rounded-xl text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-600/30 transition-all">
                            Get Started
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main id="main-content">
            <!-- 4. Hero Section -->
            <section id="home" class="relative overflow-hidden bg-gradient-to-b from-blue-50/70 via-slate-50/40 to-slate-50 pt-12 pb-20 lg:pt-20 lg:pb-28">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                        <!-- Left Column: Copy & Actions -->
                        <div class="lg:col-span-6 xl:col-span-6 flex flex-col items-start text-left">
                            <!-- Badge -->
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/80 mb-6 shadow-2xs">
                                <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                                Field Service Management Made Simple
                            </div>

                            <!-- Main Headline -->
                            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight leading-[1.15]">
                                Manage Your Service Operations <span class="text-blue-600">in One Place</span>
                            </h1>

                            <!-- Supporting Description -->
                            <p class="mt-6 text-lg sm:text-xl text-slate-600 leading-relaxed max-w-xl">
                                ServiceFlow helps service businesses manage customers, work orders, technicians, maintenance contracts, and billing with a powerful and easy-to-use platform.
                            </p>

                            <!-- CTAs -->
                            <div class="mt-8 flex flex-wrap items-center gap-4 w-full sm:w-auto">
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-600/25 transition-all duration-150 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <span>Get Started Free</span>
                                    <x-heroicon-o-arrow-right class="w-5 h-5" />
                                </a>
                                <button type="button" id="btn-open-demo-modal" class="inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl text-base font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-sm transition-all duration-150 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <x-heroicon-s-play class="w-3.5 h-3.5 ml-0.5" />
                                    </div>
                                    <span>Watch Demo</span>
                                </button>
                            </div>

                            <!-- Trust Statements -->
                            <div class="mt-10 pt-6 border-t border-slate-200/80 w-full flex flex-wrap items-center gap-y-3 gap-x-6 text-sm font-medium text-slate-600">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500 shrink-0" />
                                    <span>No credit card required</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500 shrink-0" />
                                    <span>Easy setup</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500 shrink-0" />
                                    <span>Built for growing businesses</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Visual Placeholder Composition -->
                        <div class="lg:col-span-6 xl:col-span-6 relative">
                            <!-- Background Accent Glow -->
                            <div class="absolute -inset-4 bg-gradient-to-tr from-blue-200/40 via-blue-100/30 to-indigo-100/40 rounded-3xl blur-2xl -z-10" aria-hidden="true"></div>

                            <!-- Composition Container -->
                            <div class="relative rounded-2xl bg-slate-900/5 p-2 sm:p-4 border border-slate-200/80 shadow-2xl backdrop-blur-sm">

                                <!-- [IMAGE PLACEHOLDER: Main Dashboard Screenshot] -->
                                <!-- Replace this mockup skeleton with your actual dashboard screenshot: -->
                                <!-- <img src="/images/dashboard-mockup.png" alt="ServiceFlow Operations Dashboard" class="w-full h-auto rounded-xl object-cover shadow-sm"> -->
                                <div class="w-full bg-white rounded-xl border border-slate-200 shadow-md overflow-hidden" aria-label="Interactive Dashboard Preview">
                                    <!-- Window Header / Browser Frame -->
                                    <div class="bg-slate-100/90 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                                            <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                                            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                                        </div>
                                        <div class="flex items-center gap-2 px-3 py-1 bg-white rounded-md border border-slate-200 text-xs text-slate-500 font-mono">
                                            <x-heroicon-o-lock-closed class="w-3 h-3 text-emerald-600" />
                                            <span>app.serviceflow.io/admin</span>
                                        </div>
                                        <div class="w-10"></div>
                                    </div>

                                    <!-- Mockup Dashboard Interior -->
                                    <div class="p-4 sm:p-5 bg-slate-50/50 space-y-4">
                                        <!-- Top Summary KPI Row -->
                                        <div class="grid grid-cols-3 gap-3">
                                            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                                                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Jobs</div>
                                                <div class="mt-1 flex items-baseline gap-2">
                                                    <span class="text-xl font-bold text-slate-900">28</span>
                                                    <span class="text-[11px] font-semibold text-emerald-600">+12%</span>
                                                </div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                                                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Today's Visits</div>
                                                <div class="mt-1 flex items-baseline gap-2">
                                                    <span class="text-xl font-bold text-slate-900">14</span>
                                                    <span class="text-[11px] font-semibold text-blue-600">On Track</span>
                                                </div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                                                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tech Utilization</div>
                                                <div class="mt-1 flex items-baseline gap-2">
                                                    <span class="text-xl font-bold text-slate-900">88%</span>
                                                    <span class="text-[11px] font-semibold text-emerald-600">Optimal</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Work Order Dispatch List Skeleton -->
                                        <div class="bg-white rounded-lg border border-slate-200 shadow-2xs p-3.5 space-y-2.5">
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                                <span class="text-xs font-bold text-slate-800">Dispatch Queue & Service Orders</span>
                                                <span class="text-[11px] text-blue-600 font-semibold cursor-pointer">Live Updates • 4 Active</span>
                                            </div>

                                            <!-- Row 1 -->
                                            <div class="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 border border-slate-100">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-md bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-[10px]">
                                                        WO
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-slate-900">#1042 — AC Compressor Replacement</div>
                                                        <div class="text-[11px] text-slate-500">Metro Plaza • Alex Rivera</div>
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800">
                                                    In Progress
                                                </span>
                                            </div>

                                            <!-- Row 2 -->
                                            <div class="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 border border-slate-100">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-md bg-amber-100 text-amber-700 font-bold flex items-center justify-center text-[10px]">
                                                        WO
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-slate-900">#1043 — Electrical Panel Safety Audit</div>
                                                        <div class="text-[11px] text-slate-500">Summit Health Center • David K.</div>
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">
                                                    Dispatched
                                                </span>
                                            </div>

                                            <!-- Row 3 -->
                                            <div class="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 border border-slate-100">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-md bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-[10px]">
                                                        WO
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-slate-900">#1041 — Chiller Preventive Maintenance</div>
                                                        <div class="text-[11px] text-slate-500">Apex Logistic Hub • Sarah T.</div>
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                                    Completed
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- [/IMAGE PLACEHOLDER: Main Dashboard Screenshot] -->

                                <!-- [IMAGE PLACEHOLDER: Technician Mobile App Mockup] -->
                                <!-- Positioned overlapping bottom-left -->
                                <!-- Replace with: <img src="/images/technician-mobile-app.png" alt="Mobile App for Field Technicians" class="w-full h-auto rounded-2xl"> -->
                                <div class="hidden sm:block absolute -bottom-6 -left-6 w-60 rounded-2xl bg-slate-900 text-white p-3 shadow-2xl border-2 border-slate-700 transform -rotate-1 hover:rotate-0 transition-transform">
                                    <div class="w-12 h-1 bg-slate-700 rounded-full mx-auto mb-2.5"></div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-bold text-slate-200">Technician App</span>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <div class="bg-slate-800 p-2.5 rounded-xl border border-slate-700 space-y-1.5 text-left">
                                        <div class="text-[10px] uppercase font-semibold tracking-wider text-blue-400">Current Assigned Job</div>
                                        <div class="text-xs font-bold text-white">WO #1042 — AC Service</div>
                                        <div class="text-[10px] text-slate-400">10:30 AM • Metro Plaza Suite 4B</div>
                                        <div class="pt-1 flex items-center justify-between">
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-medium">On Site</span>
                                            <span class="text-[10px] text-blue-400 font-semibold cursor-pointer">Open Work Order &rarr;</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- [/IMAGE PLACEHOLDER: Technician Mobile App Mockup] -->

                                <!-- [IMAGE PLACEHOLDER: Technician Photo / Field Specialist Badge] -->
                                <!-- Positioned on the right / top-right -->
                                <!-- Replace with: <img src="/images/technician-avatar.jpg" alt="Certified Field Technician" class="w-full h-full object-cover rounded-xl"> -->
                                <div class="hidden sm:flex absolute -top-5 -right-5 items-center gap-3 bg-white/95 backdrop-blur-md p-2.5 pr-4 rounded-2xl border border-slate-200 shadow-xl">
                                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-sm">
                                        <x-heroicon-o-wrench-screwdriver class="w-6 h-6" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Alex Rivera</div>
                                        <div class="text-[11px] text-slate-500">Lead HVAC Specialist</div>
                                        <div class="text-[10px] text-amber-500 font-semibold">★ 4.9 (142 Jobs)</div>
                                    </div>
                                </div>
                                <!-- [/IMAGE PLACEHOLDER: Technician Photo] -->

                                <!-- Floating Feature Label 1: Jobs & Schedules -->
                                <div class="absolute -top-3 left-10 bg-white/95 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-slate-200 shadow-md flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                                    <x-heroicon-o-calendar-days class="w-4 h-4 text-blue-600" />
                                    <span>Jobs & Schedules</span>
                                </div>

                                <!-- Floating Feature Label 2: Mobile App for Technicians -->
                                <div class="absolute -bottom-3 right-8 bg-white/95 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-slate-200 shadow-md flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                                    <x-heroicon-o-device-phone-mobile class="w-4 h-4 text-blue-600" />
                                    <span>Mobile App for Technicians</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Benefits Section -->
            <section class="bg-white py-16 border-y border-slate-200/80">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        <!-- Benefit 1 -->
                        <div class="flex items-start gap-4 p-2">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                                <x-heroicon-o-bolt class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Increase Productivity</h3>
                                <p class="mt-1 text-sm text-slate-600 leading-relaxed">Streamline operations and reduce manual work.</p>
                            </div>
                        </div>

                        <!-- Benefit 2 -->
                        <div class="flex items-start gap-4 p-2">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                                <x-heroicon-o-face-smile class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Improve Customer Satisfaction</h3>
                                <p class="mt-1 text-sm text-slate-600 leading-relaxed">Deliver faster and better service.</p>
                            </div>
                        </div>

                        <!-- Benefit 3 -->
                        <div class="flex items-start gap-4 p-2">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100">
                                <x-heroicon-o-currency-dollar class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Reduce Operational Costs</h3>
                                <p class="mt-1 text-sm text-slate-600 leading-relaxed">Optimize resources and prevent downtime.</p>
                            </div>
                        </div>

                        <!-- Benefit 4 -->
                        <div class="flex items-start gap-4 p-2">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-100">
                                <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Grow Your Business</h3>
                                <p class="mt-1 text-sm text-slate-600 leading-relaxed">Scale your service operations as your team grows.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6. Features Section -->
            <section id="features" class="py-24 bg-slate-50/70">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Section Header -->
                    <div class="text-center max-w-3xl mx-auto mb-16">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/60 mb-3">
                            Complete Operations Suite
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Everything You Need to Run Your Service Business
                        </h2>
                        <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed">
                            From service requests to invoices, ServiceFlow provides all the tools you need to manage your field operations efficiently.
                        </p>
                    </div>

                    <!-- 10 Feature Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- 1. Service Requests -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-inbox-arrow-down class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Service Requests</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Capture and manage customer requests easily with priority classification and seamless work-order conversion.</p>
                        </div>

                        <!-- 2. Work Orders -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-clipboard-document-check class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Work Orders</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Assign, schedule, and track jobs with full state transitions, work logs, and completion verification.</p>
                        </div>

                        <!-- 3. Customer Management -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-user-group class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Customer Management</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Keep customer profiles, service addresses, and contact points organized in one secure place.</p>
                        </div>

                        <!-- 4. Equipment Tracking -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-cog-6-tooth class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Equipment Tracking</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Manage customer machinery, serial numbers, warranty dates, location records, and maintenance logs.</p>
                        </div>

                        <!-- 5. Maintenance Contracts -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-calendar-days class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Maintenance Contracts</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Automate recurring maintenance schedules, prevent equipment downtime, and retain service agreements.</p>
                        </div>

                        <!-- 6. Technician Management -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-wrench-screwdriver class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Technician Management</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Manage job assignments, weekly availability schedules, workloads, and real-time technician utilization.</p>
                        </div>

                        <!-- 7. Scheduling & Dispatch -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-clock class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Scheduling & Dispatch</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Coordinate technicians and on-site visits with calendar-based scheduling and conflict prevention.</p>
                        </div>

                        <!-- 8. Estimates & Invoicing -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-document-text class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Estimates & Invoicing</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Generate estimates, convert them directly into customer invoices upon approval, and track sent statuses.</p>
                        </div>

                        <!-- 9. Payments -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <x-heroicon-o-banknotes class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Payments</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Track payment records, multiple payment methods, partial payments, and outstanding invoice balances.</p>
                        </div>

                        <!-- 10. Reports & Analytics (Spans across or neatly styled) -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 group md:col-span-2 lg:col-span-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                        <x-heroicon-o-presentation-chart-line class="w-6 h-6" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-900">Reports & Operational Analytics</h3>
                                        <p class="mt-1 text-sm text-slate-600 max-w-2xl leading-relaxed">Understand business performance with real-time KPI metrics for open requests, active work orders, technician availability, and revenue collection.</p>
                                    </div>
                                </div>
                                <a href="#how-it-works" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700 shrink-0">
                                    <span>Explore All Features</span>
                                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 7. How It Works Section -->
            <section id="how-it-works" class="py-24 bg-white border-t border-slate-200/80">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Section Header -->
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/60 mb-3">
                            Simple 3-Step Process
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            How ServiceFlow Works
                        </h2>
                        <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed">
                            Designed to get your entire service team coordinated without complicated training or lengthy setups.
                        </p>
                    </div>

                    <!-- 3 Connected Steps -->
                    <div class="relative">
                        <!-- Desktop Connecting Line -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-0.5 bg-blue-100 -translate-y-12 z-0" aria-hidden="true"></div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 relative z-10">
                            <!-- Step 1 -->
                            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200/80 shadow-sm flex flex-col items-center text-center">
                                <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white font-extrabold text-xl flex items-center justify-center shadow-lg shadow-blue-600/25 mb-6">
                                    01
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Create Your Workspace</h3>
                                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                                    Set up your organization, configure branch locations, and invite your team members with role-based access.
                                </p>
                                <div class="mt-6 w-full pt-4 border-t border-slate-200/60 flex items-center justify-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span>Tenant-isolated security</span>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200/80 shadow-sm flex flex-col items-center text-center">
                                <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white font-extrabold text-xl flex items-center justify-center shadow-lg shadow-blue-600/25 mb-6">
                                    02
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Manage Your Operations</h3>
                                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                                    Handle customer profiles, service requests, technician assignments, and dispatch schedules with zero friction.
                                </p>
                                <div class="mt-6 w-full pt-4 border-t border-slate-200/60 flex items-center justify-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>Real-time dispatch coordination</span>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200/80 shadow-sm flex flex-col items-center text-center">
                                <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white font-extrabold text-xl flex items-center justify-center shadow-lg shadow-blue-600/25 mb-6">
                                    03
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Track and Grow</h3>
                                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                                    Monitor job completion, automate preventive maintenance, issue invoices, and track payments to scale your business.
                                </p>
                                <div class="mt-6 w-full pt-4 border-t border-slate-200/60 flex items-center justify-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>Automated contract renewals</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 8. Audience / Use Cases Section -->
            <section id="use-cases" class="py-24 bg-slate-50/70">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Section Header -->
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/60 mb-3">
                            Target Industries
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Built for Service & Maintenance Businesses
                        </h2>
                        <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed">
                            Tailored workflows built specifically for the day-to-day realities of field service and maintenance teams.
                        </p>
                    </div>

                    <!-- 5 Audience Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Use Case 1 -->
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-5">
                                <x-heroicon-o-sun class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">AC & Refrigeration Maintenance</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Coordinate seasonal inspections, refrigerant leak checks, compressor replacements, and cooling performance contracts.</p>
                        </div>

                        <!-- Use Case 2 -->
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-5">
                                <x-heroicon-o-bolt class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Electrical & Equipment Repair</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Manage urgent diagnostic calls, circuit breaker audits, machinery repairs, safety certifications, and emergency dispatches.</p>
                        </div>

                        <!-- Use Case 3 -->
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-5">
                                <x-heroicon-o-truck class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Field Service Providers</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Dispatch mobile field technicians with clarity on location, customer history, required parts, and on-site job scopes.</p>
                        </div>

                        <!-- Use Case 4 -->
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-5">
                                <x-heroicon-o-building-office-2 class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Commercial Maintenance Teams</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Fulfill multi-facility service level agreements (SLAs), tenant service tickets, and scheduled preventive upkeep.</p>
                        </div>

                        <!-- Use Case 5 -->
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-2xs hover:shadow-md transition-shadow md:col-span-2 lg:col-span-2">
                            <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center mb-5">
                                <x-heroicon-o-chart-bar-square class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Small and Medium-Sized Service Businesses</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Replace disjointed paper records, text messages, and messy spreadsheets with a professional operations portal that gives your team total visibility.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 9. Pricing Section -->
            <section id="pricing" class="py-24 bg-white border-t border-slate-200/80">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Section Header -->
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100/80 text-emerald-800 border border-emerald-200/70 mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            100% Free Self-Hosted • Open Source
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Plans Designed for Every Team Size
                        </h2>
                        <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed">
                            ServiceFlow is 100% free and open-source to self-host on your own infrastructure. This web instance is an interactive live demo to explore every feature with zero license fees.
                        </p>
                    </div>

                    <!-- 3 Pricing Cards -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                        <!-- Plan 1: Starter -->
                        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="text-lg font-bold text-slate-900">Starter</div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">Self-Hosted</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">For small service teams getting started</p>
                                <div class="mt-6 mb-8 pb-6 border-b border-slate-100">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl sm:text-4xl font-extrabold text-slate-900">Free</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">Open Source</span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1.5 font-medium">Free to self-host for your own use • Web is demo only</div>
                                </div>
                                <ul class="space-y-3.5 text-sm text-slate-600">
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span class="font-medium text-slate-900">Recommended for up to 3 technicians</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Customer & equipment tracking</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Service request conversion</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Work orders & dispatch</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Community documentation & guides</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center px-6 py-3.5 rounded-xl text-sm font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 transition-colors">
                                    Try Live Demo
                                </a>
                            </div>
                        </div>

                        <!-- Plan 2: Professional (Featured) -->
                        <div class="bg-white rounded-3xl p-8 border-2 border-blue-600 shadow-xl flex flex-col justify-between relative">
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full text-xs font-bold uppercase tracking-wide bg-blue-600 text-white shadow-sm">
                                Most Popular • Free Self-Hosted
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="text-lg font-bold text-slate-900">Professional</div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">Self-Hosted</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">For expanding field service and maintenance businesses</p>
                                <div class="mt-6 mb-8 pb-6 border-b border-slate-100">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl sm:text-4xl font-extrabold text-slate-900">Free</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-blue-100 text-blue-800">Open Source</span>
                                    </div>
                                    <div class="text-xs text-blue-600 font-semibold mt-1.5">Zero licensing fees for self-hosters • Web is demo only</div>
                                </div>
                                <ul class="space-y-3.5 text-sm text-slate-600">
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span class="font-bold text-slate-900">Optimized for up to 15 technicians</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span>Everything in Starter</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span>Maintenance contracts & recurring schedules</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span>Estimates, invoicing & payments</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span>Weekly technician availability</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-blue-600 shrink-0" />
                                        <span>Full technician mobile workspace portal</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center px-6 py-3.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-600/30 transition-all">
                                    Try Live Demo
                                </a>
                            </div>
                        </div>

                        <!-- Plan 3: Business -->
                        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="text-lg font-bold text-slate-900">Business</div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">Self-Hosted</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">For multi-branch and commercial service contractors</p>
                                <div class="mt-6 mb-8 pb-6 border-b border-slate-100">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl sm:text-4xl font-extrabold text-slate-900">Free</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">Open Source</span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1.5 font-medium">Unlimited scale on private cloud • Web is demo only</div>
                                </div>
                                <ul class="space-y-3.5 text-sm text-slate-600">
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span class="font-bold text-slate-900">Unlimited technicians & branches</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Everything in Professional</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Multi-branch tenant data isolation</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Advanced operational analytics & reporting</span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <x-heroicon-s-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                        <span>Zero vendor lock-in • Modify any feature</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center px-6 py-3.5 rounded-xl text-sm font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 transition-colors">
                                    Try Live Demo
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Open Source Banner below cards -->
                    <div class="mt-12 bg-slate-50 border border-slate-200/90 rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-slate-900">100% Free Self-Hosted • Live Demo Online</h4>
                                <p class="mt-1 text-sm text-slate-600">The online version is provided as a live demonstration environment. All plans are completely free to self-host and customize on your own infrastructure with zero license or per-seat costs.</p>
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center gap-3">
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
                                Explore Live Demo
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 10. About / Why ServiceFlow Section -->
            <section id="about" class="py-24 bg-gradient-to-b from-slate-50 to-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                        <!-- Left: Core Philosophy -->
                        <div class="lg:col-span-7">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/60 mb-3">
                                Why ServiceFlow
                            </div>
                            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                                Bringing Service Operations Together in One Centralized System
                            </h2>
                            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                                ServiceFlow brings your service operations together in one place, helping teams coordinate work, maintain equipment, manage customer relationships, and keep track of billing.
                            </p>

                            <!-- 4 Key Pillars -->
                            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm mb-3">
                                        1
                                    </div>
                                    <h4 class="text-base font-bold text-slate-900">Centralized Operations</h4>
                                    <p class="mt-1 text-sm text-slate-600">Connect dispatchers, managers, and field staff around a single source of truth.</p>
                                </div>

                                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm mb-3">
                                        2
                                    </div>
                                    <h4 class="text-base font-bold text-slate-900">Improved Visibility</h4>
                                    <p class="mt-1 text-sm text-slate-600">Monitor real-time progress from incoming customer requests to job completion and sign-off.</p>
                                </div>

                                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm mb-3">
                                        3
                                    </div>
                                    <h4 class="text-base font-bold text-slate-900">Organized Scheduling</h4>
                                    <p class="mt-1 text-sm text-slate-600">Eliminate dispatch bottlenecks and double-booking with technician availability calendars.</p>
                                </div>

                                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm mb-3">
                                        4
                                    </div>
                                    <h4 class="text-base font-bold text-slate-900">Scalable Workflows</h4>
                                    <p class="mt-1 text-sm text-slate-600">Multi-tenant architecture engineered to scale smoothly from a single crew to multi-branch operations.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Architectural Workflow Card -->
                        <div class="lg:col-span-5">
                            <div class="bg-slate-900 text-white rounded-3xl p-8 border border-slate-800 shadow-2xl relative overflow-hidden">
                                <div class="text-xs uppercase tracking-wider font-semibold text-blue-400 mb-2">Operational Workflow</div>
                                <h3 class="text-xl font-bold mb-6">The ServiceFlow Lifecycle</h3>

                                <div class="space-y-4">
                                    <div class="flex items-center gap-4 bg-slate-800/80 p-3.5 rounded-xl border border-slate-700">
                                        <span class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xs shrink-0">01</span>
                                        <div>
                                            <div class="text-sm font-semibold">Service Request Logged</div>
                                            <div class="text-xs text-slate-400">Captured via portal with customer & equipment link</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 bg-slate-800/80 p-3.5 rounded-xl border border-slate-700">
                                        <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">02</span>
                                        <div>
                                            <div class="text-sm font-semibold">Work Order Dispatched</div>
                                            <div class="text-xs text-slate-400">Assigned based on availability and branch location</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 bg-slate-800/80 p-3.5 rounded-xl border border-slate-700">
                                        <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs shrink-0">03</span>
                                        <div>
                                            <div class="text-sm font-semibold">Job Executed & Verified</div>
                                            <div class="text-xs text-slate-400">Technician logs work entries and completion details</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 bg-slate-800/80 p-3.5 rounded-xl border border-slate-700">
                                        <span class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-xs shrink-0">04</span>
                                        <div>
                                            <div class="text-sm font-semibold">Invoiced & Paid</div>
                                            <div class="text-xs text-slate-400">Direct invoice generation and payment tracking</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 11. Final Call-to-Action Section -->
            <section class="py-20 bg-slate-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="rounded-3xl bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 text-white p-10 sm:p-16 shadow-2xl relative overflow-hidden text-center sm:text-left">
                        <!-- Decorative background circle -->
                        <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full bg-white/10 blur-2xl pointer-events-none" aria-hidden="true"></div>

                        <div class="relative z-10 max-w-3xl">
                            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
                                Ready to Simplify Your Service Operations?
                            </h2>
                            <p class="mt-4 text-base sm:text-xl text-blue-100 max-w-2xl leading-relaxed">
                                Bring your team, customers, and service workflows together with ServiceFlow.
                            </p>
                            <div class="mt-8 flex flex-wrap items-center justify-center sm:justify-start gap-4">
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-8 py-4 rounded-xl text-base font-bold text-blue-700 bg-white hover:bg-blue-50 shadow-lg shadow-black/10 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-white">
                                    Get Started
                                </a>
                                <a href="#contact" class="inline-flex items-center justify-center px-8 py-4 rounded-xl text-base font-semibold text-white bg-blue-800/60 hover:bg-blue-800 border border-blue-400/40 transition-all focus:outline-none focus:ring-2 focus:ring-white">
                                    Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Section -->
            <section id="contact" class="py-20 bg-white border-t border-slate-200/80">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                        <!-- Contact Info -->
                        <div class="lg:col-span-5">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100/70 text-blue-700 border border-blue-200/60 mb-3">
                                Get In Touch
                            </div>
                            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                                Talk with Our Team
                            </h2>
                            <p class="mt-3 text-base text-slate-600 leading-relaxed">
                                Have questions about how ServiceFlow fits your field service business? We are happy to demonstrate how our platform helps your team work faster.
                            </p>

                            <div class="mt-8 space-y-4">
                                <div class="flex items-center gap-3.5 text-sm text-slate-600">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                        <x-heroicon-o-envelope class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">Email Inquiries</div>
                                        <a href="mailto:support@serviceflow.io" class="text-blue-600 hover:underline">support@serviceflow.io</a>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3.5 text-sm text-slate-600">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                        <x-heroicon-o-clock class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">Support Hours</div>
                                        <span>Monday – Friday, 8:00 AM – 6:00 PM EST</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inquiry Form -->
                        <div class="lg:col-span-7 bg-slate-50 p-8 rounded-3xl border border-slate-200/80 shadow-sm">
                            <form id="contact-form" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="contact-name" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Your Name *</label>
                                        <input type="text" id="contact-name" required placeholder="Alex Morgan" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="contact-email" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Work Email *</label>
                                        <input type="email" id="contact-email" required placeholder="alex@company.com" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="contact-company" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Business Name</label>
                                        <input type="text" id="contact-company" placeholder="Morgan HVAC Services" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="contact-industry" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Industry / Trade</label>
                                        <select id="contact-industry" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="hvac">AC & Refrigeration</option>
                                            <option value="electrical">Electrical Repair</option>
                                            <option value="equipment">Commercial Equipment Maintenance</option>
                                            <option value="field_service">General Field Service</option>
                                            <option value="other">Other Service Business</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label for="contact-message" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Message / Requirements *</label>
                                    <textarea id="contact-message" rows="3" required placeholder="Tell us about your team size, current challenges, and workflows you want to manage..." class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                                </div>
                                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Send Message
                                </button>
                                <div id="form-success" class="hidden p-3.5 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200">
                                    Thank you! Your inquiry has been received. Our team will contact you shortly.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- 12. Footer -->
        <footer class="bg-slate-900 text-slate-400 py-16 border-t border-slate-800" aria-label="Site Footer">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
                    <!-- Brand Area -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-600/30">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                    <path d="M5 19l4-4" stroke-width="2"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold tracking-tight text-white">
                                Service<span class="text-blue-500">Flow</span>
                            </span>
                        </div>
                        <p class="text-sm text-slate-400 max-w-sm leading-relaxed">
                            Field service and maintenance management SaaS built to streamline customer requests, technician scheduling, contracts, and billing in one unified platform.
                        </p>
                        <!-- Placeholder Social Links -->
                        <div class="flex items-center gap-3 pt-2 text-slate-400">
                            <a href="#home" class="p-2 rounded-lg bg-slate-800 hover:text-white hover:bg-slate-700 transition-colors" aria-label="ServiceFlow Twitter">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                            <a href="#home" class="p-2 rounded-lg bg-slate-800 hover:text-white hover:bg-slate-700 transition-colors" aria-label="ServiceFlow LinkedIn">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                            </a>
                            <a href="#home" class="p-2 rounded-lg bg-slate-800 hover:text-white hover:bg-slate-700 transition-colors" aria-label="ServiceFlow GitHub">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Product Links -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Product</h4>
                        <ul class="space-y-2.5 text-sm">
                            <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                            <li><a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                            <li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>
                            <li><a href="#use-cases" class="hover:text-white transition-colors">Use Cases</a></li>
                        </ul>
                    </div>

                    <!-- Company Links -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Company</h4>
                        <ul class="space-y-2.5 text-sm">
                            <li><a href="#about" class="hover:text-white transition-colors">About</a></li>
                            <li><a href="#contact" class="hover:text-white transition-colors">Contact</a></li>
                            <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Admin Panel</a></li>
                        </ul>
                    </div>

                    <!-- Account & Legal Links -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Account & Legal</h4>
                        <ul class="space-y-2.5 text-sm">
                            <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Log In</a></li>
                            <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Get Started</a></li>
                            <li><button type="button" id="btn-open-privacy" class="hover:text-white transition-colors text-left">Privacy Policy</button></li>
                            <li><button type="button" id="btn-open-terms" class="hover:text-white transition-colors text-left">Terms of Service</button></li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom Copyright Bar -->
                <div class="mt-12 pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <p>&copy; {{ date('Y') }} ServiceFlow. All rights reserved.</p>
                    <p class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Field Service & Maintenance Management SaaS</span>
                    </p>
                </div>
            </div>
        </footer>

        <!-- Demo Video Modal -->
        <div id="demo-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="demo-modal-title">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" id="demo-modal-backdrop"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-3xl rounded-3xl bg-slate-900 text-white p-6 sm:p-8 shadow-2xl border border-slate-800">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                            <h3 id="demo-modal-title" class="text-lg font-bold text-white">ServiceFlow Overview Walkthrough</h3>
                        </div>
                        <button type="button" id="btn-close-demo-modal" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800" aria-label="Close demo modal">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Video Placeholder Container -->
                    <div class="mt-6 aspect-video rounded-2xl bg-slate-950 border border-slate-800 flex flex-col items-center justify-center p-8 text-center relative overflow-hidden">
                        <div class="w-16 h-16 rounded-full bg-blue-600/90 text-white flex items-center justify-center shadow-lg shadow-blue-600/30 mb-4 cursor-pointer hover:scale-105 transition-transform">
                            <x-heroicon-s-play class="w-8 h-8 ml-1" />
                        </div>
                        <h4 class="text-base font-bold text-white">Interactive Walkthrough & Feature Tour</h4>
                        <p class="mt-1.5 text-xs text-slate-400 max-w-md">
                            See how dispatchers schedule work orders, how technicians update status on-site, and how invoices generate automatically upon job completion.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-400">
                            <span class="px-2.5 py-1 rounded bg-slate-800">1. Customer Requests</span>
                            <span class="px-2.5 py-1 rounded bg-slate-800">2. Dispatch Scheduling</span>
                            <span class="px-2.5 py-1 rounded bg-slate-800">3. Work Orders</span>
                            <span class="px-2.5 py-1 rounded bg-slate-800">4. Billing & Payments</span>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            Get Started Free
                        </a>
                        <button type="button" id="btn-close-demo-modal-2" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:bg-slate-800 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy Policy Modal -->
        <div id="privacy-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="privacy-modal-title">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm" id="privacy-modal-backdrop"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-3xl bg-white text-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                        <h3 id="privacy-modal-title" class="text-lg font-bold text-slate-900">Privacy Policy</h3>
                        <button type="button" id="btn-close-privacy" class="p-1 rounded-lg text-slate-400 hover:text-slate-600" aria-label="Close modal">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 max-h-96 overflow-y-auto pr-2">
                        <p><strong>1. Data Isolation & Security:</strong> ServiceFlow implements tenant-scoped database isolation to ensure your customer records, work orders, contracts, and financial records are strictly isolated to your authorized organization.</p>
                        <p><strong>2. Information Collection:</strong> Information provided during onboarding and operational workflows is used solely to provide and support field service operations within your workspace.</p>
                        <p><strong>3. Role-Based Access:</strong> Access to sensitive operational records is restricted according to assigned organization roles (Admin, Dispatcher, Technician).</p>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="button" id="btn-close-privacy-2" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700">
                            Understood
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms of Service Modal -->
        <div id="terms-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm" id="terms-modal-backdrop"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-3xl bg-white text-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                        <h3 id="terms-modal-title" class="text-lg font-bold text-slate-900">Terms of Service</h3>
                        <button type="button" id="btn-close-terms" class="p-1 rounded-lg text-slate-400 hover:text-slate-600" aria-label="Close modal">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 max-h-96 overflow-y-auto pr-2">
                        <p><strong>1. Service Provision:</strong> ServiceFlow provides multi-tenant field service management tooling for organizations and their authorized members.</p>
                        <p><strong>2. Account Security:</strong> You are responsible for maintaining the confidentiality of your workspace credentials and ensuring only authorized technicians and dispatchers access operational resources.</p>
                        <p><strong>3. Usage Limits:</strong> Workspace resources and technician allocations correspond to selected plan parameters and service agreements.</p>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="button" id="btn-close-terms-2" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightweight Client Interaction Script -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Mobile Menu Toggle
                const menuToggle = document.getElementById('mobile-menu-toggle');
                const mobileMenu = document.getElementById('mobile-menu');
                const hamburgerIcon = document.getElementById('hamburger-icon');
                const closeIcon = document.getElementById('close-icon');

                if (menuToggle && mobileMenu) {
                    menuToggle.addEventListener('click', function () {
                        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                        menuToggle.setAttribute('aria-expanded', !isExpanded);
                        mobileMenu.classList.toggle('hidden');
                        if (hamburgerIcon && closeIcon) {
                            hamburgerIcon.classList.toggle('hidden');
                            closeIcon.classList.toggle('hidden');
                        }
                    });

                    // Close mobile menu on anchor click
                    document.querySelectorAll('.mobile-nav-link').forEach(link => {
                        link.addEventListener('click', () => {
                            mobileMenu.classList.add('hidden');
                            menuToggle.setAttribute('aria-expanded', 'false');
                            if (hamburgerIcon && closeIcon) {
                                hamburgerIcon.classList.remove('hidden');
                                closeIcon.classList.add('hidden');
                            }
                        });
                    });
                }

                // Modal Utilities
                function toggleModal(modalId, show) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        if (show) {
                            modal.classList.remove('hidden');
                            document.body.classList.add('overflow-hidden');
                        } else {
                            modal.classList.add('hidden');
                            document.body.classList.remove('overflow-hidden');
                        }
                    }
                }

                // Demo Modal Handlers
                const openDemoBtn = document.getElementById('btn-open-demo-modal');
                const closeDemoBtn = document.getElementById('btn-close-demo-modal');
                const closeDemoBtn2 = document.getElementById('btn-close-demo-modal-2');
                const demoBackdrop = document.getElementById('demo-modal-backdrop');

                if (openDemoBtn) openDemoBtn.addEventListener('click', () => toggleModal('demo-modal', true));
                if (closeDemoBtn) closeDemoBtn.addEventListener('click', () => toggleModal('demo-modal', false));
                if (closeDemoBtn2) closeDemoBtn2.addEventListener('click', () => toggleModal('demo-modal', false));
                if (demoBackdrop) demoBackdrop.addEventListener('click', () => toggleModal('demo-modal', false));

                // Privacy Modal Handlers
                const openPrivacyBtn = document.getElementById('btn-open-privacy');
                const closePrivacyBtn = document.getElementById('btn-close-privacy');
                const closePrivacyBtn2 = document.getElementById('btn-close-privacy-2');
                const privacyBackdrop = document.getElementById('privacy-modal-backdrop');

                if (openPrivacyBtn) openPrivacyBtn.addEventListener('click', () => toggleModal('privacy-modal', true));
                if (closePrivacyBtn) closePrivacyBtn.addEventListener('click', () => toggleModal('privacy-modal', false));
                if (closePrivacyBtn2) closePrivacyBtn2.addEventListener('click', () => toggleModal('privacy-modal', false));
                if (privacyBackdrop) privacyBackdrop.addEventListener('click', () => toggleModal('privacy-modal', false));

                // Terms Modal Handlers
                const openTermsBtn = document.getElementById('btn-open-terms');
                const closeTermsBtn = document.getElementById('btn-close-terms');
                const closeTermsBtn2 = document.getElementById('btn-close-terms-2');
                const termsBackdrop = document.getElementById('terms-modal-backdrop');

                if (openTermsBtn) openTermsBtn.addEventListener('click', () => toggleModal('terms-modal', true));
                if (closeTermsBtn) closeTermsBtn.addEventListener('click', () => toggleModal('terms-modal', false));
                if (closeTermsBtn2) closeTermsBtn2.addEventListener('click', () => toggleModal('terms-modal', false));
                if (termsBackdrop) termsBackdrop.addEventListener('click', () => toggleModal('terms-modal', false));

                // ESC key closes any open modal or menu
                window.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        toggleModal('demo-modal', false);
                        toggleModal('privacy-modal', false);
                        toggleModal('terms-modal', false);
                        if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                            mobileMenu.classList.add('hidden');
                            menuToggle?.setAttribute('aria-expanded', 'false');
                            hamburgerIcon?.classList.remove('hidden');
                            closeIcon?.classList.add('hidden');
                        }
                    }
                });

                // Contact Form Submission (Client validation & feedback)
                const contactForm = document.getElementById('contact-form');
                const formSuccess = document.getElementById('form-success');
                if (contactForm && formSuccess) {
                    contactForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        formSuccess.classList.remove('hidden');
                        contactForm.reset();
                        setTimeout(() => {
                            formSuccess.classList.add('hidden');
                        }, 6000);
                    });
                }
            });
        </script>
    </body>
</html>
