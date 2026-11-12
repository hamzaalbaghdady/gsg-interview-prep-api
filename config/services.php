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

    // AI service configuration
    'python_ai' => [
        'job_url' => env('JOB_PYTHON_URL'),
        'roadmap_url' => env('ROADMAP_PYTHON_URL'),
        'interview_url' => env('INTERVIEW_PYTHON_URL'),
        'interview_answer_url' => env('INTERVIEW_ANSWER_PYTHON_URL'),
        'interview_report_url' => env('INTERVIEW_REPORT_URL'),
        'token' => env('JOB_PYTHON_TOKEN'),

        'connect_timeout' => 5,
        'timeout' => 45,
    ],

];
