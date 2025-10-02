<?php
/**
 * Connection API Handler
 * Handles API key generation and connection validation
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate API Key by calling Go/Organic service.
 *
 * @param string $username The WordPress username.
 * @param string $password The application password.
 * @return array|false Response data or false on failure.
 */
function go_organic_generate_api_key($username, $password)
{
    $api_url = GO_ORGANIC_URL . '/wp-plugin/connect';

    $body = [
        'username' => $username,
        'password' => $password
    ];

    $response = wp_remote_post($api_url, [
        'body' => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        error_log('API request failed: ' . $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = wp_remote_retrieve_body($response);

    return [
        'status_code' => $status_code,
        'data' => $data
    ];
}

/**
 * Check connection status using stored API key.
 *
 * @param string $api_key The API key to validate.
 * @return bool True if connection is valid, false otherwise.
 */
function go_organic_check_connection($api_key)
{
    $api_url = GO_ORGANIC_URL . '/wp-plugin/profile';
    $response = wp_remote_get($api_url, [
        'headers' => [
            'x-api-key' => $api_key,
            'Content-Type' => 'application/json'
        ],
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    return $status_code === 200;
}

/**
 * Validates authentication using x-wp-key header for tracking API.
 *
 * @return bool True if authentication is valid, false otherwise.
 */
function go_organic_validate_wp_key()
{
    $wp_key = $_SERVER['HTTP_X_WP_KEY'] ?? '';
    $stored_password = get_option('go_organic_latest_password', '');

    return !empty($wp_key) && $wp_key === $stored_password;
}
