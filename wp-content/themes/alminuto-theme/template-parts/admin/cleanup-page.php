<?php
/**
 * Cleanup admin page view.
 *
 * Variables available:
 *   - $cleanup_result          array|null  Stats from the last run (or null).
 *   - $cleanup_was_dry_run     bool        Whether the last run was a dry run.
 *   - $cleanup_error           string|null Error message from the last run.
 *   - $cleanup_log_file_path   string|null Absolute path to the log file.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap alminuto-cleanup">
	<h1><?php esc_html_e( 'Limpiar imágenes y artículos antiguos', 'alminuto-theme' ); ?></h1>

	<p class="description">
		<?php esc_html_e(
			'Elimina artículos publicados hace más de N días (mínimo 30) junto con sus adjuntos, y reescribe los enlaces internos que apunten a los artículos eliminados como texto plano. Los sticky posts siempre se excluyen.',
			'alminuto-theme'
		); ?>
	</p>

	<form method="post" id="alminuto-cleanup-form">
		<?php wp_nonce_field( 'alminuto_cleanup', 'alminuto_cleanup_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="alminuto-before-days"><?php esc_html_e( 'Días de antigüedad mínima', 'alminuto-theme' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="alminuto-before-days"
							name="before_days"
							value="30"
							min="30"
							step="1"
							class="small-text"
						/>
						<p class="description">
							<?php esc_html_e( 'Mínimo 30. Los artículos más recientes NO se eliminarán.', 'alminuto-theme' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rango custom (opcional)', 'alminuto-theme' ); ?></th>
					<td>
						<input type="date" name="start_date" id="alminuto-start-date" />
						<span class="description"> a </span>
						<input type="date" name="end_date" id="alminuto-end-date" />
						<p class="description">
							<?php esc_html_e(
								'Si se especifica, ignora el campo de días y usa el rango exacto (inclusivo).',
								'alminuto-theme'
							); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Modo', 'alminuto-theme' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dry_run" value="1" checked />
							<?php esc_html_e( 'Solo vista previa (dry-run, recomendado)', 'alminuto-theme' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e(
								'Muestra qué se eliminaría sin tocar nada. Desmarca esta casilla SOLO cuando estés seguro de ejecutar el borrado real.',
								'alminuto-theme'
							); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="alminuto-cleanup-submit">
				<?php esc_html_e( 'Ejecutar', 'alminuto-theme' ); ?>
			</button>
		</p>
	</form>

	<?php if ( $cleanup_error ) : ?>
		<div class="notice notice-error inline">
			<p><strong><?php esc_html_e( 'Error:', 'alminuto-theme' ); ?></strong> <?php echo esc_html( $cleanup_error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $cleanup_result ) : ?>
		<h2>
			<?php
			echo esc_html(
				$cleanup_was_dry_run
					? __( 'Vista previa (dry-run)', 'alminuto-theme' )
					: __( 'Resultado del borrado', 'alminuto-theme' )
			);
			?>
		</h2>

		<?php if ( ! $cleanup_was_dry_run ) : ?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php esc_html_e( 'Limpieza completada.', 'alminuto-theme' ); ?></strong>
					<?php
					printf(
						/* translators: 1: posts deleted, 2: attachments deleted, 3: links rewritten */
						esc_html__( 'Eliminados %1$d artículos, %2$d adjuntos. Reescritos %3$d enlaces internos.', 'alminuto-theme' ),
						(int) $cleanup_result['posts_deleted'],
						(int) $cleanup_result['attachments_deleted'],
						(int) $cleanup_result['links_rewritten']
					);
					?>
				</p>
				<?php if ( $cleanup_log_file_path ) : ?>
					<p>
						<strong><?php esc_html_e( 'Log:', 'alminuto-theme' ); ?></strong>
						<code><?php echo esc_html( $cleanup_log_file_path ); ?></code>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width: 720px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Artículos a eliminar / eliminados', 'alminuto-theme' ); ?></th>
					<td>
						<?php echo esc_html( (int) $cleanup_result['posts_deleted'] ); ?> / <?php echo esc_html( (int) $cleanup_result['posts_to_delete'] ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Adjuntos borrados en cascada', 'alminuto-theme' ); ?></th>
					<td><?php echo esc_html( (int) $cleanup_result['attachments_deleted'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enlaces internos reescritos', 'alminuto-theme' ); ?></th>
					<td><?php echo esc_html( (int) $cleanup_result['links_rewritten'] ); ?></td>
				</tr>
				<?php if ( $cleanup_was_dry_run ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Posts que perderían enlaces', 'alminuto-theme' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: number of posts that would lose internal links */
								esc_html__( '%d artículos', 'alminuto-theme' ),
								(int) $cleanup_result['affected_link_count']
							);
							?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $cleanup_result['sample_titles'] ) ) : ?>
			<h3><?php esc_html_e( 'Muestra de títulos (primeros 20)', 'alminuto-theme' ); ?></h3>
			<ul style="max-width: 720px; max-height: 240px; overflow: auto; background: #fff; border: 1px solid #ccd0d4; padding: 8px 8px 8px 24px;">
				<?php foreach ( $cleanup_result['sample_titles'] as $title ) : ?>
					<li><?php echo esc_html( $title ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $cleanup_was_dry_run && ! empty( $cleanup_result['affected_link_samples'] ) ) : ?>
			<h3><?php esc_html_e( 'Muestra de posts que perderían enlaces', 'alminuto-theme' ); ?></h3>
			<ul style="max-width: 720px; max-height: 240px; overflow: auto; background: #fff; border: 1px solid #ccd0d4; padding: 8px 8px 8px 24px;">
				<?php foreach ( $cleanup_result['affected_link_samples'] as $title ) : ?>
					<li><?php echo esc_html( $title ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</div>

<script>
(function(){
	var form    = document.getElementById('alminuto-cleanup-form');
	if (!form) { return; }
	var submit  = document.getElementById('alminuto-cleanup-submit');
	var dryRun  = form.querySelector('input[name="dry_run"]');

	function updateLabel(){
		if (!dryRun) { return; }
		if (dryRun.checked) {
			submit.textContent = '<?php echo esc_js( __( 'Vista previa', 'alminuto-theme' ) ); ?>';
			submit.className = 'button button-secondary';
		} else {
			submit.textContent = '<?php echo esc_js( __( 'Ejecutar borrado real', 'alminuto-theme' ) ); ?>';
			submit.className = 'button button-primary';
		}
	}
	if (dryRun) {
		dryRun.addEventListener('change', updateLabel);
		updateLabel();
	}

	form.addEventListener('submit', function(e){
		if (dryRun && !dryRun.checked) {
			var c = window.confirm('<?php echo esc_js( __( 'Esta acción eliminará permanentemente los artículos y sus adjuntos. ¿Continuar?', 'alminuto-theme' ) ); ?>');
			if (!c) { e.preventDefault(); }
		}
	});
})();
</script>
