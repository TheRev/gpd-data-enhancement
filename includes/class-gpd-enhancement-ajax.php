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
            // self::$instance->init_hooks(); // Should be called from main plugin bootstrap
        }
        return self::$instance;
    }

    /**
     * Initialize AJAX hooks.
     */
    public function init_hooks() {
        add_action( 'wp_ajax_gpd_enhancement_scrape_insights', [ $this, 'handle_scrape_insights_request' ] );
        add_action( 'wp_ajax_gpd_enhancement_scrape_all_sources', [ $this, 'handle_scrape_all_sources_request' ] );
        // Add additional AJAX hooks here as needed.
    }

    /**
     * Handles the AJAX request to initiate scraping for a specific post (legacy, for primary site only).
     */
    public function handle_scrape_insights_request() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! check_ajax_referer( 'gpd_scrape_insights_nonce_' . $post_id, '_ajax_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'gpd-data-enhancement' ) ], 403 );
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
        $result = GPD_Enhancement_Scraper::scrape_primary_website( $post_id );
        if ( isset($result['success']) && $result['success'] ) {
            wp_send_json_success( [
                'message' => $result['message'] ?? __('Data scraped successfully.', 'gpd-data-enhancement'),
                'data'    => $result['data'] ?? null
            ] );
        } else {
            wp_send_json_error( [
                'message' => $result['message'] ?? __('An error occurred during scraping.', 'gpd-data-enhancement'),
                'debug_info' => $result
            ] );
        }
    }

    /**
     * Handles the AJAX request to scrape all sources for a specific post.
     * Triggers sequential scraping: Google Places, PADI, SSI, Facebook, Google Search Top 10.
     * Optionally accepts a 'source' POST param to scrape only one source.
     */
    public function handle_scrape_all_sources_request() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $source  = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';

        // IMPORTANT: Nonce check string must match button generation in admin UI!
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! check_ajax_referer( 'gpd_scrape_all_sources_nonce_' . $post_id, '_ajax_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'gpd-data-enhancement' ) ], 403 );
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

        $available_sources = [
            'google_places',
            'padi',
            'ssi',
            'facebook',
            'google_search_top10',
        ];
        $results = [];
        $success = true;

        if ( $source && in_array( $source, $available_sources, true ) ) {
            // Only process the requested source.
            $method = "scrape_{$source}";
            if ( is_callable( [ 'GPD_Enhancement_Scraper', $method ] ) ) {
                $results[$source] = GPD_Enhancement_Scraper::$method( $post_id );
                $success = $results[$source]['success'] ?? false;
            } else {
                $results[$source] = [ 'success' => false, 'message' => "Scraper for source '{$source}' not implemented." ];
                $success = false;
            }
        } else {
            // Scrape all sources sequentially, avoiding duplicates.
            $scraped_domains = [];
            foreach ( $available_sources as $src ) {
                $method = "scrape_{$src}";
                if ( is_callable( [ 'GPD_Enhancement_Scraper', $method ] ) ) {
                    $res = GPD_Enhancement_Scraper::$method( $post_id, $scraped_domains );
                    $results[$src] = $res;
                    // Track domains for deduplication (assume scraper returns list of domains in $res['domains'])
                    if ( isset( $res['domains'] ) && is_array( $res['domains'] ) ) {
                        $scraped_domains = array_merge( $scraped_domains, $res['domains'] );
                        $scraped_domains = array_unique( $scraped_domains );
                    }
                    $success = $success && ( $res['success'] ?? false );
                } else {
                    $results[$src] = [ 'success' => false, 'message' => "Scraper for source '{$src}' not implemented." ];
                    $success = false;
                }
            }
        }

        if ( $success ) {
            wp_send_json_success( [
                'message' => __( 'All sources scraped successfully.', 'gpd-data-enhancement' ),
                'results' => $results
            ] );
        } else {
            wp_send_json_error( [
                'message' => __( 'One or more sources failed to scrape.', 'gpd-data-enhancement' ),
                'results' => $results
            ] );
        }
    }
}