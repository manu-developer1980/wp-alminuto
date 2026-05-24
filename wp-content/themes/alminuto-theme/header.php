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
			<a class="am-pill" href="<?php echo esc_url( home_url( '/' ) ); ?>">Radio</a>
			<div class="am-social">
				<a href="#" aria-label="Facebook">f</a>
				<a href="#" aria-label="Twitter">x</a>
				<a href="#" aria-label="YouTube">▶</a>
				<a href="#" aria-label="Instagram">⌁</a>
			</div>
		</div>
	</div>
</div>

<header class="am-header">
	<div class="am-container">
		<div class="am-header-inner">
			<div class="am-brand">
				<div class="am-logo">a</div>
				<div class="am-title">
					<?php bloginfo( 'name' ); ?>
					<small><?php bloginfo( 'description' ); ?></small>
				</div>
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

<main class="am-main">
	<div class="am-container">

