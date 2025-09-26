<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Hapus opsi yang disimpan plugin (misalnya, TrackerMeta)
delete_option('go_organic_latest_password');
delete_option('tracking_session');
delete_option('tracker_meta');

// Hapus data kustom kalau ada (contoh, tabel tracking)
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tracking_events");

// Bersihkan transient atau cache kalau ada
delete_transient('tracking_cache');
