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
	const LIVE_OPTION    = 'alminuto_backup_live_status';
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
	 * Returns the live status of an in-progress (or just-finished) backup.
	 *
	 * @return array
	 */
	public static function get_live_status() {
		$is_locked = (bool) get_transient( self::LOCK_TRANSIENT );
		$live      = get_option( self::LIVE_OPTION, [] );
		if ( ! is_array( $live ) ) {
			$live = [];
		}
		return [
			'running'    => $is_locked && ! empty( $live['running'] ),
			'phase'      => (string) ( $live['phase'] ?? '' ),
			'percent'    => (int) ( $live['percent'] ?? 0 ),
			'started_at' => (int) ( $live['started_at'] ?? 0 ),
			'status'     => (string) ( $live['status'] ?? '' ),
			'message'    => (string) ( $live['message'] ?? '' ),
			'logs'       => isset( $live['logs'] ) && is_array( $live['logs'] ) ? $live['logs'] : [],
		];
	}

	/**
	 * Persists live progress for real-time UI polling.
	 *
	 * @param array $data Live status fields.
	 * @return void
	 */
	private static function update_live_status( array $data ) {
		update_option( self::LIVE_OPTION, $data, false );
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
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
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
		$live_phase  = __( 'Iniciando copia...', 'alminuto-theme' );
		$live_pct    = 5;

		$sync_live = static function ( $running = true, $status = '', $message = '' ) use ( &$logs, &$live_phase, &$live_pct, $started_at ) {
			self::update_live_status(
				[
					'running'    => $running,
					'phase'      => $live_phase,
					'percent'    => $live_pct,
					'started_at' => $started_at,
					'status'     => $status,
					'message'    => $message,
					'logs'       => $logs,
				]
			);
		};

		$log_cb = static function ( $msg ) use ( &$logs, $sync_live ) {
			$logs[] = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . $msg;
			$sync_live( true );
		};

		$sync_live( true );

		try {
			if ( ! $include_db && ! $include_media ) {
				throw new RuntimeException( __( 'Debes seleccionar al menos BBDD o Media para respaldar.', 'alminuto-theme' ) );
			}

			if ( ! Alminuto_Backup_GDrive::is_connected() ) {
				throw new RuntimeException( __( 'Google Drive no está conectado.', 'alminuto-theme' ) );
			}

			if ( $include_db ) {
				$use_gz       = function_exists( 'gzopen' );
				$ext          = $use_gz ? 'sql.gz' : 'sql';
				$db_name      = sprintf( '%s-%s-db.%s', $site_slug, $stamp, $ext );
				$db_path      = $temp_dir . '/' . $db_name;
				$temp_files[] = $db_path;

				$live_phase = __( 'Exportando base de datos...', 'alminuto-theme' );
				$live_pct   = 15;
				$log_cb( 'Exportando base de datos...' );
				$db_bytes = self::export_database( $db_path, $use_gz );
				$live_pct = 30;
				$log_cb( sprintf( 'BBDD exportada (%s).', size_format( $db_bytes ) ) );

				$live_phase  = __( 'Subiendo BBDD a Google Drive...', 'alminuto-theme' );
				$progress_db = static function ( $sent, $total ) use ( &$live_phase, &$live_pct, $sync_live ) {
					$ratio      = $total > 0 ? ( $sent / $total ) : 1;
					$live_pct   = (int) round( 30 + ( $ratio * 15 ) );
					$live_phase = sprintf( 'Subiendo BBDD a Google Drive (%d%%)...', (int) round( $ratio * 100 ) );
					$sync_live( true );
				};

				$mime     = $use_gz ? 'application/gzip' : 'application/sql';
				$uploaded = Alminuto_Backup_GDrive::upload_file( $db_path, $db_name, $mime, $backup_id, $log_cb, $progress_db );
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
				$media_name   = sprintf( '%s-%s-media.zip', $site_slug, $stamp );
				$media_path   = $temp_dir . '/' . $media_name;
				$temp_files[] = $media_path;

				$live_phase     = __( 'Empaquetando carpeta de medios (uploads)...', 'alminuto-theme' );
				$live_pct       = 50;
				$log_cb( 'Empaquetando carpeta de medios (uploads)...' );
				$pack_progress = static function ( $count ) use ( &$live_phase, &$live_pct, $sync_live ) {
					$live_phase = sprintf( 'Empaquetando media (%d archivos)...', (int) $count );
					$live_pct   = min( 64, 50 + (int) floor( $count / 1000 ) );
					$sync_live( true );
				};
				$media_bytes = self::export_media( $media_path, $pack_progress );
				$live_pct    = 65;
				$log_cb( sprintf( 'Media empaquetada (%s).', size_format( $media_bytes ) ) );

				$live_phase     = __( 'Subiendo Media a Google Drive...', 'alminuto-theme' );
				$progress_media = static function ( $sent, $total ) use ( &$live_phase, &$live_pct, $sync_live ) {
					$ratio      = $total > 0 ? ( $sent / $total ) : 1;
					$live_pct   = (int) round( 65 + ( $ratio * 30 ) );
					$live_phase = sprintf(
						'Subiendo Media a Google Drive (%d%% · %s / %s)...',
						(int) round( $ratio * 100 ),
						size_format( $sent ),
						size_format( $total )
					);
					$sync_live( true );
				};

				$uploaded = Alminuto_Backup_GDrive::upload_file( $media_path, $media_name, 'application/zip', $backup_id, $log_cb, $progress_media );
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
				$live_phase = __( 'Aplicando retención en Google Drive...', 'alminuto-theme' );
				$live_pct   = 96;
				$sync_live( true );
				Alminuto_Backup_GDrive::enforce_retention( $retention, $log_cb );
			}

			$finished_at = time();
			$live_phase  = __( 'Copia completada', 'alminuto-theme' );
			$live_pct    = 100;
			$result      = [
				'backup_id'     => $backup_id,
				'status'        => 'success',
				'trigger'       => $trigger,
				'started_at'    => $started_at,
				'finished_at'   => $finished_at,
				'duration'      => max( 1, $finished_at - $started_at ),
				'include_db'    => $include_db,
				'include_media' => $include_media,
				'db_bytes'      => $db_bytes,
				'media_bytes'   => $media_bytes,
				'total_bytes'   => $total_bytes,
				'files'         => $drive_files,
				'message'       => __( 'Copia completada y subida a Google Drive.', 'alminuto-theme' ),
				'logs'          => $logs,
			];
			$sync_live( false, 'success', $result['message'] );
		} catch ( Throwable $e ) {
			$finished_at = time();
			$live_phase  = __( 'Error en la copia', 'alminuto-theme' );
			$logs[]      = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ERROR: ' . $e->getMessage();
			$result      = [
				'backup_id'     => $backup_id,
				'status'        => 'error',
				'trigger'       => $trigger,
				'started_at'    => $started_at,
				'finished_at'   => $finished_at,
				'duration'      => max( 1, $finished_at - $started_at ),
				'include_db'    => $include_db,
				'include_media' => $include_media,
				'db_bytes'      => $db_bytes,
				'media_bytes'   => $media_bytes,
				'total_bytes'   => $total_bytes,
				'files'         => $drive_files,
				'message'       => $e->getMessage(),
				'logs'          => $logs,
			];
			$sync_live( false, 'error', $e->getMessage() );
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
	 * Flushes the ZipArchive every 400 files to avoid OS open-file-descriptor limits
	 * (ulimit -n) on large media libraries, and stores already-compressed media without
	 * re-deflating for speed.
	 *
	 * @param string        $dest_zip_path Destination ZIP file path.
	 * @param callable|null $progress_cb   Optional callback( int $file_count ).
	 * @return int Final ZIP file size in bytes.
	 * @throws RuntimeException If ZipArchive is unavailable or fails.
	 */
	public static function export_media( $dest_zip_path, $progress_cb = null ) {
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
			throw new RuntimeException(
				sprintf( 'No se pudo crear el archivo ZIP de media: %s', $zip->getStatusString() )
			);
		}

		$excluded_dirs  = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$store_exts     = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mov', 'm4v', 'mp3', 'ogg', 'pdf', 'zip', 'gz' ];
		$can_set_comp   = method_exists( $zip, 'setCompressionName' ) && defined( 'ZipArchive::CM_STORE' );
		$base_len       = strlen( $base_dir ) + 1;
		$file_count     = 0;
		$batch_count    = 0;
		$batch_limit    = 400;
		$closed_cleanly = false;

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
					if ( $zip->addFile( $full_path, $rel_path ) ) {
						if ( $can_set_comp ) {
							$ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
							if ( in_array( $ext, $store_exts, true ) ) {
								$zip->setCompressionName( $rel_path, ZipArchive::CM_STORE );
							}
						}
						$file_count++;
						$batch_count++;

						if ( $batch_count >= $batch_limit ) {
							if ( ! $zip->close() ) {
								throw new RuntimeException(
									sprintf( 'Error cerrando lote ZIP (%d archivos): %s', $file_count, $zip->getStatusString() )
								);
							}
							if ( $zip->open( $dest_zip_path, ZipArchive::CREATE ) !== true ) {
								throw new RuntimeException(
									sprintf( 'Error reabriendo ZIP (%d archivos): %s', $file_count, $zip->getStatusString() )
								);
							}
							$batch_count = 0;
							if ( is_callable( $progress_cb ) ) {
								call_user_func( $progress_cb, $file_count );
							}
						}
					}
				}
			}

			if ( $file_count === 0 ) {
				$zip->addFromString( '.empty', '' );
			}

			if ( ! $zip->close() ) {
				throw new RuntimeException(
					sprintf( 'Error finalizando archivo ZIP de media: %s', $zip->getStatusString() )
				);
			}
			$closed_cleanly = true;
		} finally {
			if ( ! $closed_cleanly ) {
				@$zip->close();
			}
		}

		clearstatcache( true, $dest_zip_path );
		$size = filesize( $dest_zip_path );
		if ( $size === false || $size <= 0 ) {
			throw new RuntimeException( __( 'El archivo ZIP de media resultó vacío.', 'alminuto-theme' ) );
		}
		return $size;
	}

	/**
	 * Restores a backup from Google Drive by its backup_id in history.
	 *
	 * @param string $backup_id Backup identifier from history.
	 * @return array Result summary.
	 */
	public static function restore( $backup_id ) {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Ya hay una copia o restauración en curso.', 'alminuto-theme' ),
			];
		}

		$history = self::get_history();
		$target  = null;
		foreach ( $history as $entry ) {
			if ( isset( $entry['backup_id'] ) && (string) $entry['backup_id'] === (string) $backup_id ) {
				$target = $entry;
				break;
			}
		}

		if ( ! $target || empty( $target['files'] ) || ! is_array( $target['files'] ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'No se encontraron archivos asociados a esa copia en el historial.', 'alminuto-theme' ),
			];
		}

		set_transient( self::LOCK_TRANSIENT, time(), 30 * MINUTE_IN_SECONDS );

		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( 0 );
		}
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}

		$started_at = time();
		$temp_dir   = self::get_backup_dir();
		$temp_files = [];
		$logs       = [];
		$live_phase = __( 'Iniciando restauración...', 'alminuto-theme' );
		$live_pct   = 5;

		$sync_live = static function ( $running = true, $status = '', $message = '' ) use ( &$logs, &$live_phase, &$live_pct, $started_at ) {
			self::update_live_status(
				[
					'running'    => $running,
					'phase'      => $live_phase,
					'percent'    => $live_pct,
					'started_at' => $started_at,
					'status'     => $status,
					'message'    => $message,
					'logs'       => $logs,
				]
			);
		};

		$log_cb = static function ( $msg ) use ( &$logs, $sync_live ) {
			$logs[] = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . $msg;
			$sync_live( true );
		};

		$sync_live( true );

		try {
			if ( ! Alminuto_Backup_GDrive::is_connected() ) {
				throw new RuntimeException( __( 'Google Drive no está conectado.', 'alminuto-theme' ) );
			}

			$db_file    = null;
			$media_file = null;
			foreach ( $target['files'] as $f ) {
				if ( ( $f['type'] ?? '' ) === 'db' && ! empty( $f['id'] ) ) {
					$db_file = $f;
				} elseif ( ( $f['type'] ?? '' ) === 'media' && ! empty( $f['id'] ) ) {
					$media_file = $f;
				}
			}

			if ( ! $db_file && ! $media_file ) {
				throw new RuntimeException( __( 'La copia seleccionada no contiene IDs de Google Drive válidos.', 'alminuto-theme' ) );
			}

			if ( $media_file ) {
				$media_local  = $temp_dir . '/restore-' . basename( (string) $media_file['name'] );
				$temp_files[] = $media_local;

				$live_phase = __( 'Descargando Media desde Google Drive...', 'alminuto-theme' );
				$live_pct   = 15;
				$log_cb( sprintf( 'Descargando Media (%s)...', $media_file['name'] ) );

				$dl = Alminuto_Backup_GDrive::download_file( $media_file['id'], $media_local, $log_cb );
				if ( is_wp_error( $dl ) ) {
					throw new RuntimeException( $dl->get_error_message() );
				}

				$live_phase = __( 'Restaurando archivos de Media (uploads)...', 'alminuto-theme' );
				$live_pct   = 35;
				$log_cb( 'Extrayendo archivos de Media en wp-content/uploads...' );
				$extracted = self::restore_media( $media_local );
				$live_pct  = 55;
				$log_cb( sprintf( 'Media restaurada (%d archivos).', $extracted ) );
			}

			if ( $db_file ) {
				$db_local     = $temp_dir . '/restore-' . basename( (string) $db_file['name'] );
				$temp_files[] = $db_local;

				$live_phase = __( 'Descargando BBDD desde Google Drive...', 'alminuto-theme' );
				$live_pct   = 65;
				$log_cb( sprintf( 'Descargando BBDD (%s)...', $db_file['name'] ) );

				$dl = Alminuto_Backup_GDrive::download_file( $db_file['id'], $db_local, $log_cb );
				if ( is_wp_error( $dl ) ) {
					throw new RuntimeException( $dl->get_error_message() );
				}

				$live_phase = __( 'Restaurando base de datos...', 'alminuto-theme' );
				$live_pct   = 80;
				$log_cb( 'Importando volcado SQL en la base de datos...' );
				$queries = self::restore_database( $db_local );
				$live_pct = 98;
				$log_cb( sprintf( 'BBDD restaurada (%d sentencias ejecutadas).', $queries ) );
			}

			$live_phase = __( 'Restauración completada', 'alminuto-theme' );
			$live_pct   = 100;
			$msg        = __( 'Copia restaurada correctamente.', 'alminuto-theme' );
			$sync_live( false, 'success', $msg );

			return [
				'status'  => 'success',
				'message' => $msg,
				'logs'    => $logs,
			];
		} catch ( Throwable $e ) {
			$live_phase = __( 'Error en la restauración', 'alminuto-theme' );
			$logs[]     = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ERROR: ' . $e->getMessage();
			$sync_live( false, 'error', $e->getMessage() );

			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
				'logs'    => $logs,
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
	}

	/**
	 * Imports a SQL or gzipped SQL dump into the current WordPress database.
	 *
	 * @param string $sql_path Path to .sql or .sql.gz file.
	 * @return int Number of SQL statements executed.
	 * @throws RuntimeException On read or SQL failure.
	 */
	public static function restore_database( $sql_path ) {
		global $wpdb;

		$is_gz = substr( $sql_path, -3 ) === '.gz';
		if ( $is_gz && ! function_exists( 'gzopen' ) ) {
			throw new RuntimeException( __( 'La extensión zlib no está disponible para descomprimir la BBDD.', 'alminuto-theme' ) );
		}

		$handle = $is_gz ? gzopen( $sql_path, 'rb' ) : fopen( $sql_path, 'rb' );
		if ( ! $handle ) {
			throw new RuntimeException( __( 'No se pudo abrir el archivo de BBDD para restaurar.', 'alminuto-theme' ) );
		}

		$executed = 0;
		$buffer   = '';

		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0;' );

			while ( true ) {
				$eof = $is_gz ? gzeof( $handle ) : feof( $handle );
				if ( $eof ) {
					break;
				}
				$line = $is_gz ? gzgets( $handle ) : fgets( $handle );
				if ( $line === false ) {
					break;
				}

				$trimmed = trim( $line );
				if ( $buffer === '' && ( $trimmed === '' || strpos( $trimmed, '--' ) === 0 || strpos( $trimmed, '/*' ) === 0 ) ) {
					continue;
				}

				$buffer .= $line;
				if ( substr( rtrim( $line ), -1 ) === ';' ) {
					$stmt   = trim( $buffer );
					$buffer = '';
					if ( $stmt !== '' ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
						$ok = $wpdb->query( $stmt );
						if ( $ok === false && $wpdb->last_error !== '' ) {
							throw new RuntimeException( sprintf( 'Error SQL al restaurar: %s', $wpdb->last_error ) );
						}
						$executed++;
					}
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1;' );
		} finally {
			if ( $is_gz ) {
				gzclose( $handle );
			} else {
				fclose( $handle );
			}
		}

		return $executed;
	}

	/**
	 * Extracts a media ZIP archive into wp-content/uploads safely.
	 *
	 * @param string $zip_path Path to the media ZIP file.
	 * @return int Number of extracted files.
	 * @throws RuntimeException If ZipArchive fails.
	 */
	public static function restore_media( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( __( 'La extensión PHP ZipArchive no está disponible en el servidor.', 'alminuto-theme' ) );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			throw new RuntimeException( __( 'No se pudo abrir el archivo ZIP de media para restaurar.', 'alminuto-theme' ) );
		}

		$upload        = wp_upload_dir();
		$base_dir      = wp_normalize_path( untrailingslashit( (string) $upload['basedir'] ) );
		$excluded_dirs = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$extracted     = 0;

		try {
			$total = $zip->numFiles;
			for ( $i = 0; $i < $total; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( ! is_string( $name ) || $name === '' || $name === '.empty' ) {
					continue;
				}
				$norm = wp_normalize_path( ltrim( $name, '/' ) );
				if ( strpos( $norm, '../' ) !== false || strpos( $norm, '..\\' ) !== false || $norm === '..' ) {
					continue;
				}

				$first_segment = strtok( $norm, '/' );
				if ( in_array( $first_segment, $excluded_dirs, true ) ) {
					continue;
				}

				$dest = $base_dir . '/' . $norm;
				if ( substr( $norm, -1 ) === '/' ) {
					wp_mkdir_p( $dest );
					continue;
				}

				wp_mkdir_p( dirname( $dest ) );
				$stream = $zip->getStream( $name );
				if ( ! $stream ) {
					continue;
				}
				$out = fopen( $dest, 'wb' );
				if ( $out ) {
					stream_copy_to_stream( $stream, $out );
					fclose( $out );
					$extracted++;
				}
				fclose( $stream );
			}
		} finally {
			$zip->close();
		}

		return $extracted;
	}
}
