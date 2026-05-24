<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function alminuto_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus(
		[
			'primary' => __( 'Menú principal', 'alminuto-theme' ),
		]
	);
}
add_action( 'after_setup_theme', 'alminuto_theme_setup' );

function alminuto_theme_enqueue_assets() {
	wp_enqueue_style( 'alminuto-theme', get_stylesheet_uri(), [], '0.1.0' );
}
add_action( 'wp_enqueue_scripts', 'alminuto_theme_enqueue_assets' );

function alminuto_theme_register_sidebars() {
	register_sidebar(
		[
			'name'          => __( 'Sidebar derecha', 'alminuto-theme' ),
			'id'            => 'sidebar-right',
			'before_widget' => '<div class="am-card"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);

	register_sidebar(
		[
			'name'          => __( 'Header (banners)', 'alminuto-theme' ),
			'id'            => 'header-banners',
			'before_widget' => '<div class="am-card" style="margin-bottom:14px;"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);
}
add_action( 'widgets_init', 'alminuto_theme_register_sidebars' );

function alminuto_theme_share_links( $url, $title ) {
	$encoded_url   = rawurlencode( $url );
	$encoded_title = rawurlencode( $title );

	return [
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
		'twitter'  => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
		'whatsapp' => 'https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url,
	];
}
