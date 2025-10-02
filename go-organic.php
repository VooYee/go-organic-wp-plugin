<?php
/**
 * Plugin Name: Go/Organic WP Plugin
 * Description: A plugin to bulk-create posts via a REST API and manage integration credentials with integrated tracking system.
 * Version: 0.9.0
 * Author: Purple Box AI
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GO_ORGANIC_URL', 'https://api.go-organic.ai');
define('GO_ORGANIC_VERSION', '0.9.0');
define('GO_ORGANIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GO_ORGANIC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize plugin update checker
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    GO_ORGANIC_URL . '/wp-plugin/metadata',
    __FILE__,
    'go-organic-wp-plugin'
);

// Load plugin configuration
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/config.php';

// Load core functionality
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/api/connection.php';
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/api/posts.php';
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/api/tracking.php';
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/admin-settings.php';
require_once GO_ORGANIC_PLUGIN_DIR . 'inc/tracking/frontend.php';
