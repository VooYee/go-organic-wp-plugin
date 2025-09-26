<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and handles the tracking batch endpoint.
 *
 * @package WP_Tracking_System
 */

add_action('rest_api_init', function () {
    register_rest_route('tracking/v1', '/batch', [
        'methods' => 'POST',
        'callback' => 'handle_tracking_batch',
        'permission_callback' => 'go_organic_validate_wp_key',
    ]);
    error_log('Tracking route /batch registered');
});

/**
    * Validates authentication using x-wp-key header.
    *
    * @return bool True if authentication is valid, false otherwise.
    */
   function go_organic_validate_wp_key() {
       $wp_key = $_SERVER['HTTP_X_WP_KEY'] ?? '';
       $stored_password = get_option('go_organic_latest_password', '');

       return !empty($wp_key) && $wp_key === $stored_password;
   }

/**
 * Processes batched tracking events and forwards them to Supabase.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response The response with processing status.
 */
function handle_tracking_batch(WP_REST_Request $request) {
    $data = $request->get_json_params();

    // Log incoming request
    $log_file = WP_CONTENT_DIR . '/tracking.log';
    $log_entry = sprintf('[%s] Received: %s', date('Y-m-d H:i:s'), json_encode($data));
    file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND);

    // Validate and prepare Supabase URL
    $supabase_url = defined('WPTS_SUPABASE_URL') && WPTS_SUPABASE_URL ? WPTS_SUPABASE_URL : '';
    if (empty($supabase_url)) {
        error_log('Error: WPTS_SUPABASE_URL is not configured');
        return new WP_REST_Response(['status' => 'error', 'message' => 'Supabase URL not configured'], 500);
    }

    // Send to Supabase
    error_log('Sending to Supabase: ' . json_encode($data));
    $response = wp_remote_post($supabase_url, [
        'body' => json_encode($data),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10, // Increased for reliability
    ]);

    // Log and handle response
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $log_entry = sprintf('[%s] Supabase Response: Code %d, Body: %s', date('Y-m-d H:i:s'), $response_code, $response_body);
    file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['status' => 'error', 'message' => $response->get_error_message()], 500);
    }

    return new WP_REST_Response(['status' => 'success', 'code' => $response_code, 'body' => $response_body], 200);
}