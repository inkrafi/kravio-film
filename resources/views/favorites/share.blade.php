@php
    $pageTitle = 'Favorit '.$owner->name;
    $description = filled($owner->bio) ? $owner->bio : 'Tontonan favorit '.$owner->name.' di '.config('app.name').'.';
    $imageUrl = route('favorites.image', ['user' => $owner, 'format' => 'kotak']).'?v='.$version;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }} · {{ config('app.name') }}</title>
        <meta name="description" content="{{ $description }}">

        {{-- Pratinjau saat tautan ditempel di WhatsApp, X, dan lainnya --}}
        <meta property="og:type" content="profile">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ route('favorites.share', $owner) }}">
        <meta property="og:image" content="{{ $imageUrl }}">
        <meta property="og:image:width" content="1080">
        <meta property="og:image:height" content="1080">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $imageUrl }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-layar font-sans text-gray-200 antialiased">
        <header class="mx-auto flex max-w-3xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ url('/') }}" class="text-perak"><x-application-logo class="text-[1.75rem]" /></a>

            @guest
                <a href="{{ route('register') }}" class="text-sm text-gray-300 underline underline-offset-4 hover:text-white">Daftar gratis</a>
            @endguest
        </header>

        <main class="mx-auto max-w-3xl px-4 pb-16 sm:px-6">
            <h1 class="mt-6 font-extra-condensed text-5xl font-extrabold leading-[0.95] tracking-tight text-white sm:text-7xl">{{ $pageTitle }}</h1>
            @if ($owner->username)
                <p class="mt-3 text-gray-400">
                    @auth
                        <a href="{{ route('profile.show', $owner) }}" class="text-gray-200 underline underline-offset-4 hover:text-white">{{ '@'.$owner->username }}</a>
                    @else
                        {{ '@'.$owner->username }}
                    @endauth
                </p>
            @endif

            <ol class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($media as $item)
                    <li>
                        <a href="{{ $item->url() }}" class="group block" title="{{ $item->title }}">
                            <div class="aspect-[2/3] overflow-hidden rounded-sm bg-kursi">
                                @if ($item->poster_url)
                                    <img src="{{ $item->poster_url }}" alt="Poster {{ $item->title }}" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm font-semibold text-gray-100 group-hover:underline">{{ $item->title }}</p>
                            <p class="text-xs text-gray-500">{{ $item->media_type->label() }}@if ($item->year), {{ $item->year }}@endif</p>
                        </a>
                    </li>
                @endforeach
            </ol>

            @if (filled($owner->bio))
                <p class="subtitle mx-auto mt-10 max-w-xl text-center text-xl leading-snug sm:text-2xl">{{ $owner->bio }}</p>
            @endif

            <div class="mt-12 border-t border-gray-800 pt-6 text-sm">
                @auth
                    <a href="{{ auth()->user()->username ? route('profile.show', auth()->user()) : route('profile') }}" class="font-semibold text-gray-100 underline underline-offset-4 hover:text-white">
                        {{ auth()->id() === $owner->id ? 'Atur favoritmu di profil' : 'Bagikan favoritmu sendiri' }}
                    </a>
                @else
                    <p class="text-gray-400">
                        {{ config('app.name') }} adalah tempat mencatat dan menilai film, series, dan anime, plus melihat apa yang ditonton temanmu.
                        <a href="{{ route('register') }}" class="font-semibold text-gray-100 underline underline-offset-4 hover:text-white">Bagikan favoritmu sendiri</a>
                    </p>
                @endauth
            </div>
        </main>
    </body>
</html>
