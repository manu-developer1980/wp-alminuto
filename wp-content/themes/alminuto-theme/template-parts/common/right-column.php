<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columna_html = '';
if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'alminuto_sidebar_right' ) ) {
	$columna_html = do_shortcode( '[alminuto_sidebar_right]' );
}

if ( trim( wp_strip_all_tags( $columna_html ) ) !== '' ) {
	$allowed = wp_kses_allowed_html( 'post' );
	$allowed['iframe'] = [
		'src'             => true,
		'width'           => true,
		'height'          => true,
		'frameborder'     => true,
		'allow'           => true,
		'allowfullscreen' => true,
		'loading'         => true,
		'referrerpolicy'  => true,
		'title'           => true,
		'scrolling'       => true,
	];
	echo wp_kses( $columna_html, $allowed );
} elseif ( is_active_sidebar( 'sidebar-right' ) ) {
	dynamic_sidebar( 'sidebar-right' );
}
