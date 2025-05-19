<?php
/**
 * Plugin Name:       GPD Data Enhancement
 * Plugin URI:        #
 * Description:       Enhances Google Places Directory data by scraping additional information from various web sources. Requires Google Places Directory.
 * Version:           0.1.4 // Incremented version
 * Author:            TheRev
 * Author URI:        #
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gpd-data-enhancement
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'GPD_ENHANCEMENT_VERSION', '0.1.4' ); // Incremented version
define( 'GPD_ENHANCEMENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPD_ENHANCEMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if Google Places Directory (core plugin) is active.
 */
function gpd_enhancement_is_core_plugin_active() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Path for your Google Places Directory plugin
    $core_plugin_path = 'google-places-directory/google-places-directory.php'; 

    // Check if a key class from Google Places Directory exists.
    // 'GPD_Importer' is an example; use a class name you know is part of your Google Places Directory plugin.
    if ( is_plugin_active( $core_plugin_path ) && class_exists( 'GPD_Importer' ) ) {
        return true;
    }
    return false;
}

/**
 * Admin notice if core plugin is not active.
 */
function gpd_enhancement_core_plugin_inactive_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php
            printf(
                esc_html__( '%1$sGPD Data Enhancement%2$s requires %1$sGoogle Places Directory%2$s to be installed and active. Please activate Google Places Directory. The GPD Data Enhancement plugin has been automatically deactivated.', 'gpd-data-enhancement' ),
                '<strong>',
                '</strong>'
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin.
 */
function gpd_enhancement_init() {
    load_plugin_textdomain( 'gpd-data-enhancement', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    if ( ! gpd_enhancement_is_core_plugin_active() ) {
        add_action( 'admin_notices', 'gpd_enhancement_core_plugin_inactive_notice' );
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
        return;
    }

    // Load plugin classes
    require_once GPD_ENHANCEMENT_PLUGIN_DIR . 'includes/class-gpd-enhancement-admin.php';
    require_once GPD_ENHANCEMENT_PLUGIN_DIR . 'includes/class-gpd-enhancement-ajax.php';
    require_once GPD_ENHANCEMENT_PLUGIN_DIR . 'includes/class-gpd-enhancement-scraper.php';
    // Future classes:
    // require_once GPD_ENHANCEMENT_PLUGIN_DIR . 'includes/class-gpd-enhancement-cron.php';

    // Initialize classes
    if ( class_exists( 'GPD_Enhancement_Admin' ) && method_exists( 'GPD_Enhancement_Admin', 'instance' ) ) {
        GPD_Enhancement_Admin::instance();
    }
    
    if ( class_exists( 'GPD_Enhancement_Ajax' ) && method_exists( 'GPD_Enhancement_Ajax', 'instance' ) ) {
        $ajax_handler = GPD_Enhancement_Ajax::instance();
        if ( method_exists( $ajax_handler, 'init_hooks' ) ) {
            $ajax_handler->init_hooks(); // Call init_hooks() to add the AJAX actions
        }
    }
    
    // GPD_Enhancement_Scraper class provides static methods and does not need to be instantiated here.
    
    // Hook for automatic scraping. 
    // You need to verify 'gpd_after_business_processed' is the correct action hook 
    // provided by YOUR Google Places Directory plugin for when a listing is saved/updated.
    // It might also pass different parameters than $post_id, $details, $is_update.
    add_action( 'gpd_after_business_processed', 'gpd_enhancement_trigger_auto_scrape', 10, 3 );
}
// Load slightly later to ensure Google Places Directory might have loaded its classes.
add_action( 'plugins_loaded', 'gpd_enhancement_init', 20 ); 


/**
 * Placeholder for automatic scraping trigger.
 * Adjust parameters if your 'gpd_after_business_processed' hook provides different ones.
 */
function gpd_enhancement_trigger_auto_scrape( $post_id, $details, $is_update ) {
    // $post_id is the Post ID of the saved listing.
    // $details might be an array of submitted data or specific details.
    // $is_update is a boolean.
    
    error_log("GPD Enhancement: Listing processed (Post ID: {$post_id}), is_update: {$is_update}. Auto-scrape placeholder. Verify hook and parameters.");
    
    // TODO: Implement logic for automatic scraping if enabled.
    // if ( get_option( 'gpd_enable_auto_scrape', false ) && $post_id ) {
    //     GPD_Enhancement_Scraper::scrape_primary_website( $post_id );
    // }
}


/**
 * Handles plugin uninstallation.
 */
function gpd_enhancement_uninstall() {
    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        exit;
    }
    // TODO: Add cleanup logic here in later phases (e.g., delete options).
}
register_uninstall_hook( __FILE__, 'gpd_enhancement_uninstall' );

?>
