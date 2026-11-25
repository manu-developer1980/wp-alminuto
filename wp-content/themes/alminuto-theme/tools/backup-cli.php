<?php
/**
 * Registers a WP-CLI command for alminuto-theme Google Drive backups.
 *
 * Usage:
 *   wp alminuto-backup
 *   wp alminuto-backup --no-media
 *   wp alminuto-backup --no-db
 *
 * @package alminuto-theme
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'Alminuto_Backup_Engine' ) ) {
	WP_CLI::error( 'Alminuto_Backup_Engine is not available.' );
}

/**
 * Runs a database and/or media backup and uploads it to Google Drive.
 *
 * ## OPTIONS
 *
 * [--no-db]
 * : Skip database backup.
 *
 * [--no-media]
 * : Skip uploads/media backup.
 *
 * @when after_wp_load
 */
function alminuto_theme_backup_command( $args, $assoc_args ) {
	$include_db    = ! isset( $assoc_args['no-db'] );
	$include_media = ! isset( $assoc_args['no-media'] );

	WP_CLI::log( 'Starting AlMinuto backup...' );

	$result = Alminuto_Backup_Engine::run(
		[
			'include_db'    => $include_db,
			'include_media' => $include_media,
			'trigger'       => 'cli',
		]
	);

	if ( ! empty( $result['logs'] ) ) {
		foreach ( $result['logs'] as $line ) {
			WP_CLI::log( $line );
		}
	}

	if ( isset( $result['status'] ) && $result['status'] === 'success' ) {
		WP_CLI::success( sprintf( 'Backup complete (%s, %ds).', size_format( (int) $result['total_bytes'] ), (int) $result['duration'] ) );
	} else {
		WP_CLI::error( isset( $result['message'] ) ? $result['message'] : 'Backup failed.' );
	}
}

WP_CLI::add_command( 'alminuto-backup', 'alminuto_theme_backup_command' );
