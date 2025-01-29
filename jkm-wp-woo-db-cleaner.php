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
    // Check for nonce to prevent unauthorized access
    if (isset($_POST['jkmccfw_clean_db']) && check_admin_referer('jkmccfw_clean_db_nonce')) {
        jkmccfw_run_full_cleanup();
        echo '<div class="updated"><p><strong>Database Cleaned Successfully!</strong></p></div>';
    }

    ?>
    <div class="wrap">
        <h1>WP & WooCommerce Database Cleaner</h1>
        <p>Click below to manually clean unnecessary data and optimize your database.</p>
        <form method="post">
            <?php wp_nonce_field('jkmccfw_clean_db_nonce'); ?>
            <input type="hidden" name="jkmccfw_clean_db" value="1">
            <?php submit_button('Clean Database Now'); ?>
        </form>
    </div>
    <?php
}

// Function to clean database
function jkmccfw_run_full_cleanup() {
    global $wpdb;

    // Ensure the tables exist before attempting to clean them
    if (table_exists($wpdb->posts) && table_exists($wpdb->postmeta) && table_exists($wpdb->comments)) {
        jkmccfw_clean_wp_data($wpdb);
        jkmccfw_clean_woo_data($wpdb);
        jkmccfw_optimize_tables($wpdb);
    } else {
        // Log error if required tables do not exist
        error_log('WP & WooCommerce DB Cleaner: Required tables are missing.');
        return;
    }
}

// Check if a table exists
function table_exists($table) {
    global $wpdb;
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    return !empty($result);
}

// Cleanup WordPress data
function jkmccfw_clean_wp_data($wpdb) {
    try {
        // Remove expired transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        
        // Remove orphaned postmeta
        $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");

        // Remove post revisions
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");

        // Remove spam and trash comments
        $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' OR comment_approved = 'trash'");

        // Remove orphaned comment meta
        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})");
    } catch (Exception $e) {
        // Log error to WordPress debug log
        error_log('WP & WooCommerce DB Cleaner (WordPress Data): ' . $e->getMessage());
    }
}

function jkmccfw_clean_woo_data($wpdb) {
    try {
        // Remove WooCommerce session data
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_wc_session_%'");

        // Remove WooCommerce orders older than 180 days
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-pending', 'wc-cancelled', 'wc-failed') AND post_date < NOW() - INTERVAL 180 DAY");

        // Check if 'wp_wc_cart_tracking' table exists before truncating
        $cart_tracking_table = $wpdb->prefix . "wc_cart_tracking";
        if ($wpdb->get_var("SHOW TABLES LIKE '$cart_tracking_table'") == $cart_tracking_table) {
            $wpdb->query("TRUNCATE TABLE $cart_tracking_table");
        }

        // Check if 'wp_wc_webhooks' table exists
        $webhooks_table = $wpdb->prefix . "wc_webhooks";
        if ($wpdb->get_var("SHOW TABLES LIKE '$webhooks_table'") == $webhooks_table) {
            // Check if the 'delivery_status' column exists before using it
            $columns = $wpdb->get_col("DESCRIBE $webhooks_table");
            if (in_array('delivery_status', $columns)) {
                $wpdb->query("DELETE FROM $webhooks_table WHERE delivery_status = 'failed' AND date_created < NOW() - INTERVAL 90 DAY");
            }
        }

        // Check if 'wc_product_meta_lookup' table exists before truncating
        $product_meta_lookup_table = $wpdb->prefix . "wc_product_meta_lookup";
        if ($wpdb->get_var("SHOW TABLES LIKE '$product_meta_lookup_table'") == $product_meta_lookup_table) {
            $wpdb->query("TRUNCATE TABLE $product_meta_lookup_table");
        }

    } catch (Exception $e) {
        error_log('WP & WooCommerce DB Cleaner (WooCommerce Data): ' . $e->getMessage());
    }
}


// Optimize database tables
function jkmccfw_optimize_tables($wpdb) {
    try {
        // Run table optimization
        $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->comments}, {$wpdb->commentmeta}, {$wpdb->options}, {$wpdb->prefix}woocommerce_sessions");
    } catch (Exception $e) {
        // Log error to WordPress debug log
        error_log('WP & WooCommerce DB Cleaner (Table Optimization): ' . $e->getMessage());
    }
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