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

	const LAST_OPTION      = 'alminuto_backup_last';
	const HISTORY_OPTION   = 'alminuto_backup_history';
	const LIVE_OPTION      = 'alminuto_backup_live_status';
	const LOCK_TRANSIENT   = 'alminuto_backup_running_lock';
	const CANCEL_TRANSIENT   = 'alminuto_backup_cancel_flag';
	const MAX_HISTORY        = 20;
	const MEDIA_VOLUME_BYTES = 419430400; // 400 MB per TAR volume.

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
	 * Requests cancellation of an active backup and clears any stale lock.
	 *
	 * @return array Updated live status.
	 */
	public static function request_cancel() {
		set_transient( self::CANCEL_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );
		delete_transient( self::LOCK_TRANSIENT );

		$live           = self::get_live_status();
		$logs           = isset( $live['logs'] ) && is_array( $live['logs'] ) ? $live['logs'] : [];
		$logs[]         = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . __( 'Copia cancelada / desbloqueada por el usuario.', 'alminuto-theme' );
		$updated_status = [
			'running'    => false,
			'phase'      => __( 'Copia cancelada', 'alminuto-theme' ),
			'percent'    => (int) ( $live['percent'] ?? 0 ),
			'started_at' => (int) ( $live['started_at'] ?? 0 ),
			'status'     => 'error',
			'message'    => __( 'Copia cancelada por el usuario.', 'alminuto-theme' ),
			'logs'       => $logs,
		];
		self::update_live_status( $updated_status );
		return $updated_status;
	}

	/**
	 * Checks whether cancellation was requested for the current run.
	 *
	 * @return bool
	 */
	public static function is_cancel_requested() {
		return (bool) get_transient( self::CANCEL_TRANSIENT );
	}

	/**
	 * Throws if cancellation was requested.
	 *
	 * @return void
	 * @throws RuntimeException When cancelled.
	 */
	public static function check_cancelled() {
		if ( self::is_cancel_requested() ) {
			throw new RuntimeException( __( 'Copia cancelada por el usuario.', 'alminuto-theme' ) );
		}
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

		delete_transient( self::CANCEL_TRANSIENT );
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
				$media_prefix = sprintf( '%s-%s-media', $site_slug, $stamp );

				$live_phase = __( 'Inventariando carpeta de medios (uploads)...', 'alminuto-theme' );
				$live_pct   = 48;
				$sync_live( true );
				$inventory       = self::inventory_media();
				$total_files     = (int) $inventory['count'];
				$inventory_bytes = max( 1, (int) $inventory['bytes'] );
				$log_cb(
					sprintf(
						'Inventario de media: %d archivos (%s). Se dividirán en volúmenes de hasta 400 MB.',
						$total_files,
						size_format( (int) $inventory['bytes'] )
					)
				);

				$packed_so_far   = 0;
				$uploaded_so_far = 0;

				$update_media_pct = static function () use ( &$packed_so_far, &$uploaded_so_far, $inventory_bytes, &$live_pct ) {
					$ratio    = min( 1, ( $packed_so_far + $uploaded_so_far ) / ( 2 * $inventory_bytes ) );
					$live_pct = 50 + (int) round( $ratio * 45 );
				};

				$pack_progress = static function ( $count, $part_index, $total_packed ) use ( &$live_phase, &$packed_so_far, $total_files, $update_media_pct, $sync_live ) {
					$packed_so_far = (int) $total_packed;
					$update_media_pct();
					$live_phase = sprintf(
						'Empaquetando media parte %d (%d / %d archivos · %s)...',
						(int) $part_index,
						(int) $count,
						$total_files,
						size_format( $packed_so_far )
					);
					$sync_live( true );
				};

				$volume_cb = static function ( $part_path, $part_name, $part_size, $part_index ) use (
					$backup_id,
					$log_cb,
					&$drive_files,
					&$media_bytes,
					&$total_bytes,
					&$uploaded_so_far,
					&$live_phase,
					$update_media_pct,
					$sync_live
				) {
					$log_cb( sprintf( 'Volumen %d listo (%s): %s', (int) $part_index, size_format( $part_size ), $part_name ) );
					$base_uploaded  = $uploaded_so_far;
					$progress_media = static function ( $sent, $total ) use ( &$live_phase, &$uploaded_so_far, $base_uploaded, $part_index, $update_media_pct, $sync_live ) {
						$uploaded_so_far = $base_uploaded + (int) $sent;
						$update_media_pct();
						$ratio      = $total > 0 ? ( $sent / $total ) : 1;
						$live_phase = sprintf(
							'Subiendo parte %d a Drive (%d%% · %s / %s)...',
							(int) $part_index,
							(int) round( $ratio * 100 ),
							size_format( $sent ),
							size_format( $total )
						);
						$sync_live( true );
					};

					try {
						$uploaded = Alminuto_Backup_GDrive::upload_file( $part_path, $part_name, 'application/x-tar', $backup_id, $log_cb, $progress_media );
						if ( is_wp_error( $uploaded ) ) {
							throw new RuntimeException( $uploaded->get_error_message() );
						}
						$uploaded_so_far = $base_uploaded + (int) $part_size;
						$drive_files[]   = [
							'type' => 'media',
							'name' => $part_name,
							'id'   => (string) ( $uploaded['id'] ?? '' ),
							'url'  => (string) ( $uploaded['webViewLink'] ?? '' ),
							'size' => $part_size,
						];
						$media_bytes += $part_size;
						$total_bytes += $part_size;
					} finally {
						if ( file_exists( $part_path ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
							@unlink( $part_path );
						}
					}
				};

				self::export_media_volumes( $temp_dir, $media_prefix, $volume_cb, $pack_progress );
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
			delete_transient( self::CANCEL_TRANSIENT );
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
	 * Counts eligible media files and total bytes in wp-content/uploads before archiving.
	 *
	 * @return array{count:int, bytes:int}
	 */
	public static function inventory_media() {
		$upload   = wp_upload_dir();
		$base_dir = wp_normalize_path( untrailingslashit( (string) $upload['basedir'] ) );
		if ( ! is_dir( $base_dir ) ) {
			return [ 'count' => 0, 'bytes' => 0 ];
		}

		$excluded_dirs = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$base_len      = strlen( $base_dir ) + 1;
		$count         = 0;
		$bytes         = 0;

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
			if ( $item->isFile() && $item->isReadable() ) {
				$count++;
				$bytes += (int) $item->getSize();
				if ( $count % 2000 === 0 ) {
					self::check_cancelled();
				}
			}
		}

		return [
			'count' => $count,
			'bytes' => $bytes,
		];
	}

	/**
	 * Packages wp-content/uploads into streaming USTAR/PAX TAR volumes (max ~400 MB each),
	 * invoking $volume_cb after each volume so it can be uploaded and deleted before the next.
	 *
	 * @param string        $temp_dir    Temporary directory for volumes.
	 * @param string        $prefix      Filename prefix (e.g. site-date-media).
	 * @param callable      $volume_cb   Callback( string $path, string $name, int $size, int $part_index ).
	 * @param callable|null $progress_cb Optional callback( int $file_count, int $part_index, int $total_packed ).
	 * @return int Total bytes across all volumes.
	 * @throws RuntimeException If writing fails.
	 */
	public static function export_media_volumes( $temp_dir, $prefix, $volume_cb, $progress_cb = null ) {
		$upload   = wp_upload_dir();
		$base_dir = wp_normalize_path( untrailingslashit( (string) $upload['basedir'] ) );
		if ( ! is_dir( $base_dir ) ) {
			throw new RuntimeException( __( 'No existe el directorio de medios (uploads).', 'alminuto-theme' ) );
		}

		$excluded_dirs = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$base_len      = strlen( $base_dir ) + 1;
		$file_count    = 0;
		$total_packed  = 0;
		$part_index    = 1;
		$part_bytes    = 0;
		$tar           = null;
		$part_path     = '';
		$part_name     = '';

		$open_part = static function () use ( &$tar, &$part_path, &$part_name, &$part_bytes, &$part_index, $temp_dir, $prefix ) {
			$part_name  = sprintf( '%s-part%02d.tar', $prefix, $part_index );
			$part_path  = trailingslashit( $temp_dir ) . $part_name;
			$part_bytes = 0;
			$tar        = fopen( $part_path, 'wb' );
			if ( ! $tar ) {
				throw new RuntimeException( sprintf( 'No se pudo crear el volumen TAR: %s', $part_name ) );
			}
		};

		$write_all = static function ( $data ) use ( &$tar, &$part_path, &$part_bytes, &$total_packed, &$file_count ) {
			$len = strlen( $data );
			$res = @fwrite( $tar, $data );
			if ( $res === false || $res !== $len ) {
				$last_err = error_get_last();
				$os_msg   = is_array( $last_err ) && ! empty( $last_err['message'] ) ? $last_err['message'] : 'fwrite failed';
				$free     = function_exists( 'disk_free_space' ) ? @disk_free_space( dirname( $part_path ) ) : false;
				$free_str = $free !== false ? size_format( (int) $free ) : 'desconocido';
				throw new RuntimeException(
					sprintf(
						'Error escribiendo en el volumen TAR tras %d archivos (%s en volumen, libre en disco: %s): %s',
						$file_count,
						size_format( $part_bytes ),
						$free_str,
						$os_msg
					)
				);
			}
			$part_bytes   += $len;
			$total_packed += $len;
		};

		$close_and_flush_part = static function () use ( &$tar, &$part_path, &$part_name, &$part_index, $write_all, $volume_cb ) {
			if ( ! $tar ) {
				return;
			}
			$write_all( str_repeat( "\0", 1024 ) );
			fclose( $tar );
			$tar = null;

			clearstatcache( true, $part_path );
			$final_size = filesize( $part_path );
			if ( $final_size === false || $final_size <= 0 ) {
				throw new RuntimeException( sprintf( 'El volumen TAR resultó vacío: %s', $part_name ) );
			}

			call_user_func( $volume_cb, $part_path, $part_name, $final_size, $part_index );
			$part_index++;
		};

		$open_part();

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

				if ( ! $item->isFile() || ! $item->isReadable() ) {
					continue;
				}

				$size  = (int) $item->getSize();
				$mtime = (int) $item->getMTime();

				if ( $part_bytes > 0 && ( $part_bytes + $size + 2048 ) > self::MEDIA_VOLUME_BYTES ) {
					$close_and_flush_part();
					$open_part();
				}

				if ( strlen( $rel_path ) > 100 ) {
					$pax_record = 'path=' . $rel_path . "\n";
					$len        = strlen( $pax_record ) + 1;
					for ( $k = 0; $k < 4; $k++ ) {
						$total_len = $len + strlen( (string) $len );
						if ( strlen( (string) $total_len ) === strlen( (string) $len ) ) {
							$len = $total_len;
							break;
						}
						$len = $total_len;
					}
					$pax_payload = $len . ' ' . $pax_record;
					$write_all( self::build_tar_header( 'PaxHeader/' . substr( $basename, 0, 80 ), strlen( $pax_payload ), $mtime, 'x' ) );
					$write_all( $pax_payload );
					$rem = strlen( $pax_payload ) % 512;
					if ( $rem > 0 ) {
						$write_all( str_repeat( "\0", 512 - $rem ) );
					}
					$write_all( self::build_tar_header( substr( $rel_path, 0, 100 ), $size, $mtime, '0' ) );
				} else {
					$write_all( self::build_tar_header( $rel_path, $size, $mtime, '0' ) );
				}

				if ( $size > 0 ) {
					$in = fopen( $full_path, 'rb' );
					if ( ! $in ) {
						throw new RuntimeException( sprintf( 'No se pudo leer el archivo: %s', $rel_path ) );
					}
					$copied = 0;
					try {
						while ( ! feof( $in ) && $copied < $size ) {
							$chunk = fread( $in, min( 65536, $size - $copied ) );
							if ( $chunk === false || $chunk === '' ) {
								break;
							}
							$write_all( $chunk );
							$copied += strlen( $chunk );
						}
					} finally {
						fclose( $in );
					}
					if ( $copied < $size ) {
						$write_all( str_repeat( "\0", $size - $copied ) );
					}
					$rem = $size % 512;
					if ( $rem > 0 ) {
						$write_all( str_repeat( "\0", 512 - $rem ) );
					}
				}

				$file_count++;
				if ( $file_count % 500 === 0 ) {
					self::check_cancelled();
				}
				if ( is_callable( $progress_cb ) && ( $file_count % 1000 === 0 ) ) {
					call_user_func( $progress_cb, $file_count, $part_index, $total_packed );
				}
			}

			$close_and_flush_part();
		} finally {
			if ( $tar ) {
				fclose( $tar );
			}
			if ( $part_path !== '' && file_exists( $part_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $part_path );
			}
		}

		return $total_packed;
	}

	/**
	 * Builds a 512-byte USTAR TAR header block.
	 *
	 * @param string $name     Entry name (<= 100 bytes).
	 * @param int    $size     File size in bytes.
	 * @param int    $mtime    Modification timestamp.
	 * @param string $typeflag '0' for regular file, 'x' for PAX extended header.
	 * @return string 512-byte header.
	 */
	private static function build_tar_header( $name, $size, $mtime, $typeflag = '0' ) {
		$header = pack(
			'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
			$name,
			sprintf( '%07o', 0644 ),
			sprintf( '%07o', 0 ),
			sprintf( '%07o', 0 ),
			sprintf( '%011o', $size ),
			sprintf( '%011o', $mtime ),
			'        ',
			$typeflag,
			'',
			'ustar',
			'00',
			'',
			'',
			sprintf( '%07o', 0 ),
			sprintf( '%07o', 0 ),
			'',
			''
		);

		$checksum = 0;
		for ( $i = 0; $i < 512; $i++ ) {
			$checksum += ord( $header[ $i ] );
		}

		return substr_replace( $header, sprintf( '%06o', $checksum ) . "\0 ", 148, 8 );
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

		delete_transient( self::CANCEL_TRANSIENT );
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

			$db_file     = null;
			$media_files = [];
			foreach ( $target['files'] as $f ) {
				if ( ( $f['type'] ?? '' ) === 'db' && ! empty( $f['id'] ) ) {
					$db_file = $f;
				} elseif ( ( $f['type'] ?? '' ) === 'media' && ! empty( $f['id'] ) ) {
					$media_files[] = $f;
				}
			}

			if ( ! $db_file && empty( $media_files ) ) {
				throw new RuntimeException( __( 'La copia seleccionada no contiene IDs de Google Drive válidos.', 'alminuto-theme' ) );
			}

			$total_parts = count( $media_files );
			foreach ( $media_files as $idx => $media_file ) {
				$part_num     = $idx + 1;
				$media_local  = $temp_dir . '/restore-' . basename( (string) $media_file['name'] );
				$temp_files[] = $media_local;

				$live_phase = sprintf( 'Descargando Media (%d/%d) desde Google Drive...', $part_num, $total_parts );
				$live_pct   = 10 + (int) round( ( $idx / max( 1, $total_parts ) ) * 45 );
				$log_cb( sprintf( 'Descargando Media %d/%d (%s)...', $part_num, $total_parts, $media_file['name'] ) );

				$dl = Alminuto_Backup_GDrive::download_file( $media_file['id'], $media_local, $log_cb );
				if ( is_wp_error( $dl ) ) {
					throw new RuntimeException( $dl->get_error_message() );
				}

				$live_phase = sprintf( 'Restaurando Media (%d/%d)...', $part_num, $total_parts );
				$log_cb( sprintf( 'Extrayendo archivos de Media (%d/%d) en wp-content/uploads...', $part_num, $total_parts ) );
				$extracted = self::restore_media( $media_local );
				$log_cb( sprintf( 'Parte %d/%d restaurada (%d archivos).', $part_num, $total_parts, $extracted ) );

				if ( file_exists( $media_local ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $media_local );
				}
			}
			$live_pct = 55;

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
			delete_transient( self::CANCEL_TRANSIENT );
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
	 * Extracts a media TAR or ZIP archive into wp-content/uploads safely.
	 *
	 * @param string $archive_path Path to the media .tar or .zip file.
	 * @return int Number of extracted files.
	 * @throws RuntimeException If extraction fails.
	 */
	public static function restore_media( $archive_path ) {
		if ( substr( strtolower( $archive_path ), -4 ) === '.tar' ) {
			return self::restore_tar_media( $archive_path );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( __( 'La extensión PHP ZipArchive no está disponible en el servidor.', 'alminuto-theme' ) );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $archive_path ) !== true ) {
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

	/**
	 * Stream-extracts a USTAR/PAX TAR archive into wp-content/uploads safely.
	 *
	 * @param string $tar_path Path to the .tar archive.
	 * @return int Number of extracted files.
	 * @throws RuntimeException On read error.
	 */
	private static function restore_tar_media( $tar_path ) {
		$tar = fopen( $tar_path, 'rb' );
		if ( ! $tar ) {
			throw new RuntimeException( __( 'No se pudo abrir el archivo TAR de media.', 'alminuto-theme' ) );
		}

		$upload        = wp_upload_dir();
		$base_dir      = wp_normalize_path( untrailingslashit( (string) $upload['basedir'] ) );
		$excluded_dirs = [ 'alminuto-backups', 'alminuto-csp-reports' ];
		$extracted     = 0;
		$pax_path      = null;

		try {
			while ( ! feof( $tar ) ) {
				$header = fread( $tar, 512 );
				if ( $header === false || strlen( $header ) < 512 ) {
					break;
				}
				if ( trim( $header, "\0" ) === '' ) {
					break;
				}

				$name     = rtrim( substr( $header, 0, 100 ), "\0" );
				$size     = octdec( trim( substr( $header, 124, 12 ), "\0 " ) );
				$typeflag = substr( $header, 156, 1 );
				$prefix   = rtrim( substr( $header, 345, 155 ), "\0" );
				if ( $prefix !== '' ) {
					$name = $prefix . '/' . $name;
				}

				if ( $typeflag === 'x' ) {
					$pax_data = $size > 0 ? fread( $tar, $size ) : '';
					$rem      = $size % 512;
					if ( $rem > 0 ) {
						fread( $tar, 512 - $rem );
					}
					if ( is_string( $pax_data ) && preg_match( '/^\d+ path=(.+)$/m', $pax_data, $m ) ) {
						$pax_path = trim( $m[1] );
					}
					continue;
				}

				$entry_name = $pax_path !== null ? $pax_path : $name;
				$pax_path   = null;
				$norm       = wp_normalize_path( ltrim( $entry_name, '/' ) );
				$skip       = $norm === ''
					|| strpos( $norm, '../' ) !== false
					|| strpos( $norm, '..\\' ) !== false
					|| $norm === '..'
					|| in_array( strtok( $norm, '/' ), $excluded_dirs, true );

				if ( $typeflag === '5' ) {
					if ( ! $skip ) {
						wp_mkdir_p( $base_dir . '/' . $norm );
					}
					continue;
				}

				if ( $typeflag !== '0' && $typeflag !== '' && $typeflag !== "\0" ) {
					if ( $size > 0 ) {
						$blocks = (int) ceil( $size / 512 );
						fseek( $tar, $blocks * 512, SEEK_CUR );
					}
					continue;
				}

				if ( $skip ) {
					if ( $size > 0 ) {
						$blocks = (int) ceil( $size / 512 );
						fseek( $tar, $blocks * 512, SEEK_CUR );
					}
					continue;
				}

				$dest = $base_dir . '/' . $norm;
				wp_mkdir_p( dirname( $dest ) );
				$out = fopen( $dest, 'wb' );
				$rem_bytes = $size;
				while ( $rem_bytes > 0 ) {
					$chunk = fread( $tar, min( 65536, $rem_bytes ) );
					if ( $chunk === false || $chunk === '' ) {
						break;
					}
					if ( $out ) {
						fwrite( $out, $chunk );
					}
					$rem_bytes -= strlen( $chunk );
				}
				if ( $out ) {
					fclose( $out );
					$extracted++;
				}

				$pad = $size % 512;
				if ( $pad > 0 ) {
					fread( $tar, 512 - $pad );
				}
			}
		} finally {
			fclose( $tar );
		}

		return $extracted;
	}
}
