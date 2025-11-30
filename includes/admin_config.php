<?php
// includes/admin_config.php
// Optional admin configuration override. This file is safe to edit on your server
// (keeps a small list of admin user IDs and an optional master admin id).

if (!defined('ADMIN_USER_IDS')) {
    // By default, no admin users — add numeric user ids you trust, for example [1]
    define('ADMIN_USER_IDS', [25]);
}

if (!defined('ADMIN_DISPLAY_NAME')) {
    define('ADMIN_DISPLAY_NAME', 'Site Admin');
}

if (!defined('ADMIN_MASTER_ID')) {
    // Master admin id (used for protected operations). Defaults to first ADMIN_USER_IDS entry.
    define('ADMIN_MASTER_ID', (defined('ADMIN_USER_IDS') && is_array(ADMIN_USER_IDS) && count(ADMIN_USER_IDS) > 0) ? ADMIN_USER_IDS[0] : 0);
}

?>
