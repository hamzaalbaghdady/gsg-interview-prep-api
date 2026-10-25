<?php

return [
    // Full Python upload endpoint; leave unset until agreed with the AI team.
    'python_url' => env('RESUME_PYTHON_URL'),
    'file_field' => env('RESUME_PYTHON_FILE_FIELD', 'file'),
    'python_token' => env('RESUME_PYTHON_TOKEN'),
    'connect_timeout' => 5,
    'timeout' => 30,
    'queue_connection' => env('RESUME_QUEUE_CONNECTION', 'database'),
];
