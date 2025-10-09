<?php
/**
 * Frontend Tracking System
 * Handles frontend analytics and tracking functionality
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues frontend tracking scripts and localizes metadata.
 *
 * @return void
 */
function go_organic_enqueue_tracking_scripts()
{
    wp_enqueue_script(
        'tracking-system-js',
        plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/analytics.bundle.js',
        [],
        '1.0.0',
        true
    );

    $tracker_meta = [
        'page_id' => get_the_ID() ?: 0,
        'session_id' => wp_generate_uuid4(),
        'page_url' => get_permalink() ?: home_url(),
        'device_type' => wp_is_mobile() ? 'mobile' : 'desktop',
        'user_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'anonymous'),
        'is_bot' => isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|spider/i', $_SERVER['HTTP_USER_AGENT']),
        'supabase_url' => defined('WPTS_SUPABASE_URL') ? WPTS_SUPABASE_URL : '',
        'supabase_key' => defined('WPTS_SUPABASE_API_KEY') ? WPTS_SUPABASE_API_KEY : '',
        'api_username' => go_organic_get_api_username(), // Use dynamic username
        'api_password' => get_option('go_organic_latest_password', ''),
    ];

    // Add SEO meta data to tracker metadata
    $tracker_meta = go_organic_add_seo_to_tracker_meta($tracker_meta);

    wp_localize_script('tracking-system-js', 'TrackerMeta', $tracker_meta);
}
add_action('wp_enqueue_scripts', 'go_organic_enqueue_tracking_scripts');

/**
 * Adds type="module" attribute to the tracking script tag.
 *
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @param string $src    The script source URL.
 * @return string Modified script tag.
 */
function go_organic_add_module_type($tag, $handle, $src)
{
    if ($handle === 'tracking-system-js') {
        return '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'go_organic_add_module_type', 10, 3);

/**
 * Simple event logging function for individual events.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response The response with logging status.
 */
function go_organic_log_individual_event(WP_REST_Request $request)
{
    $data = $request->get_json_params();

    // Log the individual event
    $log_file = WP_CONTENT_DIR . '/tracking.log';
    $log_entry = sprintf('[%s] Individual Event: %s', date('Y-m-d H:i:s'), json_encode($data));
    file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND);

    return new WP_REST_Response(['status' => 'success', 'message' => 'Event logged'], 200);
}

/**
 * Register the tracking event REST API endpoint.
 *
 * @return void
 */
function go_organic_register_tracking_api()
{
    register_rest_route('tracking-system/v1', '/event', [
        'methods' => 'POST',
        'callback' => 'go_organic_log_individual_event',
        'permission_callback' => function () {
            return is_user_logged_in() || check_ajax_referer('ts_nonce', 'nonce', false);
        },
    ]);
}
add_action('rest_api_init', 'go_organic_register_tracking_api');

/**
 * Output SEO meta tags and schema markup in the document head
 *
 * @return void
 */
function go_organic_output_seo_meta()
{
    if (!is_singular()) {
        return;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return;
    }

    // Output SEO title
    $seo_title = go_organic_get_seo_title($post_id);
    if (!empty($seo_title) && $seo_title !== get_the_title($post_id)) {
        echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($seo_title) . '">' . "\n";
    }

    // Output SEO description
    $seo_description = go_organic_get_seo_description($post_id);
    if (!empty($seo_description)) {
        echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($seo_description) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($seo_description) . '">' . "\n";
    }

    // Output schema markup
    $schema_markup = go_organic_get_schema_markup($post_id);
    if (!empty($schema_markup)) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n" . '</script>' . "\n";
    }
}
add_action('wp_head', 'go_organic_output_seo_meta');

/**
 * Add SEO meta data to the tracking metadata
 *
 * @param array $tracker_meta Existing tracker metadata
 * @return array Modified tracker metadata
 */
function go_organic_add_seo_to_tracker_meta($tracker_meta)
{
    $post_id = get_the_ID();
    if ($post_id) {
        $tracker_meta['seo_title'] = go_organic_get_seo_title($post_id);
        $tracker_meta['seo_description'] = go_organic_get_seo_description($post_id);
        $tracker_meta['has_schema_markup'] = !empty(go_organic_get_schema_markup($post_id));
    }
    
    return $tracker_meta;
}
