<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration file for Go/Organic WP Plugin with integrated tracking system.
 * Defines API credentials and plugin paths.
 *
 * @package Go_Organic_WP_Plugin
 * @version 1.0.0
 */

define('WPTS_PLUGIN_DIR', plugin_dir_path(dirname(__DIR__) . '/go-organic.php'));
define('WPTS_PLUGIN_URL', plugin_dir_url(dirname(__DIR__) . '/go-organic.php'));

// Supabase credentials (to be configured via environment or admin settings)
define('WPTS_SUPABASE_URL', 'https://nlykqjxvurvwlyforoxb.supabase.co/functions/v1/track-events');
define('WPTS_SUPABASE_API_KEY', '');
