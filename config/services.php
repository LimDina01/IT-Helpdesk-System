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

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', 'mock_token_for_local_testing'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'cbvh_helpdesk_bot'),
        'verify_ssl' => env('TELEGRAM_VERIFY_SSL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Directory (LDAP) Staff Authentication
    |--------------------------------------------------------------------------
    | Corresponds to the CBVH Domain Controller configuration.
    | When LDAP_ENABLED=false, authentication safely falls back to local database.
    */
    'ldap' => [
        'enabled' => env('LDAP_ENABLED', false),
        'url' => env('LDAP_AUTH_URL', 'ldaps://192.168.11.101:636'),
        'domain' => env('LDAP_AUTH_ACTIVE_DIRECTORY_DOMAIN', 'cbvh66'),
        'search_base' => env('LDAP_AUTH_SEARCH_BASE', 'DC=cbvh66,DC=com'),
        'username' => env('LDAP_AUTH_CONNECTION_USERNAME', 'pdf_convertor_svc'),
        'password' => env('LDAP_AUTH_CONNECTION_PASSWORD', '$Cbvh@168'),
        'use_ssl' => env('LDAP_AUTH_CONNECT_USE_SSL', true),
        'validate_cert' => env('LDAP_AUTH_VALIDATE_CERT', false),
    ],

];
