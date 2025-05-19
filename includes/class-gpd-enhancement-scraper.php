<?php
/**
 * File: class-gpd-enhancement-scraper.php
 *
 * GPD_Enhancement_Scraper Class
 *
 * Handles the scraping of website data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPD_Enhancement_Scraper {

	/**
	 * Scrapes the primary website URL for a given post.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array A status array with 'success' (boolean) and 'message' (string) or 'data' (array).
	 */
	public static function scrape_primary_website( $post_id ) {
		if ( ! $post_id ) {
			return array( 'success' => false, 'message' => 'Invalid Post ID.' );
		}

        // --- UPDATED META KEY ---
		$website_url = get_post_meta( $post_id, '_gpd_website', true ); // Changed 'geodir_website' to '_gpd_website'

		if ( empty( $website_url ) ) {
			return array( 'success' => false, 'message' => 'Primary website URL is not set for this listing (meta key: _gpd_website).' ); // Added meta key to message for clarity
		}

		// Ensure it's a valid URL, attempt to prepend http:// if scheme is missing
		if ( ! preg_match( "~^(?:f|ht)tps?://~i", $website_url ) ) {
			$website_url = 'http://' . $website_url;
		}
		
		if ( ! filter_var( $website_url, FILTER_VALIDATE_URL ) ) {
			return array( 'success' => false, 'message' => 'Invalid website URL format after attempting to normalize: ' . esc_html( get_post_meta( $post_id, '_gpd_website', true ) ), 'data' => null );
		}

		// Ensure the Simple HTML DOM parser library is loaded.
		// GPD_ENHANCEMENT_PLUGIN_DIR should be defined in the main plugin file and point to the plugin's root.
		$parser_file = GPD_ENHANCEMENT_PLUGIN_DIR . 'lib/simple_html_dom.php'; // Respecting your path
		if ( file_exists( $parser_file ) ) {
			require_once $parser_file;
		} else {
			return array( 'success' => false, 'message' => 'Error: Simple HTML DOM Parser library not found at ' . esc_html($parser_file) );
		}

		// Make sure the functions/classes from the parser are available.
		if ( !function_exists('str_get_html') ) {
			return array( 'success' => false, 'message' => 'Error: Simple HTML DOM Parser function str_get_html() not available after include.' );
		}

		// Fetch the website content using WordPress HTTP API
		$args = array(
			'timeout'     => 20, // Increased timeout slightly
			'redirection' => 5,
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . ' (GPD Data Enhancement Plugin/' . GPD_ENHANCEMENT_VERSION . ')', // Added plugin version
		);
		$response = wp_remote_get( esc_url_raw( $website_url ), $args ); // esc_url_raw is good here

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return array( 'success' => false, 'message' => 'Failed to fetch website: ' . esc_html( $error_message ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code != 200 ) {
			// It's useful to include the URL that failed in the message
			return array( 'success' => false, 'message' => 'Failed to fetch website (' . esc_url($website_url) . '). Status code: ' . esc_html( $response_code ) );
		}

		$html_body = wp_remote_retrieve_body( $response );

		if ( empty( $html_body ) ) {
			return array( 'success' => false, 'message' => 'Fetched website content is empty (' . esc_url($website_url) . ').' );
		}

		// Create a DOM object from the HTML string
		$html = str_get_html( $html_body );

		if ( ! $html ) {
			// Clean up memory if str_get_html fails but still allocates
            // Using your existing cleanup logic
			if (function_exists('simple_html_dom_メモリ_をクリアする')) { 
				simple_html_dom_メモリ_をクリアする();
			} elseif (is_object($html) && method_exists($html, 'clear')) { 
				$html->clear();
			}
			unset($html); // Ensure it's unset
			return array( 'success' => false, 'message' => 'Could not parse HTML content from ' . esc_url($website_url) . '.' );
		}

		$extracted_data = array();

		// 1. Extract Page Title
		$title_element = $html->find( 'title', 0 ); 
		$extracted_data['page_title'] = $title_element ? trim( $title_element->plaintext ) : 'Not found';

		// 2. Extract First H1 content
		$h1_element = $html->find( 'h1', 0 ); 
		$extracted_data['first_h1'] = $h1_element ? trim( $h1_element->plaintext ) : 'Not found';
		
		// 3. Extract meta description
		$meta_tag = $html->find('meta[name=description]', 0); // Find first one
		$extracted_data['meta_description'] = ( $meta_tag && isset($meta_tag->content) ) ? trim($meta_tag->content) : 'Not found';

		// IMPORTANT: Clean up memory
		if (is_object($html) && method_exists($html, 'clear')) {
			$html->clear(); 
		}
		unset($html);

		if ( $extracted_data['page_title'] === 'Not found' && $extracted_data['first_h1'] === 'Not found' && $extracted_data['meta_description'] === 'Not found' ) {
			return array( 'success' => true, 'message' => 'Scraped, but no specific data (title, H1, meta description) found from ' . esc_url($website_url) . '. Website might be structured differently or use JavaScript rendering.', 'data' => $extracted_data );
		}

		return array(
			'success' => true,
			'message' => 'Successfully scraped data from ' . esc_url($website_url) . '.', // Added URL to success message
			'data'    => $extracted_data
		);
	}

	/**
	 * Stub for Google Places scraping.
	 */
	public static function scrape_google_places($post_id, $already_domains = array()) {
		return array(
			'success' => false,
			'message' => 'Google Places scraping not yet implemented.',
			'data'    => null,
			'domains' => array()
		);
	}

	/**
	 * Stub for PADI scraping.
	 */
	public static function scrape_padi($post_id, $already_domains = array()) {
		return array(
			'success' => false,
			'message' => 'PADI scraping not yet implemented.',
			'data'    => null,
			'domains' => array()
		);
	}

	/**
	 * Stub for SSI scraping.
	 */
	public static function scrape_ssi($post_id, $already_domains = array()) {
		return array(
			'success' => false,
			'message' => 'SSI scraping not yet implemented.',
			'data'    => null,
			'domains' => array()
		);
	}

	/**
	 * Stub for Facebook scraping.
	 */
	public static function scrape_facebook($post_id, $already_domains = array()) {
		return array(
			'success' => false,
			'message' => 'Facebook scraping not yet implemented.',
			'data'    => null,
			'domains' => array()
		);
	}

	/**
	 * Stub for Google Search Top 10 scraping.
	 */
	public static function scrape_google_search_top10($post_id, $already_domains = array()) {
		return array(
			'success' => false,
			'message' => 'Google Search Top 10 scraping not yet implemented.',
			'data'    => null,
			'domains' => array()
		);
	}
}
