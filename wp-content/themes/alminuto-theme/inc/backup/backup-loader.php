<?php
/**
 * Loader for alminuto-theme Google Drive backup module.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-alminuto-backup-gdrive.php';
require_once __DIR__ . '/class-alminuto-backup-engine.php';
require_once __DIR__ . '/class-alminuto-backup-scheduler.php';

add_filter( 'cron_schedules', [ 'Alminuto_Backup_Scheduler', 'register_cron_schedules' ] );

/**
 * WP-Cron execution handler: runs the backup and schedules the next occurrence.
 */
function alminuto_backup_handle_cron() {
	$settings = Alminuto_Backup_Scheduler::get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	Alminuto_Backup_Engine::run(
		[
			'include_db'    => (bool) $settings['include_db'],
			'include_media' => (bool) $settings['include_media'],
			'retention'     => (int) $settings['retention'],
			'trigger'       => 'cron',
		]
	);

	Alminuto_Backup_Scheduler::reschedule( $settings, time() );
}
add_action( Alminuto_Backup_Scheduler::CRON_HOOK, 'alminuto_backup_handle_cron' );

/**
 * Ensures cron stays scheduled when enabled (e.g., if WP-Cron missed or theme was reactivated).
 */
function alminuto_backup_ensure_cron() {
	$settings = Alminuto_Backup_Scheduler::get_settings();
	if ( ! empty( $settings['enabled'] ) && ! Alminuto_Backup_Scheduler::get_next_scheduled() ) {
		Alminuto_Backup_Scheduler::reschedule( $settings );
	}
}
add_action( 'init', 'alminuto_backup_ensure_cron', 20 );

/**
 * Helper to redirect back to the backup admin page with a status notice.
 *
 * @param string $type    'success' | 'error'.
 * @param string $message Notice message.
 * @return void
 */
function alminuto_backup_admin_redirect( $type, $message ) {
	set_transient(
		'alminuto_backup_admin_notice_' . get_current_user_id(),
		[
			'type'    => $type,
			'message' => $message,
		],
		60
	);
	wp_safe_redirect( admin_url( 'admin.php?page=alminuto-backups' ) );
	exit;
}

/**
 * Saves Google Drive OAuth credentials & folder ID.
 */
function alminuto_backup_action_save_gdrive() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_gdrive_save', 'alminuto_backup_gdrive_nonce' );

	$client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
	$client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
	$folder_id     = isset( $_POST['folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_id'] ) ) : '';

	Alminuto_Backup_GDrive::save_settings(
		[
			'client_id'     => trim( $client_id ),
			'client_secret' => trim( $client_secret ),
			'folder_id'     => trim( $folder_id ),
		]
	);

	alminuto_backup_admin_redirect( 'success', __( 'Credenciales de Google Drive guardadas.', 'alminuto-theme' ) );
}
add_action( 'admin_post_alminuto_backup_save_gdrive', 'alminuto_backup_action_save_gdrive' );

/**
 * Redirects user to Google OAuth consent screen.
 */
function alminuto_backup_action_connect_gdrive() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_connect', 'nonce' );

	$settings = Alminuto_Backup_GDrive::get_settings();
	if ( $settings['client_id'] === '' || $settings['client_secret'] === '' ) {
		alminuto_backup_admin_redirect( 'error', __( 'Guarda primero el Client ID y Client Secret.', 'alminuto-theme' ) );
	}

	$state = wp_create_nonce( 'alminuto_backup_oauth_state' );
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	wp_redirect( Alminuto_Backup_GDrive::get_auth_url( $state ) );
	exit;
}
add_action( 'admin_post_alminuto_backup_connect', 'alminuto_backup_action_connect_gdrive' );

/**
 * Handles Google OAuth 2.0 callback.
 */
function alminuto_backup_action_oauth_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}

	if ( ! empty( $_GET['error'] ) ) {
		alminuto_backup_admin_redirect(
			'error',
			sprintf( __( 'Google devolvió un error: %s', 'alminuto-theme' ), sanitize_text_field( wp_unslash( $_GET['error'] ) ) )
		);
	}

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! wp_verify_nonce( $state, 'alminuto_backup_oauth_state' ) ) {
		alminuto_backup_admin_redirect( 'error', __( 'Estado OAuth inválido. Inténtalo de nuevo.', 'alminuto-theme' ) );
	}

	$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	if ( $code === '' ) {
		alminuto_backup_admin_redirect( 'error', __( 'No se recibió código de autorización de Google.', 'alminuto-theme' ) );
	}

	$res = Alminuto_Backup_GDrive::exchange_code( $code );
	if ( is_wp_error( $res ) ) {
		alminuto_backup_admin_redirect( 'error', $res->get_error_message() );
	}

	alminuto_backup_admin_redirect( 'success', __( 'Google Drive conectado correctamente.', 'alminuto-theme' ) );
}
add_action( 'admin_post_alminuto_backup_oauth', 'alminuto_backup_action_oauth_callback' );

/**
 * Tests Google Drive connection.
 */
function alminuto_backup_action_test_gdrive() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_test', 'nonce' );

	$res = Alminuto_Backup_GDrive::test_connection();
	if ( is_wp_error( $res ) ) {
		alminuto_backup_admin_redirect( 'error', $res->get_error_message() );
	}

	$folder_msg = is_wp_error( $res['folder_id'] ) ? 'Error carpeta' : $res['folder_id'];
	alminuto_backup_admin_redirect(
		'success',
		sprintf( __( 'Conexión correcta (%1$s). Carpeta Drive ID: %2$s', 'alminuto-theme' ), $res['email'], $folder_msg )
	);
}
add_action( 'admin_post_alminuto_backup_test', 'alminuto_backup_action_test_gdrive' );

/**
 * Disconnects Google Drive.
 */
function alminuto_backup_action_disconnect_gdrive() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_disconnect', 'nonce' );

	Alminuto_Backup_GDrive::disconnect();
	alminuto_backup_admin_redirect( 'success', __( 'Google Drive desconectado.', 'alminuto-theme' ) );
}
add_action( 'admin_post_alminuto_backup_disconnect', 'alminuto_backup_action_disconnect_gdrive' );

/**
 * Saves schedule settings.
 */
function alminuto_backup_action_save_schedule() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_schedule_save', 'alminuto_backup_schedule_nonce' );

	Alminuto_Backup_Scheduler::save_settings( wp_unslash( $_POST ) );
	alminuto_backup_admin_redirect( 'success', __( 'Programación de copias guardada.', 'alminuto-theme' ) );
}
add_action( 'admin_post_alminuto_backup_save_schedule', 'alminuto_backup_action_save_schedule' );

/**
 * Runs a manual backup immediately.
 */
function alminuto_backup_action_run_now() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}
	check_admin_referer( 'alminuto_backup_run_now', 'alminuto_backup_run_nonce' );

	$result = Alminuto_Backup_Engine::run( [ 'trigger' => 'manual' ] );
	if ( isset( $result['status'] ) && $result['status'] === 'success' ) {
		alminuto_backup_admin_redirect(
			'success',
			sprintf( __( 'Copia manual completada en %ds (%s).', 'alminuto-theme' ), (int) $result['duration'], size_format( (int) $result['total_bytes'] ) )
		);
	}

	$err = isset( $result['message'] ) ? $result['message'] : __( 'Error desconocido.', 'alminuto-theme' );
	alminuto_backup_admin_redirect( 'error', $err );
}
add_action( 'admin_post_alminuto_backup_run_now', 'alminuto_backup_action_run_now' );

/**
 * AJAX: Returns live status and logs of an active or recently finished backup.
 */
function alminuto_backup_ajax_status() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'alminuto-theme' ) ], 403 );
	}
	check_ajax_referer( 'alminuto_backup_ajax', 'nonce' );

	wp_send_json_success( Alminuto_Backup_Engine::get_live_status() );
}
add_action( 'wp_ajax_alminuto_backup_status', 'alminuto_backup_ajax_status' );

/**
 * AJAX: Starts a manual backup (runs with ignore_user_abort so polling can track it).
 */
function alminuto_backup_ajax_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'alminuto-theme' ) ], 403 );
	}
	check_ajax_referer( 'alminuto_backup_ajax', 'nonce' );

	$result = Alminuto_Backup_Engine::run( [ 'trigger' => 'manual' ] );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_alminuto_backup_start', 'alminuto_backup_ajax_start' );

/**
 * AJAX: Restores a backup from Google Drive by backup_id.
 */
function alminuto_backup_ajax_restore() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'alminuto-theme' ) ], 403 );
	}
	check_ajax_referer( 'alminuto_backup_ajax', 'nonce' );

	$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
	if ( $backup_id === '' ) {
		wp_send_json_error( [ 'message' => __( 'ID de copia inválido.', 'alminuto-theme' ) ], 400 );
	}

	$result = Alminuto_Backup_Engine::restore( $backup_id );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_alminuto_backup_restore', 'alminuto_backup_ajax_restore' );

/**
 * Renders the Copias de seguridad admin page.
 */
function alminuto_theme_render_backups_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
	}

	$template = locate_template( 'template-parts/admin/backup-page.php' );
	if ( ! $template ) {
		echo '<div class="wrap"><h1>Copias de seguridad</h1><p>Vista no encontrada.</p></div>';
		return;
	}

	include $template;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$cli_file = get_template_directory() . '/tools/backup-cli.php';
	if ( file_exists( $cli_file ) ) {
		require_once $cli_file;
	}
}
