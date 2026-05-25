<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function alminuto_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'custom-logo',
		[
			'height'      => 143,
			'width'       => 400,
			'flex-height' => true,
			'flex-width'  => true,
		]
	);

	register_nav_menus(
		[
			'primary' => __( 'Menú principal', 'alminuto-theme' ),
		]
	);
}
add_action( 'after_setup_theme', 'alminuto_theme_setup' );

function alminuto_theme_enqueue_assets() {
	$css_path = get_stylesheet_directory() . '/style.css';
	$version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '0.1.0';
	wp_enqueue_style( 'alminuto-theme', get_stylesheet_uri(), [], $version );
	wp_enqueue_style( 'font-awesome', 'https://use.fontawesome.com/releases/v5.15.4/css/all.css', [], '5.15.4' );

	wp_register_script( 'alminuto-theme', '', [], $version, true );
	wp_enqueue_script( 'alminuto-theme' );
	wp_add_inline_script(
		'alminuto-theme',
		'(function(){function init(){var btn=document.querySelector(".am-nav-toggle");if(!btn){return}var menu=document.getElementById("am-primary-menu");if(!menu){return}function sync(){var mobile=window.matchMedia("(max-width: 768px)").matches;if(!mobile){btn.setAttribute("aria-expanded","true");menu.hidden=false;return}btn.setAttribute("aria-expanded","false");menu.hidden=true}sync();window.addEventListener("resize",sync);btn.addEventListener("click",function(){if(!window.matchMedia("(max-width: 768px)").matches){return}var expanded=btn.getAttribute("aria-expanded")==="true";btn.setAttribute("aria-expanded",expanded?"false":"true");menu.hidden=expanded;});}document.addEventListener("DOMContentLoaded",init);})();'
	);
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

	register_sidebar(
		[
			'name'          => __( 'Top derecha (home)', 'alminuto-theme' ),
			'id'            => 'top-right',
			'before_widget' => '<div class="am-card"><div class="am-card-body">',
			'after_widget'  => '</div></div>',
			'before_title'  => '<h3 style="margin:0 0 10px;font-size:16px;font-weight:900;">',
			'after_title'   => '</h3>',
		]
	);
}
add_action( 'widgets_init', 'alminuto_theme_register_sidebars' );

function alminuto_theme_force_front_page_template( $template ) {
	if ( is_front_page() ) {
		$front = locate_template( 'front-page.php' );
		if ( $front ) {
			return $front;
		}
	}

	return $template;
}
add_filter( 'template_include', 'alminuto_theme_force_front_page_template', 20 );

function alminuto_theme_share_links( $url, $title ) {
	$encoded_url   = rawurlencode( $url );
	$encoded_title = rawurlencode( $title );

	return [
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
		'twitter'  => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
		'whatsapp' => 'https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url,
		'telegram' => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
	];
}

function alminuto_theme_post_meta_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return '';
	}

	$date = get_the_date( 'd/m/Y', $post_id );
	$time = get_the_time( 'H:i', $post_id );
	$author_id   = (int) get_post_field( 'post_author', $post_id );
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$avatar      = get_avatar( $author_id, 24, '', $author_name, [ 'class' => 'am-post-info-avatar' ] );

	$icon_calendar = '<i aria-hidden="true" class="fas fa-calendar"></i>';
	$icon_clock    = '<i aria-hidden="true" class="fas fa-clock"></i>';

	$out  = '<ul class="am-post-info">';
	$out .= '<li class="am-post-info-item am-post-info-item--date"><span class="am-post-info-icon">' . $icon_calendar . '</span><span class="am-post-info-text">' . esc_html( $date ) . '</span></li>';
	$out .= '<li class="am-post-info-item am-post-info-item--time"><span class="am-post-info-icon">' . $icon_clock . '</span><span class="am-post-info-text">' . esc_html( $time ) . '</span></li>';
	$out .= '<li class="am-post-info-item am-post-info-item--author"><span class="am-post-info-icon"><i aria-hidden="true" class="fas fa-user"></i></span><span class="am-post-info-text">' . esc_html( $author_name ) . '</span></li>';
	$out .= '</ul>';

	return wp_kses(
		$out,
		[
			'ul'   => [ 'class' => true ],
			'li'   => [ 'class' => true ],
			'span' => [ 'class' => true ],
			'i'    => [ 'class' => true, 'aria-hidden' => true ],
			'img'  => [
				'class'    => true,
				'src'      => true,
				'srcset'   => true,
				'sizes'    => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			],
		]
	);
}

function alminuto_theme_disable_comments_support() {
	$post_types = get_post_types( [ 'public' => true ], 'names' );
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'alminuto_theme_disable_comments_support', 100 );

function alminuto_theme_force_comments_closed( $data, $postarr ) {
	if ( is_array( $data ) ) {
		$data['comment_status'] = 'closed';
		$data['ping_status']    = 'closed';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'alminuto_theme_force_comments_closed', 10, 2 );

add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );
add_filter( 'comments_array', '__return_empty_array', 20, 2 );
