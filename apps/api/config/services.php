<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'reinfolib' => [
        // 空文字をデフォルトにする。docker build 時は .env の中身が見えない
        // （env_file はコンテナ実行時にのみ注入されるため）ため、null のままだと
        // composer install の post-autoload-dump（package:discover）が
        // ReinfolibApiClient::__construct(string $apiKey) の型エラーで
        // ビルドの度に失敗する。実キー未設定時は reinfolib 側が
        // 401/403 を返す形で実行時に気づける。
        'api_key' => env('REINFOLIB_API_KEY', ''),
    ],

];
