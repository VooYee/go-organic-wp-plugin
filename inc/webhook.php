<?php
/**
 * Webhook Handler
 * Handles triggering webhooks on content changes
 *
 * @package Go_Organic_WP_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. HANDLE SAVE/UPDATE
 * Trigger webhook validation and sending on post save
 */
function go_organic_trigger_content_webhook($post_id, $post, $update)
{
    // Ignore autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ignore revisions
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Filter allowed post types
    $allowed_post_types = ['post', 'page'];
    if (!in_array($post->post_type, $allowed_post_types)) {
        return;
    }

    // Build Payload for Update/Create
    $payload = [
        'event'       => $update ? 'update' : 'create',
        'post_id'     => $post_id,
        'post_title'  => $post->post_title,
        'post_status' => $post->post_status,
        'post_type'   => $post->post_type,
        'post_url'    => get_permalink($post_id),
        'created_at'  => $post->post_date,
        'modified_at' => $post->post_modified,
        'author_id'   => $post->post_author,
    ];

    go_organic_send_webhook($payload);
}
add_action('save_post', 'go_organic_trigger_content_webhook', 10, 3);


/**
 * 2. HANDLE DELETE
 * Trigger webhook when a post is permanently deleted
 */
function go_organic_trigger_delete_webhook($post_id)
{
    $post = get_post($post_id);

    // Safety check: ensure post object exists
    if (!$post) {
        return;
    }

    // Filter allowed post types
    $allowed_post_types = ['post', 'page'];
    if (!in_array($post->post_type, $allowed_post_types)) {
        return;
    }

    // Build Payload for Delete
    $payload = [
        'event'       => 'delete',
        'post_id'     => $post_id,
        'post_type'   => $post->post_type,
        // We include identifying info just in case the backend needs to look it up to delete it
        'post_url'    => get_permalink($post_id),
        'deleted_at'  => current_time('mysql'),
    ];

    go_organic_send_webhook($payload);
}
// 'before_delete_post' fires just before the post is removed from the DB
add_action('before_delete_post', 'go_organic_trigger_delete_webhook');


/**
 * HELPER: Send the Webhook
 * Keeps the sending logic in one place
 */
function go_organic_send_webhook($payload)
{
    // Get Webhook URL
    $webhook_url = defined('BE_GO_ORGANIC_URL') ? BE_GO_ORGANIC_URL . '/wordpress/webhooks/article' : '';

    if (empty($webhook_url)) {
        return;
    }

    // adding identity
    $payload['site_url'] = get_site_url();
    $payload['username'] = wp_get_current_user()->user_login;

    $args = [
        'body'    => json_encode($payload),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout'  => 5,
        'blocking' => false,
    ];

    wp_remote_post($webhook_url, $args);
}
