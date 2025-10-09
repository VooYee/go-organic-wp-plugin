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

/**
 * Register SEO and Schema markup meta fields for posts
 * Compatible with PHP 7.0+
 */
function go_organic_register_post_meta() {
    $meta_fields = ['_seo_title', '_seo_description', '_schema_markup'];
    
    foreach ($meta_fields as $key) {
        register_post_meta('post', $key, array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => 'go_organic_meta_auth_callback',
        ));
    }
}

/**
 * Authorization callback for meta fields
 * @return bool
 */
function go_organic_meta_auth_callback() {
    return current_user_can('edit_posts');
}

add_action('init', 'go_organic_register_post_meta');

/**
 * Get SEO title for a post with fallback to regular title
 * @param int $post_id Post ID, defaults to current post
 * @return string
 */
function go_organic_get_seo_title($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $seo_title = get_post_meta($post_id, '_seo_title', true);
    return !empty($seo_title) ? $seo_title : get_the_title($post_id);
}

/**
 * Get SEO description for a post with fallback to excerpt
 * @param int $post_id Post ID, defaults to current post
 * @return string
 */
function go_organic_get_seo_description($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $seo_description = get_post_meta($post_id, '_seo_description', true);
    if (!empty($seo_description)) {
        return $seo_description;
    }
    
    // Fallback to excerpt or generated excerpt
    $excerpt = get_the_excerpt($post_id);
    if (!empty($excerpt)) {
        return $excerpt;
    }
    
    // Generate excerpt from content if no excerpt exists
    $content = get_post_field('post_content', $post_id);
    $excerpt = wp_trim_words(strip_tags($content), 25, '...');
    return $excerpt;
}

/**
 * Get schema markup for a post
 * @param int $post_id Post ID, defaults to current post
 * @return array|null Decoded schema markup or null
 */
function go_organic_get_schema_markup($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $schema_markup = get_post_meta($post_id, '_schema_markup', true);
    if (!empty($schema_markup)) {
        $decoded = json_decode($schema_markup, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }
    
    return null;
}

/**
 * Set SEO title for a post
 * @param int $post_id Post ID
 * @param string $title SEO title
 * @return bool
 */
function go_organic_set_seo_title($post_id, $title) {
    return update_post_meta($post_id, '_seo_title', sanitize_text_field($title));
}

/**
 * Set SEO description for a post
 * @param int $post_id Post ID
 * @param string $description SEO description
 * @return bool
 */
function go_organic_set_seo_description($post_id, $description) {
    return update_post_meta($post_id, '_seo_description', sanitize_text_field($description));
}

/**
 * Set schema markup for a post
 * @param int $post_id Post ID
 * @param array|string $schema_data Schema markup data
 * @return bool
 */
function go_organic_set_schema_markup($post_id, $schema_data) {
    if (is_array($schema_data)) {
        $schema_data = wp_json_encode($schema_data);
    }
    
    // Validate JSON
    if (is_string($schema_data)) {
        $decoded = json_decode($schema_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
    }
    
    return update_post_meta($post_id, '_schema_markup', $schema_data);
}
