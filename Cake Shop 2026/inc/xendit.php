<?php
return [
    // Put your LIVE key here only on the server, never in JavaScript/frontend files.
    // Example: 'xnd_production_xxxxxxxxxxxxxxxxx'
    'secret_key' => getenv('XENDIT_SECRET_KEY') ?: 'PASTE_YOUR_XENDIT_LIVE_SECRET_KEY_HERE',

    // From Xendit Dashboard > Developers > Webhooks / Callback verification token.
    // Leave blank only while initially testing, but add it before real customer payments.
    'callback_token' => getenv('XENDIT_CALLBACK_TOKEN') ?: '',

    // Your production domain, no trailing slash.
    'base_url' => getenv('APP_BASE_URL') ?: 'https://auresanchez.shop',
];
