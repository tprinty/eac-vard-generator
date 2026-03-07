<?php
/**
 * VCard Generator Class
 *
 * @package EAC_VCard_Generator
 * @author  Tom Printy, originally by Troy Wolf
 * @link    https://gist.github.com/raramuridesign/4500527
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates vCard data for contact information.
 *
 * @since 1.0.0
 */
class VCard {

	/**
	 * Internal log string.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $log;

	/**
	 * Array of this vCard's contact data.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $data;

	/**
	 * Filename for download file naming.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $filename;

	/**
	 * vCard class: PUBLIC, PRIVATE, or CONFIDENTIAL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $class;

	/**
	 * Revision date for the vCard.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $revision_date;

	/**
	 * The generated vCard string.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $card;

	/**
	 * Constructor. Initializes contact data array.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->log  = 'New VCard() called';
		$this->data = array(
			'display_name'          => null,
			'first_name'            => null,
			'last_name'             => null,
			'additional_name'       => null,
			'name_prefix'           => null,
			'name_suffix'           => null,
			'nickname'              => null,
			'title'                 => null,
			'role'                  => null,
			'department'            => null,
			'company'               => null,
			'work_po_box'           => null,
			'work_extended_address' => null,
			'work_address'          => null,
			'work_city'             => null,
			'work_state'            => null,
			'work_postal_code'      => null,
			'work_country'          => null,
			'home_po_box'           => null,
			'home_extended_address' => null,
			'home_address'          => null,
			'home_city'             => null,
			'home_state'            => null,
			'home_postal_code'      => null,
			'home_country'          => null,
			'office_tel'            => null,
			'direct_tel'            => null,
			'home_tel'              => null,
			'cell_tel'              => null,
			'fax_tel'               => null,
			'pager_tel'             => null,
			'email1'                => null,
			'email2'                => null,
			'url'                   => null,
			'photo'                 => null,
			'birthday'              => null,
			'timezone'              => null,
			'sort_string'           => null,
			'note'                  => null,
		);
	}

	/**
	 * Build the vCard data string.
	 *
	 * Checks all values, builds appropriate defaults for missing values,
	 * and generates the vCard data string.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function build() {
		$this->log .= 'VCard build() called';

		// Set defaults for missing values.
		if ( ! $this->class ) {
			$this->class = 'PUBLIC';
		}

		if ( ! $this->data['display_name'] ) {
			$this->data['display_name'] = trim( $this->data['first_name'] . ' ' . $this->data['last_name'] );
		}

		if ( ! isset( $this->data['sort_string'] ) ) {
			$this->data['sort_string'] = $this->data['last_name'];
		}

		if ( ! $this->data['sort_string'] ) {
			$this->data['sort_string'] = $this->data['company'];
		}

		if ( ! isset( $this->data['timezone'] ) ) {
			$this->data['timezone'] = gmdate( 'O' );
		}

		if ( ! $this->revision_date ) {
			$this->revision_date = gmdate( 'Y-m-d H:i:s' );
		}

		// Build the vCard string.
		$this->card  = "BEGIN:VCARD\r\n";
		$this->card .= "VERSION:3.0\r\n";
		$this->card .= 'CLASS:' . $this->class . "\r\n";
		$this->card .= "PRODID:-//class_vcard from TroyWolf.com//NONSGML Version 1//EN\r\n";
		$this->card .= 'REV:' . $this->revision_date . "\r\n";
		$this->card .= 'FN:' . $this->data['display_name'] . "\r\n";
		$this->card .= 'N:'
			. $this->data['last_name'] . ';'
			. $this->data['first_name'] . ';'
			. $this->data['additional_name'] . ';'
			. $this->data['name_prefix'] . ';'
			. $this->data['name_suffix'] . "\r\n";

		if ( isset( $this->data['nickname'] ) ) {
			$this->card .= 'NICKNAME:' . $this->data['nickname'] . "\r\n";
		}

		if ( isset( $this->data['title'] ) ) {
			$this->card .= 'TITLE:' . $this->data['title'] . "\r\n";
		}

		if ( isset( $this->data['company'] ) ) {
			$this->card .= 'ORG:' . $this->data['company'];
		}

		if ( isset( $this->data['department'] ) ) {
			$this->card .= ';' . $this->data['department'];
		}

		$this->card .= "\r\n";

		// Work address.
		if ( isset( $this->data['work_po_box'] )
			|| isset( $this->data['work_extended_address'] )
			|| isset( $this->data['work_address'] )
			|| isset( $this->data['work_city'] )
			|| isset( $this->data['work_state'] )
			|| isset( $this->data['work_postal_code'] )
			|| isset( $this->data['work_country'] )
		) {
			$this->card .= 'ADR;TYPE=work:'
				. ( isset( $this->data['work_po_box'] ) ? $this->data['work_po_box'] : '' ) . ';'
				. ( isset( $this->data['work_extended_address'] ) ? $this->data['work_extended_address'] : '' ) . ';'
				. ( isset( $this->data['work_address'] ) ? $this->data['work_address'] : '' ) . ';'
				. ( isset( $this->data['work_city'] ) ? $this->data['work_city'] : '' ) . ';'
				. ( isset( $this->data['work_state'] ) ? $this->data['work_state'] : '' ) . ';'
				. ( isset( $this->data['work_postal_code'] ) ? $this->data['work_postal_code'] : '' ) . ';'
				. ( isset( $this->data['work_country'] ) ? $this->data['work_country'] : '' ) . "\r\n";
		}

		// Home address.
		if ( isset( $this->data['home_po_box'] )
			|| isset( $this->data['home_extended_address'] )
			|| isset( $this->data['home_address'] )
			|| isset( $this->data['home_city'] )
			|| isset( $this->data['home_state'] )
			|| isset( $this->data['home_postal_code'] )
			|| isset( $this->data['home_country'] )
		) {
			$this->card .= 'ADR;TYPE=home:'
				. ( isset( $this->data['home_po_box'] ) ? $this->data['home_po_box'] : '' ) . ';'
				. ( isset( $this->data['home_extended_address'] ) ? $this->data['home_extended_address'] : '' ) . ';'
				. ( isset( $this->data['home_address'] ) ? $this->data['home_address'] : '' ) . ';'
				. ( isset( $this->data['home_city'] ) ? $this->data['home_city'] : '' ) . ';'
				. ( isset( $this->data['home_state'] ) ? $this->data['home_state'] : '' ) . ';'
				. ( isset( $this->data['home_postal_code'] ) ? $this->data['home_postal_code'] : '' ) . ';'
				. ( isset( $this->data['home_country'] ) ? $this->data['home_country'] : '' ) . "\r\n";
		}

		if ( isset( $this->data['email1'] ) ) {
			$this->card .= 'EMAIL;TYPE=internet,pref:' . $this->data['email1'] . "\r\n";
		}

		if ( isset( $this->data['email2'] ) ) {
			$this->card .= 'EMAIL;TYPE=internet:' . $this->data['email2'] . "\r\n";
		}

		if ( isset( $this->data['office_tel'] ) ) {
			$this->card .= 'TEL;TYPE=work,voice;TYPE=MAIN:' . $this->data['office_tel'] . "\r\n";
			$this->card .= 'X-MS-TEL;VOICE;COMPANY:' . $this->data['office_tel'] . "\r\n";
		}

		if ( isset( $this->data['direct_tel'] ) ) {
			$this->card .= 'TEL;TYPE=work,voice;TYPE=DIRECT:' . $this->data['direct_tel'] . "\r\n";
		}

		if ( isset( $this->data['home_tel'] ) ) {
			$this->card .= 'TEL;TYPE=home,voice:' . $this->data['home_tel'] . "\r\n";
		}

		if ( isset( $this->data['cell_tel'] ) ) {
			$this->card .= 'TEL;TYPE=cell,voice:' . $this->data['cell_tel'] . "\r\n";
		}

		if ( isset( $this->data['fax_tel'] ) ) {
			$this->card .= 'TEL;TYPE=work,fax:' . $this->data['fax_tel'] . "\r\n";
		}

		if ( isset( $this->data['pager_tel'] ) ) {
			$this->card .= 'TEL;TYPE=work,pager:' . $this->data['pager_tel'] . "\r\n";
		}

		if ( isset( $this->data['url'] ) ) {
			$this->card .= 'URL;TYPE=work:' . $this->data['url'] . "\r\n";
		}

		// Photo.
		if ( isset( $this->data['photo'] ) ) {
			$image      = $this->data['photo'];
			$imagetypes = array(
				IMAGETYPE_JPEG => 'JPEG',
				IMAGETYPE_GIF  => 'GIF',
				IMAGETYPE_PNG  => 'PNG',
				IMAGETYPE_BMP  => 'BMP',
			);

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional suppression for missing images.
			$imageinfo = @getimagesize( $image );
			if ( $imageinfo && isset( $imagetypes[ $imageinfo[2] ] ) ) {
				$response = wp_remote_get( $image );
				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					$photo = base64_encode( wp_remote_retrieve_body( $response ) );
					$type  = $imagetypes[ $imageinfo[2] ];

					$this->card .= sprintf( 'PHOTO;ENCODING=BASE64;TYPE=%s:%s', $type, $photo ) . "\r\n";
				}
			}
		}

		if ( isset( $this->data['birthday'] ) ) {
			$this->card .= 'BDAY:' . $this->data['birthday'] . "\r\n";
		}

		if ( isset( $this->data['role'] ) ) {
			$this->card .= 'ROLE:' . $this->data['role'] . "\r\n";
		}

		if ( isset( $this->data['note'] ) ) {
			$this->card .= 'NOTE:' . $this->data['note'] . "\r\n";
		}

		$this->card .= 'TZ:' . $this->data['timezone'] . "\r\n";
		$this->card .= "END:VCARD\r\n";
	}

	/**
	 * Stream the vCard to the browser as a download.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function download() {
		$this->log .= 'VCard download() called';

		if ( ! $this->card ) {
			$this->build();
		}

		if ( ! $this->filename ) {
			$this->filename = trim( $this->data['display_name'] );
		}

		$this->filename = str_replace( ' ', '_', $this->filename );

		nocache_headers();
		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $this->filename . '.vcf"' );

		echo $this->card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- vCard raw output.
	}
}
