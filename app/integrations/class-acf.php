<?php
/**
 * ACF integration class
 *
 * This class adds compatibility with the ACF plugin.
 *
 * @link https://vcore.au
 *
 * @package CF_Images
 * @subpackage CF_Images/App/Integrations
 * @author Anton Vanyukov <a.vanyukov@vcore.ru>
 * @since 1.2.1
 */

namespace CF_Images\App\Integrations;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * ACF class.
 *
 * @since 1.2.1
 */
class ACF {

	/**
	 * Class constructor.
	 *
	 * @since 1.2.1
	 */
	public function __construct() {
		add_filter( 'wp_get_attachment_url', array( $this, 'image_field_type_url' ), 10, 2 );
	}

	/**
	 * Filters the attachment URL from the image field type.
	 *
	 * @since 1.2.1
	 *
	 * @param string $url            URL for the given attachment.
	 * @param int    $attachment_id  Attachment post ID.
	 */
	public function image_field_type_url( string $url, int $attachment_id ) {

		if ( ! doing_action( 'acf/format_value' ) ) {
			return $url;
		}

		return apply_filters( 'wp_get_attachment_image_src', [ $url ], $attachment_id, '' );

	}

}
