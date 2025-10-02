<?php
/**
 * Posts API Handler
 * Handles bulk post creation via REST API
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bulk-create posts from a REST API request.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response The response with created posts and errors.
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
 * Register the posts REST API route.
 *
 * @return void
 */
function go_organic_register_posts_api()
{
    register_rest_route('seo-gen/v1', '/posts', [
        'methods' => 'POST',
        'callback' => 'go_organic_bulk_create_posts',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);
}
add_action('rest_api_init', 'go_organic_register_posts_api');
