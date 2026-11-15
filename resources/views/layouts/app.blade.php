<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SpaceRent - Sewa ruang kerja dan event space di Jakarta')</title>
    <meta name="description" content="@yield('description', 'Pesan meeting room, kantor privat, coworking, studio, dan event space di Jakarta. Jadwal real-time, harga jelas, bayar online.')">
    <meta name="theme-color" content="#c42a4c">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-[100dvh] flex-col">
    <a href="#main" class="sr-only z-50 rounded-xl bg-white px-4 py-2 text-sm font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>

    @include('partials.header')
    @include('partials.flash')

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.chatbot')

    @stack('scripts')
</body>
</html>
