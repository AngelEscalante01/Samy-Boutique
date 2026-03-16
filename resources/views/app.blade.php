<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <!-- PWA -->
        <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
        <meta name="theme-color" content="#0a0a0a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Samy">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        <button
            id="android-printer-settings-btn"
            type="button"
            class="fixed bottom-4 right-4 z-50 hidden items-center gap-2 rounded-xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-700 shadow hover:bg-stone-50"
            onclick="window.AndroidPrinterBridge && window.AndroidPrinterBridge.abrirConfiguracion && window.AndroidPrinterBridge.abrirConfiguracion()"
            title="Configuracion de impresora"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.03a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.03a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.03a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            Config. impresora
        </button>

        <script>
            (function () {
                const button = document.getElementById('android-printer-settings-btn');
                if (!button) return;

                const isAndroidApp = () => typeof window.AndroidPrinter !== 'undefined';

                const syncVisibility = () => {
                    if (isAndroidApp()) {
                        button.classList.remove('hidden');
                        button.classList.add('inline-flex');
                        return;
                    }

                    button.classList.add('hidden');
                    button.classList.remove('inline-flex');
                };

                syncVisibility();
                window.addEventListener('load', syncVisibility);
                document.addEventListener('inertia:navigate', syncVisibility);
            })();
        </script>
    </body>
</html>
