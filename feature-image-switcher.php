<?php
/**
 * Plugin Name: Feature Image Switcher
 * Plugin URI: http://spirit-of-football.de/
 * Description: Creates a switcher for changing the feature image of a post.
 * Author: Christian Wach
 * Version: 0.1.1
 * Author URI: https://haystack.co.uk
 * Text Domain: feature-image-switcher
 * Domain Path: /languages
 *
 * @package Feature_Image_Switcher
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Set our version here.
define( 'FEATURE_IMAGE_SWITCHER_VERSION', '0.1.1' );

// Store reference to this file.
if ( ! defined( 'FEATURE_IMAGE_SWITCHER_FILE' ) ) {
	define( 'FEATURE_IMAGE_SWITCHER_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'FEATURE_IMAGE_SWITCHER_URL' ) ) {
	define( 'FEATURE_IMAGE_SWITCHER_URL', plugin_dir_url( FEATURE_IMAGE_SWITCHER_FILE ) );
}

// Store PATH to this plugin's directory.
if ( ! defined( 'FEATURE_IMAGE_SWITCHER_PATH' ) ) {
	define( 'FEATURE_IMAGE_SWITCHER_PATH', plugin_dir_path( FEATURE_IMAGE_SWITCHER_FILE ) );
}

/**
 * Feature Image Switcher Plugin Class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 0.1
 */
class Feature_Image_Switcher {

	/**
	 * Switcher object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $switcher The Switcher object
	 */
	public $switcher;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Use translation.
		add_action( 'plugins_loaded', [ $this, 'translation' ] );

		// Filter the feature image markup and add button.
		add_filter( 'commentpress_get_feature_image', [ $this, 'get_button' ], 20, 2 );

		// Save feature image.
		add_action( 'wp_ajax_set_feature_image', [ $this, 'set_feature_image' ] );

		// Filter attachments to show only those for a user.
		add_filter( 'ajax_query_attachments_args', [ $this, 'filter_media' ] );

	}

	/**
	 * Load translation if present.
	 *
	 * @since 0.1
	 */
	public function translation() {

		// Load translations.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'feature-image-switcher', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files.
		);

	}

	/**
	 * Append our markup to feature image.
	 *
	 * @since 0.1
	 *
	 * @param str $html The existing feature image markup.
	 * @param WP_Post $post The WordPress post object.
	 * @return str $html The modified feature image markup.
	 */
	public function get_button( $html, $post ) {

		// Disallow users without permissions.
		if ( ! $this->allow_button( $post ) ) {
			return $html;
		}

		// Append our HTML to the image markup.
		$html .= '<a href="#" class="feature-image-switcher button" id="feature-image-switcher-' . $post->ID . '" style="position: absolute; top: 20px; right: 20px; text-transform: uppercase; font-family: sans-serif; font-weight: bold;">' . __( 'Choose New', 'feature-image-switcher' ) . '</a>';

		// Add javascripts.
		$this->enqueue_scripts();

		// --<
		return $html;

	}

	/**
	 * Conditions for showing feature image switcher button.
	 *
	 * @since 0.1.1
	 *
	 * @param WP_Post $post The WordPress post object.
	 * @return bool $allowed True if button is to be shown, false otherwise.
	 */
	public function allow_button( $post ) {

		// Init as allowed.
		$allowed = true;

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			$allowed = false;

		// Disallow users who are not editors.
		} elseif ( ! current_user_can( 'edit_posts' ) ) {
			$allowed = false;

		// Disallow unless singular.
		} elseif ( ! is_singular() ) {
			$allowed = false;

		}

		/**
		 * Filter the conditions for showing feature image switcher button.
		 *
		 * @since 0.1.1
		 *
		 * @param bool $allowed True if button is to be shown, false otherwise.
		 * @param WP_Post $post The WordPress post object.
		 * @return bool $allowed True if button is to be shown, false otherwise.
		 */
		$allowed = apply_filters( 'feature_image_switcher_allow_button', $allowed, $post );

		// --<
		return $allowed;

	}

	/**
	 * Enqueue the necessary scripts.
	 *
	 * @since 0.1
	 */
	public function enqueue_scripts() {

		// load media.
		wp_enqueue_media();

		// Enqueue custom javascript.
		wp_enqueue_script(
			'feature-image-switcher-js',
			FEATURE_IMAGE_SWITCHER_URL . 'assets/js/feature-image-switcher.js',
			[ 'jquery' ],
			FEATURE_IMAGE_SWITCHER_VERSION,
			true // In footer.
		);

		// Init localisation.
		$localisation = [
			'title' => __( 'Choose Feature Image', 'feature-image-switcher' ),
			'button' => __( 'Set Feature Image', 'feature-image-switcher' ),
		];

		// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'loading' => FEATURE_IMAGE_SWITCHER_URL . 'assets/images/loading.gif',
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'feature-image-switcher-js',
			'Featured_Image_Switcher_Settings',
			$vars
		);

	}

	/**
	 * Set feature image as a result of the media upload modal selection.
	 *
	 * @since 0.1
	 */
	public function set_feature_image() {

		// Init data.
		$data = [
			'success' => 'false',
		];

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return $data;
		}

		// Disallow users who are not editors.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $data;
		}

		// Get post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( trim( $_POST['post_id'] ) ) : 0;

		// Sanity checks.
		if ( ! is_numeric( $post_id ) ) {
			return $data;
		}
		if ( $post_id === 0 ) {
			return $data;
		}

		// Add to data.
		$data['post_id'] = $post_id;

		// Get attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( trim( $_POST['attachment_id'] ) ) : 0;

		// Sanity checks.
		if ( ! is_numeric( $attachment_id ) ) {
			return $data;
		}
		if ( $attachment_id === 0 ) {
			return $data;
		}

		// Add to data.
		$data['attachment_id'] = $attachment_id;

		// Okay let's do it.
		set_post_thumbnail( $post_id, $attachment_id );

		// Get the image markup.
		$content = get_the_post_thumbnail( $post_id, 'commentpress-feature' );

		// Add to data.
		$data['markup'] = $content;

		// Init data.
		$data['success'] = 'true';

		// Send data to browser.
		$this->send_data( $data );

	}

	/**
	 * Ensure that users see just their own uploaded media.
	 *
	 * @since 0.1
	 *
	 * @param array $query The existing query.
	 * @return array $query The modified query.
	 */
	public function filter_media( $query ) {

		// Admins and editors get to see everything.
		if ( ! current_user_can( 'edit_posts' ) ) {
			$query['author'] = get_current_user_id();
		}

		// --<
		return $query;

	}

	/**
	 * Send JSON data to the browser.
	 *
	 * @since 0.1
	 *
	 * @param array $data The data to send.
	 */
	private function send_data( $data ) {

		// Is this an AJAX request?
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

			// Set reasonable headers.
			header( 'Content-type: text/plain' );
			header( 'Cache-Control: no-cache' );
			header( 'Expires: -1' );

			// Echo data.
			echo json_encode( $data );

			// Die.
			exit();

		}

	}

}

/**
 * Plugin reference getter.
 *
 * @since 0.1
 *
 * @return object $feature_image_switcher The plugin object.
 */
function feature_image_switcher() {
	global $feature_image_switcher;
	return $feature_image_switcher;
}

// Bootstrap plugin.
global $feature_image_switcher;
$feature_image_switcher = new Feature_Image_Switcher();
