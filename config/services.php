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

    // One eBay developer keyset shared by every connected store: each seller
    // signs in through "Login with eBay" and only their tokens are stored.
    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        // Developer ID (keyset "Dev ID") - required to verify webhook NotificationSignature
        'dev_id' => env('EBAY_DEV_ID'),
        'ru_name' => env('EBAY_RU_NAME'),
        'scopes' => env('EBAY_SCOPES', implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.payment.dispute',
            'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
            'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription',
            'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.stores.readonly',
        ])),
        'auth_url' => env('EBAY_AUTH_URL', 'https://auth.ebay.com/oauth2/authorize'),
        'token_url' => env('EBAY_TOKEN_URL', 'https://api.ebay.com/identity/v1/oauth2/token'),
    ],

];
