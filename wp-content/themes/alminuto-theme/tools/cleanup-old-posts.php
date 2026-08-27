<?php
/**
 * Cleanup tool: delete old posts and their attachments, rewriting internal
 * links as plain text in any post that referenced them.
 *
 * Usage (from WordPress root):
 *
 *   # Dry-run: count and sample only
 *   wp eval-file wp-content/themes/alminuto-theme/tools/cleanup-old-posts.php -- --dry-run
 *
 *   # Real run: delete posts older than 180 days
 *   wp eval-file wp-content/themes/alminuto-theme/tools/cleanup-old-posts.php -- --before-days=180
 *
 *   # Real run: explicit date range (overrides --before-days)
 *   wp eval-file wp-content/themes/alminuto-theme/tools/cleanup-old-posts.php -- --start=2024-01-01 --end=2024-12-31
 *
 *   # Custom batch size (default 100)
 *   wp eval-file wp-content/themes/alminuto-theme/tools/cleanup-old-posts.php -- --before-days=180 --batch=50
 *
 * Flags:
 *   --dry-run         Count only. No writes.
 *   --before-days=N   Posts older than N days will be deleted. Min 30.
 *   --start=YYYY-MM-DD  Inclusive start of custom range.
 *   --end=YYYY-MM-DD    Inclusive end of custom range.
 *   --batch=N         Posts per batch (default 100).
 *
 * Sticky posts are always excluded.
 *
 * @package alminuto-theme
 */

// This file is meant to be invoked via `wp eval-file`. WordPress is already
// loaded and the theme is active, so alminuto_theme_cleanup_old_posts() is
// available.

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must be run via WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

// Parse --key=value flags from $argv (skip the script path at index 0).
$flags = array();
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( strpos( $arg, '--' ) !== 0 ) {
		continue;
	}
	$body = substr( $arg, 2 );
	if ( strpos( $body, '=' ) !== false ) {
		list( $k, $v ) = explode( '=', $body, 2 );
		$flags[ $k ] = $v;
	} else {
		$flags[ $body ] = true;
	}
}

if ( ! function_exists( 'alminuto_theme_cleanup_old_posts' ) ) {
	fwrite( STDERR, "alminuto_theme_cleanup_old_posts() not found. Is the alminuto-theme active?\n" );
	exit( 1 );
}

$args = array(
	'dry_run' => isset( $flags['dry-run'] ) || isset( $flags['dry_run'] ),
);

// CLI: allow before_days < 30 (admin panel clamps it; CLI is for the
// developer who's already considered the risk).
if ( isset( $flags['before-days'] ) ) {
	$args['before_days'] = max( 1, (int) $flags['before-days'] );
}
if ( isset( $flags['start'] ) ) {
	$args['start_date'] = $flags['start'];
}
if ( isset( $flags['end'] ) ) {
	$args['end_date'] = $flags['end'];
}
if ( isset( $flags['batch'] ) ) {
	$args['batch_size'] = max( 1, (int) $flags['batch'] );
}

$args['log_callback'] = static function ( $msg ) {
	echo '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n";
	flush();
};

echo "=== alminuto-theme cleanup tool ===\n";
echo "Mode:        " . ( $args['dry_run'] ? 'DRY-RUN (no writes)' : 'REAL (will delete posts)' ) . "\n";
echo "Before days: " . ( $args['start_date'] ? 'N/A (custom range)' : ( $args['before_days'] ?? 30 ) ) . "\n";
echo "Start date:  " . ( $args['start_date'] ?? '(none)' ) . "\n";
echo "End date:    " . ( $args['end_date'] ?? '(none)' ) . "\n";
echo "Batch size:  " . ( $args['batch_size'] ?? 100 ) . "\n";
echo "Sticky excl: yes (always)\n";
echo "\n";

if ( ! $args['dry_run'] ) {
	echo "Proceeding with real deletion in 2 seconds. Ctrl-C to abort.\n";
	sleep( 2 );
}

$started = microtime( true );
$result  = alminuto_theme_cleanup_old_posts( $args );
$elapsed = round( microtime( true ) - $started, 2 );

echo "\n=== Summary ===\n";
echo "Elapsed:                " . $elapsed . "s\n";
echo "Posts to delete:        " . $result['posts_to_delete'] . "\n";
echo "Posts deleted:          " . $result['posts_deleted'] . "\n";
echo "Attachments deleted:    " . $result['attachments_deleted'] . "\n";
echo "Internal links rewritten: " . $result['links_rewritten'] . "\n";

if ( $args['dry_run'] ) {
	echo "\nPosts that would lose internal links: " . $result['affected_link_count'] . "\n";
	if ( ! empty( $result['sample_titles'] ) ) {
		echo "\nSample of posts to delete (first 20):\n";
		foreach ( $result['sample_titles'] as $title ) {
			echo "  - " . $title . "\n";
		}
	}
}
