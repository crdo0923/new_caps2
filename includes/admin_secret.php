<?php
// includes/admin_secret.php
// WARNING: This file contains a secret token used to enable the one-time admin signup flow.
// It is included in .gitignore by default. Change the token to a secure random value.

if (!defined('ADMIN_SIGNUP_SECRET')) {
    // Generated token (change this in production). Keep this file private.
    define('ADMIN_SIGNUP_SECRET', '3f7b9e2c5b8f9d6a4c2e7a9b6d3f1e8a7c4f2b9d1e3a6c5');
}

// Optional: a hidden trigger credential you can type on the normal login form to reach the
// protected admin signup page without exposing the main SECRET token. Keep this file private.
if (!defined('ADMIN_SIGNUP_TRIGGER_EMAIL')) {
    define('ADMIN_SIGNUP_TRIGGER_EMAIL', 'hidden-admin@example.com');
}
if (!defined('ADMIN_SIGNUP_TRIGGER_PASSWORD')) {
    // Choose a long, random password. This is stored on the server and should be kept secret.
    define('ADMIN_SIGNUP_TRIGGER_PASSWORD', 'Sup3r$ecretSignUpPass!');
}

?>
