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

function alminuto_theme_settings_defaults() {
	return [
		'home_left_posts'  => 20,
		'home_right_posts' => 20,
	];
}

function alminuto_theme_get_settings() {
	$defaults = alminuto_theme_settings_defaults();
	$raw      = get_option( 'alminuto_theme_settings', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	return array_merge( $defaults, $raw );
}

function alminuto_theme_admin_menu() {
	add_menu_page(
		'Al Minuto',
		'Al Minuto',
		'manage_options',
		'alminuto-theme-panel',
		'alminuto_theme_render_admin_page',
		'dashicons-admin-generic',
		2.1
	);
}
add_action( 'admin_menu', 'alminuto_theme_admin_menu' );

function alminuto_theme_admin_enqueue( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_alminuto-theme-panel' ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_media();

	wp_register_style( 'alminuto-theme-admin', false );
	wp_enqueue_style( 'alminuto-theme-admin' );
	wp_add_inline_style(
		'alminuto-theme-admin',
		'.toplevel_page_alminuto-theme-panel .am-admin-wrap{max-width:1100px}.toplevel_page_alminuto-theme-panel .am-admin-grid{display:grid;gap:12px}.toplevel_page_alminuto-theme-panel .am-admin-card{background:#fff;border:1px solid #dcdcde;padding:12px}.toplevel_page_alminuto-theme-panel .am-admin-card h2{margin:0 0 10px;font-size:15px}.toplevel_page_alminuto-theme-panel .am-admin-card p.am-help{margin:0 0 10px;color:#50575e}.toplevel_page_alminuto-theme-panel .am-field{display:grid;gap:6px;margin-top:10px}.toplevel_page_alminuto-theme-panel .am-field label{font-weight:600}.toplevel_page_alminuto-theme-panel .am-actions{display:flex;gap:8px;flex-wrap:wrap}.toplevel_page_alminuto-theme-panel .am-thumb{width:100px;flex:0 0 auto}.toplevel_page_alminuto-theme-panel .am-thumb img{width:100%;height:auto;display:block}.toplevel_page_alminuto-theme-panel .am-gallery-list{margin:10px 0 0;display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-item{border:1px solid #dcdcde;background:#fff;padding:10px;display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-row{display:flex;gap:10px;align-items:center}.toplevel_page_alminuto-theme-panel .am-gallery-handle{cursor:move;color:#50575e}.toplevel_page_alminuto-theme-panel .am-gallery-meta{display:grid;gap:8px}.toplevel_page_alminuto-theme-panel .am-gallery-meta input[type=url]{width:100%}.toplevel_page_alminuto-theme-panel .am-gallery-remove{margin-left:auto}.toplevel_page_alminuto-theme-panel .am-submit{margin-top:12px}@media (min-width: 960px){.toplevel_page_alminuto-theme-panel .am-admin-grid{grid-template-columns:1fr 1fr}.toplevel_page_alminuto-theme-panel .am-admin-card--full{grid-column:1 / -1}}'
	);
}
add_action( 'admin_enqueue_scripts', 'alminuto_theme_admin_enqueue' );

function alminuto_theme_maybe_migrate_plugin_data() {
	$right = get_option( 'alminuto_theme_right_column', null );
	if ( $right === null ) {
		$legacy = get_option( 'alminuto_sidebar_right', null );
		if ( is_array( $legacy ) ) {
			update_option( 'alminuto_theme_right_column', $legacy, false );
		}
	}

	$banners = get_option( 'alminuto_theme_banners', null );
	if ( $banners === null ) {
		$legacy = get_option( 'banners_alminuto_slots', null );
		if ( is_array( $legacy ) ) {
			update_option( 'alminuto_theme_banners', $legacy, false );
		}
	}
}
add_action( 'init', 'alminuto_theme_maybe_migrate_plugin_data', 1 );

function alminuto_theme_banners_defaults() {
	return [
		'top_left' => [],
	];
}

function alminuto_theme_banners_get() {
	$defaults = alminuto_theme_banners_defaults();
	$raw      = get_option( 'alminuto_theme_banners', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$out = array_merge( $defaults, $raw );
	if ( ! is_array( $out['top_left'] ?? null ) ) {
		$out['top_left'] = [];
	}
	return $out;
}

function alminuto_theme_is_valid_date_ymd( $value ) {
	if ( ! is_string( $value ) ) {
		return false;
	}
	return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
}

function alminuto_theme_sanitize_banner_items( $raw ) {
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		$start   = isset( $row['start'] ) ? sanitize_text_field( (string) $row['start'] ) : '';
		$end     = isset( $row['end'] ) ? sanitize_text_field( (string) $row['end'] ) : '';
		if ( $id <= 0 ) {
			continue;
		}
		if ( $start !== '' && ! alminuto_theme_is_valid_date_ymd( $start ) ) {
			$start = '';
		}
		if ( $end !== '' && ! alminuto_theme_is_valid_date_ymd( $end ) ) {
			$end = '';
		}
		if ( $start !== '' && $end !== '' && strcmp( $start, $end ) > 0 ) {
			$end = '';
		}
		$out[] = [
			'id'      => $id,
			'url'     => $url,
			'new_tab' => $new_tab,
			'start'   => $start,
			'end'     => $end,
		];
	}
	return $out;
}

function alminuto_theme_banner_item_is_active( $item, $now_ts ) {
	$start = is_array( $item ) && isset( $item['start'] ) ? (string) $item['start'] : '';
	$end   = is_array( $item ) && isset( $item['end'] ) ? (string) $item['end'] : '';
	if ( $start !== '' && alminuto_theme_is_valid_date_ymd( $start ) ) {
		$start_ts = strtotime( $start . ' 00:00:00' );
		if ( $start_ts && $now_ts < $start_ts ) {
			return false;
		}
	}
	if ( $end !== '' && alminuto_theme_is_valid_date_ymd( $end ) ) {
		$end_ts = strtotime( $end . ' 23:59:59' );
		if ( $end_ts && $now_ts > $end_ts ) {
			return false;
		}
	}
	return true;
}

function alminuto_theme_banners_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'    => 10,
			'slot'     => '',
			'size'     => 'full',
			'class'    => '',
			'slider'   => 0,
			'autoplay' => 9500,
		],
		(array) $atts,
		'banners_alminuto'
	);

	$limit  = max( 1, (int) $atts['limit'] );
	$slot   = sanitize_key( (string) $atts['slot'] );
	$size   = sanitize_key( (string) $atts['size'] );
	$class  = trim( (string) $atts['class'] );
	$slider = (int) $atts['slider'] === 1 || $atts['slider'] === 'true' || $atts['slider'] === 'yes';
	$autoplay = max( 0, (int) $atts['autoplay'] );

	if ( $slot !== 'top_left' ) {
		return '';
	}

	$data = alminuto_theme_banners_get();
	$list = (array) ( $data['top_left'] ?? [] );
	$now  = (int) current_time( 'timestamp' );

	$items = [];
	foreach ( $list as $row ) {
		if ( ! alminuto_theme_banner_item_is_active( $row, $now ) ) {
			continue;
		}
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$img = wp_get_attachment_image( $id, $size, false, [ 'loading' => 'eager' ] );
		if ( ! $img ) {
			continue;
		}
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		$html    = $img;
		if ( $url ) {
			$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
			$html   = '<a href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
		}
		if ( $slider ) {
			$items[] = '<div class="bam-slide">' . $html . '</div>';
		} else {
			$items[] = '<div class="bam-item">' . $html . '</div>';
		}
		if ( count( $items ) >= $limit ) {
			break;
		}
	}

	if ( empty( $items ) ) {
		return '';
	}

	$classes = 'bam-wrap';
	if ( $class !== '' ) {
		$classes .= ' ' . sanitize_html_class( $class );
	}

	if ( ! wp_style_is( 'alminuto-theme-banners', 'enqueued' ) ) {
		wp_register_style( 'alminuto-theme-banners', false );
		wp_enqueue_style( 'alminuto-theme-banners' );
		wp_add_inline_style(
			'alminuto-theme-banners',
			'.bam-wrap{display:grid;gap:10px}.bam-item img{max-width:100%;height:auto;display:block}.bam-slider{position:relative;overflow:hidden}.bam-slide{display:none}.bam-slide.is-active{display:block}'
		);
	}
	if ( ! wp_script_is( 'alminuto-theme-banners', 'enqueued' ) ) {
		wp_register_script( 'alminuto-theme-banners', '', [], null, true );
		wp_enqueue_script( 'alminuto-theme-banners' );
		wp_add_inline_script(
			'alminuto-theme-banners',
			'(function(){function initSlider(root){var slides=root.querySelectorAll(".bam-slide");if(!slides.length){return}var idx=0;slides[0].classList.add("is-active");var autoplay=parseInt(root.getAttribute("data-autoplay")||"0",10);if(!autoplay||slides.length<2){return}var timer=null;function show(i){slides[idx].classList.remove("is-active");idx=i;slides[idx].classList.add("is-active")}function next(){show((idx+1)%slides.length)}function start(){stop();timer=setInterval(next,autoplay)}function stop(){if(timer){clearInterval(timer);timer=null}}root.addEventListener("mouseenter",stop);root.addEventListener("mouseleave",start);start()}document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bam-slider").forEach(initSlider)})})();'
		);
	}

	if ( $slider ) {
		return '<div class="bam-slider" data-autoplay="' . esc_attr( (string) $autoplay ) . '">' . implode( '', $items ) . '</div>';
	}

	return '<div class="' . esc_attr( $classes ) . '">' . implode( '', $items ) . '</div>';
}
add_shortcode( 'banners_alminuto', 'alminuto_theme_banners_shortcode' );

function alminuto_theme_right_defaults() {
	return [
		'news_rigor_image_id' => 0,
		'news_rigor_url'      => '',
		'block2_title'        => 'ALGECIRAS ES SEMANA SANTA',
		'youtube_url'         => '',
		'facebook_video_url'  => '',
		'publi_gallery'       => [],
	];
}

function alminuto_theme_right_get() {
	$defaults = alminuto_theme_right_defaults();
	$raw      = get_option( 'alminuto_theme_right_column', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$out = array_merge( $defaults, $raw );
	if ( ! is_array( $out['publi_gallery'] ?? null ) ) {
		$out['publi_gallery'] = [];
	}
	return $out;
}

function alminuto_theme_right_sanitize_gallery( $raw ) {
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$out[] = [
			'id'      => $id,
			'url'     => $url,
			'new_tab' => $new_tab,
		];
	}
	return $out;
}

function alminuto_theme_right_column_html() {
	$opts = alminuto_theme_right_get();

	$size_candidates = [ 'banner-lateral', 'medium', 'thumbnail' ];
	$sizes           = function_exists( 'get_intermediate_image_sizes' ) ? (array) get_intermediate_image_sizes() : [];
	$img_size        = 'medium';
	foreach ( $size_candidates as $candidate ) {
		if ( in_array( $candidate, $sizes, true ) ) {
			$img_size = $candidate;
			break;
		}
	}

	$out = '<div class="am-right-block">';
	$out .= '<div class="am-section-title">Noticias con rigor</div>';
	if ( (int) $opts['news_rigor_image_id'] > 0 ) {
		$img = wp_get_attachment_image( (int) $opts['news_rigor_image_id'], $img_size, false, [ 'loading' => 'lazy' ] );
		if ( $img ) {
			if ( $opts['news_rigor_url'] ) {
				$out .= '<a href="' . esc_url( (string) $opts['news_rigor_url'] ) . '" target="_self" rel="nofollow noopener noreferrer">' . $img . '</a>';
			} else {
				$out .= $img;
			}
		}
	}

	$title2 = trim( (string) $opts['block2_title'] );
	if ( $title2 === '' ) {
		$title2 = 'ALGECIRAS ES SEMANA SANTA';
	}
	$out .= '<div class="am-section-title">' . esc_html( $title2 ) . '</div>';

	if ( $opts['youtube_url'] ) {
		$embed = wp_oembed_get( (string) $opts['youtube_url'] );
		if ( $embed ) {
			$out .= '<div class="am-right-embed">' . $embed . '</div>';
		}
	}
	if ( $opts['facebook_video_url'] ) {
		$fb = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode( (string) $opts['facebook_video_url'] ) . '&show_text=0&autoplay=0';
		$out .= '<div class="am-right-embed"><iframe src="' . esc_url( $fb ) . '" scrolling="no" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe></div>';
	}

	$out .= '<div class="am-section-title">Publicidad</div>';
	$gallery = (array) $opts['publi_gallery'];
	foreach ( $gallery as $idx => $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$img = wp_get_attachment_image( $id, $img_size, false, [ 'loading' => 'lazy' ] );
		if ( ! $img ) {
			continue;
		}
		$wrap_class = $idx === 0 ? 'am-right-publi-main' : 'am-right-publi-item';
		if ( $url ) {
			$target = $new_tab ? ' target="_blank" rel="nofollow noopener noreferrer"' : ' target="_self" rel="nofollow noopener noreferrer"';
			$out   .= '<a class="' . esc_attr( $wrap_class ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
		} else {
			$out .= '<div class="' . esc_attr( $wrap_class ) . '">' . $img . '</div>';
		}
	}

	$out .= '</div>';

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

	return wp_kses( $out, $allowed );
}

function alminuto_theme_render_banners_admin() {
	$tab_saved = false;
	if ( isset( $_POST['alminuto_theme_banners_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_banners_nonce'], 'alminuto_theme_banners_save' ) ) {
		$banners             = alminuto_theme_banners_get();
		$banners['top_left'] = alminuto_theme_sanitize_banner_items( $_POST['am_top_left'] ?? [] );
		update_option( 'alminuto_theme_banners', $banners, false );
		$tab_saved = true;
	}

	$banners = alminuto_theme_banners_get();
	$list    = (array) ( $banners['top_left'] ?? [] );

	echo '<div class="am-admin-wrap">';
	if ( $tab_saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'alminuto_theme_banners_save', 'alminuto_theme_banners_nonce' );
	echo '<section class="am-admin-card">';
	echo '<h2>Top banner (slider)</h2>';
	echo '<p class="am-help">Arrastra para reordenar. Fechas opcionales para programar.</p>';
	echo '<div class="am-actions"><button type="button" class="button button-primary" id="am_top_left_add">Añadir imágenes</button></div>';
	echo '<ul class="am-gallery-list" id="am_top_left_list">';
	foreach ( $list as $index => $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		$start   = isset( $row['start'] ) ? (string) $row['start'] : '';
		$end     = isset( $row['end'] ) ? (string) $row['end'] : '';

		echo '<li class="am-gallery-item" data-index="' . esc_attr( (string) $index ) . '">';
		echo '<div class="am-gallery-row">';
		echo '<span class="dashicons dashicons-move am-gallery-handle" aria-hidden="true"></span>';
		echo '<div class="am-thumb am-top-left-preview">' . ( $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : '' ) . '</div>';
		echo '<div class="am-actions"><button type="button" class="button am-top-left-pick">Cambiar</button></div>';
		echo '<button type="button" class="button-link-delete am-top-left-remove am-gallery-remove">Quitar</button>';
		echo '</div>';
		echo '<div class="am-gallery-meta">';
		echo '<input type="hidden" name="am_top_left[' . esc_attr( (string) $index ) . '][id]" value="' . esc_attr( (string) $id ) . '">';
		echo '<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="am_top_left[' . esc_attr( (string) $index ) . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://..."></div>';
		echo '<label><input type="checkbox" name="am_top_left[' . esc_attr( (string) $index ) . '][new_tab]" value="1" ' . checked( $new_tab, 1, false ) . '> Abrir en nueva pestaña</label>';
		echo '<div class="am-actions" style="gap:12px;">';
		echo '<div class="am-field" style="margin-top:0;min-width:160px;"><label>Inicio</label><input type="date" name="am_top_left[' . esc_attr( (string) $index ) . '][start]" value="' . esc_attr( $start ) . '"></div>';
		echo '<div class="am-field" style="margin-top:0;min-width:160px;"><label>Fin</label><input type="date" name="am_top_left[' . esc_attr( (string) $index ) . '][end]" value="' . esc_attr( $end ) . '"></div>';
		echo '</div>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '<div class="am-submit">';
	submit_button( 'Guardar', 'primary', 'submit', false );
	echo '</div>';
	echo '</section>';
	echo '</form>';
	echo '</div>';

	echo '<script>jQuery(function($){function canUseMedia(){return window.wp&&wp.media}function thumbUrl(att){if(att&&att.sizes&&att.sizes.thumbnail){return att.sizes.thumbnail.url}return att&&att.url?att.url:""}function pickImage(onSelect){if(!canUseMedia()){alert("No se ha cargado el selector de medios. Recarga la página.");return}var frame=wp.media({title:"Selecciona una imagen",multiple:false,library:{type:"image"}});frame.on("select",function(){var att=frame.state().get("selection").first().toJSON();onSelect(att)});frame.open()}function pickImages(onSelect){if(!canUseMedia()){alert("No se ha cargado el selector de medios. Recarga la página.");return}var frame=wp.media({title:"Selecciona imágenes",multiple:true,library:{type:"image"}});frame.on("select",function(){var selection=frame.state().get("selection");var atts=[];selection.each(function(model){atts.push(model.toJSON())});onSelect(atts)});frame.open()}function renumber(){ $("#am_top_left_list > li").each(function(i){var $li=$(this);$li.attr("data-index",i);$li.find("input,select,textarea").each(function(){var $el=$(this);var name=$el.attr("name");if(!name)return;name=name.replace(/am_top_left\\[[0-9]+\\]/g,"am_top_left["+i+"]");$el.attr("name",name)})})}function initItem($li){$li.find(".am-top-left-remove").on("click",function(){$li.remove();renumber()});$li.find(".am-top-left-pick").on("click",function(){pickImage(function(att){$li.find("input[type=hidden][name*=\\\"[id]\\\"]").val(att.id);$li.find(".am-top-left-preview").html("<img src=\\\""+thumbUrl(att)+"\\\" alt=\\\"\\\">")})})}$("#am_top_left_list > li").each(function(){initItem($(this))});$("#am_top_left_list").sortable({items:"> li",axis:"y",handle:".am-gallery-handle",cancel:"input,textarea,button,select,label,a",stop:function(){renumber()}});$(document).on("click","#am_top_left_add",function(e){e.preventDefault();pickImages(function(atts){if(!atts||!atts.length)return;var nextIndex=0;$("#am_top_left_list > li").each(function(){var idx=parseInt($(this).attr("data-index")||"0",10);if(idx>=nextIndex)nextIndex=idx+1});atts.forEach(function(att){var idx=nextIndex++;var $li=$("<li class=\\\"am-gallery-item\\\" data-index=\\\""+idx+"\\\">"+"<div class=\\\"am-gallery-row\\\">"+"<span class=\\\"dashicons dashicons-move am-gallery-handle\\\" aria-hidden=\\\"true\\\"></span>"+"<div class=\\\"am-thumb am-top-left-preview\\\"><img src=\\\""+thumbUrl(att)+"\\\" alt=\\\"\\\"></div>"+"<div class=\\\"am-actions\\\"><button type=\\\"button\\\" class=\\\"button am-top-left-pick\\\">Cambiar</button></div>"+"<button type=\\\"button\\\" class=\\\"button-link-delete am-top-left-remove am-gallery-remove\\\">Quitar</button>"+"</div>"+"<div class=\\\"am-gallery-meta\\\">"+"<input type=\\\"hidden\\\" name=\\\"am_top_left["+idx+"][id]\\\" value=\\\""+att.id+"\\\">"+"<div class=\\\"am-field\\\"><label>Enlace</label><input type=\\\"url\\\" class=\\\"regular-text\\\" name=\\\"am_top_left["+idx+"][url]\\\" value=\\\"\\\" placeholder=\\\"https://...\\\"></div>"+"<label><input type=\\\"checkbox\\\" name=\\\"am_top_left["+idx+"][new_tab]\\\" value=\\\"1\\\"> Abrir en nueva pestaña</label>"+"<div class=\\\"am-actions\\\" style=\\\"gap:12px;\\\">"+"<div class=\\\"am-field\\\" style=\\\"margin-top:0;min-width:160px;\\\"><label>Inicio</label><input type=\\\"date\\\" name=\\\"am_top_left["+idx+"][start]\\\" value=\\\"\\\"></div>"+"<div class=\\\"am-field\\\" style=\\\"margin-top:0;min-width:160px;\\\"><label>Fin</label><input type=\\\"date\\\" name=\\\"am_top_left["+idx+"][end]\\\" value=\\\"\\\"></div>"+"</div>"+"</div>"+"</li>");$("#am_top_left_list").append($li);initItem($li)});renumber()})})});</script>';
}

function alminuto_theme_render_right_admin() {
	$saved = false;
	if ( isset( $_POST['alminuto_theme_right_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_right_nonce'], 'alminuto_theme_right_save' ) ) {
		$opts = alminuto_theme_right_defaults();

		$opts['news_rigor_image_id'] = isset( $_POST['news_rigor_image_id'] ) ? (int) $_POST['news_rigor_image_id'] : 0;
		$opts['news_rigor_url']      = isset( $_POST['news_rigor_url'] ) ? esc_url_raw( (string) $_POST['news_rigor_url'] ) : '';

		$opts['block2_title']       = isset( $_POST['block2_title'] ) ? sanitize_text_field( (string) $_POST['block2_title'] ) : $opts['block2_title'];
		$opts['youtube_url']        = isset( $_POST['youtube_url'] ) ? esc_url_raw( (string) $_POST['youtube_url'] ) : '';
		$opts['facebook_video_url'] = isset( $_POST['facebook_video_url'] ) ? esc_url_raw( (string) $_POST['facebook_video_url'] ) : '';

		$opts['publi_gallery'] = alminuto_theme_right_sanitize_gallery( $_POST['publi_gallery'] ?? [] );

		update_option( 'alminuto_theme_right_column', $opts, false );
		$saved = true;
	}

	$opts = alminuto_theme_right_get();

	echo '<div class="am-admin-wrap">';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'alminuto_theme_right_save', 'alminuto_theme_right_nonce' );

	echo '<div class="am-admin-grid">';

	echo '<section class="am-admin-card">';
	echo '<h2>Noticias con rigor</h2>';
	echo '<p class="am-help">Selecciona una imagen y un enlace opcional.</p>';
	echo '<input type="hidden" name="news_rigor_image_id" id="news_rigor_image_id" value="' . esc_attr( (string) (int) $opts['news_rigor_image_id'] ) . '">';
	echo '<div class="am-actions">';
	echo '<button type="button" class="button button-primary" id="news_rigor_pick">' . ( (int) $opts['news_rigor_image_id'] > 0 ? 'Cambiar imagen' : 'Elegir imagen' ) . '</button>';
	echo '<button type="button" class="button" id="news_rigor_clear" ' . ( (int) $opts['news_rigor_image_id'] > 0 ? '' : 'disabled' ) . '>Quitar</button>';
	echo '</div>';
	echo '<div class="am-field"><label for="news_rigor_url">Enlace</label><input type="url" id="news_rigor_url" class="regular-text" name="news_rigor_url" value="' . esc_attr( (string) $opts['news_rigor_url'] ) . '" placeholder="https://..."></div>';
	echo '<div class="am-field"><label>Preview</label><div id="news_rigor_preview" style="max-width:320px;">' . ( (int) $opts['news_rigor_image_id'] > 0 ? wp_kses_post( wp_get_attachment_image( (int) $opts['news_rigor_image_id'], 'medium' ) ) : '' ) . '</div></div>';
	echo '</section>';

	echo '<section class="am-admin-card">';
	echo '<h2>Bloque 2</h2>';
	echo '<p class="am-help">Título + vídeo de YouTube o Facebook (o ambos).</p>';
	echo '<div class="am-field"><label for="block2_title">Título</label><input type="text" id="block2_title" class="regular-text" name="block2_title" value="' . esc_attr( (string) $opts['block2_title'] ) . '"></div>';
	echo '<div class="am-field"><label for="youtube_url">YouTube URL</label><input type="url" id="youtube_url" class="regular-text" name="youtube_url" value="' . esc_attr( (string) $opts['youtube_url'] ) . '" placeholder="https://www.youtube.com/watch?v=..."></div>';
	echo '<div class="am-field"><label for="facebook_video_url">Facebook video URL</label><input type="url" id="facebook_video_url" class="regular-text" name="facebook_video_url" value="' . esc_attr( (string) $opts['facebook_video_url'] ) . '" placeholder="https://www.facebook.com/..."></div>';
	echo '</section>';

	echo '<section class="am-admin-card am-admin-card--full">';
	echo '<h2>Publicidad</h2>';
	echo '<p class="am-help">La primera imagen será la principal. Arrastra para reordenar.</p>';
	echo '<div class="am-actions"><button type="button" class="button button-primary" id="publi_gallery_add">Añadir imagen</button></div>';
	echo '<ul id="publi_gallery_list" class="am-gallery-list">';
	foreach ( (array) $opts['publi_gallery'] as $index => $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
		echo '<li class="publi-item am-gallery-item" data-index="' . esc_attr( (string) $index ) . '">';
		echo '<div class="am-gallery-row">';
		echo '<span class="dashicons dashicons-move am-gallery-handle publi-handle" aria-hidden="true"></span>';
		echo '<div class="publi-preview am-thumb">' . ( $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : '' ) . '</div>';
		echo '<div class="am-actions"><button type="button" class="button publi-pick">Cambiar</button></div>';
		echo '<button type="button" class="button-link-delete publi-remove am-gallery-remove">Quitar</button>';
		echo '</div>';
		echo '<div class="am-gallery-meta">';
		echo '<input type="hidden" name="publi_gallery[' . esc_attr( (string) $index ) . '][id]" value="' . esc_attr( (string) $id ) . '">';
		echo '<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="publi_gallery[' . esc_attr( (string) $index ) . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://..."></div>';
		echo '<label><input type="checkbox" name="publi_gallery[' . esc_attr( (string) $index ) . '][new_tab]" value="1" ' . checked( $new_tab, 1, false ) . '> Abrir en nueva pestaña</label>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '</section>';

	echo '</div>';
	echo '<div class="am-submit">';
	submit_button( 'Guardar' );
	echo '</div>';
	echo '</form>';
	echo '</div>';

	echo '<script>jQuery(function($){function canUseMedia(){return window.wp&&wp.media}function previewUrl(att){if(att&&att.sizes){if(att.sizes.medium)return att.sizes.medium.url;if(att.sizes.large)return att.sizes.large.url;if(att.sizes.thumbnail)return att.sizes.thumbnail.url}return att&&att.url?att.url:""}function thumbUrl(att){if(att&&att.sizes&&att.sizes.thumbnail)return att.sizes.thumbnail.url;return previewUrl(att)}function pickImage(onSelect){if(!canUseMedia()){alert("No se ha cargado el selector de medios. Recarga la página.");return}var frame=wp.media({title:"Selecciona una imagen",multiple:false,library:{type:"image"}});frame.on("select",function(){var att=frame.state().get("selection").first().toJSON();onSelect(att)});frame.open()}function pickImages(onSelect){if(!canUseMedia()){alert("No se ha cargado el selector de medios. Recarga la página.");return}var frame=wp.media({title:"Selecciona imágenes",multiple:true,library:{type:"image"}});frame.on("select",function(){var selection=frame.state().get("selection");var atts=[];selection.each(function(model){atts.push(model.toJSON())});onSelect(atts)});frame.open()}$("#news_rigor_pick").on("click",function(){pickImage(function(att){$("#news_rigor_image_id").val(att.id);$("#news_rigor_preview").html("<img src=\\\""+previewUrl(att)+"\\\" alt=\\\"\\\">");$("#news_rigor_clear").prop("disabled",false);$("#news_rigor_pick").text("Cambiar imagen")})});$("#news_rigor_clear").on("click",function(){$("#news_rigor_image_id").val("");$("#news_rigor_preview").empty();$("#news_rigor_clear").prop("disabled",true);$("#news_rigor_pick").text("Elegir imagen")});function renumberGallery(){$("#publi_gallery_list .publi-item").each(function(i){var $li=$(this);$li.attr("data-index",i);$li.find("input,select,textarea").each(function(){var $el=$(this);var name=$el.attr("name");if(!name)return;name=name.replace(/publi_gallery\\[[0-9]+\\]/g,"publi_gallery["+i+"]");$el.attr("name",name)})})}function initGalleryItem($li){$li.find(".publi-remove").on("click",function(){$li.remove();renumberGallery()});$li.find(".publi-pick").on("click",function(){pickImage(function(att){$li.find("input[type=hidden][name*=\\\"[id]\\\"]").val(att.id);$li.find(".publi-preview").html("<img src=\\\""+thumbUrl(att)+"\\\" alt=\\\"\\\">")})})}$("#publi_gallery_list .publi-item").each(function(){initGalleryItem($(this))});$("#publi_gallery_list").sortable({items:"> li",axis:"y",handle:".publi-handle",cancel:"input,textarea,button,select,label,a",stop:function(){renumberGallery()}});$(document).on("click","#publi_gallery_add",function(e){e.preventDefault();pickImages(function(atts){if(!atts||!atts.length)return;var nextIndex=0;$("#publi_gallery_list .publi-item").each(function(){var idx=parseInt($(this).attr("data-index")||"0",10);if(idx>=nextIndex)nextIndex=idx+1});atts.forEach(function(att){var idx=nextIndex++;var $li=$("<li class=\\\"publi-item am-gallery-item\\\" data-index=\\\""+idx+"\\\">"+"<div class=\\\"am-gallery-row\\\">"+"<span class=\\\"dashicons dashicons-move am-gallery-handle publi-handle\\\" aria-hidden=\\\"true\\\"></span>"+"<div class=\\\"publi-preview am-thumb\\\"><img src=\\\""+thumbUrl(att)+"\\\" alt=\\\"\\\"></div>"+"<div class=\\\"am-actions\\\"><button type=\\\"button\\\" class=\\\"button publi-pick\\\">Cambiar</button></div>"+"<button type=\\\"button\\\" class=\\\"button-link-delete publi-remove am-gallery-remove\\\">Quitar</button>"+"</div>"+"<div class=\\\"am-gallery-meta\\\">"+"<input type=\\\"hidden\\\" name=\\\"publi_gallery["+idx+"][id]\\\" value=\\\""+att.id+"\\\">"+"<div class=\\\"am-field\\\"><label>Enlace</label><input type=\\\"url\\\" class=\\\"regular-text\\\" name=\\\"publi_gallery["+idx+"][url]\\\" value=\\\"\\\" placeholder=\\\"https://...\\\"></div>"+"<label><input type=\\\"checkbox\\\" name=\\\"publi_gallery["+idx+"][new_tab]\\\" value=\\\"1\\\"> Abrir en nueva pestaña</label>"+"</div>"+"</li>");$("#publi_gallery_list").append($li);initGalleryItem($li)});renumberGallery()})})});</script>';
}

function alminuto_theme_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permisos.' );
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'home';
	if ( ! in_array( $tab, [ 'home', 'banners', 'right' ], true ) ) {
		$tab = 'home';
	}

	if ( $tab === 'home' && isset( $_POST['alminuto_theme_panel_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_theme_panel_nonce'], 'alminuto_theme_panel_save' ) ) {
		$defaults = alminuto_theme_settings_defaults();

		$left  = isset( $_POST['home_left_posts'] ) ? (int) $_POST['home_left_posts'] : (int) $defaults['home_left_posts'];
		$right = isset( $_POST['home_right_posts'] ) ? (int) $_POST['home_right_posts'] : (int) $defaults['home_right_posts'];

		$left  = max( 1, min( 50, $left ) );
		$right = max( 1, min( 50, $right ) );

		update_option(
			'alminuto_theme_settings',
			[
				'home_left_posts'  => $left,
				'home_right_posts' => $right,
			],
			false
		);

		echo '<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>';
	}

	$settings = alminuto_theme_get_settings();

	$base_url = admin_url( 'admin.php?page=alminuto-theme-panel' );
	$tabs     = [
		'home'    => 'Inicio',
		'banners' => 'Banners',
		'right'   => 'Columna Derecha',
	];

	echo '<div class="wrap">';
	echo '<h1>Al Minuto</h1>';
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $key => $label ) {
		$url   = esc_url( add_query_arg( 'tab', $key, $base_url ) );
		$class = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a href="' . $url . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</h2>';

	if ( $tab === 'home' ) {
		echo '<form method="post">';
		wp_nonce_field( 'alminuto_theme_panel_save', 'alminuto_theme_panel_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="home_left_posts">Artículos en la Columna Izquierda</label></th><td><input type="number" min="1" max="50" id="home_left_posts" name="home_left_posts" value="' . esc_attr( (string) (int) $settings['home_left_posts'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="home_right_posts">Inicio · Artículos en la Columna Derecha</label></th><td><input type="number" min="1" max="50" id="home_right_posts" name="home_right_posts" value="' . esc_attr( (string) (int) $settings['home_right_posts'] ) . '"></td></tr>';
		echo '</table>';
		submit_button( 'Guardar' );
		echo '</form>';
	} elseif ( $tab === 'banners' ) {
		alminuto_theme_render_banners_admin();
	} elseif ( $tab === 'right' ) {
		alminuto_theme_render_right_admin();
	}

	echo '</div>';
}
