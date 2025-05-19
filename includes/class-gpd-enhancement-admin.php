<?php
/**
 * File: class-gpd-enhancement-admin.php
 * GPD Data Enhancement Admin UI
 *
 * Manages admin-specific UI elements for the GPD Data Enhancement add-on.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class GPD_Enhancement_Admin {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
        return self::$instance;
    }

    private function init_hooks() {
        // Add custom column to the 'business' CPT list table
        add_filter( 'manage_business_posts_columns', [ $this, 'add_scrape_action_column' ] );
        add_action( 'manage_business_posts_custom_column', [ $this, 'render_scrape_action_column' ], 10, 2 );

        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Adds the 'Scrape Insights' column to the Business CPT.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_scrape_action_column( $columns ) {
        // Add column before the 'date' column if it exists, otherwise at the end.
        if ( isset( $columns['date'] ) ) {
            $new_columns = [];
            foreach ( $columns as $key => $value ) {
                if ( $key === 'date' ) {
                    $new_columns['gpd_scrape_insights'] = __( 'Data Enhancement', 'gpd-data-enhancement' );
                }
                $new_columns[$key] = $value;
            }
            return $new_columns;
        } else {
            $columns['gpd_scrape_insights'] = __( 'Data Enhancement', 'gpd-data-enhancement' );
        }
        return $columns;
    }

    /**
     * Renders the content for the 'Scrape Insights' column.
     *
     * @param string $column_name The name of the current column.
     * @param int    $post_id     The ID of the current post.
     */
    public function render_scrape_action_column( $column_name, $post_id ) {
        if ( 'gpd_scrape_insights' === $column_name ) {
            $button_text = __( 'Scrape Insights', 'gpd-data-enhancement' );

            // Correct Nonce: must match the AJAX handler for all-sources scraping!
            printf(
                '<button type="button" class="button gpd-scrape-insights-button" data-postid="%d" data-nonce="%s">%s</button>',
                esc_attr( $post_id ),
                esc_attr( wp_create_nonce( 'gpd_scrape_all_sources_nonce_' . $post_id ) ), // IMPORTANT: Updated to match AJAX handler
                esc_html( $button_text )
            );
            // Status messages div
            echo '<div class="gpd-scrape-status notice" id="gpd-scrape-status-' . esc_attr( $post_id ) . '" style="display:none; padding: 5px; margin-top: 5px; margin-bottom:5px;"></div>';

            // --- ADD THIS HTML STRUCTURE FOR DISPLAYING SCRAPED DATA ---
            echo '<div id="gpd-scraped-data-container-' . esc_attr( $post_id ) . '" style="display:none; border: 1px solid #ccd0d4; padding: 10px; margin-top: 10px; margin-bottom:10px; background-color: #f9f9f9;">';
            echo '<h4>' . esc_html__( 'Scraped Data:', 'gpd-data-enhancement' ) . '</h4>';
            echo '<p style="margin-bottom: 5px;"><strong>' . esc_html__( 'Page Title:', 'gpd-data-enhancement' ) . '</strong><br><span class="scraped-page-title" style="font-size: 0.9em; color: #333;"></span></p>';
            echo '<p style="margin-bottom: 5px;"><strong>' . esc_html__( 'First H1:', 'gpd-data-enhancement' ) . '</strong><br><span class="scraped-first-h1" style="font-size: 0.9em; color: #333;"></span></p>';
            echo '<p style="margin-bottom: 0;"><strong>' . esc_html__( 'Meta Description:', 'gpd-data-enhancement' ) . '</strong><br><span class="scraped-meta-description" style="white-space: pre-wrap; word-break: break-word; display: block; max-height: 100px; overflow-y: auto; border: 1px solid #eee; padding: 5px; font-size: 0.9em; color: #333; background-color: #fff;"></span></p>';
            echo '</div>';
            // --- END OF ADDED HTML STRUCTURE ---
        }
    }

    /**
     * Enqueues admin scripts and styles for the add-on.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on the 'business' CPT list table page
        // Ensure GPD_ENHANCEMENT_PLUGIN_URL and GPD_ENHANCEMENT_VERSION are correctly defined constants
        if ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'business' === $_GET['post_type'] && defined('GPD_ENHANCEMENT_PLUGIN_URL') && defined('GPD_ENHANCEMENT_VERSION') ) {

            wp_enqueue_script(
                'gpd-enhancement-admin-js',
                GPD_ENHANCEMENT_PLUGIN_URL . 'assets/js/gpd-enhancement-admin.js', // Path to your JS file
                [ 'jquery' ],
                GPD_ENHANCEMENT_VERSION,
                true // Load in footer
            );

            // Localize script with data
            wp_localize_script(
                'gpd-enhancement-admin-js',
                'gpdEnhancementAdmin', // Object name in JavaScript
                [
                    'ajax_url'           => admin_url( 'admin-ajax.php' ),
                    'text_scraping'      => __( 'Scraping...', 'gpd-data-enhancement' ),
                    'text_error'         => __( 'Error', 'gpd-data-enhancement' ),
                    'text_done'          => __( 'Done', 'gpd-data-enhancement' ),
                    'text_retry_button'  => __( 'Retry Scrape', 'gpd-data-enhancement' )
                ]
            );
        }
    }
}
