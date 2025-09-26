<?php
/**
 * Plugin Name: Go/Organic WP Plugin
 * Description: A plugin to bulk-create posts via a REST API and manage integration credentials with integrated tracking system.
 * Version: 0.5.0
 * Author: Purple Box AI
 */

require 'inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Prevent direct file access
if (!defined('ABSPATH')) {
  exit;
}

// Load tracking system configuration and API
require_once __DIR__ . '/inc/config.php';
require_once plugin_dir_path(__FILE__) . 'inc/api/tracking.php';

/**x`x`
 * Bulk-create posts from a REST API request.
 */
function go_organic_bulk_create_posts($request)
{
  $posts = $request->get_param('posts');
  $created = [];
  $errors = [];

  foreach ($posts as $item) {
    $source_id = $item['id'] ?? null;
    $title = sanitize_text_field($item['title'] ?? '');
    $content = $item['content'] ?? '';
    $custom_slug = sanitize_text_field($item['slug'] ?? '');

    if (empty($title) || empty($custom_slug)) {
      $errors[] = [
        'source_id' => $source_id,
        'title' => $title ?: 'N/A',
        'message' => 'Item is missing a title or slug.'
      ];
      continue;
    }

    $real_slug = '';
    $category_id = null;
    $category_name = null;

    // If slug format includes category (e.g., "category/post-slug")
    if (strpos($custom_slug, '/') !== false) {
      $parts = explode('/', $custom_slug, 2);

      if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
        $errors[] = [
          'source_id' => $source_id,
          'title' => $title,
          'message' => 'Invalid slug format. Use "category/slug".'
        ];
        continue;
      }

      [$category_name, $real_slug] = $parts;

      $term = get_term_by('slug', sanitize_title($category_name), 'category');
      if (!$term) {
        $new_term = wp_insert_term($category_name, 'category');
        if (is_wp_error($new_term)) {
          $errors[] = [
            'source_id' => $source_id,
            'title' => $title,
            'message' => "Failed to create category '$category_name': " . $new_term->get_error_message()
          ];
          continue;
        }
        $category_id = $new_term['term_id'];
      } else {
        $category_id = $term->term_id;
      }
    } else {
      $real_slug = $custom_slug;
      $category_id = get_option('default_category');
    }

    if (empty($category_id)) {
      $errors[] = [
        'source_id' => $source_id,
        'title' => $title,
        'message' => 'Could not assign category.'
      ];
      continue;
    }

    // Prepare post data
    $post_data = [
      'post_title' => $title,
      'post_content' => $content,
      'post_status' => $item['status'] ?? 'publish',
      'post_name' => sanitize_title($real_slug),
      'post_category' => [$category_id],
    ];

    // Optional fields
    if (!empty($item['excerpt']))
      $post_data['post_excerpt'] = $item['excerpt'];
    if (!empty($item['date']))
      $post_data['post_date'] = $item['date'];
    if (!empty($item['author']))
      $post_data['post_author'] = intval($item['author']);
    if (!empty($item['comment_status']))
      $post_data['comment_status'] = $item['comment_status'];
    if (!empty($item['ping_status']))
      $post_data['ping_status'] = $item['ping_status'];

    // Insert post
    $post_id = wp_insert_post($post_data);

    if (!is_wp_error($post_id)) {
      $created[] = [
        'new_id' => $post_id,
        'source_id' => $source_id,
        'url' => get_permalink($post_id),
        'title' => $title,
        'slug' => $real_slug,
        'category' => $category_name ?? null
      ];
    } else {
      $errors[] = [
        'source_id' => $source_id,
        'title' => $title,
        'message' => 'Failed to insert post: ' . $post_id->get_error_message()
      ];
    }
  }

  return rest_ensure_response([
    'created_posts' => $created,
    'errors' => $errors
  ]);
}

/**
 * Registers REST API route.
 */
add_action('rest_api_init', function () {
  register_rest_route('seo-gen/v1', '/posts', [
    'methods' => 'POST',
    'callback' => 'go_organic_bulk_create_posts',
    'permission_callback' => function () {
      return current_user_can('edit_posts');
    }
  ]);
});

/**
 * Adds admin menu.
 */
add_action('admin_menu', function () {
  add_menu_page(
    'Go/Organic Settings',
    'Go/Organic',
    'manage_options',
    'go-organic',
    'render_integration_page',
    'dashicons-admin-generic',
    25
  );
});

/**
 * Render Integration Page (API Credentials).
 */
function render_integration_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $api_username = 'seo_gen_api_user';
  $new_password_info = null;
  $stored_password = get_option('go_organic_latest_password');
  $stored_api_key = get_option('go_organic_latest_api_key');

  $user_id = username_exists($api_username);
  if (!$user_id) {
    $host = parse_url(get_home_url(), PHP_URL_HOST) ?: 'example.com';
    $user_email = 'api.' . time() . '@' . $host;

    $user_id = wp_create_user($api_username, wp_generate_password(), $user_email);
    if (!is_wp_error($user_id)) {
      $user = new WP_User($user_id);
      $user->set_role('editor');
    }
  }

  // Generate Application Password
  if (isset($_POST['generate_seo_gen_password']) && check_admin_referer('seo_gen_generate_password_action', 'seo_gen_nonce')) {
    if ($user_id && !is_wp_error($user_id)) {
      WP_Application_Passwords::delete_all_application_passwords($user_id);
      $new_password_info = WP_Application_Passwords::create_new_application_password(
        $user_id,
        ['name' => 'Go/Organic Plugin']
      );

      update_option('go_organic_latest_password', $new_password_info[0]);
      $stored_password = $new_password_info[0];
    }
  }

  // Handle Connect Form
  $connect_message = '';
  if (isset($_POST['go_organic_connect']) && check_admin_referer('go_organic_connect_action', 'go_organic_connect_nonce')) {
    $input_username = sanitize_text_field($_POST['go_organic_connect_username']);
    $input_password = sanitize_text_field($_POST['go_organic_connect_password']);

    if ($input_username && $input_password) {
      $response = generate_api_key($input_username, $input_password);

      if ($response) {
        $status_code = $response['status_code'];
        if ($status_code === 201) {
          $api_key = $response['data'];
          update_option('go_organic_latest_api_key', $api_key);
          $stored_api_key = $api_key;
          $connect_message = '<div class="notice notice-success"><p>SEO Gen successfully connected</p></div>';
        } else {
          $json_response = json_decode($response['data'], true);
          $error_message = $json_response['message'];
          $connect_message = '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
        }
      } else {
        $connect_message = '<div class="notice notice-error"><p>Failed fetch API</p></div>';
      }
    } else {
      $connect_message = '<div class="notice notice-error"><p>Username and Password are required</p></div>';
    }

    echo $connect_message;
  }

  // Connection Status
  $is_connected = false;
  if ($stored_api_key) {
    $is_connected = go_organic_check_connection($stored_api_key);
  }

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

    <!-- Test Stored Password Form -->
    <form method="post" action="" style="margin-top:20px;">
      <?php wp_nonce_field('seo_gen_test_password_action', 'seo_gen_nonce_test'); ?>
      <input type="hidden" name="test_password_storage" value="1" />
      <?php submit_button('Test Password Storage'); ?>
    </form>

    <!-- Connect Header -->
    <h2 style="margin-top:30px;">Connect to SEO Gen</h2>
    <p>Enter the credentials that have been registered in SEO Gen</p>

    <!-- Connect Form -->
    <form method="post" action="" style="margin-top:20px;">
      <?php wp_nonce_field('go_organic_connect_action', 'go_organic_connect_nonce'); ?>
      <input type="hidden" name="go_organic_connect" value="1" />
      <table class="form-table">
        <tbody>
          <tr>
            <th><label for="go_organic_connect_username">Username</label></th>
            <td><input type="text" name="go_organic_connect_username" id="go_organic_connect_username" class="regular-text" /></td>
          </tr>
          <tr>
            <th><label for="go_organic_connect_password">Password</label></th>
            <td><input type="password" name="go_organic_connect_password" id="go_organic_connect_password" class="regular-text" /></td>
          </tr>
        </tbody>
      </table>
      <?php submit_button('Connect'); ?>
    </form>
  </div>
  <?php
}


$updateChecker = PucFactory::buildUpdateChecker(
  'https://seo-service.purple-box.app/wp-plugin/metadata',
  __FILE__,
  'go-organic-wp-plugin'
);
