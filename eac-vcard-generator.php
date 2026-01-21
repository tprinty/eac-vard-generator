<?php
/**
 * EAC vCard Generator
 *
 * @package           EAC_VCard_Generator
 * @author            Tom Printy
 * @copyright         2026 Tom Printy
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       EAC vCard Generator
 * Plugin URI:        https://github.com/tprinty/eac-vard-generator
 * Description:       Generates vCard downloads for Attorney posts using ACF fields.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Tom Printy
 * Author URI:        https://github.com/tprinty
 * Text Domain:       eac-vcard-generator
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'EAC_VCARD_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'EAC_VCARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include the vCard class.
require_once EAC_VCARD_PLUGIN_DIR . 'class_vcard.php';

/**
 * Main plugin class for EAC vCard Generator.
 *
 * Handles vCard generation and download for Attorney custom post types
 * using data from Advanced Custom Fields.
 *
 * @since 1.0.0
 */
class EAC_VCard_Generator {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var EAC_VCard_Generator|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return EAC_VCard_Generator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_shortcode( 'eac_vcard', array( $this, 'vcard_shortcode' ) );
		add_action( 'init', array( $this, 'handle_vcard_download' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue plugin styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		$css = '
			.eac-vcard-button {
				display: inline-block;
				padding: 10px 20px;
				background-color: #0073aa;
				color: #ffffff;
				text-decoration: none;
				border-radius: 4px;
				font-weight: 500;
			}
			.eac-vcard-button:hover {
				background-color: #005a87;
				color: #ffffff;
			}
		';

		wp_register_style( 'eac-vcard-generator', false, array(), EAC_VCARD_VERSION );
		wp_enqueue_style( 'eac-vcard-generator' );
		wp_add_inline_style( 'eac-vcard-generator', $css );
	}

	/**
	 * Handle vCard download request.
	 *
	 * Processes the download request when the appropriate query parameters are present.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_vcard_download() {
		if ( ! isset( $_GET['eac_vcard_download'] ) || ! isset( $_GET['attorney_id'] ) ) {
			return;
		}

		$attorney_id = absint( $_GET['attorney_id'] );

		if ( ! $attorney_id ) {
			return;
		}

		// Verify nonce for security.
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eac_vcard_' . $attorney_id ) ) {
			wp_die(
				esc_html__( 'Security check failed.', 'eac-vcard-generator' ),
				esc_html__( 'Error', 'eac-vcard-generator' ),
				array( 'response' => 403 )
			);
		}

		$attorney = get_post( $attorney_id );

		if ( ! $attorney || 'attorney' !== $attorney->post_type ) {
			wp_die(
				esc_html__( 'Invalid attorney.', 'eac-vcard-generator' ),
				esc_html__( 'Error', 'eac-vcard-generator' ),
				array( 'response' => 404 )
			);
		}

		$this->generate_and_download_vcard( $attorney_id );
		exit;
	}

	/**
	 * Generate and download vCard for an attorney.
	 *
	 * @since 1.0.0
	 * @param int $attorney_id The attorney post ID.
	 * @return void
	 */
	private function generate_and_download_vcard( $attorney_id ) {
		$vcard = new vcard();

		// Get attorney data from ACF fields and post.
		$post      = get_post( $attorney_id );
		$full_name = function_exists( 'get_field' ) ? get_field( 'attorneyBio__name', $attorney_id ) : '';

		// If full name field is empty, use the post title.
		if ( empty( $full_name ) ) {
			$full_name = $post->post_title;
		}

		// Parse name into components.
		$name_parts = $this->parse_name( $full_name );

		$vcard->data['first_name']      = $name_parts['first_name'];
		$vcard->data['last_name']       = $name_parts['last_name'];
		$vcard->data['additional_name'] = $name_parts['middle_name'];
		$vcard->data['name_suffix']     = $name_parts['suffix'];
		$vcard->data['display_name']    = $full_name;

		// Job title.
		if ( function_exists( 'get_field' ) ) {
			$job_title = get_field( 'attorney_job_title', $attorney_id );
			if ( ! empty( $job_title ) ) {
				$vcard->data['title'] = $job_title;
			}

			// Email.
			$email = get_field( 'office_email', $attorney_id );
			if ( ! empty( $email ) ) {
				$vcard->data['email1'] = sanitize_email( $email );
			}

			// Cell phone.
			$cell_phone = get_field( 'attorney_cell_phone', $attorney_id );
			if ( ! empty( $cell_phone ) ) {
				$vcard->data['cell_tel'] = $this->format_phone( $cell_phone );
			}

			// Address - parse from wysiwyg field.
			$address_html = get_field( 'attorneybio__address', $attorney_id );
			if ( ! empty( $address_html ) ) {
				$address_parts                   = $this->parse_address( $address_html );
				$vcard->data['work_address']     = $address_parts['street'];
				$vcard->data['work_city']        = $address_parts['city'];
				$vcard->data['work_state']       = $address_parts['state'];
				$vcard->data['work_postal_code'] = $address_parts['zip'];
				$vcard->data['work_country']     = $address_parts['country'];
			}

			// LinkedIn URL.
			$linkedin = get_field( 'linkedin', $attorney_id );
			if ( ! empty( $linkedin ) ) {
				$vcard->data['url'] = esc_url_raw( $linkedin );
			}

			// Photo.
			$photo = get_field( 'attorney_bio_image', $attorney_id );
			if ( ! empty( $photo ) && is_array( $photo ) && isset( $photo['url'] ) ) {
				$vcard->data['photo'] = esc_url_raw( $photo['url'] );
			}
		}

		// Company - use site name.
		$vcard->data['company'] = get_bloginfo( 'name' );

		// Attorney page URL as note.
		$attorney_url = get_permalink( $attorney_id );
		if ( ! empty( $attorney_url ) ) {
			/* translators: %s: Attorney profile URL */
			$vcard->data['note'] = sprintf( __( 'Profile: %s', 'eac-vcard-generator' ), $attorney_url );
		}

		// Set filename.
		$vcard->filename = sanitize_file_name( $full_name );

		// Build and download the vCard.
		$vcard->download();
	}

	/**
	 * Parse a full name into components.
	 *
	 * @since 1.0.0
	 * @param string $full_name The full name to parse.
	 * @return array {
	 *     Parsed name components.
	 *
	 *     @type string $first_name  First name.
	 *     @type string $middle_name Middle name(s).
	 *     @type string $last_name   Last name.
	 *     @type string $suffix      Name suffix (e.g., Jr., Esq.).
	 * }
	 */
	private function parse_name( $full_name ) {
		$result = array(
			'first_name'  => '',
			'middle_name' => '',
			'last_name'   => '',
			'suffix'      => '',
		);

		if ( empty( $full_name ) ) {
			return $result;
		}

		// Common suffixes.
		$suffixes = array( 'Jr.', 'Jr', 'Sr.', 'Sr', 'II', 'III', 'IV', 'Esq.', 'Esq' );

		$full_name = trim( $full_name );
		$suffix    = '';

		// Check for suffix.
		foreach ( $suffixes as $suf ) {
			if ( preg_match( '/,?\s*' . preg_quote( $suf, '/' ) . '\.?$/i', $full_name, $matches ) ) {
				$suffix    = $suf;
				$full_name = trim( preg_replace( '/,?\s*' . preg_quote( $suf, '/' ) . '\.?$/i', '', $full_name ) );
				break;
			}
		}

		$parts = preg_split( '/\s+/', $full_name );

		if ( 1 === count( $parts ) ) {
			$result['first_name'] = $parts[0];
		} elseif ( 2 === count( $parts ) ) {
			$result['first_name'] = $parts[0];
			$result['last_name']  = $parts[1];
		} else {
			$result['first_name'] = $parts[0];
			$result['last_name']  = array_pop( $parts );
			array_shift( $parts );
			$result['middle_name'] = implode( ' ', $parts );
		}

		$result['suffix'] = $suffix;

		return $result;
	}

	/**
	 * Parse address from HTML content.
	 *
	 * @since 1.0.0
	 * @param string $address_html HTML content containing address.
	 * @return array {
	 *     Parsed address components.
	 *
	 *     @type string $street  Street address.
	 *     @type string $city    City name.
	 *     @type string $state   State abbreviation.
	 *     @type string $zip     ZIP/Postal code.
	 *     @type string $country Country name.
	 * }
	 */
	private function parse_address( $address_html ) {
		$result = array(
			'street'  => '',
			'city'    => '',
			'state'   => '',
			'zip'     => '',
			'country' => 'USA',
		);

		// Strip HTML tags and decode entities.
		$address_text = html_entity_decode( wp_strip_all_tags( $address_html ) );
		$address_text = preg_replace( '/\s+/', ' ', $address_text );
		$address_text = trim( $address_text );

		if ( empty( $address_text ) ) {
			return $result;
		}

		// Parse from the end backwards - find State and ZIP first.
		// Pattern: "City, STATE ZIP" or "City STATE ZIP" at the end.
		if ( preg_match( '/,?\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $address_text, $matches ) ) {
			$result['state'] = strtoupper( trim( $matches[1] ) );
			$result['zip']   = trim( $matches[2] );

			// Remove state and ZIP from the address text.
			$remaining = trim( preg_replace( '/,?\s*[A-Z]{2}\s+\d{5}(?:-\d{4})?$/i', '', $address_text ) );

			// Now find the city - it's the last part before where state/zip was.
			// Look for "Street, City" or "Street City" pattern.
			if ( preg_match( '/^(.+),\s*([^,]+)$/', $remaining, $city_matches ) ) {
				// Format: "Street Address, City".
				$result['street'] = trim( $city_matches[1] );
				$result['city']   = trim( $city_matches[2] );
			} else {
				// No comma - try to find city as last word(s) before state.
				// Common city names may be multiple words, so look for known patterns.
				// Try to match: everything up to last 1-3 words as city.
				$words = preg_split( '/\s+/', $remaining );

				if ( count( $words ) > 1 ) {
					// Assume city is the last word (simple case).
					// For multi-word cities, check if second-to-last word is likely part of city.
					$city_words   = array();
					$street_words = $words;

					// Work backwards to find likely city boundary.
					// Cities don't usually start with numbers, streets often do.
					while ( count( $street_words ) > 1 ) {
						$last_word = array_pop( $street_words );
						array_unshift( $city_words, $last_word );

						// If next word looks like end of street (directional, number, or common suffix).
						$prev_word = end( $street_words );
						if ( preg_match( '/^\d+$/', $prev_word ) ||
							preg_match( '/^(St|Ave|Blvd|Dr|Rd|Ln|Ct|Way|Pkwy|Hwy|Suite|Ste|Floor|Fl)\.?$/i', $prev_word ) ||
							preg_match( '/^(N|S|E|W|NE|NW|SE|SW|North|South|East|West)\.?$/i', $prev_word ) ) {
							break;
						}

						// If we've collected 2 words for city, that's usually enough.
						if ( count( $city_words ) >= 2 ) {
							break;
						}
					}

					$result['street'] = implode( ' ', $street_words );
					$result['city']   = implode( ' ', $city_words );
				} else {
					// Only one word - use it as street.
					$result['street'] = $remaining;
				}
			}
		} else {
			// No state/ZIP pattern found - use entire text as street.
			$result['street'] = $address_text;
		}

		return $result;
	}

	/**
	 * Format phone number for vCard.
	 *
	 * @since 1.0.0
	 * @param string $phone Raw phone number.
	 * @return string Formatted phone number.
	 */
	private function format_phone( $phone ) {
		// Remove all non-numeric characters except +.
		$phone = preg_replace( '/[^0-9+]/', '', $phone );
		return $phone;
	}

	/**
	 * Shortcode callback for [eac_vcard].
	 *
	 * Renders a download button for the attorney's vCard.
	 *
	 * @since 1.0.0
	 * @param array $atts {
	 *     Shortcode attributes.
	 *
	 *     @type int    $id    Attorney post ID. Default current post.
	 *     @type string $text  Button text. Default 'Download vCard'.
	 *     @type string $class CSS class for button. Default 'eac-vcard-button'.
	 * }
	 * @return string HTML output.
	 */
	public function vcard_shortcode( $atts ) {
		global $post;

		$atts = shortcode_atts(
			array(
				'id'    => '',
				'text'  => __( 'Download vCard', 'eac-vcard-generator' ),
				'class' => 'eac-vcard-button',
			),
			$atts,
			'eac_vcard'
		);

		// Determine attorney ID - check multiple sources for current page context.
		$attorney_id = 0;

		if ( ! empty( $atts['id'] ) ) {
			$attorney_id = absint( $atts['id'] );
		} elseif ( is_singular( 'attorney' ) ) {
			// On a single attorney page.
			$attorney_id = get_queried_object_id();
		} elseif ( isset( $post ) && $post instanceof WP_Post && 'attorney' === $post->post_type ) {
			// Inside a loop with an attorney post.
			$attorney_id = $post->ID;
		} else {
			// Fallback to get_the_ID().
			$attorney_id = get_the_ID();
		}

		if ( ! $attorney_id ) {
			return '<!-- EAC vCard: No attorney ID specified -->';
		}

		// Verify this is an attorney post type.
		$attorney_post = get_post( $attorney_id );
		if ( ! $attorney_post || 'attorney' !== $attorney_post->post_type ) {
			return '<!-- EAC vCard: Invalid attorney post -->';
		}

		// Generate download URL with nonce.
		$download_url = add_query_arg(
			array(
				'eac_vcard_download' => 1,
				'attorney_id'        => $attorney_id,
				'nonce'              => wp_create_nonce( 'eac_vcard_' . $attorney_id ),
			),
			home_url( '/' )
		);

		// Return the button HTML.
		return sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $download_url ),
			esc_attr( $atts['class'] ),
			esc_html( $atts['text'] )
		);
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'EAC_VCard_Generator', 'get_instance' ) );
