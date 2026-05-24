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
	?>
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

function banners_alminuto_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'limit' => 2,
			'slot'  => 0,
			'size'  => 'full',
			'class' => '',
		],
		(array) $atts,
		'banners_alminuto'
	);

	$limit = max( 1, (int) $atts['limit'] );
	$slot  = max( 0, (int) $atts['slot'] );
	$size  = sanitize_key( (string) $atts['size'] );
	$class = trim( (string) $atts['class'] );

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

	if ( $slot > 0 ) {
		$query_args['posts_per_page'] = 1;
		$query_args['offset']         = $slot - 1;
	}

	$q = new WP_Query( $query_args );
	if ( ! $q->have_posts() ) {
		return '';
	}

	$items = [];
	foreach ( $q->posts as $p ) {
		$html = banners_alminuto_render_banner_html( (int) $p->ID, $size );
		if ( $html ) {
			$items[] = '<div class="bam-item">' . $html . '</div>';
		}
	}

	if ( empty( $items ) ) {
		return '';
	}

	$classes = 'bam-wrap';
	if ( $class !== '' ) {
		$classes .= ' ' . sanitize_html_class( $class );
	}

	return '<div class="' . esc_attr( $classes ) . '">' . implode( '', $items ) . '</div>';
}
add_shortcode( 'banners_alminuto', 'banners_alminuto_shortcode' );
