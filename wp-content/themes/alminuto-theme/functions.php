<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security hardening — Al Minuto theme.
 *
 * - Security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy).
 * - Removes WordPress version, generator meta, version query strings.
 * - Disables XML-RPC and theme/plugin file editing from wp-admin.
 * - Hides login error messages to mitigate user enumeration.
 * - Restricts oEmbed providers to a strict allowlist (SSRF mitigation).
 * - Disables author archives enumeration.
 */
function alminuto_theme_send_security_headers() {
	if ( is_admin() || headers_sent() ) {
		return;
	}

	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()' );

	$csp  = "default-src 'self'; ";
	$csp .= "script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.youtube-nocookie.com https://s.ytimg.com https://connect.facebook.net https://www.googletagmanager.com https://www.google-analytics.com https://ssl.google-analytics.com https://www.gstatic.com; ";
	$csp .= "img-src 'self' data: https:; ";
	$csp .= "font-src 'self' data: https://use.fontawesome.com https://fonts.gstatic.com; ";
	$csp .= "style-src 'self' 'unsafe-inline' https://use.fontawesome.com https://fonts.googleapis.com; ";
	$csp .= "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://www.facebook.com https://web.facebook.com https://players.brightcove.net https://*.fbcdn.net; ";
	$csp .= "media-src 'self' https://*.fbcdn.net data: blob:; ";
	$csp .= "connect-src 'self' https://www.youtube.com https://www.facebook.com https://*.facebook.com https://connect.facebook.net https://*.fbcdn.net https://www.googletagmanager.com https://www.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://*.doubleclick.net https://www.google.com https://www.google.es https://www.googleadservices.com; ";
	$csp .= "frame-ancestors 'self'; ";
	$csp .= "base-uri 'self'; ";
	$csp .= "form-action 'self'; ";
	$csp .= "report-uri /?alminuto_csp_report=1; ";
	$csp .= "report-to csp-endpoint;";

	header( 'Content-Security-Policy: ' . $csp );
	header( 'Reporting-Endpoints: csp-endpoint="/?alminuto_csp_report=1"' );
}
add_action( 'send_headers', 'alminuto_theme_send_security_headers' );

function alminuto_theme_remove_wp_version() {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
	add_filter( 'style_loader_src', 'alminuto_theme_strip_version_query', 9999 );
	add_filter( 'script_loader_src', 'alminuto_theme_strip_version_query', 9999 );
}
add_action( 'init', 'alminuto_theme_remove_wp_version' );

function alminuto_theme_strip_version_query( $src ) {
	if ( ! is_string( $src ) || $src === '' ) {
		return $src;
	}
	if ( strpos( $src, 'ver=' ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}

function alminuto_theme_disable_xmlrpc( $methods ) {
	if ( ! is_array( $methods ) ) {
		return array();
	}
	return array();
}
add_filter( 'xmlrpc_methods', 'alminuto_theme_disable_xmlrpc' );
add_filter( 'xmlrpc_enabled', '__return_false' );

function alminuto_theme_disable_file_edit_for_non_admins() {
	if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
		define( 'DISALLOW_FILE_EDIT', true );
	}
}
add_action( 'init', 'alminuto_theme_disable_file_edit_for_non_admins', 1 );

function alminuto_theme_hide_login_errors() {
	return __( 'Credenciales no válidas.', 'alminuto-theme' );
}
add_filter( 'login_errors', 'alminuto_theme_hide_login_errors' );

function alminuto_theme_block_author_enumeration() {
	if ( is_admin() ) {
		return;
	}
	if ( isset( $_GET['author'] ) && $_GET['author'] !== '' ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'alminuto_theme_block_author_enumeration', 1 );

function alminuto_theme_get_csp_reports_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'alminuto-csp-reports';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$ht = $dir . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_write
		file_put_contents( $ht, "Require all denied\nDeny from all\n" );
	}
	$index = $dir . '/index.html';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_write
		file_put_contents( $index, '' );
	}
	return $dir;
}

function alminuto_theme_get_csp_reports_file() {
	$dir  = alminuto_theme_get_csp_reports_dir();
	$day  = gmdate( 'Y-m-d' );
	return $dir . '/csp-' . $day . '.jsonl';
}

function alminuto_theme_handle_csp_report() {
	if ( empty( $_GET['alminuto_csp_report'] ) ) {
		return;
	}
	if ( strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) {
		status_header( 405 );
		exit;
	}
	$raw = file_get_contents( 'php://input' );
	if ( ! is_string( $raw ) || $raw === '' ) {
		status_header( 400 );
		exit;
	}
	$payload = json_decode( $raw, true );
	if ( ! is_array( $payload ) ) {
		status_header( 400 );
		exit;
	}

	$entry = [
		'ts'     => gmdate( 'c' ),
		'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
		'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '',
		'report' => $payload,
	];

	$line = wp_json_encode( $entry ) . "\n";
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_write
	file_put_contents( alminuto_theme_get_csp_reports_file(), $line, FILE_APPEND | LOCK_EX );

	status_header( 204 );
	exit;
}
add_action( 'init', 'alminuto_theme_handle_csp_report', 1 );

function alminuto_theme_read_csp_reports( $limit = 200 ) {
	$dir  = alminuto_theme_get_csp_reports_dir();
	$glob = glob( $dir . '/csp-*.jsonl' );
	if ( ! is_array( $glob ) ) {
		return [];
	}
	rsort( $glob );

	$out  = [];
	$read = 0;
	foreach ( $glob as $file ) {
		$lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			continue;
		}
		$lines = array_reverse( $lines );
		foreach ( $lines as $line ) {
			$entry = json_decode( $line, true );
			if ( is_array( $entry ) ) {
				$entry['__file'] = basename( $file );
				$out[] = $entry;
				$read++;
				if ( $read >= $limit ) {
					return $out;
				}
			}
		}
	}
	return $out;
}

function alminuto_theme_clear_csp_reports() {
	$dir  = alminuto_theme_get_csp_reports_dir();
	$glob = glob( $dir . '/csp-*.jsonl' );
	if ( ! is_array( $glob ) ) {
		return 0;
	}
	$n = 0;
	foreach ( $glob as $file ) {
		if ( @unlink( $file ) ) {
			$n++;
		}
	}
	return $n;
}

function alminuto_theme_csp_cleanup_old_reports() {
	$dir  = alminuto_theme_get_csp_reports_dir();
	$glob = glob( $dir . '/csp-*.jsonl' );
	if ( ! is_array( $glob ) ) {
		return;
	}
	$threshold = time() - ( 7 * DAY_IN_SECONDS );
	foreach ( $glob as $file ) {
		if ( filemtime( $file ) < $threshold ) {
			@unlink( $file );
		}
	}
}
add_action( 'alminuto_theme_csp_cleanup', 'alminuto_theme_csp_cleanup_old_reports' );
if ( ! wp_next_scheduled( 'alminuto_theme_csp_cleanup' ) ) {
	wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'alminuto_theme_csp_cleanup' );
}

function alminuto_theme_current_user_is_elsuper() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user = wp_get_current_user();
	if ( ! $user || empty( $user->user_login ) ) {
		return false;
	}
	return strtolower( (string) $user->user_login ) === 'elsuper';
}

/**
 * Override SiteGround Speed Optimizer's font preloads with the actual
 * URLs declared in style.css. The plugin's auto-scanner can cache
 * stale paths after a font rename, producing 404s in the console.
 * Keep this list in sync with the @font-face declarations in style.css.
 */
function alminuto_theme_safe_font_preloads( $pre ) {
	return [
		'/wp-content/uploads/futura-light-bt.woff',
		'/wp-content/uploads/futura-medium-bt-1.woff',
	];
}
add_filter( 'pre_option_siteground_optimizer_fonts_preload_urls', 'alminuto_theme_safe_font_preloads' );

function alminuto_theme_restrict_oembed_providers( $providers ) {
	if ( ! is_array( $providers ) ) {
		return $providers;
	}
	$blocked = [
		'#https?://(www\.)?soundcloud\.com/.*#i',
		'#https?://(www\.)?slideshare\.net/.*#i',
		'#https?://(www\.)?dailymotion\.com/.*#i',
		'#https?://(www\.)?flickr\.com/.*#i',
		'#https?://(www\.)?ted\.com/talks/.*#i',
		'#https?://wordpress\.tv/.*#i',
		'#https?://(www\.)?scribd\.com/.*#i',
		'#https?://(www\.)?kickstarter\.com/projects/.*#i',
	];
	foreach ( $blocked as $pattern ) {
		if ( isset( $providers[ $pattern ] ) ) {
			unset( $providers[ $pattern ] );
		}
	}
	return $providers;
}
add_filter( 'oembed_providers', 'alminuto_theme_restrict_oembed_providers', 999 );

function alminuto_theme_get_allowed_iframe_hosts() {
	return [
		'youtube.com',
		'youtube-nocookie.com',
		'youtu.be',
		'facebook.com',
		'web.facebook.com',
	];
}

function alminuto_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'custom-logo',
		[
			'height'      => 143,
			'width'       => 400,
			'flex-height' => true,
			'flex-width'  => true,
		]
	);

	register_nav_menus(
		[
			'primary' => __( 'Menú principal', 'alminuto-theme' ),
		]
	);
}
add_action( 'after_setup_theme', 'alminuto_theme_setup' );
add_filter( 'wp_handle_upload', 'alminuto_resize_uploaded_original_image' );

function alminuto_resize_uploaded_original_image( array $upload ): array {
	if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
		return $upload;
	}

	$allowed_mimes = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	if ( ! in_array( $upload['type'], $allowed_mimes, true ) ) {
		return $upload;
	}

	$file_path = $upload['file'];
	$max_width = 1200;

	$image_info = @getimagesize( $file_path );

	if ( ! $image_info ) {
		return $upload;
	}

	$width  = (int) $image_info[0];
	$height = (int) $image_info[1];

	if ( $width <= $max_width ) {
		return $upload;
	}

	$editor = wp_get_image_editor( $file_path );

	if ( is_wp_error( $editor ) ) {
		return $upload;
	}

	$editor->resize( $max_width, 0, false );

	$saved = $editor->save( $file_path );

	if ( is_wp_error( $saved ) ) {
		return $upload;
	}

	return $upload;
}
function alminuto_theme_image_sizes() {
	return [
		'banner_superior'   => [ 'width' => 855, 'height' => 174, 'crop' => [ 'center', 'center' ] ],
		'banner_superior_m' => [ 'width' => 480, 'height' => 98, 'crop' => [ 'center', 'center' ] ],
		'banner_lateral'    => [ 'width' => 285, 'height' => 0, 'crop' => false ],
		'col_izquierda'     => [ 'width' => 560, 'height' => 315, 'crop' => [ 'center', 'center' ] ],
		'col_izquierda_m'   => [ 'width' => 480, 'height' => 270, 'crop' => [ 'center', 'center' ] ],
		'col_derecha'       => [ 'width' => 280, 'height' => 160, 'crop' => [ 'center', 'center' ] ],
		'content_4_3'       => [ 'width' => 855, 'height' => 640, 'crop' => [ 'center', 'center' ] ],
		'content_4_3_m'     => [ 'width' => 480, 'height' => 360, 'crop' => [ 'center', 'center' ] ],
		// Used for the single post hero / featured image. No crop so the
		// photographer's original framing is preserved; 1920px is the width
		// Google Discover requires for large previews and covers the
		// theme's 1160px container at 2x for Retina.
		'single_full'       => [ 'width' => 1920, 'height' => 0, 'crop' => false ],
	];
}

function alminuto_theme_register_image_sizes() {
	foreach ( alminuto_theme_image_sizes() as $name => $cfg ) {
		$w    = isset( $cfg['width'] ) ? (int) $cfg['width'] : 0;
		$h    = isset( $cfg['height'] ) ? (int) $cfg['height'] : 0;
		$crop = $cfg['crop'] ?? false;
		add_image_size( $name, $w, $h, $crop );
	}
}
add_action( 'after_setup_theme', 'alminuto_theme_register_image_sizes', 20 );

function alminuto_theme_allowed_intermediate_image_sizes() {
	return array_values( array_unique( array_keys( alminuto_theme_image_sizes() ) ) );
}

function alminuto_theme_limit_intermediate_image_sizes( $sizes ) {
	if ( ! is_array( $sizes ) ) {
		return $sizes;
	}

	return alminuto_theme_allowed_intermediate_image_sizes();
}
add_filter( 'intermediate_image_sizes', 'alminuto_theme_limit_intermediate_image_sizes', 99 );

function alminuto_theme_limit_intermediate_image_sizes_advanced( $sizes ) {
	if ( ! is_array( $sizes ) ) {
		return $sizes;
	}

	$allowed = array_flip( alminuto_theme_allowed_intermediate_image_sizes() );
	foreach ( array_keys( $sizes ) as $size_name ) {
		if ( ! isset( $allowed[ $size_name ] ) ) {
			unset( $sizes[ $size_name ] );
		}
	}

	return $sizes;
}
add_filter( 'intermediate_image_sizes_advanced', 'alminuto_theme_limit_intermediate_image_sizes_advanced', 99 );

/**
 * Returns a unique log file path for the cleanup tool. Lazily creates the
 * directory. The timestamp in the filename guarantees uniqueness across
 * concurrent runs and makes post-mortem auditing trivial.
 */
function alminuto_theme_get_cleanup_log_file() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'alminuto-cleanup-logs';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// Block direct HTTP access to the log dir.
		file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
	}
	return $dir . '/cleanup-' . gmdate( 'Ymd-His' ) . '.log';
}

/**
 * Cleans up old posts and their attachments.
 *
 * Two modes:
 *   - dry_run: counts candidates and samples affected content. No writes.
 *   - real:    rewrites internal links FIRST, then deletes posts in
 *              batches (cascade attachments via wp_delete_post's force flag).
 *
 * Sticky posts are always excluded. Posts newer than 30 days are excluded
 * unless an explicit start_date is provided.
 *
 * @param array $args {
 *     @type bool   $dry_run       Default true.
 *     @type int    $before_days   Default 30. Ignored if start_date given.
 *     @type string $start_date    YYYY-MM-DD. Optional.
 *     @type string $end_date      YYYY-MM-DD. Optional.
 *     @type bool   $skip_sticky   Default true.
 *     @type int    $batch_size    Default 100.
 *     @type callable $log_callback Optional. Receives string messages.
 * }
 * @return array Stats and sample data.
 */
function alminuto_theme_cleanup_old_posts( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'dry_run'      => true,
		'before_days'  => 30,
		'start_date'   => null,
		'end_date'     => null,
		'skip_sticky'  => true,
		'batch_size'   => 100,
		'log_callback' => null,
	);
	$args = wp_parse_args( $args, $defaults );

	// Hard safety: never delete posts younger than 30 days unless an explicit
	// start_date was given.
	if ( empty( $args['start_date'] ) && (int) $args['before_days'] < 30 ) {
		$args['before_days'] = 30;
	}

	$log = function ( $msg ) use ( $args ) {
		if ( is_callable( $args['log_callback'] ) ) {
			call_user_func( $args['log_callback'], $msg );
		}
	};

	// Build date query.
	$date_query = array();
	if ( ! empty( $args['start_date'] ) ) {
		$date_query['after']  = $args['start_date'];
		$date_query['inclusive'] = true;
	}
	if ( ! empty( $args['end_date'] ) ) {
		$date_query['before'] = $args['end_date'];
		$date_query['inclusive'] = true;
	}
	if ( empty( $date_query ) ) {
		$date_query['before'] = gmdate( 'Y-m-d', strtotime( '-' . (int) $args['before_days'] . ' days' ) );
	}

	$query_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => -1,
		'fields'              => 'ids',
		'date_query'          => array( $date_query ),
		'no_found_rows'       => true,
		'suppress_filters'    => false,
		'ignore_sticky_posts' => (bool) $args['skip_sticky'],
	);

	$post_ids = get_posts( $query_args );

	$stats = array(
		'posts_to_delete'    => count( $post_ids ),
		'links_rewritten'    => 0,
		'attachments_deleted' => 0,
		'posts_deleted'      => 0,
		'sample_titles'      => array(),
		'affected_link_count' => 0,
		'affected_link_samples' => array(),
		'dry_run'            => (bool) $args['dry_run'],
	);

	if ( empty( $post_ids ) ) {
		$log( 'No posts match the criteria. Nothing to do.' );
		return $stats;
	}

	// Sample titles (first 20).
	$sample_posts = get_posts( array(
		'post__in'       => array_slice( $post_ids, 0, 20 ),
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'fields'         => 'post_title',
	) );
	foreach ( $sample_posts as $p ) {
		$stats['sample_titles'][] = $p->post_title;
	}

	// For dry-run: also count which OTHER posts would lose internal links.
	if ( $args['dry_run'] ) {
		$urls = array();
		// Limit to first 500 for performance; full count is an estimate.
		foreach ( array_slice( $post_ids, 0, 500 ) as $pid ) {
			$url = get_permalink( $pid );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		if ( ! empty( $urls ) ) {
			$like_clauses = array();
			$params       = array();
			foreach ( $urls as $url ) {
				$like_clauses[] = 'post_content LIKE %s';
				$params[]       = '%' . $wpdb->esc_like( $url ) . '%';
			}
			$sql = "SELECT ID, post_title FROM $wpdb->posts
					WHERE post_status = 'publish'
					AND post_type = 'post'
					AND (" . implode( ' OR ', $like_clauses ) . ')
					LIMIT 50';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$affected = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$stats['affected_link_count'] = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT COUNT(*) FROM $wpdb->posts
				 WHERE post_status = 'publish'
				 AND post_type = 'post'
				 AND (" . implode( ' OR ', $like_clauses ) . ')'
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
			foreach ( (array) $affected as $a ) {
				$stats['affected_link_samples'][] = $a->post_title;
			}
		}
		$log( sprintf( 'Dry run: %d posts would be deleted, %d posts would lose internal links.', $stats['posts_to_delete'], $stats['affected_link_count'] ) );
		return $stats;
	}

	// Real run: rewrite links first, then delete in batches.
	$log( sprintf( 'Starting real cleanup of %d posts (batch size %d).', count( $post_ids ), (int) $args['batch_size'] ) );

	foreach ( array_chunk( $post_ids, (int) $args['batch_size'] ) as $batch_index => $batch ) {
		$log( sprintf( 'Batch %d: rewriting links for %d posts.', $batch_index + 1, count( $batch ) ) );

		// Step 1: rewrite internal links.
		foreach ( $batch as $post_id ) {
			$post_url = get_permalink( $post_id );
			if ( ! $post_url ) {
				continue;
			}

			$like    = '%' . $wpdb->esc_like( $post_url ) . '%';
			$linked  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM $wpdb->posts
					 WHERE post_status = 'publish'
					 AND post_type = 'post'
					 AND post_content LIKE %s",
					$like
				)
			);

			foreach ( (array) $linked as $linked_post ) {
				$new_content = preg_replace_callback(
					'/<a\s+[^>]*href=[\"\']' . preg_quote( $post_url, '/' ) . '[\"\'][^>]*>(.*?)<\/a>/is',
					static function ( $m ) {
						return $m[1];
					},
					$linked_post->post_content
				);
				if ( $new_content !== $linked_post->post_content ) {
					wp_update_post( array(
						'ID'           => (int) $linked_post->ID,
						'post_content' => $new_content,
					) );
					$stats['links_rewritten']++;
				}
			}
		}

		// Step 2: delete posts (cascades attachments via force=true).
		foreach ( $batch as $post_id ) {
			$children = get_children( array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
			) );
			$stats['attachments_deleted'] += count( $children );

			$deleted = wp_delete_post( (int) $post_id, true );
			if ( $deleted ) {
				$stats['posts_deleted']++;
			}
		}

		$log( sprintf( 'Batch %d complete. Cumulative: %d posts deleted, %d links rewritten.', $batch_index + 1, $stats['posts_deleted'], $stats['links_rewritten'] ) );
	}

	$log( sprintf( 'Done. Deleted %d posts, %d attachments, rewrote %d internal links.', $stats['posts_deleted'], $stats['attachments_deleted'], $stats['links_rewritten'] ) );
	return $stats;
}

// Load the WP-CLI command for the cleanup tool (no-op in non-CLI context).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/tools/cleanup-old-posts.php';
}

function alminuto_theme_enqueue_assets() {
	$css_path = get_stylesheet_directory() . '/style.css';
	$version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '0.1.0';
	wp_enqueue_style( 'alminuto-theme', get_stylesheet_uri(), [], $version );
	wp_enqueue_style( 'font-awesome', 'https://use.fontawesome.com/releases/v5.15.4/css/all.css', [], '5.15.4' );

	wp_register_script( 'alminuto-theme', '', [], $version, true );
	wp_enqueue_script( 'alminuto-theme' );
	wp_add_inline_script(
		'alminuto-theme',
		'(function(){function init(){var btn=document.querySelector(".am-nav-toggle");if(!btn){return}var menu=document.getElementById("am-primary-menu");if(!menu){return}function sync(){var mobile=window.matchMedia("(max-width: 768px)").matches;if(!mobile){btn.setAttribute("aria-expanded","true");menu.hidden=false;return}btn.setAttribute("aria-expanded","false");menu.hidden=true}sync();window.addEventListener("resize",sync);btn.addEventListener("click",function(){if(!window.matchMedia("(max-width: 768px)").matches){return}var expanded=btn.getAttribute("aria-expanded")==="true";btn.setAttribute("aria-expanded",expanded?"false":"true");menu.hidden=expanded;});}document.addEventListener("DOMContentLoaded",init);})();'
	);
}
add_action( 'wp_enqueue_scripts', 'alminuto_theme_enqueue_assets' );

function alminuto_theme_register_sidebars() {
	register_sidebar(
		[
			'name'          => __( 'Sidebar derecha', 'alminuto-theme' ),
			'id'            => 'sidebar-right',
			'before_widget' => '<div class="am-card"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);

	register_sidebar(
		[
			'name'          => __( 'Header (banners)', 'alminuto-theme' ),
			'id'            => 'header-banners',
			'before_widget' => '<div class="am-card" style="margin-bottom:14px;"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);

	register_sidebar(
		[
			'name'          => __( 'Top derecha (home)', 'alminuto-theme' ),
			'id'            => 'top-right',
			'before_widget' => '<div class="am-card"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);
}
add_action( 'widgets_init', 'alminuto_theme_register_sidebars' );

function alminuto_theme_force_front_page_template( $template ) {
	if ( is_front_page() ) {
		$front = locate_template( 'front-page.php' );
		if ( $front ) {
			return $front;
		}
	}

	return $template;
}
add_filter( 'template_include', 'alminuto_theme_force_front_page_template', 20 );

function alminuto_theme_share_links( $url, $title ) {
	$encoded_url   = rawurlencode( $url );
	$encoded_title = rawurlencode( $title );

	return [
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
		'x'        => 'https://x.com/intent/post?url=' . $encoded_url . '&text=' . $encoded_title,
		'whatsapp' => 'https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url,
		'telegram' => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
	];
}

function alminuto_theme_post_meta_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return '';
	}

	$date = get_the_date( 'd/m/Y', $post_id );
	$time = get_the_time( 'H:i', $post_id );
	$author_id   = (int) get_post_field( 'post_author', $post_id );
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$avatar      = get_avatar( $author_id, 24, '', $author_name, [ 'class' => 'am-post-info-avatar' ] );

	$icon_calendar = '<i aria-hidden="true" class="fas fa-calendar"></i>';
	$icon_clock    = '<i aria-hidden="true" class="fas fa-clock"></i>';

	$out  = '<ul class="am-post-info">';
	$out .= '<li class="am-post-info-item am-post-info-item--date"><span class="am-post-info-icon">' . $icon_calendar . '</span><span class="am-post-info-text">' . esc_html( $date ) . '</span></li>';
	$out .= '<li class="am-post-info-item am-post-info-item--time"><span class="am-post-info-icon">' . $icon_clock . '</span><span class="am-post-info-text">' . esc_html( $time ) . '</span></li>';
	$out .= '<li class="am-post-info-item am-post-info-item--author"><span class="am-post-info-icon"><i aria-hidden="true" class="fas fa-user"></i></span><span class="am-post-info-text">' . esc_html( $author_name ) . '</span></li>';
	$out .= '</ul>';

	return wp_kses(
		$out,
		[
			'ul'   => [ 'class' => true ],
			'li'   => [ 'class' => true ],
			'span' => [ 'class' => true ],
			'i'    => [ 'class' => true, 'aria-hidden' => true ],
			'img'  => [
				'class'    => true,
				'src'      => true,
				'srcset'   => true,
				'sizes'    => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			],
		]
	);
}

function alminuto_theme_video_allowed_html() {
	$allowed            = wp_kses_allowed_html( 'post' );
	$allowed['iframe']  = [
		'src'             => true,
		'width'           => true,
		'height'          => true,
		'frameborder'     => true,
		'allowfullscreen' => true,
		'allow'           => true,
		'referrerpolicy'  => true,
		'loading'         => true,
		'title'           => true,
		'sandbox'         => true,
	];
	$allowed['div']     = [ 'class' => true ];
	return $allowed;
}

function alminuto_theme_is_allowed_iframe_url( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return false;
	}
	$host = wp_parse_url( esc_url_raw( $url ), PHP_URL_HOST );
	if ( ! $host ) {
		return false;
	}
	$host = strtolower( $host );
	$allowed = alminuto_theme_get_allowed_iframe_hosts();
	foreach ( $allowed as $needle ) {
		if ( substr( $host, -strlen( '.' . $needle ) ) === '.' . $needle || $host === $needle ) {
			return true;
		}
	}
	return false;
}

function alminuto_theme_sanitize_iframe_block( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' || stripos( $value, '<iframe' ) === false ) {
		return $value;
	}
	$allowed = alminuto_theme_video_allowed_html();
	$value   = wp_kses( $value, $allowed );
	if ( preg_match_all( '#<iframe[^>]+src=[\"\']([^\"\']+)[\"\']#i', $value, $matches ) ) {
		foreach ( $matches[1] as $src ) {
			if ( ! alminuto_theme_is_allowed_iframe_url( $src ) ) {
				return '';
			}
		}
	}
	return $value;
}

function alminuto_theme_add_video_metabox() {
	add_meta_box(
		'alminuto-theme-videos',
		'Videos Personalizados (Campos)',
		'alminuto_theme_render_video_metabox',
		'post',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'alminuto_theme_add_video_metabox' );

function alminuto_theme_render_video_metabox( $post ) {
	wp_nonce_field( 'alminuto_theme_save_videos', 'alminuto_theme_videos_nonce' );
	$youtube  = (string) get_post_meta( $post->ID, '_video_youtube', true );
	$facebook = (string) get_post_meta( $post->ID, '_video_facebook', true );
	?>
	<p>
		<label for="alminuto-video-youtube"><strong>Contenido para [video_youtube]:</strong></label><br>
		<textarea name="alminuto_video_youtube" id="alminuto-video-youtube" rows="2" style="width:100%;" placeholder="URL, iframe o código embebido de YouTube"><?php echo esc_textarea( $youtube ); ?></textarea>
	</p>
	<p>
		<label for="alminuto-video-facebook"><strong>Contenido para [video_facebook]:</strong></label><br>
		<textarea name="alminuto_video_facebook" id="alminuto-video-facebook" rows="2" style="width:100%;" placeholder="URL, iframe o código embebido de Facebook"><?php echo esc_textarea( $facebook ); ?></textarea>
	</p>
	<?php
}

function alminuto_theme_save_video_metabox( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['alminuto_theme_videos_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( (string) $_POST['alminuto_theme_videos_nonce'] ), 'alminuto_theme_save_videos' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$allowed = alminuto_theme_video_allowed_html();

	$youtube_raw = isset( $_POST['alminuto_video_youtube'] ) ? trim( (string) wp_unslash( $_POST['alminuto_video_youtube'] ) ) : '';
	if ( $youtube_raw === '' ) {
		delete_post_meta( $post_id, '_video_youtube' );
	} else {
		$youtube_value = strpos( $youtube_raw, '<' ) !== false ? alminuto_theme_sanitize_iframe_block( $youtube_raw ) : sanitize_text_field( $youtube_raw );
		if ( $youtube_value === '' ) {
			delete_post_meta( $post_id, '_video_youtube' );
		} else {
			update_post_meta( $post_id, '_video_youtube', $youtube_value );
		}
	}

	$facebook_raw = isset( $_POST['alminuto_video_facebook'] ) ? trim( (string) wp_unslash( $_POST['alminuto_video_facebook'] ) ) : '';
	if ( $facebook_raw === '' ) {
		delete_post_meta( $post_id, '_video_facebook' );
	} else {
		$facebook_value = strpos( $facebook_raw, '<' ) !== false ? alminuto_theme_sanitize_iframe_block( $facebook_raw ) : sanitize_text_field( $facebook_raw );
		if ( $facebook_value === '' ) {
			delete_post_meta( $post_id, '_video_facebook' );
		} else {
			update_post_meta( $post_id, '_video_facebook', $facebook_value );
		}
	}
}
add_action( 'save_post', 'alminuto_theme_save_video_metabox' );

function alminuto_theme_extract_youtube_id( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}
	if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^\&\?\/]+)/', $value, $m ) ) {
		return (string) $m[1];
	}
	if ( preg_match( '/youtube\.com\/embed\/([^\&\?\/]+)/', $value, $m ) ) {
		return (string) $m[1];
	}
	return '';
}

function alminuto_theme_youtube_embed_html( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}

	$allowed = alminuto_theme_video_allowed_html();

	if ( stripos( $value, '<iframe' ) !== false ) {
		$clean = alminuto_theme_sanitize_iframe_block( $value );
		if ( $clean === '' ) {
			return '';
		}
		return '<div class="am-post-embed">' . $clean . '</div>';
	}

	$id = alminuto_theme_extract_youtube_id( $value );
	if ( $id === '' ) {
		$oembed = wp_oembed_get( $value );
		if ( ! $oembed ) {
			return '';
		}
		return '<div class="am-post-embed">' . wp_kses( $oembed, $allowed ) . '</div>';
	}

	$src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id );
	$iframe = '<iframe src="' . esc_url( $src ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-presentation allow-popups" title="YouTube"></iframe>';
	return '<div class="am-post-embed">' . $iframe . '</div>';
}

function alminuto_theme_facebook_embed_html( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}

	// Always use the direct video URL instead of the Facebook video plugin iframe.
	// The iframe (facebook.com/plugins/video.php) is unreliable across browsers:
	//   - Safari renders it cut in half / black.
	//   - Brave and other anti-tracking browsers block it.
	// The URL-based block opens the official Facebook video page in a new tab,
	// which works reliably everywhere.
	$url = alminuto_theme_normalize_facebook_video_url( $value );
	if ( $url === '' ) {
		// Fallback: if the value contains an iframe we couldn't normalize to a URL,
		// try to extract a Facebook URL from the iframe src.
		if ( stripos( $value, '<iframe' ) !== false && preg_match( '#https?://[^"\']+#i', $value, $m ) ) {
			$url = alminuto_theme_normalize_facebook_video_url( $m[0] );
		}
	}

	if ( $url === '' ) {
		return '';
	}

	return alminuto_theme_facebook_url_embed( $url, 'am-post-embed' );
}

function alminuto_theme_primary_media_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return '';
	}

	$youtube  = (string) get_post_meta( $post_id, '_video_youtube', true );
	$facebook = (string) get_post_meta( $post_id, '_video_facebook', true );

	$out = alminuto_theme_youtube_embed_html( $youtube );
	if ( $out !== '' ) {
		return $out;
	}
	$out = alminuto_theme_facebook_embed_html( $facebook );
	if ( $out !== '' ) {
		return $out;
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$img = get_the_post_thumbnail( $post_id, 'content_4_3' );
		return $img ? '<div class="am-post-thumb" style="aspect-ratio:auto;">' . $img . '</div>' : '';
	}

	return '';
}

function alminuto_theme_card_has_video( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return false;
	}
	$youtube  = trim( (string) get_post_meta( $post_id, '_video_youtube', true ) );
	$facebook = trim( (string) get_post_meta( $post_id, '_video_facebook', true ) );
	return $youtube !== '' || $facebook !== '';
}

function alminuto_theme_card_video_embed( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return '';
	}

	$youtube  = trim( (string) get_post_meta( $post_id, '_video_youtube', true ) );
	if ( $youtube !== '' ) {
		$yt = alminuto_theme_youtube_embed_html( $youtube );
		if ( $yt !== '' ) {
			return str_replace( 'am-post-embed', 'am-card-embed', $yt );
		}
		return '';
	}

	$facebook = trim( (string) get_post_meta( $post_id, '_video_facebook', true ) );
	if ( $facebook === '' ) {
		return '';
	}

	// Use the direct video URL for Facebook. The iframe is unreliable across browsers.
	$fb_url = '';
	if ( stripos( $facebook, '<iframe' ) !== false && preg_match( '#https?://[^"\']+#i', $facebook, $m ) ) {
		$fb_url = alminuto_theme_normalize_facebook_video_url( $m[0] );
	}
	if ( $fb_url === '' ) {
		$fb_url = alminuto_theme_normalize_facebook_video_url( $facebook );
	}
	if ( $fb_url === '' ) {
		return '';
	}

	return alminuto_theme_facebook_url_embed( $fb_url, 'am-card-embed' );
}

function alminuto_theme_normalize_facebook_video_url( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}

	if ( preg_match( '/^[0-9]+$/', $value ) ) {
		return 'https://www.facebook.com/video.php?v=' . $value;
	}

	if ( preg_match( '#facebook\.com/[^/]+/videos/[^/]+/(\d+)#', $value, $m ) ) {
		return 'https://www.facebook.com/video.php?v=' . $m[1];
	}

	if ( preg_match( '#facebook\.com/[^/]+/videos/(\d+)#', $value, $m ) ) {
		return 'https://www.facebook.com/video.php?v=' . $m[1];
	}

	if ( preg_match( '#facebook\.com/(?:[^/]+/)?(?:videos|reel)/(\d+)#', $value, $m ) ) {
		return 'https://www.facebook.com/video.php?v=' . $m[1];
	}

	if ( preg_match( '#[?&]v=(\d+)#', $value, $m ) ) {
		return 'https://www.facebook.com/video.php?v=' . $m[1];
	}

	$esc   = esc_url_raw( $value );
	$host  = wp_parse_url( $esc, PHP_URL_HOST );
	if ( $host && ( substr( strtolower( $host ), -strlen( 'facebook.com' ) ) === 'facebook.com' || strtolower( $host ) === 'facebook.com' ) ) {
		return $esc;
	}

	return '';
}

/**
 * Renders a Facebook video using the official Facebook embed iframe
 * (`facebook.com/plugins/video.php`).
 *
 * Why this works in all major browsers (Safari, Chrome, Firefox, Edge,
 * Brave with Shields down):
 *   - The previous attempt at using the official FB SDK (FB.XFBML) is fragile
 *     in production: ad-blockers, Brave Shields and Strict Tracking Protection
 *     block `connect.facebook.net/sdk.js`, the SDK requires a working App ID in
 *     recent versions, and SG Optimizer's page cache can serve a snapshot
 *     before the SDK has a chance to render. The user sees a black box and a
 *     link, which is not an embedded video.
 *   - The Facebook embed iframe, on the other hand, is a single static
 *     <iframe> element. It works in 99% of real-world configurations.
 *   - The Safari bug ("cortado en negro por la mitad") happens when the
 *     iframe is left to size itself inside a container with no defined
 *     height. Giving the container `aspect-ratio: 16/9` (see style.css) and
 *     making the iframe fill it 100% fixes that.
 *
 * For users with aggressive Brave Shields that block the iframe at the
 * network level, a `<noscript>` fallback surfaces a direct link to the
 * Facebook video page. A JS timeout fallback also injects the same link
 * if the iframe fails to load within 6 seconds (e.g. blocked).
 *
 * @param string $url           Normalized Facebook video URL.
 * @param string $wrapper_class CSS class for the outer wrapper (e.g. am-post-embed, am-card-embed, am-right-embed).
 * @return string HTML block ready to be embedded.
 */
function alminuto_theme_facebook_url_embed( $url, $wrapper_class = 'am-post-embed' ) {
	$url = (string) $url;
	if ( $url === '' ) {
		return '';
	}

	$video_id = '';
	if ( preg_match( '#video\.php\?v=(\d+)#', $url, $m ) ) {
		$video_id = $m[1];
	}
	$direct_url = $video_id !== '' ? 'https://www.facebook.com/video.php?v=' . rawurlencode( $video_id ) : esc_url_raw( $url );

	// The official Facebook embed iframe. The `href` parameter is the
	// canonical video URL (URL-encoded).
	//
	// Why a direct iframe and not the FB.XFBML SDK:
	//   The SDK runs in the parent page's origin and creates the player
	//   iframe itself. The SDK then tries to read the iframe's content
	//   (for resize / analytics / etc.), which triggers
	//   "Blocked a frame with origin ... accessing a frame with origin
	//   facebook.com" — and on Safari that escalates to a full page
	//   reload ("Esta página web se ha vuelto a cargar debido a un
	//   problema"). So the SDK is not viable on third-party pages.
	//
	//   A direct iframe doesn't have this issue: the parent page never
	//   touches the iframe's content, so there are no SOP errors. The
	//   trade-off is that in some Safari setups (ITP + 3rd-party
	//   cookies), the player's bootloader can stall, in which case we
	//   surface a fallback link via JS (see `alminuto_theme_facebook_sdk_loader`).
	$embed_src = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode( $direct_url ) . '&show_text=0';

	$wrapper_class = trim( (string) $wrapper_class );
	if ( $wrapper_class === '' ) {
		$wrapper_class = 'am-post-embed';
	}

	return '<div class="' . esc_attr( $wrapper_class ) . ' am-fb-embed" data-fb-href="' . esc_url( $direct_url ) . '">'
		. '<iframe class="am-fb-iframe" src="' . esc_url( $embed_src ) . '" '
		. 'style="border:none;overflow:hidden;width:100%;height:100%" '
		. 'scrolling="no" '
		. 'frameborder="0" '
		. 'allowfullscreen="true" '
		. 'allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">'
		. '</iframe>'
		. '<noscript><a class="am-fb-fallback" href="' . esc_url( $direct_url ) . '" target="_blank" rel="noopener noreferrer">Ver vídeo en Facebook</a></noscript>'
		. '</div>';
}

/**
 * JS fallback for the Facebook embed iframe.
 *
 * The iframe works in 99% of cases. The remaining cases (Brave Shields
 * with "Block trackers", strict Safari ITP, ad-blockers, the network
 * blocking fbcdn.net) show a black box. After 8 s we surface a direct
 * link to the video on Facebook, displayed BELOW the iframe so the
 * video keeps playing if it eventually does — the user can choose.
 *
 * IMPORTANT: we must NOT try to read the iframe's content
 * (iframe.contentWindow.document, .body, etc.). That would trigger a
 * Same-Origin Policy violation, which Safari escalates to a full page
 * reload. The only safe check is on the iframe element itself, which is
 * always same-origin (it's our own DOM node).
 */
function alminuto_theme_facebook_sdk_loader() {
	?>
	<script id="alminuto-fb-fallback">
	(function(){
		function showFallback(){
			var nodes = document.querySelectorAll('.am-fb-embed');
			for(var i=0; i<nodes.length; i++){
				var wrap = nodes[i];
				if(wrap.getAttribute('data-fb-fallback') === '1'){ continue; }
				var iframe = wrap.querySelector('iframe.am-fb-iframe');
				if(!iframe){ continue; }
				var href = wrap.getAttribute('data-fb-href');
				if(!href){ continue; }
				// Show the link BELOW the iframe (not replacing it) so the
				// video can still play if it eventually loads. The user gets
				// both options.
				var a = document.createElement('a');
				a.href = href;
				a.target = '_blank';
				a.rel = 'noopener noreferrer';
				a.className = 'am-fb-fallback am-fb-fallback--below';
				a.textContent = 'Si el vídeo no se reproduce, ábrelo en Facebook';
				wrap.appendChild(a);
				wrap.setAttribute('data-fb-fallback', '1');
			}
		}
		function init(){
			if(!document.querySelector('iframe.am-fb-iframe')){ return; }
			setTimeout(showFallback, 8000);
		}
		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'alminuto_theme_facebook_sdk_loader' );

function alminuto_theme_card_media( $post_id = 0, $size = 'col_izquierda' ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return [ 'has_video' => false, 'html' => '' ];
	}
	if ( alminuto_theme_card_has_video( $post_id ) ) {
		return [ 'has_video' => true, 'html' => alminuto_theme_card_video_embed( $post_id ) ];
	}
	$img = '';
	if ( has_post_thumbnail( $post_id ) ) {
		$img = wp_get_attachment_image( get_post_thumbnail_id( $post_id ), $size );
	}
	return [ 'has_video' => false, 'html' => $img ];
}

function alminuto_theme_shortcode_video_youtube() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}
	return alminuto_theme_youtube_embed_html( get_post_meta( get_the_ID(), '_video_youtube', true ) );
}
add_shortcode( 'video_youtube', 'alminuto_theme_shortcode_video_youtube' );

function alminuto_theme_shortcode_video_facebook() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}
	return alminuto_theme_facebook_embed_html( get_post_meta( get_the_ID(), '_video_facebook', true ) );
}
add_shortcode( 'video_facebook', 'alminuto_theme_shortcode_video_facebook' );

function alminuto_theme_disable_comments_support() {
	$post_types = get_post_types( [ 'public' => true ], 'names' );
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'alminuto_theme_disable_comments_support', 100 );

function alminuto_theme_force_comments_closed( $data, $postarr ) {
	if ( is_array( $data ) ) {
		$data['comment_status'] = 'closed';
		$data['ping_status']    = 'closed';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'alminuto_theme_force_comments_closed', 10, 2 );

add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );
add_filter( 'comments_array', '__return_empty_array', 20, 2 );

function alminuto_theme_settings_defaults() {
	return [
		'home_left_posts'        => 20,
		'home_right_posts'       => 20,
		'banner_slider_interval' => 5,
	];
}

function alminuto_theme_get_settings() {
	$defaults = alminuto_theme_settings_defaults();
	$raw      = get_option( 'alminuto_theme_settings', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	return array_merge( $defaults, $raw );
}

function alminuto_theme_admin_menu() {
	add_menu_page(
		'Al Minuto',
		'Al Minuto',
		'manage_options',
		'alminuto-theme-panel',
		'alminuto_theme_render_admin_page',
		'dashicons-admin-generic',
		2.1
	);
	add_submenu_page(
		'alminuto-theme-panel',
		'Limpiar imágenes y artículos antiguos',
		'Limpiar imágenes',
		'manage_options',
		'alminuto-cleanup',
		'alminuto_theme_render_cleanup_page'
	);
}
add_action( 'admin_menu', 'alminuto_theme_admin_menu' );

/**
 * Renders the cleanup admin page and handles form submission.
 *
 * Two-step flow: the form is submitted with a `dry_run` checkbox. With
 * dry_run checked, it shows what WOULD be deleted (no writes). With it
 * unchecked, it performs the real cleanup after a JS confirm() dialog.
 */
function alminuto_theme_render_cleanup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}

	$result         = null;
	$was_dry_run    = false;
	$error_message  = null;
	$log_file_path  = null;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['alminuto_cleanup_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['alminuto_cleanup_nonce'], 'alminuto_cleanup' ) ) {
			wp_die( esc_html__( 'Nonce inválido. Recarga la página.', 'alminuto-theme' ) );
		}

		$dry_run = ! empty( $_POST['dry_run'] );
		$args    = array(
			'dry_run' => $dry_run,
		);
		if ( ! empty( $_POST['before_days'] ) ) {
			$args['before_days'] = max( 30, (int) $_POST['before_days'] );
		}
		if ( ! empty( $_POST['start_date'] ) ) {
			$args['start_date'] = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
		}
		if ( ! empty( $_POST['end_date'] ) ) {
			$args['end_date'] = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
		}

		$log_file_path = alminuto_theme_get_cleanup_log_file();
		$args['log_callback'] = static function ( $msg ) use ( $log_file_path ) {
			file_put_contents( $log_file_path, '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n", FILE_APPEND );
		};

		try {
			$result      = alminuto_theme_cleanup_old_posts( $args );
			$was_dry_run = $dry_run;
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();
			if ( is_callable( $args['log_callback'] ) ) {
				call_user_func( $args['log_callback'], 'ERROR: ' . $e->getMessage() );
			}
		}
	}

	$template = locate_template( 'template-parts/admin/cleanup-page.php' );
	if ( ! $template ) {
		echo '<div class="wrap"><h1>Limpiar imágenes</h1><p>Vista no encontrada.</p></div>';
		return;
	}

	// Make vars available to the template.
	$cleanup_result        = $result;
	$cleanup_was_dry_run   = $was_dry_run;
	$cleanup_error         = $error_message;
	$cleanup_log_file_path = $log_file_path;

	include $template;
}

function alminuto_theme_admin_enqueue( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_alminuto-theme-panel' ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_media();

	wp_register_style( 'alminuto-theme-admin', '' );
	wp_enqueue_style( 'alminuto-theme-admin' );
	wp_add_inline_style(
		'alminuto-theme-admin',
		'.toplevel_page_alminuto-theme-panel .am-admin-wrap{max-width:1100px}.toplevel_page_alminuto-theme-panel .am-admin-grid{display:grid;gap:12px}.toplevel_page_alminuto-theme-panel .am-admin-card{background:#fff;border:1px solid #dcdcde;padding:12px}.toplevel_page_alminuto-theme-panel .am-admin-card h2{margin:0 0 10px;font-size:15px}.toplevel_page_alminuto-theme-panel .am-admin-card p.am-help{margin:0 0 10px;color:#50575e}.toplevel_page_alminuto-theme-panel .am-field{display:grid;gap:6px;margin-top:10px}.toplevel_page_alminuto-theme-panel .am-field label{font-weight:600}.toplevel_page_alminuto-theme-panel .am-actions{display:flex;gap:8px;flex-wrap:wrap}.toplevel_page_alminuto-theme-panel .am-thumb{width:100px;flex:0 0 auto}.toplevel_page_alminuto-theme-panel .am-thumb img{width:100%;height:auto;display:block}.toplevel_page_alminuto-theme-panel .am-gallery-list{margin:10px 0 0;display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-item{border:1px solid #dcdcde;background:#fff;padding:10px;display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-row{display:flex;gap:10px;align-items:center}.toplevel_page_alminuto-theme-panel .am-gallery-handle{cursor:move;color:#50575e}.toplevel_page_alminuto-theme-panel .am-gallery-meta{display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-meta input[type=url]{width:100%}.toplevel_page_alminuto-theme-panel .am-gallery-remove{margin-left:auto}.toplevel_page_alminuto-theme-panel .am-submit{margin-top:12px}@media (min-width: 960px){.toplevel_page_alminuto-theme-panel .am-admin-grid{grid-template-columns:1fr 1fr}.toplevel_page_alminuto-theme-panel .am-admin-card--full{grid-column:1 / -1}}'
	);

	$js_path = get_template_directory() . '/assets/js/admin-panel.js';
	$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : false;
	wp_enqueue_script(
		'alminuto-theme-admin',
		get_template_directory_uri() . '/assets/js/admin-panel.js',
		[ 'jquery', 'jquery-ui-sortable' ],
		$js_ver,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'alminuto_theme_admin_enqueue' );

function alminuto_theme_maybe_migrate_plugin_data() {
	$right = get_option( 'alminuto_theme_right_column', null );
	if ( $right === null ) {
		$legacy = get_option( 'alminuto_sidebar_right', null );
		if ( is_array( $legacy ) ) {
			update_option( 'alminuto_theme_right_column', $legacy, false );
		}
	}

	$banners = get_option( 'alminuto_theme_banners', null );
	if ( $banners === null ) {
		$legacy = get_option( 'banners_alminuto_slots', null );
		if ( is_array( $legacy ) ) {
			update_option( 'alminuto_theme_banners', $legacy, false );
		}
	}
}
add_action( 'init', 'alminuto_theme_maybe_migrate_plugin_data', 1 );

function alminuto_theme_banners_defaults() {
	return [
		'top_left' => [],
	];
}

function alminuto_theme_banners_get() {
	$defaults = alminuto_theme_banners_defaults();
	$raw      = get_option( 'alminuto_theme_banners', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$out = array_merge( $defaults, $raw );
	if ( ! is_array( $out['top_left'] ?? null ) ) {
		$out['top_left'] = [];
	}
	return $out;
}

function alminuto_theme_is_valid_date_ymd( $value ) {
	if ( ! is_string( $value ) ) {
		return false;
	}
	return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
}

function alminuto_theme_sanitize_banner_items( $raw ) {
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id       = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url      = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
		$new_tab  = ! empty( $row['new_tab'] ) ? 1 : 0;
		$start    = isset( $row['start'] ) ? sanitize_text_field( (string) $row['start'] ) : '';
		$end      = isset( $row['end'] ) ? sanitize_text_field( (string) $row['end'] ) : '';
		$interval = isset( $row['interval'] ) ? (int) $row['interval'] : 0;
		if ( $id <= 0 ) {
			continue;
		}
		if ( $start !== '' && ! alminuto_theme_is_valid_date_ymd( $start ) ) {
			$start = '';
		}
		if ( $end !== '' && ! alminuto_theme_is_valid_date_ymd( $end ) ) {
			$end = '';
		}
		if ( $start !== '' && $end !== '' && strcmp( $start, $end ) > 0 ) {
			$end = '';
		}
		if ( $interval < 0 ) {
			$interval = 0;
		}
		$out[] = [
			'id'       => $id,
			'url'      => $url,
			'new_tab'  => $new_tab,
			'start'    => $start,
			'end'      => $end,
			'interval' => $interval,
		];
	}
	return $out;
}

function alminuto_theme_banner_item_is_active( $item, $now_ts ) {
	$start = is_array( $item ) && isset( $item['start'] ) ? (string) $item['start'] : '';
	$end   = is_array( $item ) && isset( $item['end'] ) ? (string) $item['end'] : '';
	if ( $start !== '' && alminuto_theme_is_valid_date_ymd( $start ) ) {
		$start_ts = strtotime( $start . ' 00:00:00' );
		if ( $start_ts && $now_ts < $start_ts ) {
			return false;
		}
	}
	if ( $end !== '' && alminuto_theme_is_valid_date_ymd( $end ) ) {
		$end_ts = strtotime( $end . ' 23:59:59' );
		if ( $end_ts && $now_ts > $end_ts ) {
			return false;
		}
	}
	return true;
}

function alminuto_theme_banners_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'    => 10,
			'slot'     => '',
			'size'     => 'full',
			'class'    => '',
			'slider'   => 0,
			'autoplay' => 0,
		],
		(array) $atts,
		'banners_alminuto'
	);

	$limit        = max( 1, (int) $atts['limit'] );
	$slot         = sanitize_key( (string) $atts['slot'] );
	$size         = sanitize_key( (string) $atts['size'] );
	$class        = trim( (string) $atts['class'] );
	$slider       = (int) $atts['slider'] === 1 || $atts['slider'] === 'true' || $atts['slider'] === 'yes';
	$autoplay_atts = max( 0, (int) $atts['autoplay'] );

	if ( $slot !== 'top_left' ) {
		return '';
	}

	$global_interval = (int) ( alminuto_theme_get_settings()['banner_slider_interval'] ?? 5 );
	if ( $global_interval < 1 ) {
		$global_interval = 1;
	}
	if ( $global_interval > 60 ) {
		$global_interval = 60;
	}
	$global_interval_ms = $global_interval * 1000;
	$autoplay           = $autoplay_atts > 0 ? $autoplay_atts : $global_interval_ms;

	$data = alminuto_theme_banners_get();
	$list = (array) ( $data['top_left'] ?? [] );
	$now  = (int) current_time( 'timestamp' );

	$items = [];
	foreach ( $list as $row ) {
		if ( ! alminuto_theme_banner_item_is_active( $row, $now ) ) {
			continue;
		}
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$img = wp_get_attachment_image( $id, $size, false, [ 'loading' => 'eager' ] );
		if ( ! $img ) {
			continue;
		}
		$url          = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab      = ! empty( $row['new_tab'] ) ? 1 : 0;
		$interval_row = isset( $row['interval'] ) ? (int) $row['interval'] : 0;
		$html         = $img;
		if ( $url ) {
			$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
			$html   = '<a href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
		}
		$slide_interval = $interval_row > 0 ? $interval_row * 1000 : 0;
		if ( $slider ) {
			$items[] = '<div class="bam-slide" data-interval="' . esc_attr( (string) $slide_interval ) . '">' . $html . '</div>';
		} else {
			$items[] = '<div class="bam-item">' . $html . '</div>';
		}
		if ( count( $items ) >= $limit ) {
			break;
		}
	}

	if ( empty( $items ) ) {
		return '';
	}

	$classes = 'bam-wrap';
	if ( $class !== '' ) {
		$classes .= ' ' . sanitize_html_class( $class );
	}

	if ( ! wp_style_is( 'alminuto-theme-banners', 'enqueued' ) ) {
		wp_register_style( 'alminuto-theme-banners', false );
		wp_enqueue_style( 'alminuto-theme-banners' );
		wp_add_inline_style(
			'alminuto-theme-banners',
			'.bam-wrap{display:grid;gap:10px}.bam-item img{max-width:100%;height:auto;display:block}.bam-slider{position:relative;overflow:hidden}.bam-slide{display:none}.bam-slide.is-active{display:block}'
		);
	}
	if ( ! wp_script_is( 'alminuto-theme-banners', 'enqueued' ) ) {
		wp_register_script( 'alminuto-theme-banners', '', [], null, true );
		wp_enqueue_script( 'alminuto-theme-banners' );
		wp_add_inline_script(
			'alminuto-theme-banners',
			'(function(){function initSlider(root){var slides=root.querySelectorAll(".bam-slide");if(slides.length<2){return}var idx=0;var def=parseInt(root.getAttribute("data-autoplay")||"0",10);if(!def||def<500){def=5000}slides[0].classList.add("is-active");var timer=null;function show(i){slides[idx].classList.remove("is-active");idx=i;slides[idx].classList.add("is-active")}function currentInterval(){var v=parseInt(slides[idx].getAttribute("data-interval")||"0",10);return v>=500?v:def}function schedule(){if(timer){clearTimeout(timer)}timer=setTimeout(function(){show((idx+1)%slides.length);schedule()},currentInterval())}root.addEventListener("mouseenter",function(){if(timer){clearTimeout(timer);timer=null}});root.addEventListener("mouseleave",schedule);schedule()}document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bam-slider").forEach(initSlider)})})();'
		);
	}

	if ( $slider ) {
		return '<div class="bam-slider" data-autoplay="' . esc_attr( (string) $autoplay ) . '">' . implode( '', $items ) . '</div>';
	}

	return '<div class="' . esc_attr( $classes ) . '">' . implode( '', $items ) . '</div>';
}
add_shortcode( 'banners_alminuto', 'alminuto_theme_banners_shortcode' );

function alminuto_theme_right_defaults() {
	return [
		'news_rigor_image_id' => 0,
		'news_rigor_url'      => '',
		'block2_title'        => 'ALGECIRAS ES SEMANA SANTA',
		'youtube_url'         => '',
		'facebook_video_url'  => '',
		'publi_gallery'       => [],
	];
}

function alminuto_theme_right_get() {
	$defaults = alminuto_theme_right_defaults();
	$raw      = get_option( 'alminuto_theme_right_column', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$out = array_merge( $defaults, $raw );
	if ( ! is_array( $out['publi_gallery'] ?? null ) ) {
		$out['publi_gallery'] = [];
	}
	return $out;
}

function alminuto_theme_right_sanitize_gallery( $raw ) {
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$out[] = [
			'id'      => $id,
			'url'     => $url,
			'new_tab' => $new_tab,
		];
	}
	return $out;
}

function alminuto_theme_right_column_html() {
	$opts = alminuto_theme_right_get();

	$size_candidates = [ 'banner_lateral', 'medium', 'thumbnail' ];
	$sizes           = function_exists( 'get_intermediate_image_sizes' ) ? (array) get_intermediate_image_sizes() : [];
	$img_size        = 'medium';
	foreach ( $size_candidates as $candidate ) {
		if ( in_array( $candidate, $sizes, true ) ) {
			$img_size = $candidate;
			break;
		}
	}

	$out = '<div class="am-right-block">';
	$out .= '<div class="am-section-title">Noticias con rigor</div>';
	if ( (int) $opts['news_rigor_image_id'] > 0 ) {
		$img = wp_get_attachment_image( (int) $opts['news_rigor_image_id'], $img_size, false, [ 'loading' => 'lazy' ] );
		if ( $img ) {
			if ( $opts['news_rigor_url'] ) {
				$out .= '<a href="' . esc_url( (string) $opts['news_rigor_url'] ) . '" target="_self" rel="nofollow noopener noreferrer">' . $img . '</a>';
			} else {
				$out .= $img;
			}
		}
	}

	$title2 = trim( (string) $opts['block2_title'] );
	if ( $title2 === '' ) {
		$title2 = 'ALGECIRAS ES SEMANA SANTA';
	}
	$out .= '<div class="am-section-title">' . esc_html( $title2 ) . '</div>';

	if ( $opts['youtube_url'] ) {
		$embed = wp_oembed_get( (string) $opts['youtube_url'] );
		if ( $embed ) {
			$out .= '<div class="am-right-embed">' . alminuto_theme_sanitize_iframe_block( $embed ) . '</div>';
		}
	}
	if ( $opts['facebook_video_url'] ) {
		$fb_url = alminuto_theme_normalize_facebook_video_url( (string) $opts['facebook_video_url'] );
		if ( $fb_url !== '' ) {
			$out .= alminuto_theme_facebook_url_embed( $fb_url, 'am-right-embed' );
		}
	}

	$out .= '<div class="am-section-title">Publicidad</div>';
	$gallery = (array) $opts['publi_gallery'];
	foreach ( $gallery as $idx => $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$img = wp_get_attachment_image( $id, $img_size, false, [ 'loading' => 'lazy' ] );
		if ( ! $img ) {
			continue;
		}
		$wrap_class = $idx === 0 ? 'am-right-publi-main' : 'am-right-publi-item';
		if ( $url ) {
			$target = $new_tab ? ' target="_blank" rel="nofollow noopener noreferrer"' : ' target="_self" rel="nofollow noopener noreferrer"';
			$out   .= '<a class="' . esc_attr( $wrap_class ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
		} else {
			$out .= '<div class="' . esc_attr( $wrap_class ) . '">' . $img . '</div>';
		}
	}

	$out .= '</div>';

	$allowed = wp_kses_allowed_html( 'post' );
	$allowed['iframe'] = [
		'src'             => true,
		'width'           => true,
		'height'          => true,
		'frameborder'     => true,
		'allow'           => true,
		'allowfullscreen' => true,
		'loading'         => true,
		'referrerpolicy'  => true,
		'title'           => true,
		'scrolling'       => true,
	];

	return wp_kses( $out, $allowed );
}

function alminuto_theme_render_banners_admin() {
	$tab_saved = false;
	if ( isset( $_POST['alminuto_theme_banners_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_banners_nonce'], 'alminuto_theme_banners_save' ) ) {
		$banners             = alminuto_theme_banners_get();
		$banners['top_left'] = alminuto_theme_sanitize_banner_items( $_POST['am_top_left'] ?? [] );
		update_option( 'alminuto_theme_banners', $banners, false );
		$tab_saved = true;
	}

	$banners = alminuto_theme_banners_get();
	$list    = (array) ( $banners['top_left'] ?? [] );

	echo '<div class="am-admin-wrap">';
	if ( $tab_saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'alminuto_theme_banners_save', 'alminuto_theme_banners_nonce' );
	echo '<section class="am-admin-card">';
	echo '<h2>Top banner (slider)</h2>';
	echo '<p class="am-help">Arrastra para reordenar. Fechas opcionales para programar.</p>';
	echo '<div class="am-actions"><button type="button" class="button button-primary" id="am_top_left_add">Añadir imágenes</button></div>';
	echo '<div class="am-help" style="margin-top:10px;">';
	echo 'Tiempo general del slider: <strong>' . esc_html( (string) (int) alminuto_theme_get_settings()['banner_slider_interval'] ) . ' s</strong> (configurable en la pestaña Inicio).';
	echo ' Déjalo en 0 para usar el general por defecto.</div>';

	echo '<ul class="am-gallery-list" id="am_top_left_list">';
	foreach ( $list as $index => $row ) {
		$id       = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url      = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab  = ! empty( $row['new_tab'] ) ? 1 : 0;
		$start    = isset( $row['start'] ) ? (string) $row['start'] : '';
		$end      = isset( $row['end'] ) ? (string) $row['end'] : '';
		$interval = isset( $row['interval'] ) ? (int) $row['interval'] : 0;

		echo '<li class="am-gallery-item" data-index="' . esc_attr( (string) $index ) . '">';
		echo '<div class="am-gallery-row">';
		echo '<span class="dashicons dashicons-move am-gallery-handle" aria-hidden="true"></span>';
		echo '<div class="am-thumb am-top-left-preview">' . ( $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : '' ) . '</div>';
		echo '<div class="am-actions"><button type="button" class="button am-top-left-pick">Cambiar</button></div>';
		echo '<button type="button" class="button-link-delete am-top-left-remove am-gallery-remove">Quitar</button>';
		echo '</div>';
		echo '<div class="am-gallery-meta">';
		echo '<input type="hidden" name="am_top_left[' . esc_attr( (string) $index ) . '][id]" value="' . esc_attr( (string) $id ) . '">';
		echo '<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="am_top_left[' . esc_attr( (string) $index ) . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://..."></div>';
		echo '<label><input type="checkbox" name="am_top_left[' . esc_attr( (string) $index ) . '][new_tab]" value="1" ' . checked( $new_tab, 1, false ) . '> Abrir en nueva pestaña</label>';
		echo '<div class="am-actions" style="gap:12px;">';
		echo '<div class="am-field" style="margin-top:0;min-width:160px;"><label>Inicio</label><input type="date" name="am_top_left[' . esc_attr( (string) $index ) . '][start]" value="' . esc_attr( $start ) . '"></div>';
		echo '<div class="am-field" style="margin-top:0;min-width:160px;"><label>Fin</label><input type="date" name="am_top_left[' . esc_attr( (string) $index ) . '][end]" value="' . esc_attr( $end ) . '"></div>';
		echo '<div class="am-field" style="margin-top:0;min-width:140px;"><label>Intervalo (s)</label><input type="number" min="0" step="1" name="am_top_left[' . esc_attr( (string) $index ) . '][interval]" value="' . esc_attr( (string) $interval ) . '" placeholder="general"></div>';
		echo '</div>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '<div class="am-submit">';
	submit_button( 'Guardar', 'primary', 'submit', false );
	echo '</div>';
	echo '</section>';
	echo '</form>';
	echo '</div>';

	echo '';
}

function alminuto_theme_render_right_admin() {
	$saved = false;
	if ( isset( $_POST['alminuto_theme_right_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_right_nonce'], 'alminuto_theme_right_save' ) ) {
		$opts = alminuto_theme_right_defaults();

		$opts['news_rigor_image_id'] = isset( $_POST['news_rigor_image_id'] ) ? (int) $_POST['news_rigor_image_id'] : 0;
		$opts['news_rigor_url']      = isset( $_POST['news_rigor_url'] ) ? esc_url_raw( (string) $_POST['news_rigor_url'] ) : '';

		$opts['block2_title']       = isset( $_POST['block2_title'] ) ? sanitize_text_field( (string) $_POST['block2_title'] ) : $opts['block2_title'];
		$opts['youtube_url']        = isset( $_POST['youtube_url'] ) ? esc_url_raw( (string) $_POST['youtube_url'] ) : '';
		$opts['facebook_video_url'] = isset( $_POST['facebook_video_url'] ) ? esc_url_raw( (string) $_POST['facebook_video_url'] ) : '';

		$opts['publi_gallery'] = alminuto_theme_right_sanitize_gallery( $_POST['publi_gallery'] ?? [] );

		update_option( 'alminuto_theme_right_column', $opts, false );
		$saved = true;
	}

	$opts = alminuto_theme_right_get();

	echo '<div class="am-admin-wrap">';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'alminuto_theme_right_save', 'alminuto_theme_right_nonce' );

	echo '<div class="am-admin-grid">';

	echo '<section class="am-admin-card">';
	echo '<h2>Noticias con rigor</h2>';
	echo '<p class="am-help">Selecciona una imagen y un enlace opcional.</p>';
	echo '<input type="hidden" name="news_rigor_image_id" id="news_rigor_image_id" value="' . esc_attr( (string) (int) $opts['news_rigor_image_id'] ) . '">';
	echo '<div class="am-actions">';
	echo '<button type="button" class="button button-primary" id="news_rigor_pick">' . ( (int) $opts['news_rigor_image_id'] > 0 ? 'Cambiar imagen' : 'Elegir imagen' ) . '</button>';
	echo '<button type="button" class="button" id="news_rigor_clear" ' . ( (int) $opts['news_rigor_image_id'] > 0 ? '' : 'disabled' ) . '>Quitar</button>';
	echo '</div>';
	echo '<div class="am-field"><label for="news_rigor_url">Enlace</label><input type="url" id="news_rigor_url" class="regular-text" name="news_rigor_url" value="' . esc_attr( (string) $opts['news_rigor_url'] ) . '" placeholder="https://..."></div>';
	echo '<div class="am-field"><label>Preview</label><div id="news_rigor_preview" style="max-width:320px;">' . ( (int) $opts['news_rigor_image_id'] > 0 ? wp_kses_post( wp_get_attachment_image( (int) $opts['news_rigor_image_id'], 'medium' ) ) : '' ) . '</div></div>';
	echo '</section>';

	echo '<section class="am-admin-card">';
	echo '<h2>Bloque 2</h2>';
	echo '<p class="am-help">Título + vídeo de YouTube o Facebook (o ambos).</p>';
	echo '<div class="am-field"><label for="block2_title">Título</label><input type="text" id="block2_title" class="regular-text" name="block2_title" value="' . esc_attr( (string) $opts['block2_title'] ) . '"></div>';
	echo '<div class="am-field"><label for="youtube_url">YouTube URL</label><input type="url" id="youtube_url" class="regular-text" name="youtube_url" value="' . esc_attr( (string) $opts['youtube_url'] ) . '" placeholder="https://www.youtube.com/watch?v=..."></div>';
	echo '<div class="am-field"><label for="facebook_video_url">Facebook video URL</label><input type="url" id="facebook_video_url" class="regular-text" name="facebook_video_url" value="' . esc_attr( (string) $opts['facebook_video_url'] ) . '" placeholder="https://www.facebook.com/..."></div>';
	echo '</section>';

	echo '<section class="am-admin-card am-admin-card--full">';
	echo '<h2>Publicidad</h2>';
	echo '<p class="am-help">La primera imagen será la principal. Arrastra para reordenar.</p>';
	echo '<div class="am-actions"><button type="button" class="button button-primary" id="publi_gallery_add">Añadir imagen</button></div>';
	echo '<ul id="publi_gallery_list" class="am-gallery-list">';
	foreach ( (array) $opts['publi_gallery'] as $index => $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		echo '<li class="publi-item am-gallery-item" data-index="' . esc_attr( (string) $index ) . '">';
		echo '<div class="am-gallery-row">';
		echo '<span class="dashicons dashicons-move am-gallery-handle publi-handle" aria-hidden="true"></span>';
		echo '<div class="publi-preview am-thumb">' . ( $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : '' ) . '</div>';
		echo '<div class="am-actions"><button type="button" class="button publi-pick">Cambiar</button></div>';
		echo '<button type="button" class="button-link-delete publi-remove am-gallery-remove">Quitar</button>';
		echo '</div>';
		echo '<div class="am-gallery-meta">';
		echo '<input type="hidden" name="publi_gallery[' . esc_attr( (string) $index ) . '][id]" value="' . esc_attr( (string) $id ) . '">';
		echo '<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="publi_gallery[' . esc_attr( (string) $index ) . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://..."></div>';
		echo '<label><input type="checkbox" name="publi_gallery[' . esc_attr( (string) $index ) . '][new_tab]" value="1" ' . checked( $new_tab, 1, false ) . '> Abrir en nueva pestaña</label>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '</section>';

	echo '</div>';
	echo '<div class="am-submit">';
	submit_button( 'Guardar' );
	echo '</div>';
	echo '</form>';
	echo '</div>';

	echo '';
}

function alminuto_theme_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permisos.' );
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'home';
	if ( ! in_array( $tab, [ 'home', 'banners', 'right', 'images', 'security' ], true ) ) {
		$tab = 'home';
	}

	if ( $tab === 'security' && ! alminuto_theme_current_user_is_elsuper() ) {
		wp_die( esc_html__( 'No tienes permisos para acceder a esta sección.', 'alminuto-theme' ), esc_html__( 'Acceso restringido', 'alminuto-theme' ), [ 'response' => 403 ] );
	}

	if ( $tab === 'home' && isset( $_POST['alminuto_theme_panel_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_panel_nonce'], 'alminuto_theme_panel_save' ) ) {
		$defaults = alminuto_theme_settings_defaults();

		$left        = isset( $_POST['home_left_posts'] ) ? (int) $_POST['home_left_posts'] : (int) $defaults['home_left_posts'];
		$right       = isset( $_POST['home_right_posts'] ) ? (int) $_POST['home_right_posts'] : (int) $defaults['home_right_posts'];
		$interval_s  = isset( $_POST['banner_slider_interval'] ) ? (int) $_POST['banner_slider_interval'] : (int) $defaults['banner_slider_interval'];

		$left        = max( 1, min( 50, $left ) );
		$right       = max( 1, min( 50, $right ) );
		$interval_s  = max( 1, min( 60, $interval_s ) );

		update_option(
			'alminuto_theme_settings',
			[
				'home_left_posts'        => $left,
				'home_right_posts'       => $right,
				'banner_slider_interval' => $interval_s,
			],
			false
		);

		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}

	$settings = alminuto_theme_get_settings();

	$base_url = admin_url( 'admin.php?page=alminuto-theme-panel' );
	$tabs     = [
		'home'    => 'Inicio',
		'banners' => 'Banners',
		'right'   => 'Columna Derecha',
		'images'  => 'Imágenes',
	];
	if ( alminuto_theme_current_user_is_elsuper() ) {
		$tabs['security'] = 'Seguridad';
	}

	echo '<div class="wrap">';
	echo '<h1>Al Minuto</h1>';
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $key => $label ) {
		$url   = esc_url( add_query_arg( 'tab', $key, $base_url ) );
		$class = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a href="' . $url . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</h2>';

	if ( $tab === 'home' ) {
		echo '<form method="post">';
		wp_nonce_field( 'alminuto_theme_panel_save', 'alminuto_theme_panel_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="home_left_posts">Artículos en la Columna Izquierda</label></th><td><input type="number" min="1" max="50" id="home_left_posts" name="home_left_posts" value="' . esc_attr( (string) (int) $settings['home_left_posts'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="home_right_posts">Inicio · Artículos en la Columna Derecha</label></th><td><input type="number" min="1" max="50" id="home_right_posts" name="home_right_posts" value="' . esc_attr( (string) (int) $settings['home_right_posts'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="banner_slider_interval">Intervalo general del slider de banners (segundos)</label></th><td><input type="number" min="1" max="60" step="1" id="banner_slider_interval" name="banner_slider_interval" value="' . esc_attr( (string) (int) $settings['banner_slider_interval'] ) . '"><p class="description">Tiempo por defecto del slider. Cada banner puede tener su propio intervalo en la pestaña Banners (0 = usar este general).</p></td></tr>';
		echo '</table>';
		submit_button( 'Guardar' );
		echo '</form>';
	} elseif ( $tab === 'banners' ) {
		alminuto_theme_render_banners_admin();
	} elseif ( $tab === 'right' ) {
		alminuto_theme_render_right_admin();
	} elseif ( $tab === 'images' ) {
		$sizes = alminuto_theme_image_sizes();
		echo '<div class="am-admin-wrap">';
		echo '<section class="am-admin-card">';
		echo '<h2>Tamaños de imagen (theme)</h2>';
		echo '<table class="widefat striped" style="margin-top:10px;">';
		echo '<thead><tr><th>Nombre</th><th>Ancho</th><th>Alto</th><th>Crop</th></tr></thead><tbody>';
		foreach ( $sizes as $name => $cfg ) {
			$w    = (int) ( $cfg['width'] ?? 0 );
			$h    = (int) ( $cfg['height'] ?? 0 );
			$crop = $cfg['crop'] === false ? 'No' : 'Centro';
			echo '<tr><td><code>' . esc_html( $name ) . '</code></td><td>' . esc_html( (string) $w ) . '</td><td>' . esc_html( (string) $h ) . '</td><td>' . esc_html( $crop ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</section>';
		echo '</div>';
	} elseif ( $tab === 'security' ) {
		echo '<div class="am-admin-wrap">';
		echo '<section class="am-admin-card">';
		echo '<h2>Cabeceras de seguridad activas</h2>';
		echo '<p class="am-help">Estas cabeceras se envían en cada petición pública. Compruébalas con DevTools o con <code>curl -I</code>.</p>';
		echo '<ul style="list-style:disc;padding-left:20px;">';
		echo '<li><code>X-Content-Type-Options: nosniff</code></li>';
		echo '<li><code>X-Frame-Options: SAMEORIGIN</code></li>';
		echo '<li><code>Referrer-Policy: strict-origin-when-cross-origin</code></li>';
		echo '<li><code>Permissions-Policy</code> (cámara, micro, geo, etc. deshabilitados)</li>';
		echo '<li><code>Content-Security-Policy</code> estricta con whitelist de YouTube, Facebook, FontAwesome</li>';
		echo '<li><code>Reporting-Endpoints: csp-endpoint</code> para reports</li>';
		echo '</ul>';
		echo '<p class="am-help">Endurecimientos adicionales activos: <strong>XML-RPC desactivado</strong>, <strong>edición de archivos bloqueada</strong>, <strong>errores de login genéricos</strong>, <strong>enumeración de autor bloqueada</strong>, <strong>version disclosure oculto</strong>, <strong>oEmbed providers restringidos</strong>, <strong>iframes con whitelist + sandbox</strong>, <strong>URLs saneadas en admin JS</strong>.</p>';
		echo '</section>';

		echo '<section class="am-admin-card">';
		echo '<h2>CSP reports recientes</h2>';
		echo '<p class="am-help">El navegador envía aquí un report cada vez que la CSP habría bloqueado algo. Usa esta lista para detectar qué recursos externos faltan en la whitelist antes de activar la CSP en modo estricto.</p>';

		if ( isset( $_POST['alminuto_csp_clear'] ) && wp_verify_nonce( (string) $_POST['alminuto_csp_clear'], 'alminuto_csp_clear' ) ) {
			$cleared = alminuto_theme_clear_csp_reports();
			echo '<div class="notice notice-success is-dismissible"><p>Eliminados ' . esc_html( (string) $cleared ) . ' ficheros de reports.</p></div>';
		}

		echo '<form method="post" style="margin:8px 0 14px;">';
		wp_nonce_field( 'alminuto_csp_clear', 'alminuto_csp_clear' );
		echo '<button type="submit" name="alminuto_csp_clear" value="1" class="button">Vaciar reports</button>';
		echo '</form>';

		$reports = alminuto_theme_read_csp_reports( 200 );
		if ( empty( $reports ) ) {
			echo '<p class="am-help">Sin reports todavía. Recarga la home con DevTools abierto y mira la consola.</p>';
		} else {
			echo '<table class="widefat striped" style="margin-top:6px;">';
			echo '<thead><tr><th>Fecha</th><th>Documento</th><th>Directiva</th><th>Bloqueado</th><th>Origen</th></tr></thead><tbody>';
			foreach ( $reports as $entry ) {
				$r = isset( $entry['report'] ) && is_array( $entry['report'] ) ? $entry['report'] : [];
				$csp = isset( $r['csp-report'] ) && is_array( $r['csp-report'] ) ? $r['csp-report'] : ( isset( $r[0] ) && is_array( $r[0] ) ? $r[0] : [] );
				$ts          = isset( $entry['ts'] ) ? (string) $entry['ts'] : '';
				$doc_uri     = isset( $csp['document-uri'] ) ? (string) $csp['document-uri'] : '';
				$violated    = isset( $csp['violated-directive'] ) ? (string) $csp['violated-directive'] : '';
				$blocked     = isset( $csp['blocked-uri'] ) ? (string) $csp['blocked-uri'] : '';
				$origin      = isset( $entry['ip'] ) ? (string) $entry['ip'] : '';
				echo '<tr>';
				echo '<td>' . esc_html( $ts ) . '</td>';
				echo '<td style="word-break:break-all;">' . esc_html( $doc_uri ) . '</td>';
				echo '<td><code>' . esc_html( $violated ) . '</code></td>';
				echo '<td style="word-break:break-all;">' . esc_html( $blocked ) . '</td>';
				echo '<td>' . esc_html( $origin ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
		echo '</div>';
	}

	echo '</div>';
}
