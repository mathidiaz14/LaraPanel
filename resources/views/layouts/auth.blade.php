<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('larapanel-theme');
                document.documentElement.dataset.theme = stored === 'light' || stored === 'dark' ? stored : 'dark';
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
    <title>@yield('title', 'Login') — LaraPanel</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon-192x192.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/larapanel.css') }}">
    @livewireStyles
</head>
<body>
<div class="auth-wrap">
    @yield('content')
</div>
@livewireScripts
@stack('scripts')
</body>
</html>
