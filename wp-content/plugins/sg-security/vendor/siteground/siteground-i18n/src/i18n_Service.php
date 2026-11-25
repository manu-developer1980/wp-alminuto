<?php
/**
 * SiteGround i18n Service.
 */

namespace SiteGround_i18n;

use CharlesRumley\PoToJson;
use SiteGround_Helper\Helper_Service;

/**
 * SiteGround_i18n_Service class.
 */
class i18n_Service {

	/**
	 * Variable holding the text domain.
	 *
	 * @var string
	 */
	public $sg_textdomain;

	/**
	 * Variable holding the plugin folder.
	 *
	 * @var string
	 */
	public $folder;

	/**
	 * WordPress.org plugin update-check API endpoint.
	 */
	const API_URL = 'https://api.wordpress.org/plugins/update-check/1.1/';

	/**
	 * Class construct.
	 *
	 * @since 1.0.0
	 *
	 * @param string $textdomain The text domain that will be used for the instance.
	 * @param string $folder     The folder that will be used for the instance.
	 */
	public function __construct( $textdomain, $folder = '' ) {
		$this->sg_textdomain = $textdomain;
		$this->folder        = empty( $folder ) ? $textdomain : $folder;
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			$this->sg_textdomain,
			false,
			$this->folder . '/languages'
		);
	}

	/**
	 * Update json translations on translations update.
	 *
	 * @since  1.0.0
	 *
	 * @param  {WP_Upgrader} $upgrader WP_Upgrader instance.
	 * @param  array         $extra    Array of bulk item update data.
	 */
	public function update_json_translations( $upgrader, $extra ) {
		// Bail if we don't update the translations.
		if (
			'update' !== $extra['action'] &&
			'translation' !== $extra['type']
		) {
			return;
		}

		// Bail if there are no translations.
		if ( empty( $extra['translations'] ) ) {
			return;
		}

		// Check for SiteGround Optimizer translations.
		$keys = array_keys( array_column( $extra['translations'], 'slug' ), $this->sg_textdomain );

		// Bail if there are no plugin translations.
		if ( empty( $keys ) ) {
			return;
		}

		// Setup the WP Filesystem.
		$wp_filesystem = Helper_Service::setup_wp_filesystem();

		// Init the convertor class.
		$po_to_json = new PoToJson();

		foreach ( $keys as $key ) {
			// Convert a PO file to Jed-compatible JSON.
			$json = @$po_to_json
						->withPoFile( WP_CONTENT_DIR . '/languages/plugins/' . $this->folder . '-' . $extra['translations'][ $key ]['language'] . '.po' )
						->toJedJson( false, $this->sg_textdomain );

			// Convert and get the json content.
			$content = json_decode( $json, true );

			// Build the json filepath.
			$json_filepath = WP_CONTENT_DIR . '/languages/plugins/' . $this->folder . '-' . $extra['translations'][ $key ]['language'] . '.json';

			// Create the file if donesn't exists.
			if ( ! is_file( $json_filepath ) ) {
				// Create the new file.
				$wp_filesystem->touch( $json_filepath );
			}

			// Add the translations to the file.
			$wp_filesystem->put_contents(
				$json_filepath,
				json_encode( $content['locale_data'][ $this->sg_textdomain ] )
			);
		}
	}

	/**
	 * Get i18n strings as a JSON-encoded string
	 *
	 * @since 1.0.0
	 *
	 * @return string The locale as JSON
	 */
	public function get_i18n_data_json() {
		// Get the user locale.
		$locale = get_user_locale();

		// Possible langugaes paths.
		$dirs = array(
			'wp-content/languages/plugins/',
			'wp-content/plugins/' . $this->folder . '/languages/json/',
		);

		foreach ( $dirs as $dir ) {
			// Build the full path to the file.
			$i18n_json = ABSPATH . $dir . $this->sg_textdomain . '-' . $locale . '.json';

			// Check if the files exists and it's readable.
			if ( is_file( $i18n_json ) && is_readable( $i18n_json ) ) {
				// Get the locale data.
				$locale_data = @file_get_contents( $i18n_json );
				if ( $locale_data ) {
					return $locale_data;
				}
			}
		}

		// Return valid empty Jed locale.
		return json_encode(
			array(
				'' => array(
					'domain' => $this->sg_textdomain,
					'lang'   => is_admin() ? get_user_locale() : get_locale(),
				),
			)
		);
	}

	/**
	 * Download the plugin translations when the site language changes.
	 *
	 * @param string $old_lang Previous WPLANG value.
	 * @param string $new_lang New WPLANG value.
	 */
	public function download_translations_on_lang_change( $old_lang, $new_lang ) {
		// Bail, if the current user cannot install language packs.
		if ( ! is_admin() || ! current_user_can( 'install_languages' ) ) {
			return;
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $this->sg_textdomain . '/' . $this->sg_textdomain . '.php';
		$plugin_data = get_plugin_data( $plugin_file );

		$request = wp_remote_post(
			self::API_URL,
			array(
				'body' => array(
					'plugins' => wp_json_encode(
						array(
							'plugins' => array(
								$this->sg_textdomain . '/' . $this->sg_textdomain . '.php' => $plugin_data,
							),
							'active'  => array(),
						)
					),
					'locale'  => wp_json_encode( array( $new_lang ) ),
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $request );
		$body = wp_remote_retrieve_body( $request );

		if ( 200 !== $code || 'error' === $body || is_wp_error( $body ) ) {
			return array();
		}

		$body = json_decode( $body, true );

		if ( empty( $body['translations'] ) ) {
			return;
		}

		// Load the WordPress upgrader classes.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Language_Pack_Upgrader( $skin );

		foreach ( $body['translations'] as $language ) {
			$this->download_package( (object) $language, $upgrader, $skin );
		}
	}

	/**
	 * Download translation for the language.
	 *
	 * @param object                  $language Language package.
	 * @param Language_Pack_Upgrader  $upgrader The instance of the core class used for updating/installing language packs (translations).
	 * @param Automatic_Upgrader_Skin $skin     Upgrader Skin for Automatic WordPress Upgrades.
	 */
	public function download_package( $language, \Language_Pack_Upgrader $upgrader, \Automatic_Upgrader_Skin $skin ) {
		// Bail, if there is no translation package URL to download.
		if ( ! property_exists( $language, 'package' ) || empty( $language->package ) ) {
			return;
		}

		$skin->language_update = $language;

		$result = $upgrader->run(
			array(
				'package'                     => $language->package,
				'destination'                 => WP_LANG_DIR . '/plugins',
				'abort_if_destination_exists' => false,
				'is_multi'                    => true,
				'hook_extra'                  => array(
					'language_update_type' => $language->type,
					'language_update'      => $language,
				),
			)
		);

		// Build the data array that 'update_json_translations()' expects.
		$extra = array(
			'action'       => 'update',
			'type'         => 'translation',
			'bulk'         => 1,
			'translations' => array(
				array(
					'language' => $language->language,
					'type'     => $language->type,
					'slug'     => $language->slug,
					'version'  => $language->version,
				),
			),
		);

		// If the translation files are downloaded successfully, update the JSON translations.
		if ( ! empty( $result ) && ! is_wp_error( $result ) ) {
			$this->update_json_translations( $upgrader, $extra );
		}
	}
}
