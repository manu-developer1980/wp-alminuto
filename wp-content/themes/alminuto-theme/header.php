<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="am-topbar">
	<div class="am-container">
		<div class="am-topbar-inner">
			<a class="am-pill" href="https://players.emitironline.com/v6/index.php?url=http%3A%2F%2Fserver10.emitironline.com%3A11022%2Fstream&amp;codec=aac&amp;volume=80&amp;autoplay=true&amp;buffering=2&amp;user=algeciras&amp;server=server10&amp;title=Algeciras+Al+Minuto+Radio" target="_blank" rel="nofollow noopener noreferrer">▶ Radio</a>
			<div class="am-social">
				<a href="https://www.facebook.com/alminuto.es" target="_blank" rel="nofollow noopener noreferrer" aria-label="Facebook">f</a>
				<a href="https://twitter.com/minutoes" target="_blank" rel="nofollow noopener noreferrer" aria-label="Twitter">x</a>
				<a href="https://www.youtube.com/channel/UCmTtcXFO8inBLtLFEBKbo3w/videos" target="_blank" rel="nofollow noopener noreferrer" aria-label="YouTube">▶</a>
				<a href="https://instagram.com/algeciras_al_minuto" target="_blank" rel="nofollow noopener noreferrer" aria-label="Instagram">⌁</a>
			</div>
		</div>
	</div>
</div>

<header class="am-header">
	<div class="am-container">
		<div class="am-header-inner">
			<div class="am-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:flex;align-items:center;gap:12px;">
					<?php if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<div class="am-logo">a</div>
						<div class="am-title">
							<?php bloginfo( 'name' ); ?>
							<small><?php bloginfo( 'description' ); ?></small>
						</div>
					<?php endif; ?>
				</a>
			</div>
			<?php if ( is_active_sidebar( 'header-banners' ) ) : ?>
				<?php dynamic_sidebar( 'header-banners' ); ?>
			<?php endif; ?>
		</div>
	</div>
</header>

<nav class="am-nav" aria-label="<?php esc_attr_e( 'Menú principal', 'alminuto-theme' ); ?>">
	<div class="am-container">
		<div class="am-nav-inner">
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'am-menu',
					'fallback_cb'    => '__return_empty_string',
				]
			);
			?>
		</div>
	</div>
</nav>

<section class="am-top-banners" id="bannersSuperiores">
	<div class="am-container">
		<div class="am-top-banners-grid">
			<div class="am-top-banner am-top-banner--left">
				<?php if ( function_exists( 'do_shortcode' ) ) : ?>
					<?php echo do_shortcode( '[banners_alminuto slot="top_left" slider="1" limit="10" autoplay="9500"]' ); ?>
				<?php endif; ?>
			</div>
			<div class="am-top-banner am-top-banner--mid">
				<?php if ( function_exists( 'do_shortcode' ) ) : ?>
					<?php echo do_shortcode( '[banners_alminuto slot="top_mid" limit="10" autoplay="9500"]' ); ?>
				<?php endif; ?>
			</div>
			<aside class="am-top-banner-sidebar">
				<?php
				$columna_html = '';
				if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'alminuto_sidebar_right' ) ) {
					$columna_html = do_shortcode( '[alminuto_sidebar_right]' );
				}
				if ( trim( wp_strip_all_tags( $columna_html ) ) !== '' ) {
					echo wp_kses_post( $columna_html );
				} elseif ( is_active_sidebar( 'top-right' ) ) {
					dynamic_sidebar( 'top-right' );
				}
				?>
			</aside>
		</div>
	</div>
</section>

<main class="am-main">
	<div class="am-container">
