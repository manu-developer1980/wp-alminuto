<?php
/*
Plugin Name: Banners al Minuto
Description: Un plugin para gestionar banners usando un Custom Post Type.
Version: 1.1.0
Author: Tu Nombre
*/

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Registrar el Custom Post Type
function crear_cpt_banners() {
    $labels = array(
        'name'               => _x('Banners', 'post type general name', 'banners-alminuto'),
        'singular_name'      => _x('Banner', 'post type singular name', 'banners-alminuto'),
        'menu_name'          => _x('Banners', 'admin menu', 'banners-alminuto'),
        'name_admin_bar'     => _x('Banner', 'add new on admin bar', 'banners-alminuto'),
        'add_new'            => _x('Añadir Nuevo', 'banner', 'banners-alminuto'),
        'add_new_item'       => __('Añadir Nuevo Banner', 'banners-alminuto'),
        'new_item'           => __('Nuevo Banner', 'banners-alminuto'),
        'edit_item'          => __('Editar Banner', 'banners-alminuto'),
        'view_item'          => __('Ver Banner', 'banners-alminuto'),
        'all_items'          => __('Todos los Banners', 'banners-alminuto'),
        'search_items'       => __('Buscar Banners', 'banners-alminuto'),
        'parent_item_colon'  => __('Banners Padre:', 'banners-alminuto'),
        'not_found'          => __('No se encontraron banners.', 'banners-alminuto'),
        'not_found_in_trash' => __('No se encontraron banners en la Papelera.', 'banners-alminuto')
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'banner'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'thumbnail', 'page-attributes'),
        'show_in_graphql'    => true, // Habilita en WPGraphQL
        'graphql_single_name' => 'Banner', // Nombre para un solo ítem
        'graphql_plural_name' => 'Banners' // Nombre para la colección
    );

    register_post_type('banner', $args);
}
add_action('init', 'crear_cpt_banners');

function banners_alminuto_admin_default_ordering( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'post_type' ) !== 'banner' ) {
		return;
	}
	if ( $query->get( 'orderby' ) ) {
		return;
	}
	$query->set( 'orderby', [ 'menu_order' => 'ASC', 'date' => 'DESC' ] );
}
add_action( 'pre_get_posts', 'banners_alminuto_admin_default_ordering' );

function banners_alminuto_admin_columns( $columns ) {
	$columns['menu_order'] = 'Orden';
	return $columns;
}
add_filter( 'manage_banner_posts_columns', 'banners_alminuto_admin_columns' );

function banners_alminuto_admin_column_value( $column, $post_id ) {
	if ( $column === 'menu_order' ) {
		$post = get_post( $post_id );
		echo esc_html( (string) ( $post ? (int) $post->menu_order : 0 ) );
	}
}
add_action( 'manage_banner_posts_custom_column', 'banners_alminuto_admin_column_value', 10, 2 );

function banners_alminuto_admin_sortable_columns( $columns ) {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-banner_sortable_columns', 'banners_alminuto_admin_sortable_columns' );

function banners_alminuto_register_metaboxes() {
	add_meta_box(
		'banners_alminuto_link',
		'Enlace del banner',
		'banners_alminuto_render_metabox_link',
		'banner',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'banners_alminuto_register_metaboxes' );

function banners_alminuto_render_metabox_link( $post ) {
	wp_nonce_field( 'banners_alminuto_save_banner', 'banners_alminuto_nonce' );
	$url     = get_post_meta( $post->ID, '_bam_url', true );
	$new_tab = (int) get_post_meta( $post->ID, '_bam_new_tab', true );
	$slot    = (string) get_post_meta( $post->ID, '_bam_slot', true );
	$slots   = [
		''         => 'Sin asignar',
		'top_left' => 'Superior izquierda (slider)',
		'top_mid'  => 'Superior centro',
	];
	?>
	<p>
		<label for="bam_slot"><strong>Posición</strong></label>
		<select id="bam_slot" name="bam_slot" style="width:100%;">
			<?php foreach ( $slots as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $slot, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="bam_url"><strong>URL</strong></label>
		<input type="url" id="bam_url" name="bam_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%;" placeholder="https://...">
	</p>
	<p>
		<label>
			<input type="checkbox" name="bam_new_tab" value="1" <?php checked( $new_tab, 1 ); ?>>
			Abrir en nueva pestaña
		</label>
	</p>
	<?php
}

function banners_alminuto_save_banner_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['banners_alminuto_nonce'] ) || ! wp_verify_nonce( $_POST['banners_alminuto_nonce'], 'banners_alminuto_save_banner' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['bam_url'] ) ) {
		$url = trim( (string) wp_unslash( $_POST['bam_url'] ) );
		$url = $url === '' ? '' : esc_url_raw( $url );
		if ( $url ) {
			update_post_meta( $post_id, '_bam_url', $url );
		} else {
			delete_post_meta( $post_id, '_bam_url' );
		}
	}

	if ( isset( $_POST['bam_slot'] ) ) {
		$slot = sanitize_key( (string) wp_unslash( $_POST['bam_slot'] ) );
		$slot = in_array( $slot, [ 'top_left', 'top_mid' ], true ) ? $slot : '';
		if ( $slot !== '' ) {
			update_post_meta( $post_id, '_bam_slot', $slot );
		} else {
			delete_post_meta( $post_id, '_bam_slot' );
		}
	}

	$new_tab = isset( $_POST['bam_new_tab'] ) ? 1 : 0;
	if ( $new_tab ) {
		update_post_meta( $post_id, '_bam_new_tab', 1 );
	} else {
		delete_post_meta( $post_id, '_bam_new_tab' );
	}
}
add_action( 'save_post_banner', 'banners_alminuto_save_banner_meta' );

function banners_alminuto_render_banner_html( $banner_post_id, $image_size = 'full' ) {
	$thumb_id = get_post_thumbnail_id( $banner_post_id );
	if ( ! $thumb_id ) {
		return '';
	}

	$img = wp_get_attachment_image( $thumb_id, $image_size, false, [ 'loading' => 'eager' ] );
	if ( ! $img ) {
		return '';
	}

	$url     = get_post_meta( $banner_post_id, '_bam_url', true );
	$new_tab = (int) get_post_meta( $banner_post_id, '_bam_new_tab', true );

	if ( $url ) {
		$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
	}

	return $img;
}

function banners_alminuto_slot_defaults() {
	return [
		'top_left' => [],
	];
}

function banners_alminuto_get_slots() {
	$defaults = banners_alminuto_slot_defaults();
	$raw      = get_option( 'banners_alminuto_slots', [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$slots = array_merge( $defaults, $raw );
	if ( ! is_array( $slots['top_left'] ?? null ) ) {
		$slots['top_left'] = [];
	}
	return $slots;
}

function banners_alminuto_is_valid_date_ymd( $value ) {
	if ( ! is_string( $value ) ) {
		return false;
	}
	return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
}

function banners_alminuto_sanitize_slot_items( $raw ) {
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
		if ( $start !== '' && ! banners_alminuto_is_valid_date_ymd( $start ) ) {
			$start = '';
		}
		if ( $end !== '' && ! banners_alminuto_is_valid_date_ymd( $end ) ) {
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

function banners_alminuto_item_is_active( $item, $now_ts ) {
	$start = is_array( $item ) && isset( $item['start'] ) ? (string) $item['start'] : '';
	$end   = is_array( $item ) && isset( $item['end'] ) ? (string) $item['end'] : '';
	if ( $start !== '' && banners_alminuto_is_valid_date_ymd( $start ) ) {
		$start_ts = strtotime( $start . ' 00:00:00' );
		if ( $start_ts && $now_ts < $start_ts ) {
			return false;
		}
	}
	if ( $end !== '' && banners_alminuto_is_valid_date_ymd( $end ) ) {
		$end_ts = strtotime( $end . ' 23:59:59' );
		if ( $end_ts && $now_ts > $end_ts ) {
			return false;
		}
	}
	return true;
}

function banners_alminuto_render_banner_item_html( $item, $image_size = 'full' ) {
	if ( ! is_array( $item ) ) {
		return '';
	}
	$id = isset( $item['id'] ) ? (int) $item['id'] : 0;
	if ( $id <= 0 ) {
		return '';
	}
	$img = wp_get_attachment_image( $id, $image_size, false, [ 'loading' => 'eager' ] );
	if ( ! $img ) {
		return '';
	}
	$url     = isset( $item['url'] ) ? (string) $item['url'] : '';
	$new_tab = ! empty( $item['new_tab'] ) ? 1 : 0;
	if ( $url ) {
		$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
	}
	return $img;
}

function banners_alminuto_enqueue_assets() {
	static $enqueued = false;
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	wp_register_style( 'banners-alminuto', false );
	wp_enqueue_style( 'banners-alminuto' );
	wp_add_inline_style(
		'banners-alminuto',
		'.bam-wrap{display:grid;gap:10px}.bam-item img{max-width:100%;height:auto;display:block}.bam-slider{position:relative;overflow:hidden}.bam-slide{display:none}.bam-slide.is-active{display:block}'
	);

	wp_register_script( 'banners-alminuto', '', [], null, true );
	wp_enqueue_script( 'banners-alminuto' );
	wp_add_inline_script(
		'banners-alminuto',
		'(function(){function initSlider(root){var slides=root.querySelectorAll(".bam-slide");if(!slides.length){return}var idx=0;slides[0].classList.add("is-active");var autoplay=parseInt(root.getAttribute("data-autoplay")||"0",10);if(!autoplay||slides.length<2){return}var timer=null;function show(i){slides[idx].classList.remove("is-active");idx=i;slides[idx].classList.add("is-active")}function next(){show((idx+1)%slides.length)}function start(){stop();timer=setInterval(next,autoplay)}function stop(){if(timer){clearInterval(timer);timer=null}}root.addEventListener("mouseenter",stop);root.addEventListener("mouseleave",start);start()}document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bam-slider").forEach(initSlider)})})();'
	);
}

function banners_alminuto_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'    => 2,
			'slot'     => 0,
			'size'     => 'full',
			'class'    => '',
			'slider'   => 0,
			'autoplay' => 9500,
		],
		(array) $atts,
		'banners_alminuto'
	);

	$limit = max( 1, (int) $atts['limit'] );
	$slot_raw = is_string( $atts['slot'] ) ? trim( $atts['slot'] ) : (string) $atts['slot'];
	$slot_key = sanitize_key( $slot_raw );
	if ( in_array( $slot_key, [ '1', '2' ], true ) ) {
		$slot_key = $slot_key === '1' ? 'top_left' : 'top_mid';
	}
	if ( ! in_array( $slot_key, [ 'top_left', 'top_mid' ], true ) ) {
		$slot_key = '';
	}
	$size  = sanitize_key( (string) $atts['size'] );
	$class = trim( (string) $atts['class'] );
	$slider   = (int) $atts['slider'] === 1 || $atts['slider'] === 'true' || $atts['slider'] === 'yes';
	$autoplay = max( 0, (int) $atts['autoplay'] );

	if ( $slot_key === 'top_left' ) {
		$slots = banners_alminuto_get_slots();
		$list  = (array) ( $slots['top_left'] ?? [] );
		$now   = (int) current_time( 'timestamp' );

		$items = [];
		foreach ( $list as $row ) {
			if ( ! banners_alminuto_item_is_active( $row, $now ) ) {
				continue;
			}
			$html = banners_alminuto_render_banner_item_html( $row, $size );
			if ( ! $html ) {
				continue;
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

		if ( ! empty( $items ) ) {
			$classes = 'bam-wrap';
			if ( $class !== '' ) {
				$classes .= ' ' . sanitize_html_class( $class );
			}
			banners_alminuto_enqueue_assets();
			if ( $slider ) {
				return '<div class="bam-slider" data-autoplay="' . esc_attr( (string) $autoplay ) . '">' . implode( '', $items ) . '</div>';
			}
			return '<div class="' . esc_attr( $classes ) . '">' . implode( '', $items ) . '</div>';
		}
	}

	$query_args = [
		'post_type'      => 'banner',
		'post_status'    => 'publish',
		'orderby'        => [
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		],
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
	];

	if ( $slot_key !== '' ) {
		$query_args['meta_query'] = [
			[
				'key'   => '_bam_slot',
				'value' => $slot_key,
			],
		];
	}

	$q = new WP_Query( $query_args );
	if ( $slot_key !== '' && ! $q->have_posts() ) {
		$slot_num = $slot_key === 'top_left' ? 1 : 2;
		unset( $query_args['meta_query'] );
		$query_args['posts_per_page'] = 1;
		$query_args['offset']         = $slot_num - 1;
		$q = new WP_Query( $query_args );
	}
	if ( ! $q->have_posts() ) {
		return '';
	}

	$items = [];
	foreach ( $q->posts as $p ) {
		$html = banners_alminuto_render_banner_html( (int) $p->ID, $size );
		if ( $html ) {
			if ( $slider ) {
				$items[] = '<div class="bam-slide">' . $html . '</div>';
			} else {
				$items[] = '<div class="bam-item">' . $html . '</div>';
			}
		}
	}

	if ( empty( $items ) ) {
		return '';
	}

	$classes = 'bam-wrap';
	if ( $class !== '' ) {
		$classes .= ' ' . sanitize_html_class( $class );
	}

	banners_alminuto_enqueue_assets();

	if ( $slider ) {
		return '<div class="bam-slider" data-autoplay="' . esc_attr( (string) $autoplay ) . '">' . implode( '', $items ) . '</div>';
	}

	return '<div class="' . esc_attr( $classes ) . '">' . implode( '', $items ) . '</div>';
}
add_shortcode( 'banners_alminuto', 'banners_alminuto_shortcode' );

function alminuto_sidebar_right_default_options() {
	return [
		'news_rigor_image_id'  => 0,
		'news_rigor_url'       => '',
		'block2_title'         => 'ALGECIRAS ES SEMANA SANTA',
		'youtube_url'          => '',
		'facebook_video_url'   => '',
		'publi_gallery'        => [],
	];
}

function alminuto_sidebar_right_get_options() {
	$defaults = alminuto_sidebar_right_default_options();
	$opts     = get_option( 'alminuto_sidebar_right', [] );
	if ( ! is_array( $opts ) ) {
		$opts = [];
	}
	return array_merge( $defaults, $opts );
}

function alminuto_sidebar_right_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=banner',
		'Columna derecha',
		'Columna derecha',
		'edit_posts',
		'alminuto-sidebar-right',
		'alminuto_sidebar_right_render_admin_page'
	);
	add_submenu_page(
		'edit.php?post_type=banner',
		'Ordenar banners',
		'Ordenar banners',
		'edit_posts',
		'banners-alminuto-order',
		'banners_alminuto_render_order_page'
	);
}
add_action( 'admin_menu', 'alminuto_sidebar_right_admin_menu' );

function alminuto_sidebar_right_admin_enqueue( $hook_suffix ) {
	if ( $hook_suffix !== 'banner_page_alminuto-sidebar-right' && $hook_suffix !== 'banner_page_banners-alminuto-order' && $hook_suffix !== 'appearance_page_alminuto-theme-panel' && $hook_suffix !== 'toplevel_page_alminuto-theme-panel' ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );

	wp_register_style( 'bam-admin', false );
	wp_enqueue_style( 'bam-admin' );
	wp_add_inline_style(
		'bam-admin',
		'.banner_page_alminuto-sidebar-right .bam-admin-wrap,.appearance_page_alminuto-theme-panel .bam-admin-wrap,.toplevel_page_alminuto-theme-panel .bam-admin-wrap{max-width:1100px}.banner_page_alminuto-sidebar-right .bam-admin-grid,.appearance_page_alminuto-theme-panel .bam-admin-grid,.toplevel_page_alminuto-theme-panel .bam-admin-grid{display:grid;gap:12px}.banner_page_alminuto-sidebar-right .bam-admin-card,.appearance_page_alminuto-theme-panel .bam-admin-card,.toplevel_page_alminuto-theme-panel .bam-admin-card{background:#fff;border:1px solid #dcdcde;padding:12px}.banner_page_alminuto-sidebar-right .bam-admin-card h2,.appearance_page_alminuto-theme-panel .bam-admin-card h2,.toplevel_page_alminuto-theme-panel .bam-admin-card h2{margin:0 0 10px;font-size:15px}.banner_page_alminuto-sidebar-right .bam-admin-card p.bam-help,.appearance_page_alminuto-theme-panel .bam-admin-card p.bam-help,.toplevel_page_alminuto-theme-panel .bam-admin-card p.bam-help{margin:0 0 10px;color:#50575e}.banner_page_alminuto-sidebar-right .bam-news-grid,.appearance_page_alminuto-theme-panel .bam-news-grid,.toplevel_page_alminuto-theme-panel .bam-news-grid{display:grid;gap:10px}.banner_page_alminuto-sidebar-right .bam-news-preview,.appearance_page_alminuto-theme-panel .bam-news-preview,.toplevel_page_alminuto-theme-panel .bam-news-preview{background:#f6f7f7;border:1px dashed #c3c4c7;padding:8px;min-height:96px;display:flex;align-items:center;justify-content:center}.banner_page_alminuto-sidebar-right .bam-news-preview img,.appearance_page_alminuto-theme-panel .bam-news-preview img,.toplevel_page_alminuto-theme-panel .bam-news-preview img{max-width:100%;height:auto;display:block}.banner_page_alminuto-sidebar-right .bam-field,.appearance_page_alminuto-theme-panel .bam-field,.toplevel_page_alminuto-theme-panel .bam-field{display:grid;gap:6px;margin-top:10px}.banner_page_alminuto-sidebar-right .bam-field label,.appearance_page_alminuto-theme-panel .bam-field label,.toplevel_page_alminuto-theme-panel .bam-field label{font-weight:600}.banner_page_alminuto-sidebar-right .bam-actions,.appearance_page_alminuto-theme-panel .bam-actions,.toplevel_page_alminuto-theme-panel .bam-actions{display:flex;gap:8px;flex-wrap:wrap}.banner_page_alminuto-sidebar-right #publi_gallery_list,.appearance_page_alminuto-theme-panel #publi_gallery_list,.toplevel_page_alminuto-theme-panel #publi_gallery_list{margin:0;display:grid;gap:8px}.banner_page_alminuto-sidebar-right .bam-gallery-item,.appearance_page_alminuto-theme-panel .bam-gallery-item,.toplevel_page_alminuto-theme-panel .bam-gallery-item{border:1px solid #dcdcde;background:#fff;padding:10px;display:grid;gap:8px}.banner_page_alminuto-sidebar-right .bam-gallery-row,.appearance_page_alminuto-theme-panel .bam-gallery-row,.toplevel_page_alminuto-theme-panel .bam-gallery-row{display:flex;gap:10px;align-items:center}.banner_page_alminuto-sidebar-right .bam-gallery-handle,.appearance_page_alminuto-theme-panel .bam-gallery-handle,.toplevel_page_alminuto-theme-panel .bam-gallery-handle{cursor:move;color:#50575e}.banner_page_alminuto-sidebar-right .bam-thumb,.appearance_page_alminuto-theme-panel .bam-thumb,.toplevel_page_alminuto-theme-panel .bam-thumb{width:100px;flex:0 0 auto}.banner_page_alminuto-sidebar-right .bam-thumb img,.appearance_page_alminuto-theme-panel .bam-thumb img,.toplevel_page_alminuto-theme-panel .bam-thumb img{width:100%;height:auto;display:block}.banner_page_alminuto-sidebar-right .bam-gallery-meta,.appearance_page_alminuto-theme-panel .bam-gallery-meta,.toplevel_page_alminuto-theme-panel .bam-gallery-meta{display:grid;gap:8px}.banner_page_alminuto-sidebar-right .bam-gallery-meta input[type=url],.appearance_page_alminuto-theme-panel .bam-gallery-meta input[type=url],.toplevel_page_alminuto-theme-panel .bam-gallery-meta input[type=url]{width:100%}.banner_page_alminuto-sidebar-right .bam-gallery-remove,.appearance_page_alminuto-theme-panel .bam-gallery-remove,.toplevel_page_alminuto-theme-panel .bam-gallery-remove{margin-left:auto}.banner_page_alminuto-sidebar-right .bam-submit,.appearance_page_alminuto-theme-panel .bam-submit,.toplevel_page_alminuto-theme-panel .bam-submit{margin-top:12px}.banner_page_alminuto-sidebar-right .bam-admin-title,.appearance_page_alminuto-theme-panel .bam-admin-title,.toplevel_page_alminuto-theme-panel .bam-admin-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 12px}.banner_page_alminuto-sidebar-right .bam-admin-title h1,.appearance_page_alminuto-theme-panel .bam-admin-title h1,.toplevel_page_alminuto-theme-panel .bam-admin-title h1{margin:0;font-size:20px}.banner_page_alminuto-sidebar-right .bam-admin-title .bam-submit-top,.appearance_page_alminuto-theme-panel .bam-admin-title .bam-submit-top,.toplevel_page_alminuto-theme-panel .bam-admin-title .bam-submit-top{margin:0}.banner_page_alminuto-sidebar-right .bam-admin-title .bam-submit-top input,.appearance_page_alminuto-theme-panel .bam-admin-title .bam-submit-top input,.toplevel_page_alminuto-theme-panel .bam-admin-title .bam-submit-top input{margin:0}.appearance_page_alminuto-theme-panel .bam-admin-wrap,.toplevel_page_alminuto-theme-panel .bam-admin-wrap{padding:0}@media (min-width: 960px){.banner_page_alminuto-sidebar-right .bam-admin-grid,.appearance_page_alminuto-theme-panel .bam-admin-grid,.toplevel_page_alminuto-theme-panel .bam-admin-grid{grid-template-columns:1fr 1fr}.banner_page_alminuto-sidebar-right .bam-admin-card--full,.appearance_page_alminuto-theme-panel .bam-admin-card--full,.toplevel_page_alminuto-theme-panel .bam-admin-card--full{grid-column:1 / -1}.banner_page_alminuto-sidebar-right .bam-news-grid,.appearance_page_alminuto-theme-panel .bam-news-grid,.toplevel_page_alminuto-theme-panel .bam-news-grid{grid-template-columns:280px 1fr;align-items:start}}'
	);
}
add_action( 'admin_enqueue_scripts', 'alminuto_sidebar_right_admin_enqueue' );

function banners_alminuto_render_banner_manager_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permisos.' );
	}

	$saved = false;
	if ( isset( $_POST['bam_banners_nonce'] ) && wp_verify_nonce( (string) $_POST['bam_banners_nonce'], 'bam_banners_save' ) ) {
		$slots            = banners_alminuto_get_slots();
		$slots['top_left'] = banners_alminuto_sanitize_slot_items( $_POST['bam_top_left'] ?? [] );
		update_option( 'banners_alminuto_slots', $slots, false );
		$saved = true;
	}

	$slots = banners_alminuto_get_slots();
	$list  = (array) ( $slots['top_left'] ?? [] );
	?>
	<div class="bam-admin-wrap">
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'bam_banners_save', 'bam_banners_nonce' ); ?>
			<section class="bam-admin-card">
				<h2>Top banner (slider)</h2>
				<p class="bam-help">Arrastra para reordenar. Usa fechas para programar (opcional).</p>
				<div class="bam-actions">
					<button type="button" class="button button-primary" id="bam_top_left_add">Añadir imágenes</button>
				</div>
				<ul id="bam_top_left_list" style="margin:10px 0 0;display:grid;gap:8px;">
					<?php foreach ( $list as $index => $row ) : ?>
						<?php
						$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
						$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
						$start   = isset( $row['start'] ) ? (string) $row['start'] : '';
						$end     = isset( $row['end'] ) ? (string) $row['end'] : '';
						?>
						<li class="bam-gallery-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
							<div class="bam-gallery-row">
								<span class="dashicons dashicons-move bam-gallery-handle" aria-hidden="true"></span>
								<div class="bam-thumb bam-top-left-preview">
									<?php echo $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : ''; ?>
								</div>
								<div class="bam-actions">
									<button type="button" class="button bam-top-left-pick">Cambiar</button>
								</div>
								<button type="button" class="button-link-delete bam-top-left-remove bam-gallery-remove">Quitar</button>
							</div>
							<div class="bam-gallery-meta">
								<input type="hidden" name="bam_top_left[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $id ); ?>">
								<div class="bam-field">
									<label>Enlace</label>
									<input type="url" class="regular-text" name="bam_top_left[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://...">
								</div>
								<label>
									<input type="checkbox" name="bam_top_left[<?php echo esc_attr( (string) $index ); ?>][new_tab]" value="1" <?php checked( $new_tab, 1 ); ?>>
									Abrir en nueva pestaña
								</label>
								<div class="bam-actions" style="gap:12px;">
									<div class="bam-field" style="margin-top:0;min-width:160px;">
										<label>Inicio</label>
										<input type="date" name="bam_top_left[<?php echo esc_attr( (string) $index ); ?>][start]" value="<?php echo esc_attr( $start ); ?>">
									</div>
									<div class="bam-field" style="margin-top:0;min-width:160px;">
										<label>Fin</label>
										<input type="date" name="bam_top_left[<?php echo esc_attr( (string) $index ); ?>][end]" value="<?php echo esc_attr( $end ); ?>">
									</div>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="bam-submit"><?php submit_button( 'Guardar', 'primary', 'submit', false ); ?></div>
			</section>
		</form>
	</div>
	<script>
	jQuery(function($){
		function canUseMedia(){
			return window.wp && wp.media;
		}
		function thumbUrl(att){
			if (att && att.sizes && att.sizes.thumbnail) return att.sizes.thumbnail.url;
			return att && att.url ? att.url : '';
		}
		function pickImage(onSelect){
			if (!canUseMedia()) { alert('No se ha cargado el selector de medios. Recarga la página.'); return; }
			var frame = wp.media({title:'Selecciona una imagen', multiple:false, library:{type:'image'}});
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				onSelect(att);
			});
			frame.open();
		}
		function pickImages(onSelect){
			if (!canUseMedia()) { alert('No se ha cargado el selector de medios. Recarga la página.'); return; }
			var frame = wp.media({title:'Selecciona imágenes', multiple:true, library:{type:'image'}});
			frame.on('select', function(){
				var selection = frame.state().get('selection');
				var atts = [];
				selection.each(function(model){ atts.push(model.toJSON()); });
				onSelect(atts);
			});
			frame.open();
		}
		function renumber(){
			$('#bam_top_left_list > li').each(function(i){
				var $li = $(this);
				$li.attr('data-index', i);
				$li.find('input,select,textarea').each(function(){
					var $el = $(this);
					var name = $el.attr('name');
					if (!name) return;
					name = name.replace(/bam_top_left\\[[0-9]+\\]/g, 'bam_top_left['+i+']');
					$el.attr('name', name);
				});
			});
		}
		function initItem($li){
			$li.find('.bam-top-left-remove').on('click', function(){
				$li.remove();
				renumber();
			});
			$li.find('.bam-top-left-pick').on('click', function(){
				pickImage(function(att){
					$li.find('input[type=hidden][name*=\"[id]\"]').val(att.id);
					$li.find('.bam-top-left-preview').html('<img src=\"'+thumbUrl(att)+'\" alt=\"\">');
				});
			});
		}
		$('#bam_top_left_list > li').each(function(){ initItem($(this)); });
		$('#bam_top_left_list').sortable({
			items: '> li',
			axis: 'y',
			handle: '.bam-gallery-handle',
			cancel: 'input,textarea,button,select,label,a',
			stop: function(){ renumber(); }
		});
		$(document).on('click', '#bam_top_left_add', function(e){
			e.preventDefault();
			pickImages(function(atts){
				if (!atts || !atts.length) return;
				var nextIndex = 0;
				$('#bam_top_left_list > li').each(function(){
					var idx = parseInt($(this).attr('data-index') || '0', 10);
					if (idx >= nextIndex) nextIndex = idx + 1;
				});
				atts.forEach(function(att){
					var idx = nextIndex++;
					var $li = $('<li class=\"bam-gallery-item\" data-index=\"'+idx+'\">\
						<div class=\"bam-gallery-row\">\
							<span class=\"dashicons dashicons-move bam-gallery-handle\" aria-hidden=\"true\"></span>\
							<div class=\"bam-thumb bam-top-left-preview\"><img src=\"'+thumbUrl(att)+'\" alt=\"\"></div>\
							<div class=\"bam-actions\">\
								<button type=\"button\" class=\"button bam-top-left-pick\">Cambiar</button>\
							</div>\
							<button type=\"button\" class=\"button-link-delete bam-top-left-remove bam-gallery-remove\">Quitar</button>\
						</div>\
						<div class=\"bam-gallery-meta\">\
							<input type=\"hidden\" name=\"bam_top_left['+idx+'][id]\" value=\"'+att.id+'\">\
							<div class=\"bam-field\">\
								<label>Enlace</label>\
								<input type=\"url\" class=\"regular-text\" name=\"bam_top_left['+idx+'][url]\" value=\"\" placeholder=\"https://...\">\
							</div>\
							<label><input type=\"checkbox\" name=\"bam_top_left['+idx+'][new_tab]\" value=\"1\"> Abrir en nueva pestaña</label>\
							<div class=\"bam-actions\" style=\"gap:12px;\">\
								<div class=\"bam-field\" style=\"margin-top:0;min-width:160px;\">\
									<label>Inicio</label>\
									<input type=\"date\" name=\"bam_top_left['+idx+'][start]\" value=\"\">\
								</div>\
								<div class=\"bam-field\" style=\"margin-top:0;min-width:160px;\">\
									<label>Fin</label>\
									<input type=\"date\" name=\"bam_top_left['+idx+'][end]\" value=\"\">\
								</div>\
							</div>\
						</div>\
					</li>');
					$('#bam_top_left_list').append($li);
					initItem($li);
				});
				renumber();
			});
		});
	});
	</script>
	<?php
}

function alminuto_sidebar_right_sanitize_gallery( $raw ) {
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

function alminuto_sidebar_right_render_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'No tienes permisos.' );
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-sortable' );

	$saved = false;
	if ( isset( $_POST['alminuto_sidebar_right_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_sidebar_right_nonce'], 'alminuto_sidebar_right_save' ) ) {
		$opts = alminuto_sidebar_right_default_options();

		$opts['news_rigor_image_id'] = isset( $_POST['news_rigor_image_id'] ) ? (int) $_POST['news_rigor_image_id'] : 0;
		$opts['news_rigor_url']      = isset( $_POST['news_rigor_url'] ) ? esc_url_raw( (string) $_POST['news_rigor_url'] ) : '';

		$opts['block2_title']       = isset( $_POST['block2_title'] ) ? sanitize_text_field( (string) $_POST['block2_title'] ) : $opts['block2_title'];
		$opts['youtube_url']        = isset( $_POST['youtube_url'] ) ? esc_url_raw( (string) $_POST['youtube_url'] ) : '';
		$opts['facebook_video_url'] = isset( $_POST['facebook_video_url'] ) ? esc_url_raw( (string) $_POST['facebook_video_url'] ) : '';

		$opts['publi_gallery']       = alminuto_sidebar_right_sanitize_gallery( $_POST['publi_gallery'] ?? [] );

		update_option( 'alminuto_sidebar_right', $opts, false );
		$saved = true;
	}

	$opts = alminuto_sidebar_right_get_options();
	?>
	<div class="wrap bam-admin-wrap">
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'alminuto_sidebar_right_save', 'alminuto_sidebar_right_nonce' ); ?>
			<div class="bam-admin-title">
				<h1>Columna derecha (home)</h1>
				<div class="bam-submit-top"><?php submit_button( 'Guardar', 'primary', 'submit', false ); ?></div>
			</div>

			<div class="bam-admin-grid">
				<section class="bam-admin-card">
					<h2>Noticias con rigor</h2>
					<p class="bam-help">Selecciona una imagen y opcionalmente un enlace.</p>
					<div class="bam-news-grid">
						<div class="bam-news-preview" id="news_rigor_preview">
							<?php
							if ( (int) $opts['news_rigor_image_id'] > 0 ) {
								echo wp_kses_post( wp_get_attachment_image( (int) $opts['news_rigor_image_id'], 'medium' ) );
							}
							?>
						</div>
						<div>
							<input type="hidden" name="news_rigor_image_id" id="news_rigor_image_id" value="<?php echo esc_attr( (string) (int) $opts['news_rigor_image_id'] ); ?>">
							<div class="bam-actions">
								<button type="button" class="button button-primary" id="news_rigor_pick"><?php echo (int) $opts['news_rigor_image_id'] > 0 ? 'Cambiar imagen' : 'Elegir imagen'; ?></button>
								<button type="button" class="button" id="news_rigor_clear" <?php echo (int) $opts['news_rigor_image_id'] > 0 ? '' : 'disabled'; ?>>Quitar</button>
							</div>
							<div class="bam-field">
								<label for="news_rigor_url">Enlace</label>
								<input type="url" id="news_rigor_url" class="regular-text" name="news_rigor_url" value="<?php echo esc_attr( (string) $opts['news_rigor_url'] ); ?>" placeholder="https://...">
							</div>
						</div>
					</div>
				</section>

				<section class="bam-admin-card">
					<h2>Bloque 2</h2>
					<p class="bam-help">Título + vídeo de YouTube o Facebook (o ambos).</p>
					<div class="bam-field">
						<label for="block2_title">Título</label>
						<input type="text" id="block2_title" class="regular-text" name="block2_title" value="<?php echo esc_attr( (string) $opts['block2_title'] ); ?>">
					</div>
					<div class="bam-field">
						<label for="youtube_url">YouTube URL</label>
						<input type="url" id="youtube_url" class="regular-text" name="youtube_url" value="<?php echo esc_attr( (string) $opts['youtube_url'] ); ?>" placeholder="https://www.youtube.com/watch?v=...">
					</div>
					<div class="bam-field">
						<label for="facebook_video_url">Facebook video URL</label>
						<input type="url" id="facebook_video_url" class="regular-text" name="facebook_video_url" value="<?php echo esc_attr( (string) $opts['facebook_video_url'] ); ?>" placeholder="https://www.facebook.com/...">
					</div>
				</section>

				<section class="bam-admin-card bam-admin-card--full">
					<h2>Publicidad</h2>
					<p class="bam-help">La primera imagen será la principal. Arrastra para reordenar.</p>
					<div class="bam-actions">
						<button type="button" class="button button-primary" id="publi_gallery_add">Añadir imagen</button>
					</div>
					<ul id="publi_gallery_list">
						<?php foreach ( (array) $opts['publi_gallery'] as $index => $row ) : ?>
							<?php
							$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
							$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
							$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
							?>
							<li class="publi-item bam-gallery-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
								<div class="bam-gallery-row">
									<span class="dashicons dashicons-move bam-gallery-handle publi-handle" aria-hidden="true"></span>
									<div class="publi-preview bam-thumb">
										<?php echo $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : ''; ?>
									</div>
									<div class="bam-actions">
										<button type="button" class="button publi-pick">Cambiar</button>
									</div>
									<button type="button" class="button-link-delete publi-remove bam-gallery-remove">Quitar</button>
								</div>
								<div class="bam-gallery-meta">
									<input type="hidden" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $id ); ?>">
									<div class="bam-field">
										<label>Enlace</label>
										<input type="url" class="regular-text" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://...">
									</div>
									<label>
										<input type="checkbox" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][new_tab]" value="1" <?php checked( $new_tab, 1 ); ?>>
										Abrir en nueva pestaña
									</label>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>

			<div class="bam-submit"><?php submit_button( 'Guardar' ); ?></div>
		</form>
	</div>
	<script>
	jQuery(function($){
		function canUseMedia(){
			return window.wp && wp.media;
		}

		function previewUrl(att){
			if (att && att.sizes) {
				if (att.sizes.medium) return att.sizes.medium.url;
				if (att.sizes.large) return att.sizes.large.url;
				if (att.sizes.thumbnail) return att.sizes.thumbnail.url;
			}
			return att && att.url ? att.url : '';
		}
		function thumbUrl(att){
			if (att && att.sizes && att.sizes.thumbnail) return att.sizes.thumbnail.url;
			return previewUrl(att);
		}

		function pickImage(onSelect){
			if (!canUseMedia()) {
				alert('No se ha cargado el selector de medios. Recarga la página.');
				return;
			}
			var frame = wp.media({title:'Selecciona una imagen', multiple:false, library:{type:'image'}});
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				onSelect(att);
			});
			frame.open();
		}

		function pickImages(onSelect){
			if (!canUseMedia()) {
				alert('No se ha cargado el selector de medios. Recarga la página.');
				return;
			}
			var frame = wp.media({title:'Selecciona imágenes', multiple:true, library:{type:'image'}});
			frame.on('select', function(){
				var selection = frame.state().get('selection');
				var atts = [];
				selection.each(function(model){
					atts.push(model.toJSON());
				});
				onSelect(atts);
			});
			frame.open();
		}

		$('#news_rigor_pick').on('click', function(){
			pickImage(function(att){
				$('#news_rigor_image_id').val(att.id);
				$('#news_rigor_preview').html('<img src="'+previewUrl(att)+'" alt="">');
				$('#news_rigor_clear').prop('disabled', false);
				$('#news_rigor_pick').text('Cambiar imagen');
			});
		});
		$('#news_rigor_clear').on('click', function(){
			$('#news_rigor_image_id').val('');
			$('#news_rigor_preview').empty();
			$('#news_rigor_clear').prop('disabled', true);
			$('#news_rigor_pick').text('Elegir imagen');
		});

		function renumberGallery(){
			$('#publi_gallery_list .publi-item').each(function(i){
				var $li = $(this);
				$li.attr('data-index', i);
				$li.find('input,select,textarea').each(function(){
					var $el = $(this);
					var name = $el.attr('name');
					if (!name) return;
					name = name.replace(/publi_gallery\\[[0-9]+\\]/g, 'publi_gallery['+i+']');
					$el.attr('name', name);
				});
			});
		}

		function initGalleryItem($li){
			$li.find('.publi-remove').on('click', function(){
				$li.remove();
				renumberGallery();
			});
			$li.find('.publi-pick').on('click', function(){
				pickImage(function(att){
					$li.find('input[type=hidden][name*=\"[id]\"]').val(att.id);
					$li.find('.publi-preview').html('<img src=\"'+thumbUrl(att)+'\" alt=\"\">');
				});
			});
		}

		$('#publi_gallery_list .publi-item').each(function(){ initGalleryItem($(this)); });

		$('#publi_gallery_list').sortable({
			items: '> li',
			axis: 'y',
			handle: '.publi-handle',
			cancel: 'input,textarea,button,select,label,a',
			stop: function(){
				renumberGallery();
			}
		});

		$(document).on('click', '#publi_gallery_add', function(e){
			e.preventDefault();
			pickImages(function(atts){
				if (!atts || !atts.length) return;
				var nextIndex = 0;
				$('#publi_gallery_list .publi-item').each(function(){
					var idx = parseInt($(this).attr('data-index') || '0', 10);
					if (idx >= nextIndex) nextIndex = idx + 1;
				});

				atts.forEach(function(att){
					var idx = nextIndex++;
					var $li = $('<li class=\"publi-item bam-gallery-item\" data-index=\"'+idx+'\">\
						<div class=\"bam-gallery-row\">\
							<span class=\"dashicons dashicons-move bam-gallery-handle publi-handle\" aria-hidden=\"true\"></span>\
							<div class=\"publi-preview bam-thumb\"><img src=\"'+thumbUrl(att)+'\" alt=\"\"></div>\
							<div class=\"bam-actions\">\
								<button type=\"button\" class=\"button publi-pick\">Cambiar</button>\
							</div>\
							<button type=\"button\" class=\"button-link-delete publi-remove bam-gallery-remove\">Quitar</button>\
						</div>\
						<div class=\"bam-gallery-meta\">\
							<input type=\"hidden\" name=\"publi_gallery['+idx+'][id]\" value=\"'+att.id+'\">\
							<div class=\"bam-field\">\
								<label>Enlace</label>\
								<input type=\"url\" class=\"regular-text\" name=\"publi_gallery['+idx+'][url]\" value=\"\" placeholder=\"https://...\">\
							</div>\
							<label><input type=\"checkbox\" name=\"publi_gallery['+idx+'][new_tab]\" value=\"1\"> Abrir en nueva pestaña</label>\
						</div>\
					</li>');
					$('#publi_gallery_list').append($li);
					initGalleryItem($li);
				});
				renumberGallery();
			});
		});
	});
	</script>
	<?php
}

function alminuto_sidebar_right_shortcode() {
	$opts = alminuto_sidebar_right_get_options();

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
	return $out;
}
add_shortcode( 'alminuto_sidebar_right', 'alminuto_sidebar_right_shortcode' );

function banners_alminuto_render_order_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'No tienes permisos.' );
	}

	$slots = [
		'top_left' => 'Superior izquierda (slider)',
		'top_mid'  => 'Superior centro',
	];

	?>
	<div class="wrap">
		<h1>Ordenar banners (drag &amp; drop)</h1>
		<p>Arrastra para reordenar. Se guarda al soltar.</p>
		<?php foreach ( $slots as $slot => $label ) : ?>
			<h2 class="title"><?php echo esc_html( $label ); ?></h2>
			<ul class="bam-order-list" data-slot="<?php echo esc_attr( $slot ); ?>" style="margin:0;max-width:700px;">
				<?php
				$q = new WP_Query(
					[
						'post_type'      => 'banner',
						'post_status'    => 'publish',
						'posts_per_page' => 200,
						'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
						'no_found_rows'  => true,
						'meta_query'     => [
							[
								'key'   => '_bam_slot',
								'value' => $slot,
							],
						],
					]
				);
				foreach ( $q->posts as $p ) :
					$thumb = get_the_post_thumbnail( $p->ID, 'thumbnail' );
					?>
					<li class="bam-order-item" data-id="<?php echo esc_attr( (string) (int) $p->ID ); ?>" style="margin:0 0 10px;padding:10px;border:1px solid #ddd;background:#fff;display:flex;gap:12px;align-items:center;cursor:move;">
						<span style="font-weight:700;">↕</span>
						<div style="width:120px;"><?php echo wp_kses_post( $thumb ); ?></div>
						<div>
							<div style="font-weight:800;"><?php echo esc_html( get_the_title( $p ) ); ?></div>
							<div style="color:#666;font-size:12px;">ID <?php echo esc_html( (string) (int) $p->ID ); ?></div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	</div>
	<script>
	(function($){
		var ajaxurl = window.ajaxurl;
		function saveOrder($list){
			var slot = $list.data('slot');
			var ids = $list.find('.bam-order-item').map(function(){ return $(this).data('id'); }).get();
			$.post(ajaxurl, {action:'banners_alminuto_save_order', slot:slot, ids:ids, _ajax_nonce:'<?php echo esc_js( wp_create_nonce( 'banners_alminuto_save_order' ) ); ?>'});
		}
		$('.bam-order-list').each(function(){
			var $list=$(this);
			$list.sortable({
				items: '> li',
				axis: 'y',
				stop: function(){ saveOrder($list); }
			});
		});
	})(jQuery);
	</script>
	<?php
}

function banners_alminuto_ajax_save_order() {
	check_ajax_referer( 'banners_alminuto_save_order' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}
	$slot = isset( $_POST['slot'] ) ? sanitize_key( (string) wp_unslash( $_POST['slot'] ) ) : '';
	if ( ! in_array( $slot, [ 'top_left', 'top_mid' ], true ) ) {
		wp_send_json_error();
	}
	$ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : [];
	$order = 0;
	foreach ( $ids as $id ) {
		$post_id = (int) $id;
		if ( $post_id <= 0 ) {
			continue;
		}
		if ( get_post_type( $post_id ) !== 'banner' ) {
			continue;
		}
		$current_slot = (string) get_post_meta( $post_id, '_bam_slot', true );
		if ( $current_slot !== $slot ) {
			continue;
		}
		wp_update_post(
			[
				'ID'         => $post_id,
				'menu_order' => $order,
			]
		);
		$order++;
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_banners_alminuto_save_order', 'banners_alminuto_ajax_save_order' );
