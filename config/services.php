<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p'),
        'poster_size' => env('TMDB_POSTER_SIZE', 'w342'),
        'language' => env('TMDB_LANGUAGE', 'id-ID'),
        'include_adult' => env('TMDB_INCLUDE_ADULT', false),
    ],

    'omdb' => [
        'key' => env('OMDB_API_KEY'),
        'base_url' => env('OMDB_BASE_URL', 'https://www.omdbapi.com/'),
    ],

    'jikan' => [
        'base_url' => env('JIKAN_BASE_URL', 'https://api.jikan.moe/v4'),
    ],

    'anilist' => [
        'base_url' => env('ANILIST_BASE_URL', 'https://graphql.anilist.co'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Model ringan sebagai default supaya kuota gratis awet.
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
        // Terjemahan & romanisasi (banyak permintaan, sederhana).
        'translation_model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-3.1-flash-lite'),
        // Dipakai sekali kalau kuota harian model yang diminta habis. Harus model
        // lain, karena kuota gratis dihitung per model per hari.
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.8-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

];
