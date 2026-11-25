<?php
/**
 * Site Snapshot API class for providing comprehensive site structure
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_REST_Response;
use WP_REST_Request;
use WP_Block_Type_Registry;

/**
 * Handles REST API endpoint for site snapshot.
 * Provides a comprehensive site map in a single authenticated read-only call.
 */
class Site_Snapshot extends Rest_Controller_Base {
	/**
	 * REST API base
	 *
	 * @var string
	 */
	private $base = 'site-snapshot';

	/**
	 * Navigation block types that count as menu items in FSE navigation
	 *
	 * Must stay synchronized with the list in Menus::parse_navigation_blocks().
	 *
	 * @var array
	 */
	private $nav_block_types = array(
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/home-link',
		'core/loginout',
		'core/search',
		'core/social-links',
		'core/spacer',
		'core/icon',
		'core/site-title',
		'core/site-logo',
		'core/buttons',
	);

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_site_snapshot' ),
				'permission_callback' => array( $this, 'read_permissions_check' ),
				'args'                => $this->get_snapshot_args(),
				'description'         => 'Retrieves comprehensive site snapshot including theme, templates, posts, pages, and structure.',
			)
		);
	}

	/**
	 * Get arguments for site snapshot
	 *
	 * @return array
	 */
	protected function get_snapshot_args() {
		return array(
			'per_page' => array(
				'description'       => 'Maximum items for posts/pages collections.',
				'type'              => 'integer',
				'default'           => 50,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'offset'   => array(
				'description'       => 'Offset for posts/pages pagination.',
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get comprehensive site snapshot
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object with site snapshot data.
	 */
	public function get_site_snapshot( $request ) {
		$per_page = $request->get_param( 'per_page' ) ?: 50;
		$offset   = $request->get_param( 'offset' ) ?: 0;

		$snapshot = array(
			'theme'               => $this->get_theme_info(),
			'wp'                  => $this->get_wp_info(),
			'theme_tokens'        => $this->get_theme_tokens(),
			'pages'               => $this->get_lightweight_posts( 'page', $per_page, $offset ),
			'posts'               => $this->get_lightweight_posts( 'post', $per_page, $offset ),
			'templates'           => $this->get_templates(),
			'template_parts'      => $this->get_template_parts(),
			'reusable_blocks'     => $this->get_reusable_blocks(),
			'custom_block_types'  => $this->get_custom_block_types(),
			'pattern_categories'  => $this->get_pattern_categories(),
			'menus'               => $this->get_menus(),
			'post_types'          => $this->get_post_types(),
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $snapshot,
			),
			200
		);
	}

	/**
	 * Get active theme information
	 *
	 * @return array Theme info.
	 */
	private function get_theme_info() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return array(
			'slug'           => $theme->get_stylesheet(),
			'name'           => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'parent'         => $parent ? $parent->get_stylesheet() : null,
			// Gate for validated block editing: true for block AND hybrid themes.
			'has_theme_json' => function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json(),
			// Reserved for Site Editor / template work, not the block editing gate.
			'is_block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			'page_builders'  => $this->detect_page_builders( $theme, $parent ),
		);
	}

	/**
	 * Detect which page builders are active on the site
	 *
	 * Detects from active plugins and theme. Every builder is checked, since a
	 * site can run several at once (e.g. Elementor and Beaver Builder). Returns
	 * an empty array when no builder is detected, so the agent can distinguish
	 * "no builder" from "not reported".
	 *
	 * @param \WP_Theme      $theme  Active theme object.
	 * @param \WP_Theme|bool $parent Parent theme object or false if no parent.
	 * @return array Page builder slugs.
	 */
	private function detect_page_builders( $theme, $parent ) {
		// Get theme names once.
		$theme_names = array_filter( array( $theme->get( 'Name' ), $parent ? $parent->get( 'Name' ) : '' ) );

		$builders = array(
			'elementor'        => class_exists( '\Elementor\Plugin' ),
			// Divi - theme (Divi or Extra) or the standalone Divi Builder plugin.
			'divi'             => (bool) array_intersect( array( 'Divi', 'Extra' ), $theme_names ) || function_exists( 'et_divi_fonts_url' ),
			'wpbakery'         => class_exists( '\Vc_Manager' ) || function_exists( 'vc_is_inline' ),
			'avada'            => in_array( 'Avada', $theme_names, true ),
			'beaver-builder'   => class_exists( '\FLBuilder' ) || class_exists( '\FLBuilderModel' ),
			'bricks'           => in_array( 'Bricks', $theme_names, true ),
			'oxygen'           => defined( 'CT_VERSION' ) || function_exists( 'oxygen_vsb_init' ),
			'thrive-architect' => function_exists( 'tve_in_architect' ),
			'cornerstone'      => function_exists( 'cornerstone_is_permalink_endpoint' ),
		);

		return array_keys( array_filter( $builders ) );
	}

	/**
	 * Get WordPress environment info
	 *
	 * Lets the block markup validator pin its core block library to the
	 * version actually installed on the site.
	 *
	 * @return array WordPress version info.
	 */
	private function get_wp_info() {
		return array(
			'version'   => get_bloginfo( 'version' ),
			'gutenberg' => defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : null,
		);
	}

	/**
	 * Get theme tokens from theme.json (only theme-defined values, not WP core defaults)
	 *
	 * @return array Theme tokens.
	 */
	private function get_theme_tokens() {
		$tokens = array(
			'colors'         => array(),
			'gradients'      => array(),
			'duotones'       => array(),
			'font_families'  => array(),
			'font_sizes'     => array(),
			'spacing_sizes'  => array(),
			'shadows'        => array(),
			'layout'         => array(),
		);

		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return $tokens;
		}

		$theme_json = \WP_Theme_JSON_Resolver::get_theme_data();
		if ( ! $theme_json ) {
			return $tokens;
		}

		$settings = $theme_json->get_settings();

		if ( isset( $settings['color']['palette']['theme'] ) ) {
			$tokens['colors'] = $settings['color']['palette']['theme'];
		}

		if ( isset( $settings['color']['gradients']['theme'] ) ) {
			$tokens['gradients'] = $settings['color']['gradients']['theme'];
		}

		if ( isset( $settings['color']['duotone']['theme'] ) ) {
			$tokens['duotones'] = $settings['color']['duotone']['theme'];
		}

		$tokens['font_families'] = $this->get_font_families( $settings );

		if ( isset( $settings['typography']['fontSizes']['theme'] ) ) {
			$tokens['font_sizes'] = $settings['typography']['fontSizes']['theme'];
		}

		if ( isset( $settings['spacing']['spacingSizes']['theme'] ) ) {
			$tokens['spacing_sizes'] = $settings['spacing']['spacingSizes']['theme'];
		}

		if ( isset( $settings['shadow']['presets']['theme'] ) ) {
			$tokens['shadows'] = $settings['shadow']['presets']['theme'];
		}

		if ( isset( $settings['layout'] ) ) {
			$tokens['layout'] = $settings['layout'];
		}

		return $tokens;
	}

	/**
	 * Get font families declared by the theme and the Font Library
	 *
	 * Faces carry src and weight, so the agent can tell local fonts from
	 * externally loaded ones.
	 *
	 * @param array $settings Theme.json settings.
	 * @return array Font families.
	 */
	private function get_font_families( $settings ) {
		$families = array();

		if ( ! empty( $settings['typography']['fontFamilies']['theme'] ) ) {
			$families = $this->format_font_families( (array) $settings['typography']['fontFamilies']['theme'] );
		}

		return $this->merge_user_font_families( $families );
	}

	/**
	 * Merge in the fonts added through the Font Library
	 *
	 * They live in the user origin, not the theme one. A shared slug overrides
	 * the theme font, as WordPress does.
	 *
	 * @param array $families Theme font families.
	 * @return array Theme and user font families.
	 */
	private function merge_user_font_families( $families ) {
		if ( ! method_exists( '\WP_Theme_JSON_Resolver', 'get_user_data' ) ) {
			return $families;
		}

		$user_data = \WP_Theme_JSON_Resolver::get_user_data();

		if ( ! $user_data ) {
			return $families;
		}

		$user_settings = $user_data->get_settings();

		if ( empty( $user_settings['typography']['fontFamilies']['custom'] ) ) {
			return $families;
		}

		$custom = $this->format_font_families( (array) $user_settings['typography']['fontFamilies']['custom'] );

		if ( empty( $custom ) ) {
			return $families;
		}

		$positions = $this->get_font_family_positions( $families );

		foreach ( $custom as $family ) {
			$slug = isset( $family['slug'] ) ? $family['slug'] : '';

			// Unknown slug: a font added on top of the theme.
			if ( ! isset( $positions[ $slug ] ) ) {
				$families[] = $family;
				continue;
			}

			$families[ $positions[ $slug ] ] = $family;
		}

		return $families;
	}

	/**
	 * Map font family slugs to their position in the list
	 *
	 * @param array $families Font families.
	 * @return array Map of slug to list position.
	 */
	private function get_font_family_positions( $families ) {
		$positions = array();

		foreach ( $families as $position => $family ) {
			if ( empty( $family['slug'] ) ) {
				continue;
			}

			$positions[ $family['slug'] ] = $position;
		}

		return $positions;
	}

	/**
	 * Normalize font families to the theme.json font family shape
	 *
	 * @param array $families Raw font families.
	 * @return array Normalized font families.
	 */
	private function format_font_families( $families ) {
		$formatted = array();

		foreach ( $families as $family ) {
			if ( ! is_array( $family ) ) {
				continue;
			}

			$entry = $this->pick_string_values( $family, array( 'name', 'slug', 'fontFamily' ) );

			if ( empty( $entry ) ) {
				continue;
			}

			$faces = array();

			if ( ! empty( $family['fontFace'] ) ) {
				$faces = $this->format_font_faces( (array) $family['fontFace'] );
			}

			// No faces means a system font stack: nothing is loaded.
			if ( ! empty( $faces ) ) {
				$entry['fontFace'] = $faces;
			}

			$formatted[] = $entry;
		}

		return $formatted;
	}

	/**
	 * Normalize font faces to the theme.json font face shape
	 *
	 * The src stays as authored: 'file:./' marks a theme-bundled font, an
	 * absolute URL shows the host serving it.
	 *
	 * @param array $faces Raw font faces.
	 * @return array Normalized font faces.
	 */
	private function format_font_faces( $faces ) {
		$formatted = array();

		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) || empty( $face['src'] ) ) {
				continue;
			}

			// theme.json allows a single src string as well as a list.
			$src = array_values( array_filter( (array) $face['src'], 'is_string' ) );

			// Without a src the face loads nothing.
			if ( empty( $src ) ) {
				continue;
			}

			$formatted[] = array_merge(
				array( 'src' => $src ),
				$this->pick_string_values( $face, array( 'fontWeight', 'fontStyle', 'fontFamily' ) )
			);
		}

		return $formatted;
	}

	/**
	 * Pick the given keys out of a theme.json entry as strings
	 *
	 * Keeps the schema identical across sites, where themes and plugins add keys
	 * of their own. Numbers are cast, so a weight of 400 reads as "400".
	 *
	 * @param array $source Raw theme.json entry.
	 * @param array $keys   Keys to keep, in output order.
	 * @return array Picked values.
	 */
	private function pick_string_values( $source, $keys ) {
		$picked = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $source[ $key ] ) ) {
				continue;
			}

			if ( ! is_string( $source[ $key ] ) && ! is_numeric( $source[ $key ] ) ) {
				continue;
			}

			$picked[ $key ] = (string) $source[ $key ];
		}

		return $picked;
	}

	/**
	 * Get lightweight posts or pages (capped and paginated)
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $per_page  Number of items per page.
	 * @param int    $offset    Offset for pagination.
	 * @return array Lightweight post data.
	 */
	private function get_lightweight_posts( $post_type, $per_page, $offset ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
				'posts_per_page' => $per_page,
				'offset'         => $offset,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'       => $post->ID,
				'slug'     => $post->post_name,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'parent'   => $post->post_parent,
				'template' => get_page_template_slug( $post->ID ) ?: '',
			);
		}

		return $items;
	}

	/**
	 * Get block theme templates
	 *
	 * @return array Templates data.
	 */
	private function get_templates() {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return array();
		}

		$templates = array();

		if ( function_exists( 'get_block_templates' ) ) {
			$block_templates = get_block_templates();
			foreach ( $block_templates as $template ) {
				$templates[] = array(
					'id'     => $template->id,
					'slug'   => $template->slug,
					'title'  => $template->title,
					'source' => $template->source,
					'area'   => isset( $template->area ) ? $template->area : null,
				);
			}
		}

		return $templates;
	}

	/**
	 * Get block theme template parts
	 *
	 * @return array Template parts data.
	 */
	private function get_template_parts() {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return array();
		}

		$template_parts = array();

		if ( function_exists( 'get_block_templates' ) ) {
			$block_template_parts = get_block_templates( array(), 'wp_template_part' );
			foreach ( $block_template_parts as $part ) {
				$template_parts[] = array(
					'id'     => $part->id,
					'slug'   => $part->slug,
					'title'  => $part->title,
					'source' => $part->source,
					'area'   => isset( $part->area ) ? $part->area : null,
				);
			}
		}

		return $template_parts;
	}

	/**
	 * Get reusable blocks
	 *
	 * @return array Reusable blocks data.
	 */
	private function get_reusable_blocks() {
		$reusable_query = new \WP_Query(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$blocks = array();
		foreach ( $reusable_query->posts as $block ) {
			$blocks[] = array(
				'id'    => $block->ID,
				'slug'  => $block->post_name,
				'title' => $block->post_title,
			);
		}

		return $blocks;
	}

	/**
	 * Get custom block types (non-core only)
	 *
	 * Names only (name, title, category) - this is the validator's skip list.
	 * Per-type block schemas are a separate on-demand fetch, not part of the snapshot.
	 *
	 * @return array Custom block types.
	 */
	private function get_custom_block_types() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return array();
		}

		$registry      = WP_Block_Type_Registry::get_instance();
		$all_blocks    = $registry->get_all_registered();
		$custom_blocks = array();

		foreach ( $all_blocks as $block_name => $block_type ) {
			if ( strpos( $block_name, 'core/' ) === 0 ) {
				continue;
			}

			$custom_blocks[] = array(
				'name'     => $block_name,
				'title'    => isset( $block_type->title ) ? $block_type->title : '',
				'category' => isset( $block_type->category ) ? $block_type->category : null,
			);
		}

		return $custom_blocks;
	}

	/**
	 * Get pattern categories with counts.
	 *
	 * Tallies the categories referenced by every registered block pattern and
	 * returns a map of category slug => number of patterns in that category.
	 *
	 * @return object Map of category slug to pattern count.
	 */
	private function get_pattern_categories() {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return (object) array();
		}

		$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$counts   = array();

		foreach ( $patterns as $pattern ) {
			if ( empty( $pattern['categories'] ) || ! is_array( $pattern['categories'] ) ) {
				continue;
			}

			foreach ( $pattern['categories'] as $category ) {
				if ( ! isset( $counts[ $category ] ) ) {
					$counts[ $category ] = 0;
				}
				$counts[ $category ]++;
			}
		}

		ksort( $counts );

		// Cast to object so it always JSON-encodes as an object, even when empty.
		return (object) $counts;
	}

	/**
	 * Get navigation menus
	 *
	 * Mirrors Menus::get_menus() so the snapshot's menu list uses the same
	 * ID space and shape as the menu endpoints. On block themes this returns
	 * wp_navigation posts; on classic themes it returns nav_menu terms.
	 *
	 * @return array Menus data.
	 */
	private function get_menus() {
		$menus          = array();
		$menu_locations = get_nav_menu_locations();

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// FSE: Get wp_navigation posts (same as Menus::get_menus).
			$navigations = get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			foreach ( $navigations as $nav ) {
				// Find locations assigned to this wp_navigation post.
				$locations = array();
				foreach ( $menu_locations as $location => $menu_id ) {
					if ( (int) $menu_id === (int) $nav->ID ) {
						$locations[] = $location;
					}
				}

				// Count navigation items in the post content.
				$item_count = $this->count_fse_nav_items( $nav->post_content );

				$menus[] = array(
					'id'         => $nav->ID,
					'name'       => $nav->post_title,
					'slug'       => $nav->post_name,
					'type'       => 'fse',
					'status'     => $nav->post_status,
					'locations'  => $locations,
					'item_count' => $item_count,
				);
			}
		} else {
			// Traditional: Get nav menus (same as Menus::get_menus).
			$nav_menus = wp_get_nav_menus();

			foreach ( $nav_menus as $menu ) {
				// Find locations assigned to this menu.
				$locations = array();
				foreach ( $menu_locations as $location => $menu_id ) {
					if ( (int) $menu_id === (int) $menu->term_id ) {
						$locations[] = $location;
					}
				}

				// Count menu items - wp_get_nav_menu_items returns false on error.
				$items      = wp_get_nav_menu_items( $menu->term_id );
				$item_count = ( is_array( $items ) && ! empty( $items ) ) ? count( $items ) : 0;

				$menus[] = array(
					'id'         => $menu->term_id,
					'name'       => $menu->name,
					'slug'       => $menu->slug,
					'type'       => 'traditional',
					'locations'  => $locations,
					'item_count' => $item_count,
				);
			}
		}

		return $menus;
	}

	/**
	 * Count navigation items in FSE navigation post content
	 *
	 * @param string $content The post content containing navigation blocks.
	 * @return int Number of navigation items.
	 */
	private function count_fse_nav_items( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return 0;
		}

		// parse_blocks can return unexpected data on malformed content.
		$blocks = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $blocks as $block ) {
			if ( is_array( $block ) ) {
				$count += $this->count_nav_blocks_recursive( $block );
			}
		}

		return $count;
	}

	/**
	 * Recursively count navigation blocks
	 *
	 * @param array $block The block to process.
	 * @return int Count of navigation blocks.
	 */
	private function count_nav_blocks_recursive( $block ) {
		$count      = 0;
		$block_name = $block['blockName'] ?? '';

		// Count this block if it's a navigation block type.
		if ( in_array( $block_name, $this->nav_block_types, true ) ) {
			++$count;
		}

		// Recursively count inner blocks.
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				if ( is_array( $inner_block ) ) {
					$count += $this->count_nav_blocks_recursive( $inner_block );
				}
			}
		}

		return $count;
	}

	/**
	 * Get registered post types (lightweight)
	 *
	 * @return array Post types data.
	 */
	private function get_post_types() {
		$post_types = get_post_types(
			array(
				'show_in_rest' => true,
			),
			'objects'
		);

		$result = array();
		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'slug'         => $post_type->name,
				'name'         => $post_type->label,
				'rest_base'    => ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name,
				'hierarchical' => (bool) $post_type->hierarchical,
			);
		}

		return $result;
	}
}
