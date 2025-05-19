<?php
/**
 * File: class-gpd-enhancement-ajax.php
 * GPD Data Enhancement AJAX Handler (Enhanced)
 *
 * Handles AJAX requests for the GPD Data Enhancement add-on, including multi-source scraping.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class GPD_Enhancement_Ajax {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init_hooks() {
        // Keep your existing handle_scrape_insights_request if it's still used or for legacy
        add_action( 'wp_ajax_gpd_enhancement_scrape_insights', [ $this, 'handle_scrape_insights_request' ] ); 
        add_action( 'wp_ajax_gpd_enhancement_scrape_all_sources', [ $this, 'handle_scrape_all_sources_request' ] );
    }

    // Keep your handle_scrape_insights_request method as is if you still need it.
    public function handle_scrape_insights_request() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        // IMPORTANT: Ensure nonce matches what's generated for this specific action if it's different
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! check_ajax_referer( 'gpd_scrape_insights_nonce_' . $post_id, '_ajax_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed for scrape_insights.', 'gpd-data-enhancement' ) ], 403 );
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'gpd-data-enhancement' ) ], 403 );
            return;
        }
        $post_type = get_post_type( $post_id );
        if ( ! $post_id || ( $post_type !== 'gd_place' && $post_type !== 'business' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Post ID or Post Type. Expected gd_place or business.', 'gpd-data-enhancement' ) . ' (Received: ' . esc_html($post_type) . ')' ], 400 );
            return;
        }
        if ( ! class_exists( 'GPD_Enhancement_Scraper' ) ) {
            wp_send_json_error( [ 'message' => __( 'Scraper class not found.', 'gpd-data-enhancement' ) ], 500 );
            return;
        }

        // This now directly calls the primary website scraper for the 'insights' action
        $result = GPD_Enhancement_Scraper::scrape_primary_website( $post_id );
        $data_saved_message_suffix = '';

        // --- SAVING LOGIC FOR 'scrape_insights' (primary website) ---
        if ( isset($result['success']) && $result['success'] && !empty($result['data']) ) {
            $scraped_data_array = $result['data'];
            if ( ! empty( $scraped_data_array ) && isset( $scraped_data_array['page_title'] ) ) { // Check if there's something to save
                update_post_meta( $post_id, '_gpd_scraped_page_title', sanitize_text_field( $scraped_data_array['page_title'] ) );
                update_post_meta( $post_id, '_gpd_scraped_first_h1', sanitize_text_field( $scraped_data_array['first_h1'] ) );
                update_post_meta( $post_id, '_gpd_scraped_meta_description', sanitize_textarea_field( $scraped_data_array['meta_description'] ) );
                update_post_meta( $post_id, '_gpd_last_scraped_timestamp', current_time( 'mysql', true ) );
                $data_saved_message_suffix = ' ' . __( 'Data saved.', 'gpd-data-enhancement' );
            }
        }
        // --- END SAVING LOGIC ---

        if ( isset($result['success']) && $result['success'] ) {
            wp_send_json_success( [
                'message' => ($result['message'] ?? __('Data scraped successfully.', 'gpd-data-enhancement')) . $data_saved_message_suffix,
                'data'    => $result['data'] ?? null // This is what the JS for single source expects
            ] );
        } else {
            wp_send_json_error( [
                'message' => $result['message'] ?? __('An error occurred during scraping.', 'gpd-data-enhancement'),
                'debug_info' => $result // Keep debug_info if it's helpful for you
            ] );
        }
    }


    public function handle_scrape_all_sources_request() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        // $source  = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : ''; // Keep if you plan to use single-source triggering

        if ( ! isset( $_POST['_ajax_nonce'] ) || ! check_ajax_referer( 'gpd_scrape_all_sources_nonce_' . $post_id, '_ajax_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed for scrape_all_sources.', 'gpd-data-enhancement' ) ], 403 );
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'gpd-data-enhancement' ) ], 403 );
            return;
        }
        $post_type = get_post_type( $post_id );
        if ( ! $post_id || ( $post_type !== 'gd_place' && $post_type !== 'business' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Post ID or Post Type. Expected gd_place or business.', 'gpd-data-enhancement' ) . ' (Received: ' . esc_html($post_type) . ')' ], 400 );
            return;
        }
        if ( ! class_exists( 'GPD_Enhancement_Scraper' ) ) {
            wp_send_json_error( [ 'message' => __( 'Scraper class not found.', 'gpd-data-enhancement' ) ], 500 );
            return;
        }

        // For now, let's assume 'scrape_all_sources' primarily uses the 'scrape_primary_website'
        // and then you'll add the other sources.
        // We will treat 'primary_website' as one of the sources.
        
        $results = [];
        $overall_success = true; // Assume success until a source fails

        // --- 1. Scrape Primary Website (as the first source) ---
        $primary_result = GPD_Enhancement_Scraper::scrape_primary_website( $post_id );
        $data_saved_message_suffix = '';

        if (isset($primary_result['success']) && $primary_result['success'] && !empty($primary_result['data'])) {
            $scraped_data_array = $primary_result['data'];
            // Save only if there's actual data to save
            if ( !empty( $scraped_data_array['page_title'] ) || !empty( $scraped_data_array['first_h1'] ) || !empty( $scraped_data_array['meta_description'] ) ) {
                 if ($scraped_data_array['page_title'] !== 'Not found' || $scraped_data_array['first_h1'] !== 'Not found' || $scraped_data_array['meta_description'] !== 'Not found') {
                    update_post_meta( $post_id, '_gpd_scraped_page_title', sanitize_text_field( $scraped_data_array['page_title'] ) );
                    update_post_meta( $post_id, '_gpd_scraped_first_h1', sanitize_text_field( $scraped_data_array['first_h1'] ) );
                    update_post_meta( $post_id, '_gpd_scraped_meta_description', sanitize_textarea_field( $scraped_data_array['meta_description'] ) );
                    update_post_meta( $post_id, '_gpd_last_scraped_timestamp', current_time( 'mysql', true ) );
                    $data_saved_message_suffix = ' ' . __( 'Data saved.', 'gpd-data-enhancement' );
                 } else {
                    $data_saved_message_suffix = ' ' . __( 'No new data to save.', 'gpd-data-enhancement' );
                 }
            } else {
                 $data_saved_message_suffix = ' ' . __( 'No data found to save.', 'gpd-data-enhancement' );
            }
        }
        $results['primary_website'] = [
            'success' => $primary_result['success'] ?? false,
            'message' => ($primary_result['message'] ?? __('Scraping primary website failed.', 'gpd-data-enhancement')) . $data_saved_message_suffix,
            'data'    => $primary_result['data'] ?? null // This is what JS expects for display
        ];
        if (!($primary_result['success'] ?? false)) {
            $overall_success = false;
        }
        // --- End Scrape Primary Website ---


        // --- Stubs for other sources (as per your existing code) ---
        $other_sources = ['google_places', 'padi', 'ssi', 'facebook', 'google_search_top10'];
        $scraped_domains_for_dedup = []; // For future use if other scrapers also hit websites

        foreach ($other_sources as $src_key) {
            $method_name = "scrape_{$src_key}";
            if (is_callable(['GPD_Enhancement_Scraper', $method_name])) {
                // Pass $post_id and potentially $scraped_domains_for_dedup if needed by those methods
                $res = GPD_Enhancement_Scraper::$method_name($post_id, $scraped_domains_for_dedup); 
                $results[$src_key] = $res;
                if (!($res['success'] ?? false)) $overall_success = false;
                // if (isset($res['domains']) && is_array($res['domains'])) { // For deduplication later
                //    $scraped_domains_for_dedup = array_unique(array_merge($scraped_domains_for_dedup, $res['domains']));
                // }
            } else {
                $results[$src_key] = ['success' => false, 'message' => "Scraper for source '{$src_key}' not implemented.", 'data' => null];
                $overall_success = false;
            }
        }
        // --- End Stubs for other sources ---

        $final_message = $overall_success ? 
            __( 'All source processing attempted.', 'gpd-data-enhancement' ) :
            __( 'One or more sources had issues.', 'gpd-data-enhancement' );

        // The wp_send_json_success/error depends on an overall status, 
        // but the JS iterates through per-source success.
        // So, we always send success if the AJAX itself worked, and let JS interpret per-source results.
        wp_send_json_success( [
            'message' => $final_message, // This is the main message shown by JS
            'results' => $results       // This is the object JS iterates over
        ] );
    }
}
