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


    // Google AI Studio (Gemini) — the store assistant's second provider, picked in Admin → Settings → AI assistant
    'gemini' => ['key' => env('GEMINI_API_KEY', '')],
    // OpenRouter — third provider for the store assistant; free models, routed with fallbacks
    'openrouter' => ['key' => env('OPENROUTER_API_KEY', '')],
    // Groq — fourth provider (OpenAI-compatible, very fast open models)
    'groq' => ['key' => env('GROQ_API_KEY', '')],

    // ElevenLabs — voice for the store assistant: customers' voice messages are transcribed (Scribe) and
    // answers can be read aloud. Library voices need a paid plan; until then the premade fallback speaks.
    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY', ''),
        'voice' => env('ELEVENLABS_VOICE_ID', '7EzWGsX10sAS4c9m9cPf'),          // Jack John, customer support
        'fallback_voice' => env('ELEVENLABS_FALLBACK_VOICE_ID', 'cjVigY5qzO86Huf0OWal'),   // Eric (premade)
        'tts_model' => env('ELEVENLABS_TTS_MODEL', 'eleven_flash_v2_5'),
        'stt_model' => env('ELEVENLABS_STT_MODEL', 'scribe_v1'),
    ],

    'anthropic' => [
        // Store assistant ("UDS Assistant", beta). Signed-in customers only.
        // On/off lives in Admin → Settings → AI assistant (setting assistant.enabled), not here.
        'key' => env('ANTHROPIC_API_KEY', ''),
        // The key is organisation-wide, so every request names the workspace it bills to.
        'workspace' => env('ANTHROPIC_WORKSPACE_ID', ''),
        'model' => env('CHATBOT_MODEL', 'claude-opus-5-5'),
        // abuse ceilings, per signed-in account
        'per_minute' => (int) env('CHATBOT_PER_MINUTE', 8),
        'per_day' => (int) env('CHATBOT_PER_DAY', 60),
        'max_chars' => (int) env('CHATBOT_MAX_CHARS', 800),
        'max_turns' => (int) env('CHATBOT_MAX_TURNS', 30),
        'inbox' => env('CHATBOT_INBOX', 'inquiry@universaldriveshafts.com'),
    ],

    'easypost' => [
        'enabled' => (bool) env('EASYPOST_ENABLED', false),
        'key' => env('EASYPOST_KEY', ''),
        // EZTK… key: the only way to make a free fake label (staging buys nothing real with it)
        'test_key' => env('EASYPOST_TEST_KEY', ''),
    ],

    'warp' => [
        // LTL freight. Quoting is keyless and free; a wak_live_* key (wearewarp.com/agents/account)
        // is only needed to make each carrier's quote bookable.
        'enabled' => (bool) env('WARP_ENABLED', false),
        'key' => env('WARP_KEY', ''),
        // wak_test_* sandbox key: bookings with it are simulated (no charge, no truck)
        'test_key' => env('WARP_TEST_KEY', ''),
    ],

    'shippo' => [
        'enabled' => (bool) env('SHIPPO_ENABLED', false),
        'token' => env('SHIPPO_TOKEN', ''),
    ],

    // read-only REST keys for the live WooCommerce store (catalog sync)
    // Powertrain Industries dealer portal (stock, new parts, ship boxes) — pti:sync
    'pti' => ['user' => env('PTI_USER'), 'pass' => env('PTI_PASS')],

    // Google Search Console (Admin → SEO → Performance): service-account JSON key, kept outside the web root
    'gsc' => [
        'key' => env('GSC_KEY_PATH', storage_path('app/google/search-console.json')),
        'site' => env('GSC_SITE', 'sc-domain:universaldriveshafts.com'),
    ],

    // Google Analytics 4 (admin dashboard traffic card): property of G-MG6P2JC6GL, read with the same service account
    'ga4' => ['property' => env('GA4_PROPERTY', '472848382')],

    'woocommerce' => [
        'url' => env('WC_URL', 'https://universaldriveshafts.com'),
        'key' => env('WC_KEY'),
        'secret' => env('WC_SECRET'),
        'origin_ip' => env('WC_ORIGIN_IP'),   // optional: pin the live host's IP instead of looking it up
    ],

    // the shop's work order station (uds-checkin on driveshaftsindustries.com): signed integration API, App\Services\CheckinApi.
    // Unset = the bridge is off (staging and tests never touch the real station). The secret is the station's INTEGRATION_SECRET.
    'checkin' => [
        'url' => env('CHECKIN_API_URL'),
        'secret' => env('CHECKIN_API_SECRET'),
        'timeout' => (int) env('CHECKIN_API_TIMEOUT', 20),
        'resolve' => env('CHECKIN_API_RESOLVE'),   // "host:443:ip", only where local DNS lies about the station
    ],

    'twilio' => [
        'enabled' => (bool) env('TWILIO_ENABLED', false),
        'sid' => env('TWILIO_SID', ''),
        'token' => env('TWILIO_TOKEN', ''),
        'from' => env('TWILIO_FROM', ''),
    ],

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'key' => env('STRIPE_ENABLED', false) ? env('STRIPE_KEY') : '',
        'secret' => env('STRIPE_ENABLED', false) ? env('STRIPE_SECRET') : '',
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        // Stripe payment-method configuration: the single place that decides which methods are offered
        'pm_config' => env('STRIPE_PM_CONFIG', ''),
    ],

    'brevo' => [
        'enabled' => env('BREVO_ENABLED', false),
        'key' => env('BREVO_API_KEY', ''),
        'from' => env('BREVO_FROM_ADDRESS', 'noreply@udshafts.com'),
        'from_name' => env('BREVO_FROM_NAME', 'Universal Driveshafts'),
    ],
];
