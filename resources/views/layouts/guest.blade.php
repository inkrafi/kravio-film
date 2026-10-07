<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-layar font-sans text-gray-200 antialiased">
        <div class="flex min-h-screen flex-col items-center px-4 pt-16 sm:justify-center sm:pt-0">
            <a href="/" wire:navigate class="text-perak">
                <x-application-logo class="text-5xl" />
            </a>
            <p class="mt-2 text-sm text-redup">Catat, nilai, dan lihat apa yang ditonton temanmu.</p>

            <div class="mt-8 w-full rounded-lg bg-kursi px-6 py-6 sm:max-w-md">
                {{ $slot }}
            </div>

            <p class="mt-8 pb-8 text-xs text-gray-500">{{ config('app.name') }} dibuat oleh Kravio.</p>
        </div>
    </body>
</html>
