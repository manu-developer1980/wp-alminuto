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