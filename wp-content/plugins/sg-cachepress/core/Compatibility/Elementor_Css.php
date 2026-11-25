<?php
namespace SiteGround_Optimizer\Compatibility;

/**
 * Prevent cache writes while Elementor CSS is being regenerated.
 */
class Elementor_Css {

	/**
	 * Option holding the current Elementor CSS generation.
	 *
	 * @var string
	 */
	const GENERATION_OPTION = 'siteground_optimizer_elementor_css_generation';

	/**
	 * Prefix used for per-URL validation transients.
	 *
	 * @var string
	 */
	const VALIDATION_TRANSIENT_PREFIX = 'sgo_elementor_css_';

	/**
	 * Keep validation records in sync with the longest file-cache interval.
	 *
	 * @var int
	 */
	const VALIDATION_TTL = 604800;

	/**
	 * Generation captured when the current response headers were prepared.
	 *
	 * @var string
	 */
	private $generation = '';

	/**
	 * Validation transient for the current URL.
	 *
	 * @var string
	 */
	private $validation_key = '';

	/**
	 * Whether the current request must prove that its CSS is healthy.
	 *
	 * @var bool
	 */
	private $validation_required = false;

	/**
	 * Whether every Elementor CSS file observed on this request is healthy.
	 *
	 * @var bool
	 */
	private $css_is_healthy = true;

	/**
	 * Whether the theme completed its frontend render.
	 *
	 * @var bool
	 */
	private $render_completed = false;

	/**
	 * Start a new validation generation after Elementor clears its CSS files.
	 *
	 * @since 7.8.3
	 */
	public function start_generation() {
		update_option( self::GENERATION_OPTION, wp_generate_uuid4(), false );
	}

	/**
	 * Quarantine the first render of a URL in the current CSS generation.
	 *
	 * @since 7.8.3
	 *
	 * @param array $headers WordPress response headers.
	 *
	 * @return array Filtered response headers.
	 */
	public function filter_cache_headers( $headers ) {
		$dynamic_cache_enabled = (int) get_option( 'siteground_optimizer_enable_cache', 0 );
		$file_cache_enabled    = (int) get_option( 'siteground_optimizer_file_caching', 0 );

		if (
			! is_array( $headers ) ||
			( 0 === $dynamic_cache_enabled && 0 === $file_cache_enabled ) ||
			! $this->is_html_response( $headers ) ||
			$this->is_cache_already_disabled( $headers )
		) {
			return $headers;
		}

		$generation = get_option( self::GENERATION_OPTION, '' );

		if ( ! is_string( $generation ) || '' === $generation ) {
			return $headers;
		}

		$this->generation     = $generation;
		$this->validation_key = $this->get_validation_key();

		if ( $generation === get_transient( $this->validation_key ) ) {
			return $headers;
		}

		$this->validation_required = true;

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$cache_header_name              = $this->find_header_name( $headers, 'x-cache-enabled', 'X-Cache-Enabled' );
		$cache_control_name             = $this->find_header_name( $headers, 'cache-control', 'Cache-Control' );
		$headers[ $cache_header_name ]  = 'False';
		$headers[ $cache_control_name ] = 'no-cache, must-revalidate, max-age=0, no-store, private';

		return $headers;
	}

	/**
	 * Record Elementor's final state for a CSS file used by this response.
	 *
	 * @since 7.8.3
	 *
	 * @param object $css_file Elementor CSS file object.
	 */
	public function record_css_health( $css_file ) {
		if ( ! $this->validation_required || ! $this->css_is_healthy ) {
			return;
		}

		if ( ! is_object( $css_file ) || ! method_exists( $css_file, 'get_meta' ) ) {
			$this->css_is_healthy = false;
			return;
		}

		$template_type = get_post_meta( $css_file->get_post_id(), '_elementor_template_type', true );

		if ( 'loop-item' === $template_type ) {
			$css_file = new \ElementorPro\Modules\LoopBuilder\Files\Css\Loop( $css_file->get_post_id() );
		}

		$meta = $css_file->get_meta();

		if ( ! is_array( $meta ) || ! isset( $meta['status'] ) ) {
			$this->css_is_healthy = false;
			return;
		}

		switch ( $meta['status'] ) {
			case 'empty':
				return;

			case 'inline':
				$this->css_is_healthy =
					isset( $meta['css'] ) &&
					is_scalar( $meta['css'] ) &&
					'' !== trim( (string) $meta['css'] );
				return;

			case 'file':
				$this->css_is_healthy =
					method_exists( $css_file, 'get_path' ) &&
					is_readable( $css_file->get_path() );
				return;

			default:
				$this->css_is_healthy = false;
		}
	}

	/**
	 * Mark that the theme reached the end of its frontend render.
	 *
	 * @since 7.8.3
	 */
	public function mark_render_complete() {
		$this->render_completed = true;
	}

	/**
	 * Allow the next request to cache only after a complete, healthy response.
	 *
	 * @since 7.8.3
	 */
	public function commit_validation() {
		if (
			! $this->validation_required ||
			! $this->css_is_healthy ||
			! $this->render_completed ||
			200 !== http_response_code() ||
			$this->has_fatal_error() ||
			$this->generation !== get_option( self::GENERATION_OPTION, '' )
		) {
			return;
		}

		set_transient( $this->validation_key, $this->generation, self::VALIDATION_TTL );
	}

	/**
	 * Get the validation key for the current URL.
	 *
	 * @return string Validation transient name.
	 */
	private function get_validation_key() {
		$host        = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';

		return self::VALIDATION_TRANSIENT_PREFIX . md5( strtolower( $host ) . $request_uri );
	}

	/**
	 * Check whether an earlier cache policy already disabled caching.
	 *
	 * @param array $headers WordPress response headers.
	 *
	 * @return bool True when caching is already disabled.
	 */
	private function is_cache_already_disabled( $headers ) {
		foreach ( $headers as $name => $value ) {
			if ( 'x-cache-enabled' === strtolower( (string) $name ) && 'false' === strtolower( (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether Elementor CSS can be part of this response.
	 *
	 * @param array $headers WordPress response headers.
	 *
	 * @return bool True for HTML responses.
	 */
	private function is_html_response( $headers ) {
		foreach ( $headers as $name => $value ) {
			if ( 'content-type' !== strtolower( (string) $name ) ) {
				continue;
			}

			$content_type = strtolower( (string) $value );

			return
				false !== strpos( $content_type, 'text/html' ) ||
				false !== strpos( $content_type, 'application/xhtml+xml' );
		}

		return true;
	}

	/**
	 * Preserve the casing of an existing response header.
	 *
	 * @param array  $headers      WordPress response headers.
	 * @param string $header_name  Lowercase header name to find.
	 * @param string $default_name Header name to use when it is absent.
	 *
	 * @return string Existing or default header name.
	 */
	private function find_header_name( $headers, $header_name, $default_name ) {
		foreach ( $headers as $name => $value ) {
			if ( $header_name === strtolower( (string) $name ) ) {
				return $name;
			}
		}

		return $default_name;
	}

	/**
	 * Check whether the request ended with a fatal PHP error.
	 *
	 * @return bool True when a fatal error ended the request.
	 */
	private function has_fatal_error() {
		$error = error_get_last();

		if ( empty( $error['type'] ) ) {
			return false;
		}

		return in_array(
			$error['type'],
			array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ),
			true
		);
	}
}
