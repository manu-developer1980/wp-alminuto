<?php
/**
 * Registers a WP-CLI command for the alminuto-theme cleanup tool.
 *
 * Usage:
 *
 *   wp alminuto cleanup --dry-run
 *   wp alminuto cleanup --before-days=180
 *   wp alminuto cleanup --start=2020-01-01 --end=2020-06-30
 *   wp alminuto cleanup --before-days=180 --batch=50
 *
 * Run `wp help alminuto cleanup` for full docs.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'alminuto_theme_cleanup_old_posts' ) ) {
	WP_CLI::error( 'alminuto_theme_cleanup_old_posts() is not available. Is the alminuto-theme active?' );
}

/**
 * Cleans up old posts and their attachments, rewriting internal links as
 * plain text in any post that referenced them.
 *
 * ## OPTIONS
 *
 * [--dry-run]
 * : Count and sample only. No writes.
 *
 * [--before-days=<days>]
 * : Delete posts older than N days. Ignored if --start is given. Min 30 via
 *   the admin panel; CLI allows lower values for developer use.
 *
 * [--start=<date>]
 * : Inclusive start of a custom date range (YYYY-MM-DD). Overrides --before-days.
 *
 * [--end=<date>]
 * : Inclusive end of a custom date range (YYYY-MM-DD).
 *
 * [--batch=<size>]
 * : Posts per batch (default 100). Lower this if you hit PHP timeouts.
 *
 * [--yes]
 * : Skip the confirmation prompt for real runs.
 *
 * ## EXAMPLES
 *
 *     wp alminuto cleanup --dry-run
 *     wp alminuto cleanup --before-days=180
 *     wp alminuto cleanup --start=2020-01-01 --end=2020-06-30
 *     wp alminuto cleanup --before-days=365 --batch=50
 *
 * @when after_wp_load
 * @subcommand cleanup
 */
function alminuto_theme_cleanup_command( $args, $assoc_args ) {
	$dry_run = isset( $assoc_args['dry-run'] );

	$cleanup_args = array(
		'dry_run' => $dry_run,
	);

	if ( isset( $assoc_args['before-days'] ) ) {
		$cleanup_args['before_days'] = max( 1, (int) $assoc_args['before-days'] );
	}
	if ( isset( $assoc_args['start'] ) ) {
		$cleanup_args['start_date'] = (string) $assoc_args['start'];
	}
	if ( isset( $assoc_args['end'] ) ) {
		$cleanup_args['end_date'] = (string) $assoc_args['end'];
	}
	if ( isset( $assoc_args['batch'] ) ) {
		$cleanup_args['batch_size'] = max( 1, (int) $assoc_args['batch'] );
	}

	$cleanup_args['log_callback'] = static function ( $msg ) {
		WP_CLI::log( $msg );
	};

	WP_CLI::log( '=== alminuto-theme cleanup tool ===' );
	WP_CLI::log( 'Mode:        ' . ( $dry_run ? 'DRY-RUN (no writes)' : 'REAL (will delete posts)' ) );
	WP_CLI::log( 'Before days: ' . ( ! empty( $cleanup_args['start_date'] ) ? 'N/A (custom range)' : ( $cleanup_args['before_days'] ?? 30 ) ) );
	WP_CLI::log( 'Start date:  ' . ( $cleanup_args['start_date'] ?? '(none)' ) );
	WP_CLI::log( 'End date:    ' . ( $cleanup_args['end_date'] ?? '(none)' ) );
	WP_CLI::log( 'Batch size:  ' . ( $cleanup_args['batch_size'] ?? 100 ) );
	WP_CLI::log( 'Sticky excl: yes (always)' );
	WP_CLI::log( '' );

	if ( ! $dry_run && ! isset( $assoc_args['yes'] ) ) {
		WP_CLI::confirm( 'Proceed with real deletion? Ctrl-C to abort.' );
	}

	$started = microtime( true );
	$result  = alminuto_theme_cleanup_old_posts( $cleanup_args );
	$elapsed = round( microtime( true ) - $started, 2 );

	WP_CLI::log( '' );
	WP_CLI::log( '=== Summary ===' );
	WP_CLI::log( 'Elapsed:                  ' . $elapsed . 's' );
	WP_CLI::log( 'Posts to delete:          ' . $result['posts_to_delete'] );
	WP_CLI::log( 'Posts deleted:            ' . $result['posts_deleted'] );
	WP_CLI::log( 'Attachments to delete:    ' . $result['attachments_to_delete'] );
	WP_CLI::log( 'Attachments deleted:      ' . $result['attachments_deleted'] );
	WP_CLI::log( 'Internal links rewritten: ' . $result['links_rewritten'] );

	if ( $dry_run ) {
		WP_CLI::log( 'Posts that would lose internal links: ' . $result['affected_link_count'] );
		if ( ! empty( $result['sample_titles'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Sample of posts to delete (first 20):' );
			foreach ( $result['sample_titles'] as $title ) {
				WP_CLI::log( '  - ' . $title );
			}
		}
		if ( ! empty( $result['sample_attachments'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Sample of attachments to delete (first 20):' );
			foreach ( $result['sample_attachments'] as $att ) {
				$parent = $att['parent_title'] !== '' ? $att['parent_title'] : '(no parent title)';
				WP_CLI::log( sprintf( '  - #%d  %s  (parent: %s)', $att['id'], $att['filename'], $parent ) );
			}
			$remaining = $result['attachments_to_delete'] - count( $result['sample_attachments'] );
			if ( $remaining > 0 ) {
				WP_CLI::log( sprintf( '  ... and %d more attachments not shown.', $remaining ) );
			}
		}
	}
}

WP_CLI::add_command( 'alminuto', 'alminuto_theme_cleanup_command' );
