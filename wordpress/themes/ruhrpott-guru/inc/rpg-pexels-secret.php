<?php
// PUBLIC ARCHIVE: credential removed. Configure securely before deployment.
/**
 * RuhrpottGuru private server-side media credential.
 * Do not expose, enqueue or localize this file.
 */
if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}
if (!defined('RPG_PEXELS_API_KEY')) {
    define('RPG_PEXELS_API_KEY', '');
}
