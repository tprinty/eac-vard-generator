<?php
/**
 * Plugin Name: EAC vCard Generator
 * Plugin URI:
 * Description: Generates vCard downloads for Attorney posts using ACF fields
 * Version: 1.0.0
 * Author: Tom Printy
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the vCard class
require_once plugin_dir_path(__FILE__) . 'class_vcard.php';

/**
 * Main plugin class
 */
class EAC_VCard_Generator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('eac_vcard', array($this, 'vcard_shortcode'));
        add_action('init', array($this, 'handle_vcard_download'));
    }

    /**
     * Handle vCard download request
     */
    public function handle_vcard_download() {
        if (!isset($_GET['eac_vcard_download']) || !isset($_GET['attorney_id'])) {
            return;
        }

        $attorney_id = absint($_GET['attorney_id']);

        if (!$attorney_id) {
            return;
        }

        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'eac_vcard_' . $attorney_id)) {
            wp_die('Security check failed');
        }

        $attorney = get_post($attorney_id);

        if (!$attorney || $attorney->post_type !== 'attorney') {
            wp_die('Invalid attorney');
        }

        $this->generate_and_download_vcard($attorney_id);
        exit;
    }

    /**
     * Generate and download vCard for an attorney
     */
    private function generate_and_download_vcard($attorney_id) {
        $vcard = new vcard();

        // Get attorney data from ACF fields and post
        $post = get_post($attorney_id);
        $full_name = get_field('attorneyBio__name', $attorney_id);

        // If full name field is empty, use the post title
        if (empty($full_name)) {
            $full_name = $post->post_title;
        }

        // Parse name into components
        $name_parts = $this->parse_name($full_name);

        $vcard->data['first_name'] = $name_parts['first_name'];
        $vcard->data['last_name'] = $name_parts['last_name'];
        $vcard->data['additional_name'] = $name_parts['middle_name'];
        $vcard->data['name_suffix'] = $name_parts['suffix'];
        $vcard->data['display_name'] = $full_name;

        // Job title
        $job_title = get_field('attorney_job_title', $attorney_id);
        if (!empty($job_title)) {
            $vcard->data['title'] = $job_title;
        }

        // Email
        $email = get_field('office_email', $attorney_id);
        if (!empty($email)) {
            $vcard->data['email1'] = $email;
        }

        // Cell phone
        $cell_phone = get_field('attorney_cell_phone', $attorney_id);
        if (!empty($cell_phone)) {
            $vcard->data['cell_tel'] = $this->format_phone($cell_phone);
        }

        // Address - parse from wysiwyg field
        $address_html = get_field('attorneybio__address', $attorney_id);
        if (!empty($address_html)) {
            $address_parts = $this->parse_address($address_html);
            $vcard->data['work_address'] = $address_parts['street'];
            $vcard->data['work_city'] = $address_parts['city'];
            $vcard->data['work_state'] = $address_parts['state'];
            $vcard->data['work_postal_code'] = $address_parts['zip'];
            $vcard->data['work_country'] = $address_parts['country'];
        }

        // Company - could be set from site name or a constant
        $vcard->data['company'] = get_bloginfo('name');

        // LinkedIn URL
        $linkedin = get_field('linkedin', $attorney_id);
        if (!empty($linkedin)) {
            $vcard->data['url'] = $linkedin;
        }

        // Photo
        $photo = get_field('attorney_bio_image', $attorney_id);
        if (!empty($photo) && is_array($photo) && isset($photo['url'])) {
            $vcard->data['photo'] = $photo['url'];
        }

        // Attorney page URL as note
        $attorney_url = get_permalink($attorney_id);
        if (!empty($attorney_url)) {
            $vcard->data['note'] = 'Profile: ' . $attorney_url;
        }

        // Set filename
        $vcard->filename = sanitize_file_name($full_name);

        // Build and download the vCard
        $vcard->download();
    }

    /**
     * Parse a full name into components
     */
    private function parse_name($full_name) {
        $result = array(
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'suffix' => ''
        );

        if (empty($full_name)) {
            return $result;
        }

        // Common suffixes
        $suffixes = array('Jr.', 'Jr', 'Sr.', 'Sr', 'II', 'III', 'IV', 'Esq.', 'Esq');

        $full_name = trim($full_name);
        $suffix = '';

        // Check for suffix
        foreach ($suffixes as $suf) {
            if (preg_match('/,?\s*' . preg_quote($suf, '/') . '\.?$/i', $full_name, $matches)) {
                $suffix = $suf;
                $full_name = trim(preg_replace('/,?\s*' . preg_quote($suf, '/') . '\.?$/i', '', $full_name));
                break;
            }
        }

        $parts = preg_split('/\s+/', $full_name);

        if (count($parts) === 1) {
            $result['first_name'] = $parts[0];
        } elseif (count($parts) === 2) {
            $result['first_name'] = $parts[0];
            $result['last_name'] = $parts[1];
        } else {
            $result['first_name'] = $parts[0];
            $result['last_name'] = array_pop($parts);
            array_shift($parts);
            $result['middle_name'] = implode(' ', $parts);
        }

        $result['suffix'] = $suffix;

        return $result;
    }

    /**
     * Parse address from HTML
     */
    private function parse_address($address_html) {
        $result = array(
            'street' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => 'USA'
        );

        // Strip HTML tags and decode entities
        $address_text = html_entity_decode(strip_tags($address_html));
        $address_text = preg_replace('/\s+/', ' ', $address_text);
        $address_text = trim($address_text);

        // Try to parse common address formats
        // Format: Street, City, State ZIP
        if (preg_match('/^(.+?),\s*(.+?),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $address_text, $matches)) {
            $result['street'] = trim($matches[1]);
            $result['city'] = trim($matches[2]);
            $result['state'] = strtoupper(trim($matches[3]));
            $result['zip'] = trim($matches[4]);
        }
        // Format: Street\nCity, State ZIP (with newlines converted to spaces)
        elseif (preg_match('/^(.+?)\s+(.+?),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $address_text, $matches)) {
            $result['street'] = trim($matches[1]);
            $result['city'] = trim($matches[2]);
            $result['state'] = strtoupper(trim($matches[3]));
            $result['zip'] = trim($matches[4]);
        }
        // If no pattern matches, just use the whole thing as street
        else {
            $result['street'] = $address_text;
        }

        return $result;
    }

    /**
     * Format phone number
     */
    private function format_phone($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return $phone;
    }

    /**
     * Shortcode callback
     *
     * Usage: [eac_vcard] - uses current post
     *        [eac_vcard id="123"] - specific attorney ID
     *        [eac_vcard text="Download vCard"] - custom button text
     *        [eac_vcard class="my-class"] - custom CSS class
     */
    public function vcard_shortcode($atts) {
        global $post;

        $atts = shortcode_atts(array(
            'id' => '',
            'text' => 'Download vCard',
            'class' => 'eac-vcard-button'
        ), $atts, 'eac_vcard');

        // Determine attorney ID - check multiple sources for current page context
        $attorney_id = 0;

        if (!empty($atts['id'])) {
            $attorney_id = absint($atts['id']);
        } elseif (is_singular('attorney')) {
            // On a single attorney page
            $attorney_id = get_queried_object_id();
        } elseif (isset($post) && $post instanceof WP_Post && $post->post_type === 'attorney') {
            // Inside a loop with an attorney post
            $attorney_id = $post->ID;
        } else {
            // Fallback to get_the_ID()
            $attorney_id = get_the_ID();
        }

        if (!$attorney_id) {
            return '<!-- EAC vCard: No attorney ID specified -->';
        }

        // Verify this is an attorney post type
        $post = get_post($attorney_id);
        if (!$post || $post->post_type !== 'attorney') {
            return '<!-- EAC vCard: Invalid attorney post -->';
        }

        // Generate download URL with nonce
        $download_url = add_query_arg(array(
            'eac_vcard_download' => 1,
            'attorney_id' => $attorney_id,
            'nonce' => wp_create_nonce('eac_vcard_' . $attorney_id)
        ), home_url('/'));

        // Return the button HTML
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($download_url),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('EAC_VCard_Generator', 'get_instance'));

/**
 * Add basic styles for the vCard button
 */
function eac_vcard_styles() {
    echo '<style>
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
    </style>';
}
add_action('wp_head', 'eac_vcard_styles');
