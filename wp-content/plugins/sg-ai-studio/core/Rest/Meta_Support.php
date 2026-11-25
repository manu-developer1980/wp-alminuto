<?php
/**
 * Shared custom field (post meta) helpers for REST controllers.
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

/**
 * Read and discovery helpers for the custom fields of posts, pages and custom
 * post type items.
 */
trait Meta_Support {
	/**
	 * Upper bound on the number of in-use keys sampled when discovering the
	 * custom fields of a post type.
	 *
	 * @var int
	 */
	protected $meta_fields_limit = 200;

	/**
	 * Get the exposed custom fields of a post as a key => value map.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array|null Map of meta key => value, or null when the user cannot edit the post.
	 */
	protected function get_post_meta_map( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}

		$registered = $this->get_registered_post_meta( $post->post_type );
		$map        = array();

		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( ! $this->is_exposed_meta_key( $key, $registered ) ) {
				continue;
			}

			$values = array_map( 'maybe_unserialize', $values );

			$single = isset( $registered[ $key ] ) ? ! empty( $registered[ $key ]['single'] ) : 1 === count( $values );

			$map[ $key ] = $single ? $values[0] : $values;
		}

		return $map;
	}

	/**
	 * Discover the custom fields available on a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array List of field descriptors.
	 */
	protected function get_post_type_meta_fields( $post_type ) {
		$registered = $this->get_registered_post_meta( $post_type );
		$fields     = array();

		foreach ( $registered as $key => $args ) {
			if ( ! $this->is_exposed_meta_key( $key, $registered ) ) {
				continue;
			}

			$fields[ $key ] = array(
				'key'         => $key,
				'label'       => null,
				'type'        => isset( $args['type'] ) ? $args['type'] : 'string',
				'description' => isset( $args['description'] ) ? $args['description'] : '',
				'source'      => 'registered',
			);
		}

		foreach ( $this->get_acf_fields( $post_type ) as $field ) {
			if ( empty( $field['name'] ) || ! $this->is_exposed_meta_key( $field['name'], $registered ) ) {
				continue;
			}

			$fields[ $field['name'] ] = array(
				'key'         => $field['name'],
				'label'       => isset( $field['label'] ) ? $field['label'] : null,
				'type'        => isset( $field['type'] ) ? $field['type'] : 'text',
				'description' => isset( $field['instructions'] ) ? $field['instructions'] : '',
				'source'      => 'acf',
			);
		}

		foreach ( $this->get_in_use_meta_keys( $post_type ) as $key ) {
			if ( isset( $fields[ $key ] ) || ! $this->is_exposed_meta_key( $key, $registered ) ) {
				continue;
			}

			$fields[ $key ] = array(
				'key'         => $key,
				'label'       => null,
				'type'        => null,
				'description' => '',
				'source'      => 'in_use',
			);
		}

		return array_values( $fields );
	}

	/**
	 * Whether a meta key may be shown to the agent.
	 *
	 * @param string $key        Meta key.
	 * @param array  $registered Registered meta of the post type, keyed by meta key.
	 * @return bool
	 */
	protected function is_exposed_meta_key( $key, $registered ) {
		if ( ! is_protected_meta( $key, 'post' ) ) {
			return true;
		}

		return ! empty( $registered[ $key ]['show_in_rest'] );
	}

	/**
	 * Get the meta registered for a post type, including meta registered for all post types.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Registered meta arguments keyed by meta key.
	 */
	protected function get_registered_post_meta( $post_type ) {
		return array_merge(
			get_registered_meta_keys( 'post' ),
			get_registered_meta_keys( 'post', $post_type )
		);
	}

	/**
	 * Get the top level fields of the ACF field groups assigned to a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array List of ACF field arrays, empty when ACF is not active.
	 */
	protected function get_acf_fields( $post_type ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		$fields = array();

		foreach ( acf_get_field_groups( array( 'post_type' => $post_type ) ) as $group ) {
			$group_fields = acf_get_fields( $group );

			if ( is_array( $group_fields ) ) {
				$fields = array_merge( $fields, $group_fields );
			}
		}

		return $fields;
	}

	/**
	 * Get the distinct meta keys stored on posts of a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return string[] Meta keys.
	 */
	protected function get_in_use_meta_keys( $post_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key NOT LIKE %s
				LIMIT %d",
				$post_type,
				$wpdb->esc_like( '_' ) . '%',
				$this->meta_fields_limit
			)
		);

		return is_array( $keys ) ? $keys : array();
	}
}
