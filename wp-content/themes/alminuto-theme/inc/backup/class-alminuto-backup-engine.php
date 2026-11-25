<?php
/**
 * Database & Media backup engine for alminuto-theme.
 *
 * Exports the WordPress database to a gzipped SQL dump and packages wp-content/uploads
 * into a ZIP archive, uploads them to Google Drive, and cleans up local temp files.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alminuto_Backup_Engine {

	const LAST_OPTION    = 'alminuto_backup_last';
	const HISTORY_OPTION = 'alminuto_backup_history';
	const LOCK_TRANSIENT = 'alminuto_backup_running_lock';
	const MAX_HISTORY    = 20;

	/**
	 * Returns (and secures) the temporary local backup directory inside uploads.
	 *
	 * @return string
	 */
	public static function get_backup_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'alminuto-backups';
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

	/**
	 * Returns info about the last backup run, or null if none yet.
	 *
	 * @return array|null
	 */
	public static function get_last_backup() {
		$last = get_option( self::LAST_OPTION, null );
		return is_array( $last ) ? $last : null;
	}

	/**
	 * Returns the recent backup history list (newest first).
	 *
	 * @return array
	 */
	public static function get_history() {
		$history = get_option( self::HISTORY_OPTION, [] );
		return is_array( $history ) ? $history : [];
	}

	/**
	 * Runs a full or partial backup and uploads to Google Drive.
	 *
	 * @param array $args {
	 *     Optional arguments.
	 *     @type bool   $include_db    Whether to back up the database.
	 *     @type bool   $include_media Whether to back up wp-content/uploads.
	 *     @type int    $retention     Max backup runs to keep in Drive (0 = unlimited).
	 *     @type string $trigger       'manual', 'cron', or 'cli'.
	 * }
	 * @return array Summary of the backup run.
	 */
	public static function run( array $args = [] ) {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Ya hay una copia de seguridad en ejecución.', 'alminuto-theme' ),
			];
		}

		set_transient( self::LOCK_TRANSIENT, time(), 30 * MINUTE_IN_SECONDS );

		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( 0 );
		}

		$settings      = Alminuto_Backup_Scheduler::get_settings();
		$include_db    = isset( $args['include_db'] ) ? (bool) $args['include_db'] : (bool) $settings['include_db'];
		$include_media = isset( $args['include_media'] ) ? (bool) $args['include_media'] : (bool) $settings['include_media'];
		$retention     = isset( $args['retention'] ) ? (int) $args['retention'] : (int) $settings['retention'];
		$trigger       = isset( $args['trigger'] ) ? (string) $args['trigger'] : 'manual';

		$started_at  = time();
		$backup_id   = gmdate( 'Ymd-His', $started_at ) . '-' . substr( wp_hash( (string) $started_at ), 0, 6 );
		$local_tz    = wp_timezone();
		$dt          = new DateTimeImmutable( '@' . $started_at );
		$stamp       = $dt->setTimezone( $local_tz )->format( 'Y-m-d_H-i-s' );
		$site_slug   = sanitize_title( parse_url( home_url(), PHP_URL_HOST ) ?: 'site' );
		$temp_dir    = self::get_backup_dir();
		$temp_files  = [];
		$logs        = [];
		$drive_files = [];
		$total_bytes = 0;
		$db_bytes    = 0;
		$media_bytes = 0;

		$log_cb = static function ( $msg ) use ( &$logs ) {
			$logs[] = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . $msg;
		};

		try {
			if ( ! $include_db && ! $include_media ) {
				throw new RuntimeException( __( 'Debes seleccionar al menos BBDD o Media para respaldar.', 'alminuto-theme' ) );
			}

			if ( ! Alminuto_Backup_GDrive::is_connected() ) {
				throw new RuntimeException( __( 'Google Drive no está conectado.', 'alminuto-theme' ) );
			}

			if ( $include_db ) {
				$use_gz   = function_exists( 'gzopen' );
				$ext      = $use_gz ? 'sql.gz' : 'sql';
				$db_name  = sprintf( '%s-%s-db.%s', $site_slug, $stamp, $ext );
				$db_path  = $temp_dir . '/' . $db_name;
				$temp_files[] = $db_path;

				$log_cb( 'Exportando base de datos...' );
				$db_bytes = self::export_database( $db_path, $use_gz );
				$log_cb( sprintf( 'BBDD exportada (%s).', size_format( $db_bytes ) ) );

				$mime     = $use_gz ? 'application/gzip' : 'application/sql';
				$uploaded = Alminuto_Backup_GDrive::upload_file( $db_path, $db_name, $mime, $backup_id, $log_cb );
				if ( is_wp_error( $uploaded ) ) {
					throw new RuntimeException( $uploaded->get_error_message() );
				}
				$drive_files[] = [
					'type' => 'db',
					'name' => $db_name,
					'id'   => (string) ( $uploaded['id'] ?? '' ),
					'url'  => (string) ( $uploaded['webViewLink'] ?? '' ),
					'size' => $db_bytes,
				];
				$total_bytes += $db_bytes;
			}

			if ( $include_media ) {
				$media_name = sprintf( '%s-%s-media.zip', $site_slug, $stamp );
				$media_path = $temp_dir . '/' . $media_name;
				$temp_files[] = $media_path;

				$log_cb( 'Empaquetando carpeta de medios (uploads)...' );
				$media_bytes = self::export_media( $media_path );
				$log_cb( sprintf( 'Media empaquetada (%s).', size_format( $media_bytes ) ) );

				$uploaded = Alminuto_Backup_GDrive::upload_file( $media_path, $media_name, 'application/zip', $backup_id, $log_cb );
				if ( is_wp_error( $uploaded ) ) {
					throw new RuntimeException( $uploaded->get_error_message() );
				}
				$drive_files[] = [
					'type' => 'media',
					'name' => $media_name,
					'id'   => (string) ( $uploaded['id'] ?? '' ),
					'url'  => (string) ( $uploaded['webViewLink'] ?? '' ),
					'size' => $media_bytes,
				];
				$total_bytes += $media_bytes;
			}

			if ( $retention > 0 ) {
				Alminuto_Backup_GDrive::enforce_retention( $retention, $log_cb );
			}

			$finished_at = time();
			$result      = [
				'backup_id'   => $backup_id,
				'status'      => 'success',
				'trigger'     => $trigger,
				'started_at'  => $started_at,
				'finished_at' => $finished_at,
				'duration'    => max( 1, $finished_at - $started_at ),
				'include_db'  => $include_db,
				'include_media' => $include_media,
				'db_bytes'    => $db_bytes,
				'media_bytes' => $media_bytes,
				'total_bytes' => $total_bytes,
				'files'       => $drive_files,
				'message'     => __( 'Copia completada y subida a Google Drive.', 'alminuto-theme' ),
				'logs'        => $logs,
			];
		} catch ( Throwable $e ) {
			$finished_at = time();
			$log_cb( 'ERROR: ' . $e->getMessage() );
			$result = [
				'backup_id'   => $backup_id,
				'status'      => 'error',
				'trigger'     => $trigger,
				'started_at'  => $started_at,
				'finished_at' => $finished_at,
				'duration'    => max( 1, $finished_at - $started_at ),
				'include_db'  => $include_db,
				'include_media' => $include_media,
				'db_bytes'    => $db_bytes,
				'media_bytes' => $media_bytes,
				'total_bytes' => $total_bytes,
				'files'       => $drive_files,
				'message'     => $e->getMessage(),
				'logs'        => $logs,
			];
		} finally {
			foreach ( $temp_files as $file ) {
				if ( file_exists( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $file );
				}
			}
			delete_transient( self::LOCK_TRANSIENT );
		}

		update_option( self::LAST_OPTION, $result, false );

		$history = self::get_history();
		array_unshift( $history, $result );
		$history = array_slice( $history, 0, self::MAX_HISTORY );
		update_option( self::HISTORY_OPTION, $history, false );

		return $result;
	}

	/**
	 * Exports all tables in the current WordPress database to a SQL (or gzipped SQL) file.
	 *
	 * @param string $dest_path Destination file path.
	 * @param bool   $gzip      Whether to gzip-compress the output.
	 * @return int Final file size in bytes.
	 * @throws RuntimeException On write or query failure.
	 */
	public static function export_database( $dest_path, $gzip = true ) {
		global $wpdb;

		$writer = $gzip ? gzopen( $dest_path, 'wb6' ) : fopen( $dest_path, 'wb' );
		if ( ! $writer ) {
			throw new RuntimeException( __( 'No se pudo crear el archivo temporal de BBDD.', 'alminuto-theme' ) );
		}

		$write = static function ( $str ) use ( $writer, $gzip ) {
			if ( $gzip ) {
				gzwrite( $writer, $str );
			} else {
				fwrite( $writer, $str );
			}
		};

		try {
			$write( "-- AlMinuto Theme Database Backup\n" );
			$write( '-- Generated: ' . gmdate( 'c' ) . "\n" );
			$write( "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables = $wpdb->get_col( 'SHOW TABLES' );
			if ( ! is_array( $tables ) || empty( $tables ) ) {
				throw new RuntimeException( __( 'No se encontraron tablas en la base de datos.', 'alminuto-theme' ) );
			}

			$batch_size = 500;

			foreach ( $tables as $table ) {
				$safe_table = str_replace( '`', '``', (string) $table );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
				$create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$safe_table}`", ARRAY_N );
				if ( empty( $create_row[1] ) ) {
					continue;
				}

				$write( "DROP TABLE IF EXISTS `{$safe_table}`;\n" );
				$write( $create_row[1] . ";\n\n" );

				$offset = 0;
				while ( true ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
					$rows = $wpdb->get_results(
						$wpdb->prepare( "SELECT * FROM `{$safe_table}` LIMIT %d, %d", $offset, $batch_size ),
						ARRAY_A
					);
					if ( empty( $rows ) ) {
						break;
					}

					$columns     = array_keys( $rows[0] );
					$col_list    = '`' . implode( '`, `', array_map( static function ( $c ) {
						return str_replace( '`', '``', (string) $c );
					}, $columns ) ) . '`';
					$values_sql  = [];

					foreach ( $rows as $row ) {
						$vals = [];
						foreach ( $columns as $col ) {
							$val = $row[ $col ];
							if ( $val === null ) {
								$vals[] = 'NULL';
							} else {
								$vals[] = "'" . $wpdb->_real_escape( (string) $val ) . "'";
							}
						}
						$values_sql[] = '(' . implode( ', ', $vals ) . ')';
					}

					$write( "INSERT INTO `{$safe_table}` ({$col_list}) VALUES\n" . implode( ",\n", $values_sql ) . ";\n" );
					$offset += $batch_size;

					if ( count( $rows ) < $batch_size ) {
						break;
					}
				}
				$write( "\n" );
			}

			$write( "SET FOREIGN_KEY_CHECKS = 1;\n" );
		} finally {
			if ( $gzip ) {
				gzclose( $writer );
			} else {
				fclose( $writer );
			}
		}

		clearstatcache( true, $dest_path );
		$size = filesize( $dest_path );
		if ( $size === false || $size <= 0 ) {
			throw new RuntimeException( __( 'El volcado de BBDD resultó vacío.', 'alminuto-theme' ) );
		}
		return $size;
	}

	/**
	 * Packages wp-content/uploads into a ZIP archive, excluding backup & CSP temp folders.
	 *
	 * @param string $dest_zip_path Destination ZIP file path.
	 * @return int Final ZIP file size in bytes.
	 * @throws RuntimeException If ZipArchive is unavailable or fails.
	 */
	public static function export_media( $dest_zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( __( 'La extensión PHP ZipArchive no está disponible en el servidor.', 'alminuto-theme' ) );
		}

		$upload   = wp_upload_dir();
		$base_dir = wp_normalize_path( untrailingslashit( (string) $upload['basedir'] ) );
		if ( ! is_dir( $base_dir ) ) {
			throw new RuntimeException( __( 'No existe el directorio de medios (uploads).', 'alminuto-theme' ) );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $dest_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			throw new RuntimeException( __( 'No se pudo crear el archivo ZIP de media.', 'alminuto-theme' ) );
		}

		$excluded_dirs = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$base_len      = strlen( $base_dir ) + 1;
		$file_count    = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				$full_path = wp_normalize_path( $item->getPathname() );
				$rel_path  = substr( $full_path, $base_len );
				if ( $rel_path === false || $rel_path === '' ) {
					continue;
				}

				$first_segment = strtok( $rel_path, '/' );
				if ( in_array( $first_segment, $excluded_dirs, true ) ) {
					continue;
				}

				$basename = $item->getFilename();
				if ( $basename === '.DS_Store' || $basename === 'Thumbs.db' ) {
					continue;
				}

				if ( $item->isDir() ) {
					$zip->addEmptyDir( $rel_path );
				} elseif ( $item->isFile() && $item->isReadable() ) {
					$zip->addFile( $full_path, $rel_path );
					$file_count++;
				}
			}

			if ( $file_count === 0 ) {
				$zip->addFromString( '.empty', '' );
			}
		} finally {
			$zip->close();
		}

		clearstatcache( true, $dest_zip_path );
		$size = filesize( $dest_zip_path );
		if ( $size === false || $size <= 0 ) {
			throw new RuntimeException( __( 'El archivo ZIP de media resultó vacío.', 'alminuto-theme' ) );
		}
		return $size;
	}
}
