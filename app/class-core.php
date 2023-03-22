<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that App attributes and functions used across both the
 * public-facing side of the site and the Admin area.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @link https://vcore.au
 *
 * @package CF_Images
 * @subpackage CF_Images/App
 * @author Anton Vanyukov <a.vanyukov@vcore.ru>
 * @since 1.0.0
 */

namespace CF_Images\App;

use Exception;
use WP_Error;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 *
 * @since 1.0.0
 */
class Core {

	use Traits\Helpers;

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var null|Core $instance  Plugin instance.
	 */
	private static $instance = null;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var string $plugin_name  The string used to uniquely identify this plugin.
	 */
	protected $plugin_name = 'cf-images';

	/**
	 * Error status.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var bool|WP_Error $error
	 */
	private static $error = false;

	/**
	 * Admin instance.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var Admin $admin
	 */
	private $admin;

	/**
	 * Async upload instance.
	 *
	 * @since 1.1.5
	 * @access private
	 * @var Async\Upload $upload
	 */
	private $upload;

	/**
	 * CDN domain.
	 *
	 * @since 1.2.0
	 * @access private
	 * @var string
	 */
	private $cdn_domain = 'https://imagedelivery.net';

	/**
	 * Get plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Core
	 */
	public static function get_instance(): Core {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the Admin area and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {

		$this->load_libs();
		$this->init_integrations();
		$this->load_modules();
		$this->set_cdn_domain();

		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		if ( ! $this->is_set_up() ) {
			return;
		}

		add_action( 'cf_images_error', array( $this, 'set_error' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'enable_flexible_variants' ) );

		// Use custom paths.
		add_filter( 'cf_images_upload_data', array( $this, 'use_custom_image_path' ) );

	}

	/**
	 * Load all required libraries.
	 *
	 * @since 1.0.0
	 */
	private function load_libs() {

		require_once __DIR__ . '/class-media.php';
		require_once __DIR__ . '/class-admin.php';
		require_once __DIR__ . '/class-settings.php';
		require_once __DIR__ . '/modules/class-loader.php';

		require_once __DIR__ . '/api/class-api.php';
		require_once __DIR__ . '/api/class-image.php';
		require_once __DIR__ . '/api/class-variant.php';

		if ( ! get_option( 'cf-images-disable-async', false ) ) {
			require_once __DIR__ . '/async/class-task.php';
			require_once __DIR__ . '/async/class-upload.php';
			$this->upload = new Async\Upload();
		}

	}

	/**
	 * Get Cloudflare CDN domain.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	private function set_cdn_domain() {

		$custom_domain = get_option( 'cf-images-custom-domain', false );

		if ( $custom_domain ) {
			$domain  = wp_http_validate_url( $custom_domain ) ? $custom_domain : get_site_url();
			$domain .= '/cdn-cgi/imagedelivery';

			$this->cdn_domain = $domain;
		}

	}

	/**
	 * Init integrations.
	 *
	 * @since 1.1.5
	 *
	 * @return void
	 */
	private function init_integrations() {

		require_once __DIR__ . '/integrations/class-spectra.php';
		$spectra = new Integrations\Spectra();

		require_once __DIR__ . '/integrations/class-multisite-global-media.php';
		$mgm = new Integrations\Multisite_Global_Media();

		require_once __DIR__ . '/integrations/class-rank-math.php';
		$rank_math = new Integrations\Rank_Math();

	}

	/**
	 * Load modules.
	 *
	 * @since 1.2.1
	 *
	 * @see Modules\Auto_Resize
	 * @see Modules\Cloudflare_Images
	 * @see Modules\Disable_Generation
	 *
	 * @return void
	 */
	private function load_modules() {

		$loader = Modules\Loader::get_instance();

		$loader->register( 'auto-resize' );
		$loader->register( 'cloudflare-images' ); // Core module.
		$loader->register( 'disable-generation' );

	}

	/**
	 * Setter for error.
	 *
	 * @since 1.2.0
	 *
	 * @param int|mixed $code     Error code.
	 * @param string    $message  Error message.
	 *
	 * @return void
	 */
	public function set_error( $code = '', string $message = '' ) {
		if ( '' === $code ) {
			self::$error = false;
		} else {
			self::$error = new WP_Error( $code, $message );
		}
	}

	/**
	 * Maybe redirect to plugin page on activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_redirect_to_plugin_page() {

		if ( ! get_transient( 'cf-images-admin-redirect' ) ) {
			return;
		}

		delete_transient( 'cf-images-admin-redirect' );
		wp_safe_redirect( admin_url( 'upload.php?page=cf-images' ) );
		exit;

	}

	/**
	 * Enable flexible variants, which are disabled by default.
	 *
	 * This action is only required once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enable_flexible_variants() {

		// Already done.
		if ( get_option( 'cf-images-setup-done', false ) ) {
			return;
		}

		$variant = new Api\Variant();

		try {
			$variant->toggle_flexible( true );
			update_option( 'cf-images-setup-done', true, false );
		} catch ( Exception $e ) {
			self::$error = new WP_Error( $e->getCode(), $e->getMessage() );
		}

	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since 1.0.0
	 *
	 * @return string  The name of the plugin.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * Retrieve stored error.
	 *
	 * @since 1.0.0
	 * @sicne 1.2.0  Change to static method.
	 *
	 * @return bool|WP_Error
	 */
	public static function get_error() {
		return self::$error;
	}

	/**
	 * Getter method for CDN domain.
	 *
	 * @since 1.2.1
	 *
	 * @return string
	 */
	public function get_cdn_domain(): string {
		return $this->cdn_domain;
	}

	/**
	 * Set custom ID for image to use the custom paths in image URLs.
	 *
	 * @since 1.2.0
	 *
	 * @param array $data  Image data sent to the Cloudflare Images API.
	 *
	 * @return array
	 */
	public function use_custom_image_path( array $data ): array {

		if ( ! get_option( 'cf-images-custom-id', false ) ) {
			return $data;
		}

		if ( ! isset( $data['id'] ) && isset( $data['file']->postname ) ) {
			$data['id'] = $data['file']->postname;
		}

		return $data;

	}

}
