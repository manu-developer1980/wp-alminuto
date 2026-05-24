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

function alminuto_sidebar_right_default_options() {
	return [
		'news_rigor_image_id'  => 0,
		'news_rigor_url'       => '',
		'block2_title'         => 'ALGECIRAS ES SEMANA SANTA',
		'youtube_url'          => '',
		'facebook_video_url'   => '',
		'publi_main_image_id'  => 0,
		'publi_main_url'       => '',
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
	if ( $hook_suffix !== 'banner_page_alminuto-sidebar-right' && $hook_suffix !== 'banner_page_banners-alminuto-order' ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );
}
add_action( 'admin_enqueue_scripts', 'alminuto_sidebar_right_admin_enqueue' );

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

	$saved = false;
	if ( isset( $_POST['alminuto_sidebar_right_nonce'] ) && wp_verify_nonce( (string) $_POST['alminuto_sidebar_right_nonce'], 'alminuto_sidebar_right_save' ) ) {
		$opts = alminuto_sidebar_right_default_options();

		$opts['news_rigor_image_id'] = isset( $_POST['news_rigor_image_id'] ) ? (int) $_POST['news_rigor_image_id'] : 0;
		$opts['news_rigor_url']      = isset( $_POST['news_rigor_url'] ) ? esc_url_raw( (string) $_POST['news_rigor_url'] ) : '';

		$opts['block2_title']       = isset( $_POST['block2_title'] ) ? sanitize_text_field( (string) $_POST['block2_title'] ) : $opts['block2_title'];
		$opts['youtube_url']        = isset( $_POST['youtube_url'] ) ? esc_url_raw( (string) $_POST['youtube_url'] ) : '';
		$opts['facebook_video_url'] = isset( $_POST['facebook_video_url'] ) ? esc_url_raw( (string) $_POST['facebook_video_url'] ) : '';

		$opts['publi_main_image_id'] = isset( $_POST['publi_main_image_id'] ) ? (int) $_POST['publi_main_image_id'] : 0;
		$opts['publi_main_url']      = isset( $_POST['publi_main_url'] ) ? esc_url_raw( (string) $_POST['publi_main_url'] ) : '';
		$opts['publi_gallery']       = alminuto_sidebar_right_sanitize_gallery( $_POST['publi_gallery'] ?? [] );

		update_option( 'alminuto_sidebar_right', $opts, false );
		$saved = true;
	}

	$opts = alminuto_sidebar_right_get_options();
	?>
	<div class="wrap">
		<h1>Columna derecha (home)</h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Guardado.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'alminuto_sidebar_right_save', 'alminuto_sidebar_right_nonce' ); ?>

			<h2 class="title">Noticias con rigor</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Imagen</th>
					<td>
						<input type="hidden" name="news_rigor_image_id" id="news_rigor_image_id" value="<?php echo esc_attr( (string) (int) $opts['news_rigor_image_id'] ); ?>">
						<button type="button" class="button" id="news_rigor_pick">Elegir imagen</button>
						<div id="news_rigor_preview" style="margin-top:10px;max-width:320px;">
							<?php
							if ( (int) $opts['news_rigor_image_id'] > 0 ) {
								echo wp_kses_post( wp_get_attachment_image( (int) $opts['news_rigor_image_id'], 'medium' ) );
							}
							?>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">Enlace</th>
					<td>
						<input type="url" class="regular-text" name="news_rigor_url" value="<?php echo esc_attr( (string) $opts['news_rigor_url'] ); ?>" placeholder="https://...">
					</td>
				</tr>
			</table>

			<h2 class="title">Bloque 2</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Título</th>
					<td>
						<input type="text" class="regular-text" name="block2_title" value="<?php echo esc_attr( (string) $opts['block2_title'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">YouTube URL</th>
					<td>
						<input type="url" class="regular-text" name="youtube_url" value="<?php echo esc_attr( (string) $opts['youtube_url'] ); ?>" placeholder="https://www.youtube.com/watch?v=...">
					</td>
				</tr>
				<tr>
					<th scope="row">Facebook video URL</th>
					<td>
						<input type="url" class="regular-text" name="facebook_video_url" value="<?php echo esc_attr( (string) $opts['facebook_video_url'] ); ?>" placeholder="https://www.facebook.com/...">
					</td>
				</tr>
			</table>

			<h2 class="title">Publicidad</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Imagen principal</th>
					<td>
						<input type="hidden" name="publi_main_image_id" id="publi_main_image_id" value="<?php echo esc_attr( (string) (int) $opts['publi_main_image_id'] ); ?>">
						<button type="button" class="button" id="publi_main_pick">Elegir imagen</button>
						<div id="publi_main_preview" style="margin-top:10px;max-width:320px;">
							<?php
							if ( (int) $opts['publi_main_image_id'] > 0 ) {
								echo wp_kses_post( wp_get_attachment_image( (int) $opts['publi_main_image_id'], 'medium' ) );
							}
							?>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">Enlace imagen principal</th>
					<td>
						<input type="url" class="regular-text" name="publi_main_url" value="<?php echo esc_attr( (string) $opts['publi_main_url'] ); ?>" placeholder="https://...">
					</td>
				</tr>
			</table>

			<h2 class="title">Galería (drag &amp; drop)</h2>
			<p><button type="button" class="button" id="publi_gallery_add">Añadir imagen</button></p>
			<ul id="publi_gallery_list" style="margin:0;max-width:360px;">
				<?php foreach ( (array) $opts['publi_gallery'] as $index => $row ) : ?>
					<?php
					$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
					$new_tab = ! empty( $row['new_tab'] ) ? 1 : 0;
					?>
					<li class="publi-item" style="margin:0 0 10px;padding:10px;border:1px solid #ddd;background:#fff;display:grid;gap:8px;cursor:move;" data-index="<?php echo esc_attr( (string) $index ); ?>">
						<div style="display:flex;gap:10px;align-items:center;">
							<span style="font-weight:700;">↕</span>
							<div class="publi-preview" style="width:120px;">
								<?php echo $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, 'thumbnail' ) ) : ''; ?>
							</div>
							<button type="button" class="button publi-pick">Cambiar</button>
							<button type="button" class="button-link-delete publi-remove">Quitar</button>
						</div>
						<input type="hidden" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $id ); ?>">
						<label>Enlace
							<input type="url" class="regular-text" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://...">
						</label>
						<label>
							<input type="checkbox" name="publi_gallery[<?php echo esc_attr( (string) $index ); ?>][new_tab]" value="1" <?php checked( $new_tab, 1 ); ?>>
							Abrir en nueva pestaña
						</label>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php submit_button( 'Guardar' ); ?>
		</form>
	</div>
	<script>
	(function($){
		function pickImage(onSelect){
			var frame = wp.media({title:'Selecciona una imagen', multiple:false, library:{type:'image'}});
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				onSelect(att);
			});
			frame.open();
		}

		$('#news_rigor_pick').on('click', function(){
			pickImage(function(att){
				$('#news_rigor_image_id').val(att.id);
				$('#news_rigor_preview').html('<img src="'+att.sizes.medium.url+'" style="max-width:100%;height:auto;">');
			});
		});

		$('#publi_main_pick').on('click', function(){
			pickImage(function(att){
				$('#publi_main_image_id').val(att.id);
				$('#publi_main_preview').html('<img src="'+att.sizes.medium.url+'" style="max-width:100%;height:auto;">');
			});
		});

		function initGalleryItem($li){
			$li.find('.publi-remove').on('click', function(){
				$li.remove();
			});
			$li.find('.publi-pick').on('click', function(){
				pickImage(function(att){
					$li.find('input[type=hidden][name*=\"[id]\"]').val(att.id);
					$li.find('.publi-preview').html('<img src=\"'+(att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'\" style=\"max-width:100%;height:auto;\">');
				});
			});
		}

		$('#publi_gallery_list .publi-item').each(function(){ initGalleryItem($(this)); });

		$('#publi_gallery_list').sortable({
			items: '> li',
			axis: 'y'
		});

		$('#publi_gallery_add').on('click', function(){
			var nextIndex = 0;
			$('#publi_gallery_list .publi-item').each(function(){
				var idx = parseInt($(this).attr('data-index') || '0', 10);
				if (idx >= nextIndex) nextIndex = idx + 1;
			});

			var $li = $('<li class=\"publi-item\" style=\"margin:0 0 10px;padding:10px;border:1px solid #ddd;background:#fff;display:grid;gap:8px;cursor:move;\" data-index=\"'+nextIndex+'\">\
				<div style=\"display:flex;gap:10px;align-items:center;\">\
					<span style=\"font-weight:700;\">↕</span>\
					<div class=\"publi-preview\" style=\"width:120px;\"></div>\
					<button type=\"button\" class=\"button publi-pick\">Elegir</button>\
					<button type=\"button\" class=\"button-link-delete publi-remove\">Quitar</button>\
				</div>\
				<input type=\"hidden\" name=\"publi_gallery['+nextIndex+'][id]\" value=\"\">\
				<label>Enlace\
					<input type=\"url\" class=\"regular-text\" name=\"publi_gallery['+nextIndex+'][url]\" value=\"\" placeholder=\"https://...\">\
				</label>\
				<label><input type=\"checkbox\" name=\"publi_gallery['+nextIndex+'][new_tab]\" value=\"1\"> Abrir en nueva pestaña</label>\
			</li>');
			$('#publi_gallery_list').append($li);
			initGalleryItem($li);
			pickImage(function(att){
				$li.find('input[type=hidden][name*=\"[id]\"]').val(att.id);
				$li.find('.publi-preview').html('<img src=\"'+(att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'\" style=\"max-width:100%;height:auto;\">');
			});
		});
	})(jQuery);
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
	if ( (int) $opts['publi_main_image_id'] > 0 ) {
		$img = wp_get_attachment_image( (int) $opts['publi_main_image_id'], $img_size, false, [ 'loading' => 'lazy' ] );
		if ( $img ) {
			if ( $opts['publi_main_url'] ) {
				$out .= '<a href="' . esc_url( (string) $opts['publi_main_url'] ) . '" target="_self" rel="nofollow noopener noreferrer">' . $img . '</a>';
			} else {
				$out .= $img;
			}
		}
	}

	foreach ( (array) $opts['publi_gallery'] as $row ) {
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
		if ( $url ) {
			$target = $new_tab ? ' target="_blank" rel="nofollow noopener noreferrer"' : ' target="_self" rel="nofollow noopener noreferrer"';
			$out   .= '<a href="' . esc_url( $url ) . '"' . $target . '>' . $img . '</a>';
		} else {
			$out .= $img;
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
