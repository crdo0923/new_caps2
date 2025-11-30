<?php
// includes/ai_config.php
// Optional server-side AI configuration. Define GENERATIVE_API_KEY here OR set an environment
// variable named GENERATIVE_API_KEY in your PHP / webserver environment.

// Example (NOT recommended to commit real keys):
// define('GENERATIVE_API_KEY', 'YOUR_SERVER_SIDE_KEY_HERE');

// If you want the server to use a key for Gemini/Generative Language, define it below.
// This file is in .gitignore so it won't be committed if you add a real key here.
// ---------- Add your key here (local only) ----------
// Warning: Do NOT commit your real key to source control. This file is listed in .gitignore.
define('GENERATIVE_API_KEY', 'AIzaSyCT9vvMP6clYC4K3JwNLTTajSJPNP9255s');

// Toggle whether server should use the external AI provider.
// Set to false to disable external calls and use canned FAQ responses instead.
// For production, if you want the AI working, set this to true and ensure GENERATIVE_API_KEY is configured.
define('AI_ENABLED', false);

// Admin user ids — by default, user id 1 is admin. Edit includes/admin_config.php to customize.

// Keep this file out of version control if you add your real API key.
