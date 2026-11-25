<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	</div>
</main>

<footer class="am-footer">
	<div class="am-container">
		<?php
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$site_host = $site_host ? $site_host : get_bloginfo( 'name' );
		?>
		<div class="am-footer-top">
			<div class="am-footer-cols">
				<div class="am-footer-col am-footer-col--logo">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="am-footer-logo-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
						<?php if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<img src="<?php echo esc_url( content_url( '/uploads/logo-algeciras-600x300-transparente-e1615641431159.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="400" height="143" loading="lazy">
						<?php endif; ?>
					</a>
				</div>
				<div class="am-footer-col am-footer-col--contact">
					<a class="am-footer-contact" href="mailto:redaccion@algecirasalminuto.es" rel="nofollow noopener noreferrer">
						<span class="am-footer-contact-icon" aria-hidden="true"><svg viewBox="0 0 512 512" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L308.8 328.8c-30.4 22.8-72.1 22.8-102.5 0L0 176z"/></svg></span>
						<span class="am-footer-contact-text">redaccion@algecirasalminuto.es</span>
					</a>
				</div>
				<div class="am-footer-col am-footer-col--links" aria-label="<?php esc_attr_e( 'Enlaces legales', 'alminuto-theme' ); ?>">
					<?php
					$am_cookies_settings = function_exists( 'alminuto_cookies_get_settings' ) ? alminuto_cookies_get_settings() : [];
					$am_legal_url        = ! empty( $am_cookies_settings['legal_url'] ) ? alminuto_cookies_resolve_url( $am_cookies_settings['legal_url'] ) : home_url( '/aviso-legal/' );
					$am_privacy_url      = ! empty( $am_cookies_settings['privacy_url'] ) ? alminuto_cookies_resolve_url( $am_cookies_settings['privacy_url'] ) : home_url( '/politica-de-privacidad/' );
					$am_cookies_url      = ! empty( $am_cookies_settings['cookies_url'] ) ? alminuto_cookies_resolve_url( $am_cookies_settings['cookies_url'] ) : home_url( '/politica-de-cookies/' );
					?>
					<ul class="am-footer-links">
						<li><a href="<?php echo esc_url( $am_legal_url ); ?>">Aviso Legal</a></li>
						<li><a href="<?php echo esc_url( $am_privacy_url ); ?>" rel="privacy-policy">Política de Privacidad</a></li>
						<li><a href="<?php echo esc_url( $am_cookies_url ); ?>">Política de Cookies</a></li>
						<?php if ( ! empty( $am_cookies_settings['enabled'] ) && ! empty( $am_cookies_settings['show_footer_link'] ) ) : ?>
							<li><button type="button" class="am-footer-cookie-btn" data-am-cookies-open="1"><?php echo esc_html( $am_cookies_settings['footer_link_text'] ); ?></button></li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
		</div>
		<div class="am-footer-bottom">
			<p class="am-footer-copyright">Copyright © <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_host ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
