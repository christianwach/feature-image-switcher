/**
 * Featured Image Switcher Javascript.
 *
 * Implements functionality for the "Switcher" button.
 *
 * @since 0.1
 *
 * @package Featured_Image_Switcher
 */

/**
 * Create Featured Image Switcher object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.1
 */
var Featured_Image_Switcher = Featured_Image_Switcher || {};

/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.1
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Object.
	 *
	 * @since 0.1
	 */
	Featured_Image_Switcher.settings = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.1
		 */
		this.init = function() {

			// Init localisation.
			me.init_localisation();

			// Init settings.
			me.init_settings();

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.1
		 */
		this.dom_ready = function() {

		};

		// Init localisation array.
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 0.1
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof Featured_Image_Switcher_Settings ) {
				me.localisation = Featured_Image_Switcher_Settings.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 0.1
		 *
		 * @param {String} The identifier for the desired localisation string.
		 * @return {String} The localised string.
		 */
		this.get_localisation = function( identifier ) {
			return me.localisation[identifier];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 0.1
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof Featured_Image_Switcher_Settings ) {
				me.settings = Featured_Image_Switcher_Settings.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 0.1
		 *
		 * @param {String} The identifier for the desired setting.
		 * @return The value of the setting.
		 */
		this.get_setting = function( identifier ) {
			return me.settings[identifier];
		};

	};

	/**
	 * Create Switcher Object.
	 *
	 * @since 0.1
	 */
	Featured_Image_Switcher.switcher = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Switcher.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.1
		 */
		this.init = function() {

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.1
		 */
		this.dom_ready = function() {

			// Set up instance.
			me.setup();

			// Enable listeners.
			me.listeners();

		};

		/**
		 * Set up Switcher instance.
		 *
		 * @since 0.1
		 */
		this.setup = function() {

			var src, spinner;

			src = Featured_Image_Switcher.settings.get_setting( 'loading' ),
			spinner = '<img src="' + src + '" id="feature-image-loading" style="position: absolute; top: 30%; left: 35%;" />'

			// Init AJAX spinner
			$('.feature-image-switcher').after( spinner );
			$('#feature-image-loading').hide();

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.1
		 */
		this.listeners = function() {

			// Declare vars.
			var button = $('.feature-image-switcher');

			/**
			 * Add a click event listener to button.
			 *
			 * @param {Object} event The event object.
			 */
			button.on( 'click', function( event ) {

				// Prevent link action.
				if ( event.preventDefault ) {
					event.preventDefault();
				}

				var file_frame,
					post_id = $(this).attr( 'id' ).split( '-' )[3];

				// Sanity check.
				if ( file_frame ) {
					file_frame.open();
					return;
				}

				// Init WP Media.
				file_frame = wp.media.frames.file_frame = wp.media({
					title: Featured_Image_Switcher.settings.get_localisation( 'title' ),
					button: {
						text: Featured_Image_Switcher.settings.get_localisation( 'button' )
					},
					multiple: false
				});

				// Add callback for image selection.
				file_frame.on( 'select', function() {

					// Grab Attachment data and security token.
					var attachment = file_frame.state().get( 'selection' ).first().toJSON(),
						token = button.data( 'security' );

					// Show spinner.
					$('#feature-image-loading').show();

					// Send the data to the server.
					me.send( post_id, attachment.id, token );

				});

				// Open modal.
				file_frame.open();

				// --<
				return false;

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.1
		 *
		 * @param {Integer} post_id The numeric ID of the Post.
		 * @param {Integer} attachment_id The numeric ID of the Attachment.
		 * @param {String} token The AJAX security token.
		 */
		this.send = function( post_id, attachment_id, token ) {

			// Define vars.
			var url, data;

			// URL to post to.
			url = Featured_Image_Switcher.settings.get_setting( 'ajax_url' );

			// Data received by WordPress.
			data = {
				action: 'set_feature_image',
				post_id: post_id,
				attachment_id: attachment_id,
				_ajax_nonce: token
			};

			// Use jQuery post.
			$.post( url, data,

				/**
				 * AJAX callback which receives response from the server.
				 *
				 * Calls feedback method on success or shows an error in the console.
				 *
				 * @since 0.1
				 *
				 * @param {Mixed} response The received JSON data array.
				 * @param {String} textStatus The status of the response.
				 */
				function( response, textStatus ) {

					// Update if success, otherwise show error.
					if ( textStatus == 'success' ) {
						me.update( response );
					} else {
						if ( console.log ) {
							console.log( textStatus );
						}
					}

				},

				// Expected format.
				'json'

			);

		};

		/**
		 * Receive data from an AJAX request.
		 *
		 * @since 0.1
		 *
		 * @param {Array} data The data received from the server.
		 */
		this.update = function( data ) {

			// Convert to jQuery object.
			if ( $.parseHTML ) {
				markup = $( $.parseHTML( data.markup ) );
			} else {
				markup = $(data.markup);
			}

			// Switch image.
			$( '#feature-image-switcher-' + data.post_id ).prev( 'img' ).replaceWith( markup );

			// Hide spinner.
			$('#feature-image-loading').hide();

		};

	};

	// Init settings.
	Featured_Image_Switcher.settings.init();

	// Init switcher.
	Featured_Image_Switcher.switcher.init();

} )( jQuery );

/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.1
 */
jQuery(document).ready( function($) {

	// The DOM is loaded now.
	Featured_Image_Switcher.settings.dom_ready();
	Featured_Image_Switcher.switcher.dom_ready();

});
