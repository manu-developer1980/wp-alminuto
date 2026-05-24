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

// Verificar dependencias
function verificar_dependencias() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('post-types-order/post-types-order.php')) {
        add_action('admin_notices', 'mostrar_aviso_dependencia');
    }
}
add_action('admin_init', 'verificar_dependencias');

function mostrar_aviso_dependencia() {
    echo '<div class="error"><p>El plugin "Banners al Minuto" requiere que el plugin "Post Types Order" esté activo.</p></div>';
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
        'supports'           => array('title', 'editor', 'thumbnail'),
        'show_in_graphql'    => true, // Habilita en WPGraphQL
        'graphql_single_name' => 'Banner', // Nombre para un solo ítem
        'graphql_plural_name' => 'Banners' // Nombre para la colección
    );

    register_post_type('banner', $args);
}
add_action('init', 'crear_cpt_banners');

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

function alminuto_columna_dcha_register_cpt() {
	$labels = [
		'name'               => 'Columna derecha',
		'singular_name'      => 'Item columna derecha',
		'menu_name'          => 'Columna derecha',
		'name_admin_bar'     => 'Item columna derecha',
		'add_new'            => 'Añadir nuevo',
		'add_new_item'       => 'Añadir nuevo item',
		'new_item'           => 'Nuevo item',
		'edit_item'          => 'Editar item',
		'view_item'          => 'Ver item',
		'all_items'          => 'Todos los items',
		'search_items'       => 'Buscar items',
		'not_found'          => 'No se encontraron items.',
		'not_found_in_trash' => 'No se encontraron items en la papelera.',
	];

	$args = [
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_position'      => null,
		'supports'           => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
		'has_archive'        => false,
		'show_in_rest'       => true,
	];

	register_post_type( 'alminuto_columna_dcha', $args );
}
add_action( 'init', 'alminuto_columna_dcha_register_cpt' );

function alminuto_columna_dcha_register_metaboxes() {
	add_meta_box(
		'alminuto_columna_dcha_link',
		'Enlace (opcional)',
		'alminuto_columna_dcha_render_metabox_link',
		'alminuto_columna_dcha',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'alminuto_columna_dcha_register_metaboxes' );

function alminuto_columna_dcha_render_metabox_link( $post ) {
	wp_nonce_field( 'alminuto_columna_dcha_save', 'alminuto_columna_dcha_nonce' );
	$url     = get_post_meta( $post->ID, '_amcd_url', true );
	$new_tab = (int) get_post_meta( $post->ID, '_amcd_new_tab', true );
	?>
	<p>
		<label for="amcd_url"><strong>URL</strong></label>
		<input type="url" id="amcd_url" name="amcd_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%;" placeholder="https://...">
	</p>
	<p>
		<label>
			<input type="checkbox" name="amcd_new_tab" value="1" <?php checked( $new_tab, 1 ); ?>>
			Abrir en nueva pestaña
		</label>
	</p>
	<?php
}

function alminuto_columna_dcha_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['alminuto_columna_dcha_nonce'] ) || ! wp_verify_nonce( $_POST['alminuto_columna_dcha_nonce'], 'alminuto_columna_dcha_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['amcd_url'] ) ) {
		$url = trim( (string) wp_unslash( $_POST['amcd_url'] ) );
		$url = $url === '' ? '' : esc_url_raw( $url );
		if ( $url ) {
			update_post_meta( $post_id, '_amcd_url', $url );
		} else {
			delete_post_meta( $post_id, '_amcd_url' );
		}
	}

	$new_tab = isset( $_POST['amcd_new_tab'] ) ? 1 : 0;
	if ( $new_tab ) {
		update_post_meta( $post_id, '_amcd_new_tab', 1 );
	} else {
		delete_post_meta( $post_id, '_amcd_new_tab' );
	}
}
add_action( 'save_post_alminuto_columna_dcha', 'alminuto_columna_dcha_save_meta' );

function alminuto_columna_dcha_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'limit' => 20,
		],
		(array) $atts,
		'alminuto_columna_dcha'
	);

	$limit = max( 1, (int) $atts['limit'] );
	$q     = new WP_Query(
		[
			'post_type'      => 'alminuto_columna_dcha',
			'post_status'    => 'publish',
			'orderby'        => [
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			],
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
		]
	);

	if ( ! $q->have_posts() ) {
		return '';
	}

	$out = '';
	foreach ( $q->posts as $p ) {
		$content = apply_filters( 'the_content', $p->post_content );
		$thumb   = get_the_post_thumbnail( $p->ID, 'medium', [ 'loading' => 'lazy' ] );
		$url     = get_post_meta( $p->ID, '_amcd_url', true );
		$new_tab = (int) get_post_meta( $p->ID, '_amcd_new_tab', true );

		$out .= '<article class="am-card"><div class="am-card-body">';
		if ( $thumb ) {
			if ( $url ) {
				$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
				$out   .= '<a href="' . esc_url( $url ) . '"' . $target . '>' . $thumb . '</a>';
			} else {
				$out .= $thumb;
			}
		}
		if ( $p->post_title !== '' ) {
			$out .= '<h3 style="margin:10px 0 8px;font-size:16px;font-weight:900;">' . esc_html( $p->post_title ) . '</h3>';
		}
		if ( trim( wp_strip_all_tags( $content ) ) !== '' ) {
			$out .= '<div class="am-content">' . wp_kses_post( $content ) . '</div>';
		}
		$out .= '</div></article>';
	}

	return $out;
}
add_shortcode( 'alminuto_columna_dcha', 'alminuto_columna_dcha_shortcode' );
