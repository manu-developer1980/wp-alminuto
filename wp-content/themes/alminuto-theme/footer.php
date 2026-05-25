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
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="am-footer-logo-link">
						<?php if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<img src="<?php echo esc_url( content_url( '/uploads/logo-algeciras-600x300-transparente-e1615641431159.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="400" height="143" loading="lazy">
						<?php endif; ?>
					</a>
				</div>
				<div class="am-footer-col am-footer-col--contact">
					<a class="am-footer-contact" href="mailto:redaccion@algecirasalminuto.es" target="_blank" rel="nofollow noopener noreferrer">
						<span class="am-footer-contact-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
						<span class="am-footer-contact-text">redaccion@algecirasalminuto.es</span>
					</a>
				</div>
				<div class="am-footer-col am-footer-col--links" aria-label="<?php esc_attr_e( 'Enlaces legales', 'alminuto-theme' ); ?>">
					<ul class="am-footer-links">
						<li><a href="<?php echo esc_url( home_url( '/aviso-legal/' ) ); ?>">Aviso Legal</a></li>
						<li><a href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>" rel="privacy-policy">Política de Privacidad</a></li>
						<li><a href="<?php echo esc_url( home_url( '/politica-de-cookies/' ) ); ?>">Política de Cookies</a></li>
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
