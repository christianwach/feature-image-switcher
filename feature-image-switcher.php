<?php /*
--------------------------------------------------------------------------------
Plugin Name: Feature Image Switcher
Plugin URI: http://spirit-of-football.de/
Description: Creates a switcher for changing the feature image of a post.
Author: Christian Wach
Version: 0.1.1
Author URI: http://haystack.co.uk
Text Domain: feature-image-switcher
Domain Path: /languages
--------------------------------------------------------------------------------
*/



// set our version here
define( 'FEATURE_IMAGE_SWITCHER_VERSION', '0.1.1' );

// store reference to this file
if ( ! defined( 'FEATURE_IMAGE_SWITCHER_FILE' ) ) {
	define( 'FEATURE_IMAGE_SWITCHER_FILE', __FILE__ );
}

// store URL to this plugin's directory
if ( ! defined( 'FEATURE_IMAGE_SWITCHER_URL' ) ) {
	define( 'FEATURE_IMAGE_SWITCHER_URL', plugin_dir_url( FEATURE_IMAGE_SWITCHER_FILE ) );
}

// store PATH to this plugin's directory
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

		// use translation
		add_action( 'plugins_loaded', array( $this, 'translation' ) );

		// filter the feature image markup on a page
		add_filter( 'commentpress_get_feature_image', array( $this, 'get_feature_image' ), 20, 2 );

		// save feature image
		add_action( 'wp_ajax_set_feature_image', array( $this, 'set_feature_image' ) );

		// filter attachments to show only those for a user
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media' ) );

	}



	/**
	 * Load translation if present.
	 *
	 * @since 0.1
	 */
	public function translation() {

		// allow translations to be added
		load_plugin_textdomain(
			'feature-image-switcher', // unique name
			false, // deprecated argument
			dirname( plugin_basename( FEATURE_IMAGE_SWITCHER_FILE ) ) . '/languages/'
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
	public function get_feature_image( $html, $post ) {

		// disallow users without upload permissions
		if ( ! current_user_can( 'upload_files' ) ) return $html;

		// disallow users who are not editors
		if ( ! current_user_can( 'edit_posts' ) ) return $html;

		// disallow unless singular
		if ( ! is_singular() ) return $html;

		// disallow poets
		if ( $post->post_type == 'poet' ) return $html;

		// append our HTML to the image markup
		$html .= '<a href="#" class="feature-image-switcher button" id="feature-image-switcher-' . $post->ID . '" style="position: absolute; top: 20px; right: 20px; text-transform: uppercase; font-family: sans-serif; font-weight: bold;">' . __( 'Choose New', 'feature-image-switcher' ) . '</a>';

		// add javascripts
		$this->enqueue_scripts();

		// --<
		return $html;

	}



	/**
	 * Enqueue the necessary scripts.
	 *
	 * @since 0.1
	 */
	public function enqueue_scripts() {

		// load media
		wp_enqueue_media();

		// enqueue custom javascript
		wp_enqueue_script(
			'feature-image-switcher-js',
			FEATURE_IMAGE_SWITCHER_URL . 'assets/js/feature-image-switcher.js',
			array( 'jquery' ),
			FEATURE_IMAGE_SWITCHER_VERSION,
			true // in footer
		);

		// init localisation
		$localisation = array(
			'title' => __( 'Choose Feature Image', 'feature-image-switcher' ),
			'button' => __( 'Set Feature Image', 'feature-image-switcher' ),
		);

		/// init settings
		$settings = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'loading' => FEATURE_IMAGE_SWITCHER_URL . 'assets/images/loading.gif',
		);

		// localisation array
		$vars = array(
			'localisation' => $localisation,
			'settings' => $settings,
		);

		// localise the WordPress way
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

		// init data
		$data = array(
			'success' => 'false',
		);

		// disallow users without upload permissions
		if ( ! current_user_can( 'upload_files' ) ) return $data;

		// disallow users who are not editors
		if ( ! current_user_can( 'edit_posts' ) ) return $html;

		// get post ID
		$post_id = isset( $_POST['post_id'] ) ? absint( trim( $_POST['post_id'] ) ) : 0;

		// sanity checks
		if ( ! is_numeric( $post_id ) ) return $data;
		if ( $post_id === 0 ) return $data;

		// add to data
		$data['post_id'] = $post_id;

		// get attachment ID
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( trim( $_POST['attachment_id'] ) ) : 0;

		// sanity checks
		if ( ! is_numeric( $attachment_id ) ) return $data;
		if ( $attachment_id === 0 ) return $data;

		// add to data
		$data['attachment_id'] = $attachment_id;

		// okay let's do it
		set_post_thumbnail( $post_id, $attachment_id );

		// get the image markup
		$content = get_the_post_thumbnail( $post_id, 'commentpress-feature' );

		// add to data
		$data['markup'] = $content;

		// init data
		$data['success'] = 'true';

		// send data to browser
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

		// admins and editors get to see everything
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

		// is this an AJAX request?
		if ( defined( 'DOING_AJAX' ) AND DOING_AJAX ) {

			// set reasonable headers
			header('Content-type: text/plain');
			header("Cache-Control: no-cache");
			header("Expires: -1");

			// echo
			echo json_encode( $data );

			// die
			exit();

		}

	}



} // class Feature_Image_Switcher ends



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



// instantiate the class
global $feature_image_switcher;
$feature_image_switcher = new Feature_Image_Switcher();



