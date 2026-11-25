<?php
/**
 * Backup admin page view: Google Drive connection, schedule + calendar, last backup status & history.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gdrive          = Alminuto_Backup_GDrive::get_settings();
$is_connected    = Alminuto_Backup_GDrive::is_connected();
$redirect_uri    = Alminuto_Backup_GDrive::get_redirect_uri();
$schedule        = Alminuto_Backup_Scheduler::get_settings();
$next_run_ts     = Alminuto_Backup_Scheduler::get_next_scheduled();
$last_backup     = Alminuto_Backup_Engine::get_last_backup();
$live_status     = Alminuto_Backup_Engine::get_live_status();
$history         = Alminuto_Backup_Engine::get_history();
$upcoming_dates  = Alminuto_Backup_Scheduler::project_upcoming_dates( $schedule, 90 );
$tz_string       = wp_timezone_string();
$notice_key      = 'alminuto_backup_admin_notice_' . get_current_user_id();
$notice          = get_transient( $notice_key );
if ( $notice ) {
	delete_transient( $notice_key );
}

$history_by_date = [];
foreach ( $history as $entry ) {
	if ( empty( $entry['started_at'] ) ) {
		continue;
	}
	$d_key = wp_date( 'Y-m-d', (int) $entry['started_at'] );
	if ( ! isset( $history_by_date[ $d_key ] ) ) {
		$history_by_date[ $d_key ] = [];
	}
	$history_by_date[ $d_key ][] = [
		'time'   => wp_date( 'H:i', (int) $entry['started_at'] ),
		'status' => (string) ( $entry['status'] ?? 'error' ),
		'size'   => size_format( (int) ( $entry['total_bytes'] ?? 0 ) ),
	];
}

$calendar_payload = [
	'today'     => wp_date( 'Y-m-d' ),
	'scheduled' => $upcoming_dates,
	'history'   => $history_by_date,
	'live'      => $live_status,
	'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
	'ajaxNonce' => wp_create_nonce( 'alminuto_backup_ajax' ),
];

$weekdays = [
	1 => __( 'Lunes', 'alminuto-theme' ),
	2 => __( 'Martes', 'alminuto-theme' ),
	3 => __( 'Miércoles', 'alminuto-theme' ),
	4 => __( 'Jueves', 'alminuto-theme' ),
	5 => __( 'Viernes', 'alminuto-theme' ),
	6 => __( 'Sábado', 'alminuto-theme' ),
	7 => __( 'Domingo', 'alminuto-theme' ),
];
?>
<div class="wrap am-backup-wrap">
	<h1><?php esc_html_e( 'Copias de seguridad (BBDD y Media → Google Drive)', 'alminuto-theme' ); ?></h1>

	<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<style>
		.am-backup-wrap { max-width: 1180px; }
		.am-backup-grid { display: grid; gap: 16px; margin-top: 16px; }
		@media (min-width: 960px) { .am-backup-grid { grid-template-columns: 1fr 1fr; } }
		.am-backup-card { background: #fff; border: 1px solid #dcdcde; padding: 16px 20px; border-radius: 4px; }
		.am-backup-card--full { grid-column: 1 / -1; }
		.am-backup-card h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; }
		.am-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
		.am-badge--ok { background: #edfaef; color: #007017; border: 1px solid #b8e6bf; }
		.am-badge--err { background: #fcf0f1; color: #b32d2e; border: 1px solid #f0b8b8; }
		.am-badge--muted { background: #f0f0f1; color: #50575e; border: 1px solid #dcdcde; }
		.am-status-row { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; }
		.am-status-meta { display: grid; gap: 6px; }
		.am-cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
		.am-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
		.am-cal-dow { text-align: center; font-weight: 600; font-size: 12px; color: #50575e; padding: 4px 0; }
		.am-cal-day { min-height: 54px; border: 1px solid #e2e4e7; border-radius: 3px; padding: 4px; font-size: 12px; background: #fff; display: flex; flex-direction: column; justify-content: space-between; }
		.am-cal-day--empty { background: #f9f9f9; border-color: transparent; }
		.am-cal-day--today { border-color: #2271b1; box-shadow: inset 0 0 0 1px #2271b1; }
		.am-cal-day__num { font-weight: 600; color: #1d2327; }
		.am-cal-dots { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 4px; }
		.am-cal-pill { font-size: 10px; padding: 1px 4px; border-radius: 2px; line-height: 1.3; }
		.am-cal-pill--sched { background: #e5f5fa; color: #0a4b78; }
		.am-cal-pill--ok { background: #edfaef; color: #007017; }
		.am-cal-pill--err { background: #fcf0f1; color: #b32d2e; }
		.am-cal-legend { display: flex; gap: 14px; font-size: 12px; color: #50575e; margin-top: 10px; flex-wrap: wrap; }
		.am-inline-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 12px; }
		.am-log-box { background: #1d2327; color: #dcdcde; padding: 10px; font-family: monospace; font-size: 12px; max-height: 180px; overflow: auto; border-radius: 3px; margin-top: 6px; white-space: pre-wrap; }
		.am-live-panel { margin-top: 14px; padding-top: 14px; border-top: 1px solid #f0f0f1; }
		.am-progress-track { width: 100%; height: 14px; background: #f0f0f1; border-radius: 7px; overflow: hidden; margin: 6px 0 8px; }
		.am-progress-bar { height: 100%; background: #2271b1; transition: width 0.3s ease; width: 0%; }
	</style>

	<div class="am-backup-grid">
		<!-- Estado y última copia -->
		<section class="am-backup-card am-backup-card--full">
			<h2><?php esc_html_e( 'Estado y última copia de seguridad', 'alminuto-theme' ); ?></h2>
			<div class="am-status-row">
				<div class="am-status-meta">
					<div>
						<strong><?php esc_html_e( 'Última copia:', 'alminuto-theme' ); ?></strong>
						<?php if ( $last_backup && ! empty( $last_backup['started_at'] ) ) : ?>
							<?php
							$last_ts = (int) $last_backup['started_at'];
							$is_ok   = ( $last_backup['status'] ?? '' ) === 'success';
							?>
							<span class="am-badge <?php echo $is_ok ? 'am-badge--ok' : 'am-badge--err'; ?>">
								<?php echo $is_ok ? esc_html__( 'Correcta', 'alminuto-theme' ) : esc_html__( 'Error', 'alminuto-theme' ); ?>
							</span>
							<span>
								<?php
								echo esc_html(
									sprintf(
										'%s (hace %s)',
										wp_date( 'd/m/Y H:i:s', $last_ts ),
										human_time_diff( $last_ts, time() )
									)
								);
								?>
							</span>
							<?php if ( ! empty( $last_backup['total_bytes'] ) ) : ?>
								<span class="description">
									— <?php echo esc_html( size_format( (int) $last_backup['total_bytes'] ) ); ?>
									(<?php echo esc_html( sprintf( '%ds', (int) ( $last_backup['duration'] ?? 0 ) ) ); ?>)
								</span>
							<?php endif; ?>
							<?php if ( ! $is_ok && ! empty( $last_backup['message'] ) ) : ?>
								<div style="color:#b32d2e;margin-top:4px;"><?php echo esc_html( (string) $last_backup['message'] ); ?></div>
							<?php endif; ?>
						<?php else : ?>
							<span class="am-badge am-badge--muted"><?php esc_html_e( 'Ninguna realizada aún', 'alminuto-theme' ); ?></span>
						<?php endif; ?>
					</div>

					<div>
						<strong><?php esc_html_e( 'Próxima copia programada:', 'alminuto-theme' ); ?></strong>
						<?php if ( ! empty( $schedule['enabled'] ) && $next_run_ts ) : ?>
							<span>
								<?php
								echo esc_html(
									sprintf(
										'%s (%s)',
										wp_date( 'd/m/Y H:i', (int) $next_run_ts ),
										$tz_string
									)
								);
								?>
							</span>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Programación automática desactivada', 'alminuto-theme' ); ?></span>
						<?php endif; ?>
					</div>

					<div>
						<strong><?php esc_html_e( 'Google Drive:', 'alminuto-theme' ); ?></strong>
						<?php if ( $is_connected ) : ?>
							<span class="am-badge am-badge--ok"><?php esc_html_e( 'Conectado', 'alminuto-theme' ); ?></span>
							<?php if ( ! empty( $gdrive['connected_email'] ) ) : ?>
								<span><?php echo esc_html( $gdrive['connected_email'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="am-badge am-badge--err"><?php esc_html_e( 'No conectado', 'alminuto-theme' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="am-backup-run-now-form">
						<input type="hidden" name="action" value="alminuto_backup_run_now" />
						<?php wp_nonce_field( 'alminuto_backup_run_now', 'alminuto_backup_run_nonce' ); ?>
						<button type="submit" class="button button-primary button-hero" id="am-backup-run-btn" <?php disabled( ! $is_connected || ! empty( $live_status['running'] ) ); ?>>
							<?php echo ! empty( $live_status['running'] ) ? esc_html__( 'Copia en curso...', 'alminuto-theme' ) : esc_html__( 'Ejecutar copia ahora', 'alminuto-theme' ); ?>
						</button>
					</form>
					<?php if ( ! $is_connected ) : ?>
						<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Conecta Google Drive para habilitar.', 'alminuto-theme' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="am-live-panel" id="am-live-panel" style="<?php echo ! empty( $live_status['running'] ) ? '' : 'display:none;'; ?>">
				<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
					<strong id="am-live-phase"><?php echo esc_html( (string) ( $live_status['phase'] ?: __( 'Copia en curso...', 'alminuto-theme' ) ) ); ?></strong>
					<div style="display:flex;align-items:center;gap:10px;">
						<span id="am-live-percent"><?php echo esc_html( (string) (int) $live_status['percent'] ); ?>%</span>
						<button type="button" class="button button-small" id="am-backup-cancel-btn">
							<?php esc_html_e( 'Cancelar / Desbloquear', 'alminuto-theme' ); ?>
						</button>
					</div>
				</div>
				<div class="am-progress-track">
					<div class="am-progress-bar" id="am-live-bar" style="width:<?php echo esc_attr( (string) (int) $live_status['percent'] ); ?>%;"></div>
				</div>
				<div class="am-log-box" id="am-live-logs"><?php echo esc_html( implode( "\n", $live_status['logs'] ) ); ?></div>
			</div>
		</section>

		<!-- Programación -->
		<section class="am-backup-card">
			<h2><?php esc_html_e( 'Programación y frecuencia', 'alminuto-theme' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="alminuto_backup_save_schedule" />
				<?php wp_nonce_field( 'alminuto_backup_schedule_save', 'alminuto_backup_schedule_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Copia automática', 'alminuto-theme' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $schedule['enabled'] ) ); ?> />
									<?php esc_html_e( 'Activar programación automática', 'alminuto-theme' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Contenido a respaldar', 'alminuto-theme' ); ?></th>
							<td>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="include_db" value="1" <?php checked( ! empty( $schedule['include_db'] ) ); ?> />
									<?php esc_html_e( 'Base de datos (.sql.gz)', 'alminuto-theme' ); ?>
								</label>
								<label style="display:block;">
									<input type="checkbox" name="include_media" value="1" <?php checked( ! empty( $schedule['include_media'] ) ); ?> />
									<?php esc_html_e( 'Media / Uploads (.zip)', 'alminuto-theme' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="am-sched-unit"><?php esc_html_e( 'Frecuencia', 'alminuto-theme' ); ?></label></th>
							<td>
								<span><?php esc_html_e( 'Cada', 'alminuto-theme' ); ?></span>
								<input type="number" name="interval" id="am-sched-interval" min="1" max="365" value="<?php echo esc_attr( (string) (int) $schedule['interval'] ); ?>" class="small-text" />
								<select name="unit" id="am-sched-unit">
									<option value="hours" <?php selected( $schedule['unit'], 'hours' ); ?>><?php esc_html_e( 'Horas', 'alminuto-theme' ); ?></option>
									<option value="days" <?php selected( $schedule['unit'], 'days' ); ?>><?php esc_html_e( 'Días', 'alminuto-theme' ); ?></option>
									<option value="weeks" <?php selected( $schedule['unit'], 'weeks' ); ?>><?php esc_html_e( 'Semanas', 'alminuto-theme' ); ?></option>
									<option value="months" <?php selected( $schedule['unit'], 'months' ); ?>><?php esc_html_e( 'Meses', 'alminuto-theme' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="am-row-weekday" style="<?php echo $schedule['unit'] === 'weeks' ? '' : 'display:none;'; ?>">
							<th scope="row"><label for="am-sched-weekday"><?php esc_html_e( 'Día de la semana', 'alminuto-theme' ); ?></label></th>
							<td>
								<select name="weekday" id="am-sched-weekday">
									<?php foreach ( $weekdays as $num => $label ) : ?>
										<option value="<?php echo esc_attr( (string) $num ); ?>" <?php selected( (int) $schedule['weekday'], $num ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr id="am-row-monthday" style="<?php echo $schedule['unit'] === 'months' ? '' : 'display:none;'; ?>">
							<th scope="row"><label for="am-sched-monthday"><?php esc_html_e( 'Día del mes', 'alminuto-theme' ); ?></label></th>
							<td>
								<input type="number" name="monthday" id="am-sched-monthday" min="1" max="28" value="<?php echo esc_attr( (string) (int) $schedule['monthday'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Entre 1 y 28 para garantizar ejecución todos los meses.', 'alminuto-theme' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="am-sched-time"><?php esc_html_e( 'Hora de ejecución', 'alminuto-theme' ); ?></label></th>
							<td>
								<input type="time" name="time" id="am-sched-time" value="<?php echo esc_attr( (string) $schedule['time'] ); ?>" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: site timezone */
										esc_html__( 'Zona horaria del sitio: %s. En frecuencia por horas se toma el minuto indicado.', 'alminuto-theme' ),
										esc_html( $tz_string )
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="am-sched-retention"><?php esc_html_e( 'Retención en Drive', 'alminuto-theme' ); ?></label></th>
							<td>
								<input type="number" name="retention" id="am-sched-retention" min="0" max="365" value="<?php echo esc_attr( (string) (int) $schedule['retention'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Número máximo de copias a conservar en Google Drive (0 = sin límite).', 'alminuto-theme' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Guardar programación', 'alminuto-theme' ) ); ?>
			</form>
		</section>

		<!-- Calendario visual -->
		<section class="am-backup-card">
			<h2><?php esc_html_e( 'Calendario de copias', 'alminuto-theme' ); ?></h2>
			<div class="am-cal-header">
				<button type="button" class="button" id="am-cal-prev">&larr; <?php esc_html_e( 'Anterior', 'alminuto-theme' ); ?></button>
				<strong id="am-cal-title"></strong>
				<button type="button" class="button" id="am-cal-next"><?php esc_html_e( 'Siguiente', 'alminuto-theme' ); ?> &rarr;</button>
			</div>
			<div class="am-cal-grid" id="am-cal-grid"></div>
			<div class="am-cal-legend">
				<span><span class="am-cal-pill am-cal-pill--ok">HH:MM</span> <?php esc_html_e( 'Copia realizada', 'alminuto-theme' ); ?></span>
				<span><span class="am-cal-pill am-cal-pill--err">HH:MM</span> <?php esc_html_e( 'Copia con error', 'alminuto-theme' ); ?></span>
				<span><span class="am-cal-pill am-cal-pill--sched">HH:MM</span> <?php esc_html_e( 'Próxima programada', 'alminuto-theme' ); ?></span>
			</div>
		</section>

		<!-- Google Drive OAuth -->
		<section class="am-backup-card am-backup-card--full">
			<h2><?php esc_html_e( 'Conexión con Google Drive (OAuth 2.0)', 'alminuto-theme' ); ?></h2>
			<?php
			$has_constants       = Alminuto_Backup_GDrive::has_config_constants();
			$default_folder_name = Alminuto_Backup_GDrive::get_default_folder_name();
			?>
			<?php if ( ! $has_constants ) : ?>
				<p class="description">
					<?php esc_html_e( 'Crea credenciales OAuth 2.0 (Aplicación web) en Google Cloud Console con la API de Google Drive habilitada y añade esta URI de redirección autorizada:', 'alminuto-theme' ); ?>
				</p>
				<div style="display:flex;gap:8px;align-items:center;margin:8px 0 14px;flex-wrap:wrap;">
					<code id="am-redirect-uri" style="padding:6px 10px;background:#f6f7f7;border:1px solid #dcdcde;"><?php echo esc_html( $redirect_uri ); ?></code>
					<button type="button" class="button" id="am-copy-redirect"><?php esc_html_e( 'Copiar URI', 'alminuto-theme' ); ?></button>
				</div>
			<?php else : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: default Drive folder name */
						esc_html__( 'Credenciales configuradas mediante constantes en wp-config.php. Carpeta por defecto en Drive: %s.', 'alminuto-theme' ),
						'<code>' . esc_html( $default_folder_name ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="alminuto_backup_save_gdrive" />
				<?php wp_nonce_field( 'alminuto_backup_gdrive_save', 'alminuto_backup_gdrive_nonce' ); ?>

				<?php if ( ! $has_constants ) : ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="am-gdrive-client-id"><?php esc_html_e( 'Client ID', 'alminuto-theme' ); ?></label></th>
								<td><input type="text" name="client_id" id="am-gdrive-client-id" class="regular-text" value="<?php echo esc_attr( (string) $gdrive['client_id'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="am-gdrive-client-secret"><?php esc_html_e( 'Client Secret', 'alminuto-theme' ); ?></label></th>
								<td><input type="password" name="client_secret" id="am-gdrive-client-secret" class="regular-text" value="<?php echo esc_attr( (string) $gdrive['client_secret'] ); ?>" autocomplete="new-password" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="am-gdrive-folder-id"><?php esc_html_e( 'ID de carpeta en Drive (opcional)', 'alminuto-theme' ); ?></label></th>
								<td>
									<input type="text" name="folder_id" id="am-gdrive-folder-id" class="regular-text" value="<?php echo esc_attr( (string) $gdrive['folder_id'] ); ?>" />
									<p class="description">
										<?php
										printf(
											/* translators: %s: default folder name */
											esc_html__( 'Si se deja vacío, se creará automáticamente una carpeta con el dominio del sitio (%s).', 'alminuto-theme' ),
											esc_html( $default_folder_name )
										);
										?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				<?php endif; ?>

				<div class="am-inline-actions">
					<?php if ( ! $has_constants ) : ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar credenciales', 'alminuto-theme' ); ?></button>
					<?php endif; ?>

					<?php if ( $gdrive['client_id'] !== '' && $gdrive['client_secret'] !== '' ) : ?>
						<?php
						$connect_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=alminuto_backup_connect' ),
							'alminuto_backup_connect',
							'nonce'
						);
						?>
						<a href="<?php echo esc_url( $connect_url ); ?>" class="button <?php echo $has_constants && ! $is_connected ? 'button-primary' : ''; ?>">
							<?php echo $is_connected ? esc_html__( 'Reconectar Google Drive', 'alminuto-theme' ) : esc_html__( 'Conectar con Google Drive', 'alminuto-theme' ); ?>
						</a>
					<?php endif; ?>

					<?php if ( $is_connected ) : ?>
						<?php
						$test_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=alminuto_backup_test' ),
							'alminuto_backup_test',
							'nonce'
						);
						$disconnect_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=alminuto_backup_disconnect' ),
							'alminuto_backup_disconnect',
							'nonce'
						);
						?>
						<a href="<?php echo esc_url( $test_url ); ?>" class="button"><?php esc_html_e( 'Probar conexión', 'alminuto-theme' ); ?></a>
						<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button"><?php esc_html_e( 'Desconectar', 'alminuto-theme' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</section>

		<!-- Historial -->
		<section class="am-backup-card am-backup-card--full">
			<h2><?php esc_html_e( 'Historial reciente', 'alminuto-theme' ); ?></h2>
			<?php if ( empty( $history ) ) : ?>
				<p class="description"><?php esc_html_e( 'Todavía no hay copias registradas.', 'alminuto-theme' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Fecha', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Origen', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Contenido', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Tamaño', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Duración', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Detalle', 'alminuto-theme' ); ?></th>
							<th><?php esc_html_e( 'Acciones', 'alminuto-theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $item ) : ?>
							<?php
							$ok      = ( $item['status'] ?? '' ) === 'success';
							$ts      = (int) ( $item['started_at'] ?? 0 );
							$parts   = [];
							if ( ! empty( $item['include_db'] ) ) {
								$parts[] = 'BBDD';
							}
							if ( ! empty( $item['include_media'] ) ) {
								$parts[] = 'Media';
							}
							?>
							<tr>
								<td><?php echo esc_html( $ts ? wp_date( 'd/m/Y H:i:s', $ts ) : '-' ); ?></td>
								<td><code><?php echo esc_html( (string) ( $item['trigger'] ?? 'manual' ) ); ?></code></td>
								<td>
									<span class="am-badge <?php echo $ok ? 'am-badge--ok' : 'am-badge--err'; ?>">
										<?php echo $ok ? esc_html__( 'Correcta', 'alminuto-theme' ) : esc_html__( 'Error', 'alminuto-theme' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( implode( ' + ', $parts ) ?: '-' ); ?></td>
								<td><?php echo esc_html( size_format( (int) ( $item['total_bytes'] ?? 0 ) ) ); ?></td>
								<td><?php echo esc_html( sprintf( '%ds', (int) ( $item['duration'] ?? 0 ) ) ); ?></td>
								<td>
									<?php echo esc_html( (string) ( $item['message'] ?? '' ) ); ?>
									<?php if ( ! empty( $item['logs'] ) && is_array( $item['logs'] ) ) : ?>
										<details style="margin-top:4px;">
											<summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e( 'Ver log', 'alminuto-theme' ); ?></summary>
											<div class="am-log-box"><?php echo esc_html( implode( "\n", $item['logs'] ) ); ?></div>
										</details>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$can_restore = $is_connected
										&& $ok
										&& ! empty( $item['backup_id'] )
										&& ! empty( $item['files'] );
									?>
									<?php if ( $can_restore ) : ?>
										<button
											type="button"
											class="button button-small am-restore-btn"
											data-backup-id="<?php echo esc_attr( (string) $item['backup_id'] ); ?>"
											data-label="<?php echo esc_attr( ( $ts ? wp_date( 'd/m/Y H:i', $ts ) : '' ) . ' (' . implode( ' + ', $parts ) . ')' ); ?>"
											<?php disabled( ! empty( $live_status['running'] ) ); ?>
										>
											<?php esc_html_e( 'Restaurar', 'alminuto-theme' ); ?>
										</button>
									<?php else : ?>
										<span class="description">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	</div>
</div>

<script>
	window.alminutoBackupCalendar = <?php echo wp_json_encode( $calendar_payload ); ?>;
</script>
