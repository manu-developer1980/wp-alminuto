<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columna_html = function_exists( 'alminuto_theme_right_column_html' ) ? alminuto_theme_right_column_html() : '';

if ( trim( wp_strip_all_tags( (string) $columna_html ) ) !== '' ) {
	echo $columna_html;
} elseif ( is_active_sidebar( 'sidebar-right' ) ) {
	dynamic_sidebar( 'sidebar-right' );
}
