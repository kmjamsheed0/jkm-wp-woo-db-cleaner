<?php
/**
 * Plugin Name: WP & WooCommerce DB Cleaner
 * Plugin URI: https://github.com/kmjamsheed0/jkm-wp-woo-db-cleaner
 * Description: Automatically cleans and optimizes WordPress & WooCommerce database.
 * Version: 1.0
 * Author: Jamsheed
 * Author URI: https://github.com/kmjamsheed0
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the admin menu
function jkmccfw_add_db_cleaner_menu() {
    add_submenu_page(
        'tools.php',
        'WP & WooCommerce DB Cleaner',
        'WP & WooCommerce DB Cleaner',
        'manage_options',
        'jkmccfw-db-cleaner',
        'jkmccfw_db_cleaner_page'
    );
}
add_action('admin_menu', 'jkmccfw_add_db_cleaner_menu');

// Display the admin page
function jkmccfw_db_cleaner_page() {
    if (isset($_POST['jkmccfw_clean_db'])) {
        jkmccfw_run_full_cleanup();
        echo '<div class="updated"><p><strong>Database Cleaned Successfully!</strong></p></div>';
    }

    ?>
    <div class="wrap">
        <h1>WP & WooCommerce Database Cleaner</h1>
        <p>Click below to manually clean unnecessary data and optimize your database.</p>
        <form method="post">
            <input type="hidden" name="jkmccfw_clean_db" value="1">
            <?php submit_button('Clean Database Now'); ?>
        </form>
    </div>
    <?php
}

// Function to clean database
function jkmccfw_run_full_cleanup() {
    global $wpdb;

    // Run cleanup in smaller batches to avoid timeout issues
    jkmccfw_clean_wp_data($wpdb);
    jkmccfw_clean_woo_data($wpdb);
    jkmccfw_optimize_tables($wpdb);
}

// Cleanup WordPress data
function jkmccfw_clean_wp_data($wpdb) {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
    $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
    $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' OR comment_approved = 'trash'");
    $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})");
}

// Cleanup WooCommerce data
function jkmccfw_clean_woo_data($wpdb) {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_wc_session_%'");
    $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_sessions WHERE session_expiry < UNIX_TIMESTAMP(NOW())");
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-pending', 'wc-cancelled', 'wc-failed') AND post_date < NOW() - INTERVAL 180 DAY");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wc_cart_tracking");
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_webhooks WHERE delivery_status = 'failed' AND date_created < NOW() - INTERVAL 90 DAY");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wc_product_meta_lookup");
}

// Optimize database tables
function jkmccfw_optimize_tables($wpdb) {
    $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->comments}, {$wpdb->commentmeta}, {$wpdb->options}, {$wpdb->prefix}woocommerce_sessions");
}

// Schedule automatic cleanup every 24 hours
function jkmccfw_schedule_cron() {
    if (!wp_next_scheduled('jkmccfw_daily_db_cleanup')) {
        wp_schedule_event(time(), 'daily', 'jkmccfw_daily_db_cleanup');
    }
}
add_action('wp', 'jkmccfw_schedule_cron');

// Hook for scheduled cleanup
add_action('jkmccfw_daily_db_cleanup', 'jkmccfw_run_full_cleanup');

// Clear scheduled cron on plugin deactivation
function jkmccfw_remove_cron() {
    wp_clear_scheduled_hook('jkmccfw_daily_db_cleanup');
}
register_deactivation_hook(__FILE__, 'jkmccfw_remove_cron');
?>
