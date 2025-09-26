<?php
/**
 * Admin Settings Page for WP Tracking System
 *
 * @package WPTrackingSystem
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register menu item in WP Admin.
 */
function wpts_register_settings_page() {
    add_menu_page(
        __( 'Tracking Settings', 'wp-tracking-system' ),
        __( 'Tracking', 'wp-tracking-system' ),
        'manage_options',
        'wpts-settings',
        'wpts_render_settings_page',
        'dashicons-chart-line'
    );
}
add_action( 'admin_menu', 'wpts_register_settings_page' );

/**
 * Render the settings page content.
 */
function wpts_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Tracking Settings', 'wp-tracking-system' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wpts_settings_group' );
            do_settings_sections( 'wpts-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register settings fields.
 */
function wpts_register_settings() {
    register_setting( 'wpts_settings_group', 'wpts_supabase_url', [
        'sanitize_callback' => 'esc_url_raw',
    ] );

    register_setting( 'wpts_settings_group', 'wpts_supabase_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    add_settings_section(
        'wpts_main_section',
        __( 'Supabase Configuration', 'wp-tracking-system' ),
        null,
        'wpts-settings'
    );

    add_settings_field(
        'wpts_supabase_url',
        __( 'Supabase URL', 'wp-tracking-system' ),
        function() {
            $value = get_option( 'wpts_supabase_url', '' );
            echo '<input type="url" name="wpts_supabase_url" value="' . esc_attr( $value ) . '" class="regular-text">';
        },
        'wpts-settings',
        'wpts_main_section'
    );

    add_settings_field(
        'wpts_supabase_key',
        __( 'Supabase Key', 'wp-tracking-system' ),
        function() {
            $value = get_option( 'wpts_supabase_key', '' );
            echo '<input type="password" name="wpts_supabase_key" value="' . esc_attr( $value ) . '" class="regular-text">';
        },
        'wpts-settings',
        'wpts_main_section'
    );
}
add_action( 'admin_init', 'wpts_register_settings' );
