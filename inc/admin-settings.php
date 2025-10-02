<?php
/**
 * Admin Settings Handler
 * Handles the admin interface for Go/Organic plugin settings
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds admin menu for Go/Organic settings.
 *
 * @return void
 */
function go_organic_add_admin_menu()
{
    add_menu_page(
        'Go/Organic Settings',
        'Go/Organic',
        'manage_options',
        'go-organic',
        'go_organic_render_admin_page',
        'dashicons-admin-generic',
        25
    );
}
add_action('admin_menu', 'go_organic_add_admin_menu');

/**
 * Render the main admin integration page.
 *
 * @return void
 */
function go_organic_render_admin_page()
{
    if (!current_user_can('go_organic_manage_settings')) {
        return;
    }

    $api_username = go_organic_get_api_username(); // Use dynamic username
    $new_password_info = null;
    $stored_password = get_option('go_organic_latest_password');
    $stored_api_key = get_option('go_organic_latest_api_key');

    // Ensure API user exists
    $user_id = go_organic_ensure_api_user($api_username);

    // Handle password generation
    if (isset($_POST['generate_seo_gen_password']) && check_admin_referer('seo_gen_generate_password_action', 'seo_gen_nonce')) {
        $new_password_info = go_organic_generate_application_password($user_id);
        if ($new_password_info) {
            update_option('go_organic_latest_password', $new_password_info[0]);
            $stored_password = $new_password_info[0];
        }
    }

    // Handle connection
    $connect_message = '';
    if (isset($_POST['go_organic_connect']) && check_admin_referer('go_organic_connect_action', 'go_organic_connect_nonce')) {
        $connect_message = go_organic_handle_connection($api_username, $stored_password);
        if (strpos($connect_message, 'success') !== false) {
            $stored_api_key = get_option('go_organic_latest_api_key');
        }
    }

    // Check connection status
    $is_connected = false;
    if ($stored_api_key) {
        $is_connected = go_organic_check_connection($stored_api_key);
    }

    // Display the admin interface
    go_organic_display_admin_interface($api_username, $new_password_info, $stored_password, $stored_api_key, $is_connected, $connect_message);
}

/**
 * Ensure the API user exists and has proper permissions.
 *
 * @param string $username The API username.
 * @return int|false User ID or false on failure.
 */
function go_organic_ensure_api_user($username)
{
    $user_id = username_exists($username);

    if (!$user_id) {
        $host = parse_url(get_home_url(), PHP_URL_HOST) ?: 'example.com';
        $user_email = 'api.' . time() . '@' . $host;

        $user_id = wp_create_user($username, wp_generate_password(), $user_email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('editor');
        }
    }

    return $user_id;
}

/**
 * Generate a new application password for the API user.
 *
 * @param int $user_id The user ID.
 * @return array|false Password info or false on failure.
 */
function go_organic_generate_application_password($user_id)
{
    if ($user_id && !is_wp_error($user_id)) {
        WP_Application_Passwords::delete_all_application_passwords($user_id);
        return WP_Application_Passwords::create_new_application_password(
            $user_id,
            ['name' => 'Go/Organic Plugin']
        );
    }
    return false;
}

/**
 * Handle the connection process to Go/Organic.
 *
 * @param string $username The API username.
 * @param string $password The application password.
 * @return string Connection message.
 */
function go_organic_handle_connection($username, $password)
{
    if (!$username || !$password) {
        return '<div class="notice notice-error"><p>Please generate an application password first</p></div>';
    }

    $response = go_organic_generate_api_key($username, $password);

    if ($response) {
        $status_code = $response['status_code'];
        if ($status_code === 201) {
            $api_key = $response['data'];
            update_option('go_organic_latest_api_key', $api_key);
            return '<div class="notice notice-success"><p>Go/Organic successfully connected</p></div>';
        } else {
            $json_response = json_decode($response['data'], true);
            $error_message = $json_response['message'] ?? 'Unknown error';
            return '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
        }
    } else {
        return '<div class="notice notice-error"><p>Failed to connect to API</p></div>';
    }
}

/**
 * Display the admin interface HTML.
 *
 * @param string $api_username The API username.
 * @param array|null $new_password_info New password information.
 * @param string $stored_password The stored password.
 * @param string $stored_api_key The stored API key.
 * @param bool $is_connected Connection status.
 * @param string $connect_message Connection message.
 * @return void
 */
function go_organic_display_admin_interface($api_username, $new_password_info, $stored_password, $stored_api_key, $is_connected, $connect_message)
{
    echo $connect_message;
    ?>
    <div class="wrap">
        <h1>SEO Gen Integration Credentials</h1>
        <p>Use these credentials for external API access.</p>

        <!-- Connection Status -->
        <?php if ($stored_api_key): ?>
            <?php if ($is_connected): ?>
                <div class="notice notice-success">
                    <p>
                        <strong>Connection Status : </strong>
                        <span style="color: green; font-size: 1.2em;">&#x2714;</span>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Connection Status : </strong>
                        <span style="color: red; font-size: 1.2em;">&#x2716;</span>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th>Site Base URL</th>
                    <td><code><?php echo esc_url(get_home_url()); ?></code></td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td><code><?php echo esc_html($api_username); ?></code></td>
                </tr>
                <tr>
                    <th>Password</th>
                    <td>
                        <?php if (is_array($new_password_info)): ?>
                            <p><strong>Copy this password now - it will not be shown again.</strong></p>
                            <input type="text" class="large-text" readonly value="<?php echo esc_attr($new_password_info[0]); ?>" />
                            <p class="description">This is an Application Password, valid for API access only.</p>
                        <?php else: ?>
                            <p class="description">Click below to generate a new password.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Generate Password Form -->
        <form method="post" action="">
            <?php wp_nonce_field('seo_gen_generate_password_action', 'seo_gen_nonce'); ?>
            <input type="hidden" name="generate_seo_gen_password" value="1" />
            <?php submit_button('Generate New Application Password'); ?>
        </form>

        <!-- Connect Header -->
        <h2 style="margin-top:30px;">Connect to Go/Organic</h2>
        <?php if ($stored_password): ?>
            <p>Use the generated credentials above to connect to Go/Organic service.</p>

            <!-- Connect Form -->
            <form method="post" action="" style="margin-top:20px;">
                <?php wp_nonce_field('go_organic_connect_action', 'go_organic_connect_nonce'); ?>
                <input type="hidden" name="go_organic_connect" value="1" />
                <?php submit_button('Connect to Go/Organic'); ?>
            </form>
        <?php else: ?>
            <p>Please generate an application password first before connecting to Go/Organic.</p>
        <?php endif; ?>
    </div>
    <?php
}
