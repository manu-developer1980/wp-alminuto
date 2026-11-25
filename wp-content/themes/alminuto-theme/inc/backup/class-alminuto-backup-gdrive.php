<?php
/**
 * Google Drive API v3 client (OAuth 2.0 + Resumable Upload) in pure PHP / WordPress HTTP API.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alminuto_Backup_GDrive {

	const OPTION_KEY = 'alminuto_backup_gdrive';
	const CHUNK_SIZE = 4194304; // 4 MB (must be a multiple of 256 KB for Google Drive resumable uploads).

	/**
	 * Returns stored Google Drive settings merged with defaults and wp-config constants.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = [
			'client_id'       => '',
			'client_secret'   => '',
			'folder_id'       => '',
			'access_token'    => '',
			'refresh_token'   => '',
			'expires_at'      => 0,
			'connected_email' => '',
		];
		$raw = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$merged = array_merge( $defaults, $raw );

		if ( defined( 'ALMINUTO_GDRIVE_CLIENT_ID' ) && ALMINUTO_GDRIVE_CLIENT_ID !== '' ) {
			$merged['client_id'] = (string) ALMINUTO_GDRIVE_CLIENT_ID;
		}
		if ( defined( 'ALMINUTO_GDRIVE_CLIENT_SECRET' ) && ALMINUTO_GDRIVE_CLIENT_SECRET !== '' ) {
			$merged['client_secret'] = (string) ALMINUTO_GDRIVE_CLIENT_SECRET;
		}
		if ( defined( 'ALMINUTO_GDRIVE_FOLDER_ID' ) && ALMINUTO_GDRIVE_FOLDER_ID !== '' ) {
			$merged['folder_id'] = (string) ALMINUTO_GDRIVE_FOLDER_ID;
		}

		return $merged;
	}

	/**
	 * Checks whether Client ID and Secret are provided via wp-config.php constants.
	 *
	 * @return bool
	 */
	public static function has_config_constants() {
		return defined( 'ALMINUTO_GDRIVE_CLIENT_ID' )
			&& ALMINUTO_GDRIVE_CLIENT_ID !== ''
			&& defined( 'ALMINUTO_GDRIVE_CLIENT_SECRET' )
			&& ALMINUTO_GDRIVE_CLIENT_SECRET !== '';
	}

	/**
	 * Returns the default Google Drive folder name based on the site's domain.
	 *
	 * @return string
	 */
	public static function get_default_folder_name() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && $host !== '' ? $host : 'AlMinuto Backups';
	}

	/**
	 * Saves Google Drive settings.
	 *
	 * @param array $data Partial or full settings array.
	 * @return void
	 */
	public static function save_settings( array $data ) {
		$current = self::get_settings();
		$merged  = array_merge( $current, $data );
		update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * Checks whether a refresh token is stored.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		$settings = self::get_settings();
		return $settings['client_id'] !== ''
			&& $settings['client_secret'] !== ''
			&& $settings['refresh_token'] !== '';
	}

	/**
	 * Returns the OAuth 2.0 Redirect URI for Google Cloud Console.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin-post.php?action=alminuto_backup_oauth' );
	}

	/**
	 * Builds the Google OAuth 2.0 authorization URL.
	 *
	 * @param string $state Nonce/state token.
	 * @return string
	 */
	public static function get_auth_url( $state ) {
		$settings = self::get_settings();
		$params   = [
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => self::get_redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'https://www.googleapis.com/auth/drive',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Exchanges an OAuth authorization code for access and refresh tokens.
	 *
	 * @param string $code Authorization code from Google.
	 * @return array|WP_Error
	 */
	public static function exchange_code( $code ) {
		$settings = self::get_settings();
		if ( $settings['client_id'] === '' || $settings['client_secret'] === '' ) {
			return new WP_Error( 'missing_credentials', __( 'Faltan Client ID o Client Secret.', 'alminuto-theme' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			[
				'timeout' => 30,
				'body'    => [
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['error_description'] )
				? $body['error_description']
				: ( is_array( $body ) && ! empty( $body['error'] ) ? $body['error'] : 'HTTP ' . $status );
			return new WP_Error( 'oauth_exchange_failed', $msg );
		}

		$update = [
			'access_token' => (string) $body['access_token'],
			'expires_at'   => time() + (int) ( $body['expires_in'] ?? 3600 ),
		];
		if ( ! empty( $body['refresh_token'] ) ) {
			$update['refresh_token'] = (string) $body['refresh_token'];
		}
		self::save_settings( $update );

		$info = self::fetch_user_email( $update['access_token'] );
		if ( is_string( $info ) && $info !== '' ) {
			self::save_settings( [ 'connected_email' => $info ] );
		}

		return $update;
	}

	/**
	 * Disconnects Google Drive by clearing tokens.
	 *
	 * @return void
	 */
	public static function disconnect() {
		self::save_settings(
			[
				'access_token'    => '',
				'refresh_token'   => '',
				'expires_at'      => 0,
				'connected_email' => '',
			]
		);
	}

	/**
	 * Returns a valid access token, refreshing it if expired.
	 *
	 * @return string|WP_Error
	 */
	public static function get_valid_access_token() {
		$settings = self::get_settings();
		if ( ! self::is_connected() ) {
			return new WP_Error( 'not_connected', __( 'Google Drive no está conectado.', 'alminuto-theme' ) );
		}

		if ( $settings['access_token'] !== '' && (int) $settings['expires_at'] > ( time() + 60 ) ) {
			return $settings['access_token'];
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			[
				'timeout' => 30,
				'body'    => [
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $settings['refresh_token'],
					'grant_type'    => 'refresh_token',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['error_description'] )
				? $body['error_description']
				: ( is_array( $body ) && ! empty( $body['error'] ) ? $body['error'] : 'HTTP ' . $status );
			return new WP_Error( 'token_refresh_failed', $msg );
		}

		$token      = (string) $body['access_token'];
		$expires_at = time() + (int) ( $body['expires_in'] ?? 3600 );
		self::save_settings(
			[
				'access_token' => $token,
				'expires_at'   => $expires_at,
			]
		);

		return $token;
	}

	/**
	 * Tests the Google Drive connection and refreshes connected_email.
	 *
	 * @return array|WP_Error
	 */
	public static function test_connection() {
		$token = self::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$email = self::fetch_user_email( $token );
		if ( is_wp_error( $email ) ) {
			return $email;
		}

		self::save_settings( [ 'connected_email' => $email ] );

		return [
			'email'     => $email,
			'folder_id' => self::ensure_folder( $token ),
		];
	}

	/**
	 * Fetches the authenticated Google user's email address.
	 *
	 * @param string $token Access token.
	 * @return string|WP_Error
	 */
	private static function fetch_user_email( $token ) {
		$response = wp_remote_get(
			'https://www.googleapis.com/drive/v3/about?fields=user(emailAddress,displayName)',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'drive_about_failed', 'HTTP ' . $status );
		}

		return isset( $body['user']['emailAddress'] ) ? (string) $body['user']['emailAddress'] : '';
	}

	/**
	 * Ensures a destination folder exists in Google Drive and returns its ID.
	 *
	 * @param string $token Access token.
	 * @return string|WP_Error
	 */
	public static function ensure_folder( $token ) {
		$settings = self::get_settings();
		if ( $settings['folder_id'] !== '' ) {
			return $settings['folder_id'];
		}

		$folder_name = self::get_default_folder_name();
		$query       = "name = '" . str_replace( "'", "\\'", $folder_name ) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
		$search_url  = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			[
				'q'        => $query,
				'fields'   => 'files(id,name)',
				'pageSize' => 1,
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		$search = wp_remote_get(
			$search_url,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( ! is_wp_error( $search ) && wp_remote_retrieve_response_code( $search ) === 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $search ), true );
			if ( ! empty( $data['files'][0]['id'] ) ) {
				$folder_id = (string) $data['files'][0]['id'];
				self::save_settings( [ 'folder_id' => $folder_id ] );
				return $folder_id;
			}
		}

		$create = wp_remote_post(
			'https://www.googleapis.com/drive/v3/files?fields=id',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=UTF-8',
				],
				'body'    => wp_json_encode(
					[
						'name'     => $folder_name,
						'mimeType' => 'application/vnd.google-apps.folder',
					]
				),
			]
		);

		if ( is_wp_error( $create ) ) {
			return $create;
		}

		$status = wp_remote_retrieve_response_code( $create );
		$body   = json_decode( wp_remote_retrieve_body( $create ), true );
		if ( $status < 200 || $status >= 300 || empty( $body['id'] ) ) {
			return new WP_Error( 'drive_folder_create_failed', 'HTTP ' . $status );
		}

		$folder_id = (string) $body['id'];
		self::save_settings( [ 'folder_id' => $folder_id ] );
		return $folder_id;
	}

	/**
	 * Uploads a local file to Google Drive using a chunked resumable upload session.
	 *
	 * @param string        $local_path  Absolute path to local file.
	 * @param string        $remote_name File name in Google Drive.
	 * @param string        $mime_type   MIME type.
	 * @param string        $backup_id   Shared backup run identifier for retention grouping.
	 * @param callable|null $log_cb      Optional logger callback.
	 * @param callable|null $progress_cb Optional progress callback( int $sent_bytes, int $total_bytes ).
	 * @return array|WP_Error File metadata (id, name, size, webViewLink).
	 */
	public static function upload_file( $local_path, $remote_name, $mime_type, $backup_id, $log_cb = null, $progress_cb = null ) {
		if ( ! file_exists( $local_path ) || ! is_readable( $local_path ) ) {
			return new WP_Error( 'file_missing', sprintf( 'Archivo local no encontrado: %s', $local_path ) );
		}

		$token = self::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$folder_id = self::ensure_folder( $token );
		if ( is_wp_error( $folder_id ) ) {
			return $folder_id;
		}

		$file_size = filesize( $local_path );
		if ( $file_size === false || $file_size <= 0 ) {
			return new WP_Error( 'empty_file', sprintf( 'El archivo está vacío: %s', $remote_name ) );
		}

		$metadata = [
			'name'          => $remote_name,
			'parents'       => [ $folder_id ],
			'appProperties' => [
				'alminuto_backup' => '1',
				'backup_id'       => (string) $backup_id,
			],
		];

		$init_res = wp_remote_post(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,webViewLink',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization'           => 'Bearer ' . $token,
					'Content-Type'            => 'application/json; charset=UTF-8',
					'X-Upload-Content-Type'   => $mime_type,
					'X-Upload-Content-Length' => (string) $file_size,
				],
				'body'    => wp_json_encode( $metadata ),
			]
		);

		if ( is_wp_error( $init_res ) ) {
			return $init_res;
		}

		$init_code  = wp_remote_retrieve_response_code( $init_res );
		$upload_uri = wp_remote_retrieve_header( $init_res, 'location' );
		if ( $init_code < 200 || $init_code >= 300 || empty( $upload_uri ) ) {
			return new WP_Error(
				'resumable_init_failed',
				sprintf( 'No se pudo iniciar la subida a Drive (HTTP %d): %s', $init_code, wp_remote_retrieve_body( $init_res ) )
			);
		}

		$handle = fopen( $local_path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'fopen_failed', 'No se pudo abrir el archivo para subirlo.' );
		}

		$offset   = 0;
		$uploaded = null;

		try {
			while ( $offset < $file_size ) {
				$chunk = fread( $handle, self::CHUNK_SIZE );
				if ( $chunk === false || $chunk === '' ) {
					return new WP_Error( 'fread_failed', 'Error leyendo bloque del archivo.' );
				}

				$len = strlen( $chunk );
				$end = $offset + $len - 1;

				$put_res = wp_remote_request(
					$upload_uri,
					[
						'method'  => 'PUT',
						'timeout' => 120,
						'headers' => [
							'Content-Type'   => $mime_type,
							'Content-Length' => (string) $len,
							'Content-Range'  => sprintf( 'bytes %d-%d/%d', $offset, $end, $file_size ),
						],
						'body'    => $chunk,
					]
				);

				if ( is_wp_error( $put_res ) ) {
					return $put_res;
				}

				$code = wp_remote_retrieve_response_code( $put_res );
				if ( $code === 308 ) {
					$offset += $len;
					if ( is_callable( $progress_cb ) ) {
						call_user_func( $progress_cb, $offset, $file_size );
					}
					continue;
				}

				if ( $code >= 200 && $code < 300 ) {
					if ( is_callable( $progress_cb ) ) {
						call_user_func( $progress_cb, $file_size, $file_size );
					}
					$decoded  = json_decode( wp_remote_retrieve_body( $put_res ), true );
					$uploaded = is_array( $decoded ) ? $decoded : [ 'id' => '', 'name' => $remote_name ];
					break;
				}

				return new WP_Error(
					'chunk_upload_failed',
					sprintf( 'Error subiendo bloque a Drive (HTTP %d): %s', $code, wp_remote_retrieve_body( $put_res ) )
				);
			}
		} finally {
			fclose( $handle );
		}

		if ( is_callable( $log_cb ) ) {
			call_user_func( $log_cb, sprintf( 'Subido a Google Drive: %s (%s)', $remote_name, size_format( $file_size ) ) );
		}

		return is_array( $uploaded ) ? $uploaded : new WP_Error( 'upload_incomplete', 'La subida no devolvió metadatos.' );
	}

	/**
	 * Downloads a file from Google Drive directly to a local path using streaming.
	 *
	 * @param string        $file_id    Google Drive file ID.
	 * @param string        $dest_path  Local destination file path.
	 * @param callable|null $log_cb     Optional logger callback.
	 * @return true|WP_Error
	 */
	public static function download_file( $file_id, $dest_path, $log_cb = null ) {
		$file_id = trim( (string) $file_id );
		if ( $file_id === '' ) {
			return new WP_Error( 'missing_file_id', __( 'Falta el ID del archivo en Google Drive.', 'alminuto-theme' ) );
		}

		$token = self::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) . '?alt=media';
		$res = wp_remote_get(
			$url,
			[
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $dest_path,
				'headers'  => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'drive_download_failed',
				sprintf( 'Error descargando de Drive (HTTP %d).', $code )
			);
		}

		if ( ! file_exists( $dest_path ) || filesize( $dest_path ) <= 0 ) {
			return new WP_Error( 'drive_download_empty', __( 'El archivo descargado de Google Drive está vacío.', 'alminuto-theme' ) );
		}

		if ( is_callable( $log_cb ) ) {
			call_user_func( $log_cb, sprintf( 'Descargado de Google Drive (%s).', size_format( filesize( $dest_path ) ) ) );
		}

		return true;
	}

	/**
	 * Deletes older backup groups in Google Drive beyond the configured retention count.
	 *
	 * @param int           $keep_count Number of most recent backup runs to keep.
	 * @param callable|null $log_cb     Optional logger callback.
	 * @return int Number of remote files deleted.
	 */
	public static function enforce_retention( $keep_count, $log_cb = null ) {
		$keep_count = (int) $keep_count;
		if ( $keep_count <= 0 ) {
			return 0;
		}

		$token = self::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return 0;
		}

		$folder_id = self::ensure_folder( $token );
		if ( is_wp_error( $folder_id ) ) {
			return 0;
		}

		$query = sprintf(
			"'%s' in parents and trashed = false and appProperties has { key='alminuto_backup' and value='1' }",
			str_replace( "'", "\\'", $folder_id )
		);

		$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			[
				'q'        => $query,
				'orderBy'  => 'createdTime desc',
				'fields'   => 'files(id,name,createdTime,appProperties)',
				'pageSize' => 200,
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		$res = wp_remote_get(
			$url,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return 0;
		}

		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$files = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : [];
		if ( empty( $files ) ) {
			return 0;
		}

		$groups = [];
		foreach ( $files as $file ) {
			$bid = isset( $file['appProperties']['backup_id'] )
				? (string) $file['appProperties']['backup_id']
				: (string) $file['id'];
			if ( ! isset( $groups[ $bid ] ) ) {
				$groups[ $bid ] = [];
			}
			$groups[ $bid ][] = $file;
		}

		if ( count( $groups ) <= $keep_count ) {
			return 0;
		}

		$to_delete_groups = array_slice( $groups, $keep_count, null, true );
		$deleted          = 0;

		foreach ( $to_delete_groups as $group_files ) {
			foreach ( $group_files as $file ) {
				$del = wp_remote_request(
					'https://www.googleapis.com/drive/v3/files/' . rawurlencode( (string) $file['id'] ),
					[
						'method'  => 'DELETE',
						'timeout' => 20,
						'headers' => [
							'Authorization' => 'Bearer ' . $token,
						],
					]
				);
				if ( ! is_wp_error( $del ) ) {
					$code = wp_remote_retrieve_response_code( $del );
					if ( $code >= 200 && $code < 300 ) {
						$deleted++;
						if ( is_callable( $log_cb ) ) {
							call_user_func( $log_cb, sprintf( 'Retención: eliminado archivo antiguo %s', $file['name'] ) );
						}
					}
				}
			}
		}

		return $deleted;
	}

	/**
	 * Lists and groups existing backup files in the Google Drive destination folder.
	 *
	 * @return array|WP_Error List of grouped backup entries (newest first).
	 */
	public static function list_backups() {
		$token = self::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$folder_id = self::ensure_folder( $token );
		if ( is_wp_error( $folder_id ) ) {
			return $folder_id;
		}

		$query = sprintf(
			"'%s' in parents and trashed = false",
			str_replace( "'", "\\'", $folder_id )
		);

		$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			[
				'q'        => $query,
				'orderBy'  => 'createdTime desc',
				'fields'   => 'files(id,name,size,createdTime,webViewLink,appProperties)',
				'pageSize' => 200,
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		$res = wp_remote_get(
			$url,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'drive_list_failed', sprintf( 'Error listando archivos en Drive (HTTP %d).', $code ) );
		}

		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$files = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : [];
		$groups = [];

		foreach ( $files as $file ) {
			$name = (string) ( $file['name'] ?? '' );
			$id   = (string) ( $file['id'] ?? '' );
			if ( $name === '' || $id === '' ) {
				continue;
			}

			$type = '';
			if ( preg_match( '/-db\.sql(\.gz)?$/i', $name ) ) {
				$type = 'db';
			} elseif ( preg_match( '/-media(-part\d+)?\.(tar|zip)$/i', $name ) ) {
				$type = 'media';
			}

			if ( $type === '' ) {
				continue;
			}

			$bid = '';
			if ( ! empty( $file['appProperties']['backup_id'] ) ) {
				$bid = (string) $file['appProperties']['backup_id'];
			} elseif ( preg_match( '/(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $name, $m ) ) {
				$bid = 'drive-' . $m[1];
			} else {
				$bid = 'drive-' . $id;
			}

			$created_ts = ! empty( $file['createdTime'] ) ? strtotime( (string) $file['createdTime'] ) : time();
			$size       = (int) ( $file['size'] ?? 0 );

			if ( ! isset( $groups[ $bid ] ) ) {
				$groups[ $bid ] = [
					'backup_id'     => $bid,
					'status'        => 'success',
					'trigger'       => 'drive',
					'started_at'    => $created_ts ?: time(),
					'finished_at'   => $created_ts ?: time(),
					'duration'      => 0,
					'include_db'    => false,
					'include_media' => false,
					'db_bytes'      => 0,
					'media_bytes'   => 0,
					'total_bytes'   => 0,
					'files'         => [],
					'message'       => __( 'Sincronizado desde Google Drive.', 'alminuto-theme' ),
					'logs'          => [],
				];
			}

			if ( $type === 'db' ) {
				$groups[ $bid ]['include_db'] = true;
				$groups[ $bid ]['db_bytes']  += $size;
			} else {
				$groups[ $bid ]['include_media'] = true;
				$groups[ $bid ]['media_bytes']  += $size;
			}
			$groups[ $bid ]['total_bytes'] += $size;
			$groups[ $bid ]['files'][]      = [
				'type' => $type,
				'name' => $name,
				'id'   => $id,
				'url'  => (string) ( $file['webViewLink'] ?? '' ),
				'size' => $size,
			];
		}

		foreach ( $groups as &$g ) {
			usort(
				$g['files'],
				static function ( $a, $b ) {
					return strcmp( (string) $a['name'], (string) $b['name'] );
				}
			);
		}
		unset( $g );

		$list = array_values( $groups );
		usort(
			$list,
			static function ( $a, $b ) {
				return (int) $b['started_at'] <=> (int) $a['started_at'];
			}
		);

		return $list;
	}
}
