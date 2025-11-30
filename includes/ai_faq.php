<?php
// includes/ai_faq.php - canned site FAQs used when the generative API is not available
// Make this a single, well-formed array so other scripts can include and iterate.
$AI_FAQ = [
    [
        'id' => 'how_schedule',
        'question' => 'How do I generate a study schedule?',
        'answer' => 'Open the dashboard, type your task into the Quick Add box, pick a subject and priority, then click Generate with AI. The planner will suggest a short plan you can customize before saving.',
        'keywords' => ['schedule','plan','generate','ai','task'],
        'contexts' => ['dashboard']
    ],
    [
        'id' => 'how_focus',
        'question' => 'How does Focus Mode work?',
        'answer' => 'Focus Mode starts a timer for a chosen duration. Keep the tab visible and follow the steps — you earn points for completed sessions. Pausing or switching tabs will pause your progress to protect your session.',
        'keywords' => ['focus','timer','session'],
        'contexts' => ['dashboard']
    ],
    [
        'id' => 'how_messages',
        'question' => 'How do I message classmates?',
        'answer' => 'Go to Messaging and choose a user or group to open a chat. From there you can type messages, react, upload files or start calls. Use the search to find people quickly.',
        'keywords' => ['message','chat','groups','messaging'],
        'contexts' => ['dashboard','messaging']
    ],
    [
        'id' => 'faq_admin',
        'question' => 'How do I become an admin?',
        'answer' => 'Admin creation is controlled by a one-time protected signup flow. The admin signup page requires a secret token (stored securely on the server) so only a trusted user can create the first admin.',
        'keywords' => ['admin','signup','admin signup'],
        'contexts' => ['dashboard','admin']
    ],
    [
        'id' => 'no_key',
        'question' => 'Why are AI answers sometimes fallback messages?',
        'answer' => 'If the server does not have a valid GENERATIVE_API_KEY configured, the assistant returns deterministic fallback replies so the UI stays functional for testing without external API calls.',
        'keywords' => ['api key','fallback','no api key'],
        'contexts' => ['dashboard']
    ],
    [
        'id' => 'onboarding',
        'question' => 'How do I start the tutorial?',
        'answer' => 'Open Settings and click "Show tutorial again" or add ?start_tour=1 to the dashboard URL to launch the guided tour from the next page load.',
        'keywords' => ['tutorial','onboarding','start tour','start_tour'],
        'contexts' => ['dashboard','settings']
    ],
    [
        'id' => 'reset_tutorial',
        'question' => 'How do I reset the tutorial so it shows again?',
        'answer' => 'Open Settings and click "Show tutorial again" — that will reset the server flag and redirect you to the dashboard to start the tour.',
        'keywords' => ['reset','show tutorial again','settings'],
        'contexts' => ['settings','dashboard']
    ],
    [
        'id' => 'ai_enabled',
        'question' => 'Why is the assistant returning canned answers?',
        'answer' => 'If you do not have a server API key configured the assistant will use the canned FAQ and deterministic fallbacks. Add a GENERATIVE_API_KEY in includes/ai_config.php or as an environment variable to enable full generative responses.',
        'keywords' => ['ai','generative','api key','fallback','gemini'],
        'contexts' => ['dashboard']
    ],
    [
        'id' => 'plan_task',
        'question' => 'How do I generate a SmartStudy plan for my task?',
        'answer' => 'Add a task in the quick add form and click Generate with AI — the assistant will suggest a short schedule and motivation tip which you can edit before saving to your schedule.',
        'keywords' => ['plan','generate','task','ai','schedule'],
        'contexts' => ['dashboard']
    ]
];

// $AI_FAQ is intentionally simple and editable. This file is included by ajax_gemini.php and the dashboard assistant.
?>
