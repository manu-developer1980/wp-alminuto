<?php
/**
 * Loader and frontend integration for alminuto-theme GDPR Cookie Consent.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-alminuto-cookies-admin.php';

/**
 * Returns default settings for the cookie consent manager.
 *
 * @return array<string, mixed>
 */
function alminuto_cookies_defaults() {
	return [
		'enabled'                 => true,
		'layout'                  => 'bar',
		'consent_days'            => 365,
		'policy_version'          => 1,
		'privacy_url'             => '/politica-de-privacidad/',
		'legal_url'               => '/aviso-legal/',
		'cookies_url'             => '/politica-de-cookies/',
		'banner_title'            => 'Uso de cookies',
		'banner_text'             => 'Utilizamos cookies propias y de terceros para garantizar el funcionamiento básico de la web, analizar el tráfico y mostrar contenidos multimedia externos. Puedes aceptar todas las cookies, rechazarlas o configurar tus preferencias en cualquier momento. Más información en nuestra {cookies}, {privacidad} y {aviso}.',
		'btn_accept'              => 'Aceptar todas',
		'btn_reject'              => 'Rechazar todas',
		'btn_settings'            => 'Configurar',
		'show_footer_link'        => true,
		'footer_link_text'        => 'Configurar cookies',
		'show_floating_btn'       => false,
		'enable_consent_mode'     => true,
		'block_embeds'            => true,
		'cat_preferences_enabled' => false,
		'cat_analytics_enabled'   => true,
		'cat_marketing_enabled'   => true,
		'scripts_analytics'       => '',
		'scripts_marketing'       => '',
		'cookies_list'            => [
			[
				'name'     => 'am_cookie_consent',
				'provider' => 'Propia',
				'category' => 'necessary',
				'purpose'  => 'Almacena el estado de consentimiento de cookies del usuario.',
				'duration' => '1 año',
			],
			[
				'name'     => '_ga, _ga_*',
				'provider' => 'Google Analytics',
				'category' => 'analytics',
				'purpose'  => 'Medición de audiencia y estadísticas de uso del sitio.',
				'duration' => '2 años',
			],
			[
				'name'     => 'VISITOR_INFO1_LIVE, YSC',
				'provider' => 'YouTube',
				'category' => 'marketing',
				'purpose'  => 'Reproducción de vídeos incrustados y preferencias del reproductor.',
				'duration' => '6 meses / Sesión',
			],
			[
				'name'     => 'fr, datr, sb',
				'provider' => 'Facebook / Meta',
				'category' => 'marketing',
				'purpose'  => 'Visualización de publicaciones y vídeos incrustados de Facebook.',
				'duration' => '3 meses / 2 años',
			],
		],
	];
}

/**
 * Returns merged cookie settings.
 *
 * @return array<string, mixed>
 */
function alminuto_cookies_get_settings() {
	$defaults = alminuto_cookies_defaults();
	$saved    = get_option( 'alminuto_cookies_settings', [] );
	if ( ! is_array( $saved ) ) {
		$saved = [];
	}
	$merged = array_merge( $defaults, $saved );
	if ( ! isset( $merged['cookies_list'] ) || ! is_array( $merged['cookies_list'] ) ) {
		$merged['cookies_list'] = $defaults['cookies_list'];
	}
	return $merged;
}

/**
 * Resolves a relative or absolute legal URL safely.
 *
 * @param string $url Raw URL or path.
 * @return string
 */
function alminuto_cookies_resolve_url( $url ) {
	$url = trim( (string) $url );
	if ( $url === '' ) {
		return home_url( '/' );
	}
	if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
		return esc_url( $url );
	}
	return esc_url( home_url( '/' . ltrim( $url, '/' ) ) );
}

/**
 * Formats the banner text replacing {cookies}, {privacidad}, {aviso} placeholders with safe links.
 *
 * @param array<string, mixed> $settings Cookie settings.
 * @return string Safe HTML.
 */
function alminuto_cookies_format_banner_html( $settings ) {
	$text = (string) $settings['banner_text'];
	$text = esc_html( $text );

	$cookies_link = sprintf(
		'<a href="%s" class="am-cookies-link">%s</a>',
		alminuto_cookies_resolve_url( $settings['cookies_url'] ),
		esc_html__( 'Política de Cookies', 'alminuto-theme' )
	);
	$privacy_link = sprintf(
		'<a href="%s" class="am-cookies-link" rel="privacy-policy">%s</a>',
		alminuto_cookies_resolve_url( $settings['privacy_url'] ),
		esc_html__( 'Política de Privacidad', 'alminuto-theme' )
	);
	$legal_link   = sprintf(
		'<a href="%s" class="am-cookies-link">%s</a>',
		alminuto_cookies_resolve_url( $settings['legal_url'] ),
		esc_html__( 'Aviso Legal', 'alminuto-theme' )
	);

	$has_placeholders = (
		strpos( $text, '{cookies}' ) !== false ||
		strpos( $text, '{privacidad}' ) !== false ||
		strpos( $text, '{aviso}' ) !== false
	);

	$html = strtr(
		$text,
		[
			'{cookies}'    => $cookies_link,
			'{privacidad}' => $privacy_link,
			'{aviso}'      => $legal_link,
		]
	);

	if ( ! $has_placeholders ) {
		$html .= ' <span class="am-cookies-legal-inline">' . $cookies_link . ' · ' . $privacy_link . ' · ' . $legal_link . '</span>';
	}

	return $html;
}

/**
 * Outputs Google Consent Mode v2 default state in <head> before analytics/GTM scripts.
 */
function alminuto_cookies_head_consent_mode() {
	if ( is_admin() ) {
		return;
	}
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['enable_consent_mode'] ) ) {
		return;
	}
	$version = (int) $settings['policy_version'];
	?>
<script data-alminuto-consent-mode="1">
(function(){
  window.dataLayer = window.dataLayer || [];
  function gtag(){ window.dataLayer.push(arguments); }
  window.gtag = window.gtag || gtag;
  var state = { analytics: false, marketing: false, preferences: false };
  try {
    var m = document.cookie.match(/(?:^|;\s*)am_cookie_consent=([^;]+)/);
    if (m) {
      var parsed = JSON.parse(decodeURIComponent(m[1]));
      if (parsed && Number(parsed.version) === <?php echo (int) $version; ?> && parsed.categories) {
        state.analytics = !!parsed.categories.analytics;
        state.marketing = !!parsed.categories.marketing;
        state.preferences = !!parsed.categories.preferences;
      }
    }
  } catch (e) {}
  gtag('consent', 'default', {
    analytics_storage: state.analytics ? 'granted' : 'denied',
    ad_storage: state.marketing ? 'granted' : 'denied',
    ad_user_data: state.marketing ? 'granted' : 'denied',
    ad_personalization: state.marketing ? 'granted' : 'denied',
    functionality_storage: state.preferences ? 'granted' : 'denied',
    personalization_storage: state.preferences ? 'granted' : 'denied',
    security_storage: 'granted',
    wait_for_update: 500
  });
})();
</script>
	<?php
}
add_action( 'wp_head', 'alminuto_cookies_head_consent_mode', 1 );

/**
 * Enqueues cookie consent CSS and JS on the frontend.
 */
function alminuto_cookies_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	$css_file = get_stylesheet_directory() . '/assets/css/alminuto-cookies.css';
	$js_file  = get_stylesheet_directory() . '/assets/js/alminuto-cookies.js';
	$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : '1.0.0';
	$js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : '1.0.0';

	wp_enqueue_style(
		'alminuto-cookies',
		get_stylesheet_directory_uri() . '/assets/css/alminuto-cookies.css',
		[ 'alminuto-theme' ],
		$css_ver
	);

	wp_enqueue_script(
		'alminuto-cookies',
		get_stylesheet_directory_uri() . '/assets/js/alminuto-cookies.js',
		[],
		$js_ver,
		true
	);

	wp_localize_script(
		'alminuto-cookies',
		'alminutoCookiesConfig',
		[
			'cookieName'        => 'am_cookie_consent',
			'version'           => (int) $settings['policy_version'],
			'consentDays'       => (int) $settings['consent_days'],
			'enableConsentMode' => ! empty( $settings['enable_consent_mode'] ),
			'categories'        => [
				'necessary'   => true,
				'preferences' => ! empty( $settings['cat_preferences_enabled'] ),
				'analytics'   => ! empty( $settings['cat_analytics_enabled'] ),
				'marketing'   => ! empty( $settings['cat_marketing_enabled'] ),
			],
		]
	);
}
add_action( 'wp_enqueue_scripts', 'alminuto_cookies_enqueue_assets', 20 );

/**
 * Blocks third-party YouTube and Facebook iframes in post content until marketing consent is given.
 *
 * @param string $content Post content HTML.
 * @return string
 */
function alminuto_cookies_block_content_embeds( $content ) {
	if ( is_admin() || is_feed() || ! is_string( $content ) || $content === '' ) {
		return $content;
	}
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['block_embeds'] ) || empty( $settings['cat_marketing_enabled'] ) ) {
		return $content;
	}

	if ( stripos( $content, '<iframe' ) === false ) {
		return $content;
	}

	$placeholder_tpl = '<div class="am-cookie-embed-box" data-am-cookie-embed="marketing">%s'
		. '<div class="am-cookie-embed-placeholder" role="region" aria-label="' . esc_attr__( 'Contenido externo bloqueado', 'alminuto-theme' ) . '">'
		. '<p class="am-cookie-embed-title">' . esc_html__( 'Contenido externo bloqueado por privacidad', 'alminuto-theme' ) . '</p>'
		. '<p class="am-cookie-embed-desc">' . esc_html__( 'Este vídeo o publicación pertenece a un servicio externo (YouTube / Facebook) que puede instalar cookies de terceros.', 'alminuto-theme' ) . '</p>'
		. '<div class="am-cookie-embed-actions">'
		. '<button type="button" class="am-cookie-btn am-cookie-btn--primary" data-am-accept-category="marketing">' . esc_html__( 'Aceptar y mostrar contenido', 'alminuto-theme' ) . '</button>'
		. '<button type="button" class="am-cookie-btn am-cookie-btn--outline" data-am-cookies-open="1">' . esc_html( $settings['btn_settings'] ) . '</button>'
		. '</div></div></div>';

	$result = preg_replace_callback(
		'/<iframe\b([^>]*)\bsrc=(["\'])([^"\']+)\2([^>]*)><\/iframe>/is',
		static function ( $matches ) use ( $placeholder_tpl ) {
			$src = $matches[3];
			if ( ! preg_match( '/(youtube\.com|youtube-nocookie\.com|facebook\.com|web\.facebook\.com)/i', $src ) ) {
				return $matches[0];
			}
			$attrs_before = $matches[1];
			$attrs_after  = $matches[4];
			$blocked_tag  = '<iframe' . $attrs_before . ' data-am-src="' . esc_attr( $src ) . '" src="about:blank" data-am-cookie-category="marketing"' . $attrs_after . '></iframe>';
			return sprintf( $placeholder_tpl, $blocked_tag );
		},
		$content
	);

	return is_string( $result ) ? $result : $content;
}
add_filter( 'the_content', 'alminuto_cookies_block_content_embeds', 99 );

/**
 * Renders the cookie banner, preferences modal (<dialog>), floating button and deferred script templates.
 */
function alminuto_cookies_render_frontend_ui() {
	if ( is_admin() ) {
		return;
	}
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	$layout_class = $settings['layout'] === 'card' ? 'am-cookie-banner--card' : 'am-cookie-banner--bar';
	$banner_html  = alminuto_cookies_format_banner_html( $settings );
	?>
	<div id="am-cookie-banner" class="am-cookie-banner <?php echo esc_attr( $layout_class ); ?>" role="region" aria-label="<?php esc_attr_e( 'Aviso de cookies', 'alminuto-theme' ); ?>" hidden>
		<div class="am-cookie-banner-inner">
			<div class="am-cookie-banner-copy">
				<?php if ( ! empty( $settings['banner_title'] ) ) : ?>
					<p class="am-cookie-banner-title"><?php echo esc_html( $settings['banner_title'] ); ?></p>
				<?php endif; ?>
				<p class="am-cookie-banner-text"><?php echo wp_kses_post( $banner_html ); ?></p>
			</div>
			<div class="am-cookie-banner-actions">
				<button type="button" class="am-cookie-btn am-cookie-btn--equal" data-am-action="accept-all"><?php echo esc_html( $settings['btn_accept'] ); ?></button>
				<button type="button" class="am-cookie-btn am-cookie-btn--equal" data-am-action="reject-all"><?php echo esc_html( $settings['btn_reject'] ); ?></button>
				<button type="button" class="am-cookie-btn am-cookie-btn--equal am-cookie-btn--outline" data-am-action="open-settings"><?php echo esc_html( $settings['btn_settings'] ); ?></button>
			</div>
		</div>
	</div>

	<dialog id="am-cookies-modal" class="am-cookie-modal" aria-labelledby="am-cookies-modal-title" closedby="any">
		<div class="am-cookie-modal-box">
			<div class="am-cookie-modal-header">
				<h1 id="am-cookies-modal-title" class="am-cookie-modal-title"><?php esc_html_e( 'Configuración de cookies', 'alminuto-theme' ); ?></h1>
				<button type="button" class="am-cookie-modal-close" data-am-action="close-modal" aria-label="<?php esc_attr_e( 'Cerrar configuración de cookies', 'alminuto-theme' ); ?>">&times;</button>
			</div>
			<div class="am-cookie-modal-body">
				<p class="am-cookie-modal-intro">
					<?php esc_html_e( 'Aquí puedes gestionar tus preferencias sobre el uso de cookies. Las cookies técnicas son imprescindibles para el funcionamiento básico de la web y no pueden desactivarse.', 'alminuto-theme' ); ?>
				</p>
				<p class="am-cookie-modal-links">
					<a href="<?php echo alminuto_cookies_resolve_url( $settings['cookies_url'] ); ?>"><?php esc_html_e( 'Política de Cookies', 'alminuto-theme' ); ?></a>
					<span aria-hidden="true">·</span>
					<a href="<?php echo alminuto_cookies_resolve_url( $settings['privacy_url'] ); ?>" rel="privacy-policy"><?php esc_html_e( 'Política de Privacidad', 'alminuto-theme' ); ?></a>
					<span aria-hidden="true">·</span>
					<a href="<?php echo alminuto_cookies_resolve_url( $settings['legal_url'] ); ?>"><?php esc_html_e( 'Aviso Legal', 'alminuto-theme' ); ?></a>
				</p>

				<div class="am-cookie-categories">
					<div class="am-cookie-cat">
						<div class="am-cookie-cat-head">
							<div>
								<strong class="am-cookie-cat-name"><?php esc_html_e( 'Cookies técnicas o necesarias', 'alminuto-theme' ); ?></strong>
								<span class="am-cookie-cat-badge"><?php esc_html_e( 'Siempre activas', 'alminuto-theme' ); ?></span>
							</div>
							<input type="checkbox" switch checked disabled aria-label="<?php esc_attr_e( 'Cookies técnicas siempre activas', 'alminuto-theme' ); ?>">
						</div>
						<p class="am-cookie-cat-desc"><?php esc_html_e( 'Imprescindibles para la navegación, seguridad y para recordar tu elección de consentimiento.', 'alminuto-theme' ); ?></p>
					</div>

					<?php if ( ! empty( $settings['cat_preferences_enabled'] ) ) : ?>
					<div class="am-cookie-cat">
						<div class="am-cookie-cat-head">
							<label for="am-cookie-toggle-preferences" class="am-cookie-cat-name"><?php esc_html_e( 'Cookies de preferencias', 'alminuto-theme' ); ?></label>
							<input id="am-cookie-toggle-preferences" type="checkbox" switch data-am-category-toggle="preferences">
						</div>
						<p class="am-cookie-cat-desc"><?php esc_html_e( 'Permiten recordar ajustes personalizados de visualización o idioma.', 'alminuto-theme' ); ?></p>
					</div>
					<?php endif; ?>

					<?php if ( ! empty( $settings['cat_analytics_enabled'] ) ) : ?>
					<div class="am-cookie-cat">
						<div class="am-cookie-cat-head">
							<label for="am-cookie-toggle-analytics" class="am-cookie-cat-name"><?php esc_html_e( 'Cookies de analítica y medición', 'alminuto-theme' ); ?></label>
							<input id="am-cookie-toggle-analytics" type="checkbox" switch data-am-category-toggle="analytics">
						</div>
						<p class="am-cookie-cat-desc"><?php esc_html_e( 'Nos ayudan a medir de forma agregada las visitas y el rendimiento de los contenidos (por ejemplo, Google Analytics).', 'alminuto-theme' ); ?></p>
					</div>
					<?php endif; ?>

					<?php if ( ! empty( $settings['cat_marketing_enabled'] ) ) : ?>
					<div class="am-cookie-cat">
						<div class="am-cookie-cat-head">
							<label for="am-cookie-toggle-marketing" class="am-cookie-cat-name"><?php esc_html_e( 'Cookies de marketing y redes sociales', 'alminuto-theme' ); ?></label>
							<input id="am-cookie-toggle-marketing" type="checkbox" switch data-am-category-toggle="marketing">
						</div>
						<p class="am-cookie-cat-desc"><?php esc_html_e( 'Necesarias para mostrar vídeos y publicaciones incrustadas de terceros (YouTube, Facebook) o publicidad personalizada.', 'alminuto-theme' ); ?></p>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<div class="am-cookie-modal-footer">
				<button type="button" class="am-cookie-btn am-cookie-btn--outline" data-am-action="reject-all"><?php echo esc_html( $settings['btn_reject'] ); ?></button>
				<button type="button" class="am-cookie-btn am-cookie-btn--outline" data-am-action="save-settings"><?php esc_html_e( 'Guardar preferencias', 'alminuto-theme' ); ?></button>
				<button type="button" class="am-cookie-btn" data-am-action="accept-all"><?php echo esc_html( $settings['btn_accept'] ); ?></button>
			</div>
		</div>
	</dialog>

	<?php if ( ! empty( $settings['show_floating_btn'] ) ) : ?>
		<button type="button" class="am-cookie-floating-trigger" data-am-cookies-open="1" aria-label="<?php echo esc_attr( $settings['footer_link_text'] ); ?>">
			<?php echo esc_html( $settings['footer_link_text'] ); ?>
		</button>
	<?php endif; ?>

	<?php
	if ( ! empty( $settings['cat_analytics_enabled'] ) && trim( (string) $settings['scripts_analytics'] ) !== '' ) {
		echo '<template id="am-cookie-tpl-analytics" data-am-cookie-category="analytics">' . $settings['scripts_analytics'] . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( ! empty( $settings['cat_marketing_enabled'] ) && trim( (string) $settings['scripts_marketing'] ) !== '' ) {
		echo '<template id="am-cookie-tpl-marketing" data-am-cookie-category="marketing">' . $settings['scripts_marketing'] . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_footer', 'alminuto_cookies_render_frontend_ui', 90 );

/**
 * Shortcode [alminuto_cookie_settings] to render a button that opens the cookie preferences modal.
 *
 * @param array<string, string> $atts Shortcode attributes.
 * @return string
 */
function alminuto_cookies_shortcode_settings( $atts ) {
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return '';
	}
	$atts = shortcode_atts(
		[
			'label' => $settings['footer_link_text'],
		],
		$atts,
		'alminuto_cookie_settings'
	);
	return '<button type="button" class="am-btn am-cookie-shortcode-btn" data-am-cookies-open="1">' . esc_html( $atts['label'] ) . '</button>';
}
add_shortcode( 'alminuto_cookie_settings', 'alminuto_cookies_shortcode_settings' );

/**
 * Shortcode [alminuto_cookie_declaration] to render the configured cookie declaration table.
 *
 * @return string
 */
function alminuto_cookies_shortcode_declaration() {
	$settings = alminuto_cookies_get_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['cookies_list'] ) ) {
		return '';
	}

	$labels = [
		'necessary'   => __( 'Técnica / Necesaria', 'alminuto-theme' ),
		'preferences' => __( 'Preferencias', 'alminuto-theme' ),
		'analytics'   => __( 'Analítica', 'alminuto-theme' ),
		'marketing'   => __( 'Marketing / Redes', 'alminuto-theme' ),
	];

	$out  = '<div class="am-cookie-declaration-wrap"><table class="am-cookie-declaration-table"><thead><tr>';
	$out .= '<th>' . esc_html__( 'Cookie', 'alminuto-theme' ) . '</th>';
	$out .= '<th>' . esc_html__( 'Proveedor', 'alminuto-theme' ) . '</th>';
	$out .= '<th>' . esc_html__( 'Categoría', 'alminuto-theme' ) . '</th>';
	$out .= '<th>' . esc_html__( 'Finalidad', 'alminuto-theme' ) . '</th>';
	$out .= '<th>' . esc_html__( 'Duración', 'alminuto-theme' ) . '</th>';
	$out .= '</tr></thead><tbody>';

	foreach ( $settings['cookies_list'] as $row ) {
		$cat_key   = isset( $row['category'] ) ? (string) $row['category'] : 'necessary';
		$cat_label = isset( $labels[ $cat_key ] ) ? $labels[ $cat_key ] : $cat_key;
		$out      .= '<tr>';
		$out      .= '<td><code>' . esc_html( (string) ( $row['name'] ?? '' ) ) . '</code></td>';
		$out      .= '<td>' . esc_html( (string) ( $row['provider'] ?? '' ) ) . '</td>';
		$out      .= '<td>' . esc_html( $cat_label ) . '</td>';
		$out      .= '<td>' . esc_html( (string) ( $row['purpose'] ?? '' ) ) . '</td>';
		$out      .= '<td>' . esc_html( (string) ( $row['duration'] ?? '' ) ) . '</td>';
		$out      .= '</tr>';
	}

	$out .= '</tbody></table></div>';
	return $out;
}
add_shortcode( 'alminuto_cookie_declaration', 'alminuto_cookies_shortcode_declaration' );
