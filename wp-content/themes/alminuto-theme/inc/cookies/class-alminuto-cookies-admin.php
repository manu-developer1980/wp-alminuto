<?php
/**
 * Admin settings page for alminuto-theme GDPR Cookie Consent.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alminuto_Cookies_Admin {

	/**
	 * Renders the Al Minuto -> Cookies admin page and handles saving.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permisos insuficientes.', 'alminuto-theme' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'general';
		if ( ! in_array( $active_tab, [ 'general', 'categories', 'declaration' ], true ) ) {
			$active_tab = 'general';
		}

		$notice = '';
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['alminuto_cookies_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alminuto_cookies_nonce'] ) ), 'alminuto_cookies_save' ) ) {
				wp_die( esc_html__( 'Nonce inválido. Recarga la página.', 'alminuto-theme' ) );
			}
			self::handle_save( $active_tab );
			$notice = __( 'Configuración de cookies guardada correctamente.', 'alminuto-theme' );
		}

		$settings = alminuto_cookies_get_settings();
		$base_url = admin_url( 'admin.php?page=alminuto-cookies' );
		$tabs     = [
			'general'     => __( 'General, Textos y Enlaces', 'alminuto-theme' ),
			'categories'  => __( 'Categorías y Scripts', 'alminuto-theme' ),
			'declaration' => __( 'Declaración de Cookies', 'alminuto-theme' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gestor de Cookies (RGPD)', 'alminuto-theme' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Banner y gestor de consentimiento adaptado al RGPD (España / UE) y a la estética del tema Al Minuto.', 'alminuto-theme' ); ?>
			</p>

			<?php if ( $notice !== '' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<?php
					$url   = esc_url( add_query_arg( 'tab', $key, $base_url ) );
					$class = $active_tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
					?>
					<a href="<?php echo $url; ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', $active_tab, $base_url ) ); ?>">
				<?php wp_nonce_field( 'alminuto_cookies_save', 'alminuto_cookies_nonce' ); ?>

				<?php if ( $active_tab === 'general' ) : ?>
					<?php self::render_tab_general( $settings ); ?>
				<?php elseif ( $active_tab === 'categories' ) : ?>
					<?php self::render_tab_categories( $settings ); ?>
				<?php else : ?>
					<?php self::render_tab_declaration( $settings ); ?>
				<?php endif; ?>

				<?php submit_button( __( 'Guardar cambios', 'alminuto-theme' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves submitted settings for the active tab.
	 *
	 * @param string $tab Active tab key.
	 * @return void
	 */
	private static function handle_save( $tab ) {
		$current = alminuto_cookies_get_settings();

		if ( $tab === 'general' ) {
			$current['enabled']           = ! empty( $_POST['enabled'] );
			$layout                       = isset( $_POST['layout'] ) ? sanitize_key( (string) wp_unslash( $_POST['layout'] ) ) : 'bar';
			$current['layout']            = in_array( $layout, [ 'bar', 'card' ], true ) ? $layout : 'bar';
			$days                         = isset( $_POST['consent_days'] ) ? (int) $_POST['consent_days'] : 365;
			$current['consent_days']      = max( 30, min( 730, $days ) );
			$version                      = isset( $_POST['policy_version'] ) ? (int) $_POST['policy_version'] : 1;
			$current['policy_version']    = max( 1, $version );
			$current['cookies_url']       = isset( $_POST['cookies_url'] ) ? sanitize_text_field( wp_unslash( $_POST['cookies_url'] ) ) : '/politica-de-cookies/';
			$current['privacy_url']       = isset( $_POST['privacy_url'] ) ? sanitize_text_field( wp_unslash( $_POST['privacy_url'] ) ) : '/politica-de-privacidad/';
			$current['legal_url']         = isset( $_POST['legal_url'] ) ? sanitize_text_field( wp_unslash( $_POST['legal_url'] ) ) : '/aviso-legal/';
			$current['banner_title']      = isset( $_POST['banner_title'] ) ? sanitize_text_field( wp_unslash( $_POST['banner_title'] ) ) : '';
			$current['banner_text']       = isset( $_POST['banner_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['banner_text'] ) ) : '';
			$current['btn_accept']        = isset( $_POST['btn_accept'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_accept'] ) ) : 'Aceptar todas';
			$current['btn_reject']        = isset( $_POST['btn_reject'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_reject'] ) ) : 'Rechazar todas';
			$current['btn_settings']      = isset( $_POST['btn_settings'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_settings'] ) ) : 'Configurar';
			$current['show_footer_link']  = ! empty( $_POST['show_footer_link'] );
			$current['footer_link_text']  = isset( $_POST['footer_link_text'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_link_text'] ) ) : 'Configurar cookies';
			$current['show_floating_btn'] = ! empty( $_POST['show_floating_btn'] );
		} elseif ( $tab === 'categories' ) {
			$current['enable_consent_mode']     = ! empty( $_POST['enable_consent_mode'] );
			$current['block_embeds']            = ! empty( $_POST['block_embeds'] );
			$current['cat_preferences_enabled'] = ! empty( $_POST['cat_preferences_enabled'] );
			$current['cat_analytics_enabled']   = ! empty( $_POST['cat_analytics_enabled'] );
			$current['cat_marketing_enabled']   = ! empty( $_POST['cat_marketing_enabled'] );
			$current['scripts_analytics']       = isset( $_POST['scripts_analytics'] ) ? (string) wp_unslash( $_POST['scripts_analytics'] ) : '';
			$current['scripts_marketing']       = isset( $_POST['scripts_marketing'] ) ? (string) wp_unslash( $_POST['scripts_marketing'] ) : '';
		} elseif ( $tab === 'declaration' ) {
			$names      = isset( $_POST['cookie_name'] ) && is_array( $_POST['cookie_name'] ) ? wp_unslash( $_POST['cookie_name'] ) : [];
			$providers  = isset( $_POST['cookie_provider'] ) && is_array( $_POST['cookie_provider'] ) ? wp_unslash( $_POST['cookie_provider'] ) : [];
			$categories = isset( $_POST['cookie_category'] ) && is_array( $_POST['cookie_category'] ) ? wp_unslash( $_POST['cookie_category'] ) : [];
			$purposes   = isset( $_POST['cookie_purpose'] ) && is_array( $_POST['cookie_purpose'] ) ? wp_unslash( $_POST['cookie_purpose'] ) : [];
			$durations  = isset( $_POST['cookie_duration'] ) && is_array( $_POST['cookie_duration'] ) ? wp_unslash( $_POST['cookie_duration'] ) : [];

			$rows = [];
			foreach ( $names as $idx => $raw_name ) {
				$name = sanitize_text_field( (string) $raw_name );
				if ( $name === '' ) {
					continue;
				}
				$cat = isset( $categories[ $idx ] ) ? sanitize_key( (string) $categories[ $idx ] ) : 'necessary';
				if ( ! in_array( $cat, [ 'necessary', 'preferences', 'analytics', 'marketing' ], true ) ) {
					$cat = 'necessary';
				}
				$rows[] = [
					'name'     => $name,
					'provider' => sanitize_text_field( (string) ( $providers[ $idx ] ?? '' ) ),
					'category' => $cat,
					'purpose'  => sanitize_text_field( (string) ( $purposes[ $idx ] ?? '' ) ),
					'duration' => sanitize_text_field( (string) ( $durations[ $idx ] ?? '' ) ),
				];
			}
			$current['cookies_list'] = $rows;
		}

		update_option( 'alminuto_cookies_settings', $current, false );
	}

	/**
	 * Renders General, Texts and Legal Links tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return void
	 */
	private static function render_tab_general( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Activar gestor de cookies', 'alminuto-theme' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<?php esc_html_e( 'Mostrar banner y gestionar el consentimiento en el sitio web', 'alminuto-theme' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_cookies_layout"><?php esc_html_e( 'Diseño del banner', 'alminuto-theme' ); ?></label></th>
				<td>
					<select id="am_cookies_layout" name="layout">
						<option value="bar" <?php selected( $settings['layout'], 'bar' ); ?>><?php esc_html_e( 'Barra inferior (recomendado)', 'alminuto-theme' ); ?></option>
						<option value="card" <?php selected( $settings['layout'], 'card' ); ?>><?php esc_html_e( 'Tarjeta flotante inferior', 'alminuto-theme' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_cookies_days"><?php esc_html_e( 'Caducidad del consentimiento (días)', 'alminuto-theme' ); ?></label></th>
				<td>
					<input id="am_cookies_days" type="number" name="consent_days" min="30" max="730" value="<?php echo esc_attr( (string) $settings['consent_days'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'La AEPD recomienda un máximo de 24 meses (730 días). Por defecto: 365 días.', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_cookies_version"><?php esc_html_e( 'Versión de la política', 'alminuto-theme' ); ?></label></th>
				<td>
					<input id="am_cookies_version" type="number" name="policy_version" min="1" value="<?php echo esc_attr( (string) $settings['policy_version'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Incrementa este número si cambias las cookies o textos legales y quieres volver a pedir consentimiento a todos los visitantes.', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_cookies_url"><?php esc_html_e( 'Enlace a Política de Cookies', 'alminuto-theme' ); ?></label></th>
				<td><input id="am_cookies_url" type="text" name="cookies_url" value="<?php echo esc_attr( (string) $settings['cookies_url'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="am_privacy_url"><?php esc_html_e( 'Enlace a Política de Privacidad', 'alminuto-theme' ); ?></label></th>
				<td><input id="am_privacy_url" type="text" name="privacy_url" value="<?php echo esc_attr( (string) $settings['privacy_url'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="am_legal_url"><?php esc_html_e( 'Enlace a Aviso Legal', 'alminuto-theme' ); ?></label></th>
				<td><input id="am_legal_url" type="text" name="legal_url" value="<?php echo esc_attr( (string) $settings['legal_url'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="am_banner_title"><?php esc_html_e( 'Título del banner', 'alminuto-theme' ); ?></label></th>
				<td><input id="am_banner_title" type="text" name="banner_title" value="<?php echo esc_attr( (string) $settings['banner_title'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="am_banner_text"><?php esc_html_e( 'Texto del banner', 'alminuto-theme' ); ?></label></th>
				<td>
					<textarea id="am_banner_text" name="banner_text" rows="4" class="large-text"><?php echo esc_textarea( (string) $settings['banner_text'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Puedes usar {cookies}, {privacidad} y {aviso} para insertar automáticamente los enlaces configurados arriba.', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Textos de botones', 'alminuto-theme' ); ?></th>
				<td>
					<p>
						<label><?php esc_html_e( 'Aceptar:', 'alminuto-theme' ); ?> <input type="text" name="btn_accept" value="<?php echo esc_attr( (string) $settings['btn_accept'] ); ?>" class="regular-text"></label>
					</p>
					<p>
						<label><?php esc_html_e( 'Rechazar:', 'alminuto-theme' ); ?> <input type="text" name="btn_reject" value="<?php echo esc_attr( (string) $settings['btn_reject'] ); ?>" class="regular-text"></label>
					</p>
					<p>
						<label><?php esc_html_e( 'Configurar:', 'alminuto-theme' ); ?> <input type="text" name="btn_settings" value="<?php echo esc_attr( (string) $settings['btn_settings'] ); ?>" class="regular-text"></label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Revocación del consentimiento', 'alminuto-theme' ); ?></th>
				<td>
					<p>
						<label>
							<input type="checkbox" name="show_footer_link" value="1" <?php checked( ! empty( $settings['show_footer_link'] ) ); ?>>
							<?php esc_html_e( 'Mostrar enlace en el pie de página (footer)', 'alminuto-theme' ); ?>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Texto del enlace / botón:', 'alminuto-theme' ); ?> <input type="text" name="footer_link_text" value="<?php echo esc_attr( (string) $settings['footer_link_text'] ); ?>" class="regular-text"></label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="show_floating_btn" value="1" <?php checked( ! empty( $settings['show_floating_btn'] ) ); ?>>
							<?php esc_html_e( 'Mostrar también un botón flotante discreto en la esquina inferior izquierda', 'alminuto-theme' ); ?>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'También puedes usar el shortcode [alminuto_cookie_settings] en tu página de Política de Cookies.', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renders Categories, Consent Mode v2 and Conditional Scripts tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return void
	 */
	private static function render_tab_categories( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'alminuto-theme' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_consent_mode" value="1" <?php checked( ! empty( $settings['enable_consent_mode'] ) ); ?>>
						<?php esc_html_e( 'Enviar estado por defecto (denied) y actualizar automáticamente con Google Site Kit / GA4 / GTM', 'alminuto-theme' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bloqueo de embeds externos', 'alminuto-theme' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="block_embeds" value="1" <?php checked( ! empty( $settings['block_embeds'] ) ); ?>>
						<?php esc_html_e( 'Bloquear iframes de YouTube y Facebook hasta que el usuario acepte cookies de marketing/redes', 'alminuto-theme' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Categorías activas en el modal', 'alminuto-theme' ); ?></th>
				<td>
					<p><strong><?php esc_html_e( 'Técnicas / Necesarias:', 'alminuto-theme' ); ?></strong> <?php esc_html_e( 'Siempre activa (obligatorio).', 'alminuto-theme' ); ?></p>
					<p><label><input type="checkbox" name="cat_preferences_enabled" value="1" <?php checked( ! empty( $settings['cat_preferences_enabled'] ) ); ?>> <?php esc_html_e( 'Cookies de preferencias', 'alminuto-theme' ); ?></label></p>
					<p><label><input type="checkbox" name="cat_analytics_enabled" value="1" <?php checked( ! empty( $settings['cat_analytics_enabled'] ) ); ?>> <?php esc_html_e( 'Cookies de analítica y medición', 'alminuto-theme' ); ?></label></p>
					<p><label><input type="checkbox" name="cat_marketing_enabled" value="1" <?php checked( ! empty( $settings['cat_marketing_enabled'] ) ); ?>> <?php esc_html_e( 'Cookies de marketing y redes sociales', 'alminuto-theme' ); ?></label></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_scripts_analytics"><?php esc_html_e( 'Scripts condicionales: Analítica', 'alminuto-theme' ); ?></label></th>
				<td>
					<textarea id="am_scripts_analytics" name="scripts_analytics" rows="5" class="large-text code"><?php echo esc_textarea( (string) $settings['scripts_analytics'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Opcional. Se inyectará solo cuando el usuario acepte "Analítica". (Si ya usas Google Site Kit con Consent Mode v2, no necesitas duplicar el código aquí).', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="am_scripts_marketing"><?php esc_html_e( 'Scripts condicionales: Marketing', 'alminuto-theme' ); ?></label></th>
				<td>
					<textarea id="am_scripts_marketing" name="scripts_marketing" rows="5" class="large-text code"><?php echo esc_textarea( (string) $settings['scripts_marketing'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Opcional. Se inyectará solo cuando el usuario acepte "Marketing y redes sociales" (por ejemplo, píxeles o etiquetas publicitarias).', 'alminuto-theme' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renders the Cookie Declaration table editor tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return void
	 */
	private static function render_tab_declaration( $settings ) {
		$rows = isset( $settings['cookies_list'] ) && is_array( $settings['cookies_list'] ) ? $settings['cookies_list'] : [];
		?>
		<p>
			<?php esc_html_e( 'Define las cookies que se mostrarán en tu página legal usando el shortcode:', 'alminuto-theme' ); ?>
			<code>[alminuto_cookie_declaration]</code>
		</p>
		<table class="widefat striped" id="am-cookies-declaration-editor">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nombre', 'alminuto-theme' ); ?></th>
					<th><?php esc_html_e( 'Proveedor', 'alminuto-theme' ); ?></th>
					<th><?php esc_html_e( 'Categoría', 'alminuto-theme' ); ?></th>
					<th><?php esc_html_e( 'Finalidad', 'alminuto-theme' ); ?></th>
					<th><?php esc_html_e( 'Duración', 'alminuto-theme' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php self::render_declaration_row( $row ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:12px;">
			<button type="button" class="button" id="am-add-cookie-row"><?php esc_html_e( '+ Añadir cookie', 'alminuto-theme' ); ?></button>
		</p>
		<script>
		(function(){
			var btn = document.getElementById('am-add-cookie-row');
			var tbody = document.querySelector('#am-cookies-declaration-editor tbody');
			if (!btn || !tbody) return;
			btn.addEventListener('click', function(){
				var tr = document.createElement('tr');
				tr.innerHTML = '<td><input type="text" name="cookie_name[]" class="regular-text" style="width:100%;" placeholder="_ga"></td>'
					+ '<td><input type="text" name="cookie_provider[]" class="regular-text" style="width:100%;" placeholder="Google"></td>'
					+ '<td><select name="cookie_category[]"><option value="necessary">Técnica</option><option value="preferences">Preferencias</option><option value="analytics">Analítica</option><option value="marketing">Marketing</option></select></td>'
					+ '<td><input type="text" name="cookie_purpose[]" class="regular-text" style="width:100%;"></td>'
					+ '<td><input type="text" name="cookie_duration[]" class="regular-text" style="width:100%;" placeholder="1 año"></td>'
					+ '<td><button type="button" class="button-link-delete am-remove-cookie-row">Eliminar</button></td>';
				tbody.appendChild(tr);
			});
			tbody.addEventListener('click', function(e){
				if (e.target && e.target.classList.contains('am-remove-cookie-row')) {
					var row = e.target.closest('tr');
					if (row) row.remove();
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Renders a single row in the cookie declaration editor.
	 *
	 * @param array<string, string> $row Cookie row data.
	 * @return void
	 */
	private static function render_declaration_row( $row ) {
		$cat = isset( $row['category'] ) ? (string) $row['category'] : 'necessary';
		?>
		<tr>
			<td><input type="text" name="cookie_name[]" value="<?php echo esc_attr( (string) ( $row['name'] ?? '' ) ); ?>" class="regular-text" style="width:100%;"></td>
			<td><input type="text" name="cookie_provider[]" value="<?php echo esc_attr( (string) ( $row['provider'] ?? '' ) ); ?>" class="regular-text" style="width:100%;"></td>
			<td>
				<select name="cookie_category[]">
					<option value="necessary" <?php selected( $cat, 'necessary' ); ?>><?php esc_html_e( 'Técnica', 'alminuto-theme' ); ?></option>
					<option value="preferences" <?php selected( $cat, 'preferences' ); ?>><?php esc_html_e( 'Preferencias', 'alminuto-theme' ); ?></option>
					<option value="analytics" <?php selected( $cat, 'analytics' ); ?>><?php esc_html_e( 'Analítica', 'alminuto-theme' ); ?></option>
					<option value="marketing" <?php selected( $cat, 'marketing' ); ?>><?php esc_html_e( 'Marketing', 'alminuto-theme' ); ?></option>
				</select>
			</td>
			<td><input type="text" name="cookie_purpose[]" value="<?php echo esc_attr( (string) ( $row['purpose'] ?? '' ) ); ?>" class="regular-text" style="width:100%;"></td>
			<td><input type="text" name="cookie_duration[]" value="<?php echo esc_attr( (string) ( $row['duration'] ?? '' ) ); ?>" class="regular-text" style="width:100%;"></td>
			<td><button type="button" class="button-link-delete am-remove-cookie-row"><?php esc_html_e( 'Eliminar', 'alminuto-theme' ); ?></button></td>
		</tr>
		<?php
	}
}
