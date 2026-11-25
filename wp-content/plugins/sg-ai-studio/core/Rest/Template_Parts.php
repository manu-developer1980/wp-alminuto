<?php
/**
 * Template Parts API class for managing WordPress template parts via REST API
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use SG_AI_Studio\Activity_Log\Activity_Log_Helper;
use SG_AI_Studio\Helper\Helper;

/**
 * Handles REST API endpoints for template part operations in block themes.
 */
class Template_Parts extends Rest_Controller_Base {
	use Template_Fork;

	/**
	 * REST API base
	 *
	 * @var string
	 */
	private $base = 'template-parts';

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register endpoint for updating a template part.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[^/]+//[^/]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_template_part' ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
					'args'                => array(
						'id'      => array(
							'description' => 'Template part ID in theme//slug format.',
							'type'        => 'string',
							'required'    => true,
						),
						'content' => array(
							'description' => 'The new content for the template part.',
							'type'        => 'string',
							'required'    => true,
						),
					),
					'description'         => 'Updates a template part with the provided content.',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_template_part' ),
					'permission_callback' => array( $this, 'delete_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => 'Template part ID in theme//slug format.',
							'type'        => 'string',
							'required'    => true,
						),
					),
					'description'         => 'Reverts a customized template part to its theme version by deleting the database post.',
				),
			)
		);

		// Register endpoint for listing template part revisions.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[^/]+//[^/]+)/revisions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_revisions' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => 'Template part ID in theme//slug format.',
							'type'        => 'string',
							'required'    => true,
						),
					),
					'description'         => 'Lists the revisions of a template part.',
				),
			)
		);

		// Register endpoint for reading a single revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[^/]+//[^/]+)/revisions/(?P<revision_id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_revision' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
					'args'                => $this->get_revision_route_args(),
					'description'         => 'Retrieves the content of a single template part revision.',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_revision' ),
					'permission_callback' => array( $this, 'delete_permissions_check' ),
					'args'                => $this->get_revision_route_args(),
					'description'         => 'Permanently deletes a single template part revision.',
				),
			)
		);

		// Register endpoint for restoring a revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[^/]+//[^/]+)/revisions/(?P<revision_id>[\d]+)/restore',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'restore_revision' ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
					'args'                => $this->get_revision_route_args(),
					'description'         => 'Restores a template part revision. Non-destructive: a new revision is created.',
				),
			)
		);
	}

	/**
	 * Route args shared by the single-revision endpoints.
	 *
	 * @return array
	 */
	protected function get_revision_route_args() {
		return array(
			'id'          => array(
				'description' => 'Template part ID in theme//slug format.',
				'type'        => 'string',
				'required'    => true,
			),
			'revision_id' => array(
				'description' => 'Unique identifier for the revision.',
				'type'        => 'integer',
				'required'    => true,
			),
		);
	}

	/**
	 * Update a template part
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success, or error response on failure.
	 */
	public function update_template_part( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Powermode check.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$template_id = $request['id'];
		$content     = $request['content'];

		// Fetch current template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// ETag validation using If-Match header.
		$if_match_header = $request->get_header( 'If-Match' );

		if ( empty( $if_match_header ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'If-Match header is required for template part updates.', 'sg-ai-studio' ),
				),
				428
			);
		}

		// Generate current ETag to compare.
		$entity_handler = new Entity();
		$modified       = $this->get_template_modified_date( $template );
		$current_etag   = $entity_handler->generate_etag( $template->content, $modified );

		if ( $if_match_header !== $current_etag ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part has been modified. Please fetch the latest version.', 'sg-ai-studio' ),
				),
				412
			);
		}

		// Validate content.
		$content = wp_kses_post( $content );

		// Find existing post or create new one.
		$template_post = get_page_by_path( $template->slug, OBJECT, 'wp_template_part' );

		if ( ! $template_post ) {
			// First-time fork: wp_insert_post() creates no revision and there is no
			// prior post state to snapshot. History begins from the next edit.
			$result      = $this->create_template_fork( $template, 'wp_template_part', $template->slug, $content );
			$revision_id = 0;
		} else {
			// Snapshot the pre-edit content before updating so the change has a
			// deterministic restore point, mirroring the posts/pages edit flow.
			$revision_id = Helper::save_pre_edit_revision( $template_post->ID );

			// Update existing template part post.
			$result = wp_update_post(
				array(
					'ID'           => $template_post->ID,
					'post_content' => $content,
				),
				true
			);
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		// Clear caches.
		Helper::purge_caches();

		// Get the updated template.
		$updated_template = get_block_template( $template_id, 'wp_template_part' );
		$new_modified     = $this->get_template_modified_date( $updated_template );
		$new_etag         = $entity_handler->generate_etag( $updated_template->content, $new_modified );

		// Log the activity.
		/* translators: %s is the template part title. */
		Activity_Log_Helper::add_log_entry( 'Template Parts', sprintf( __( 'Template Part Updated: %s', 'sg-ai-studio' ), $template->title ) );

		// Return response.
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'                => $updated_template->id,
					'slug'              => $updated_template->slug,
					'title'             => $updated_template->title,
					'source'            => $updated_template->source,
					'modified'          => $new_modified,
					'etag'              => $new_etag,
					'revision_id'       => $revision_id ? $revision_id : null,
					'revisions_enabled' => $this->are_revisions_enabled(),
				),
			),
			200
		);
	}

	/**
	 * Delete a template part (revert to theme version)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success, or error response on failure.
	 */
	public function delete_template_part( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Powermode check.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$template_id = $request['id'];

		// Fetch current template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Verify source is 'custom'.
		if ( 'custom' !== $template->source ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part is already using the theme version.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Find the post to delete.
		$template_post = get_page_by_path( $template->slug, OBJECT, 'wp_template_part' );

		if ( ! $template_post ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part database entry not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Delete the post (hard delete to revert).
		$deleted = wp_delete_post( $template_post->ID, true );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'The template part could not be deleted.', 'sg-ai-studio' ),
				),
				500
			);
		}

		// Clear caches.
		Helper::purge_caches();

		// Log the activity.
		/* translators: %s is the template part title. */
		Activity_Log_Helper::add_log_entry( 'Template Parts', sprintf( __( 'Template Part Reverted: %s', 'sg-ai-studio' ), $template->title ) );

		// Return response.
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'     => $template_id,
					'source' => 'theme',
				),
			),
			200
		);
	}

	/**
	 * List the revisions of a template part.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revisions( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$template_id = $request['id'];

		// Fetch template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Get the post ID.
		$post_id = $this->get_template_post_id( $template );

		// If no post ID (theme-only template), return empty revisions.
		if ( ! $post_id ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'revisions_enabled' => $this->are_revisions_enabled(),
						'revisions'         => array(),
					),
				),
				200
			);
		}

		$revisions = wp_get_post_revisions( $post_id );
		$data      = array();

		foreach ( $revisions as $revision ) {
			// Skip autosaves; only true restore points are listed.
			if ( false !== strpos( $revision->post_name, $post_id . '-autosave' ) ) {
				continue;
			}

			$data[] = array(
				'id'          => $revision->ID,
				'author'      => (int) $revision->post_author,
				'author_name' => get_the_author_meta( 'display_name', $revision->post_author ),
				'date'        => mysql_to_rfc3339( $revision->post_date ),
				'date_gmt'    => mysql_to_rfc3339( $revision->post_date_gmt ),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'revisions_enabled' => $this->are_revisions_enabled(),
					'revisions'         => $data,
				),
			),
			200
		);
	}

	/**
	 * Retrieve a single revision's content.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revision( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$template_id = $request['id'];
		$revision_id = (int) $request['revision_id'];

		// Fetch template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Get the post ID.
		$post_id = $this->get_template_post_id( $template );

		if ( ! $post_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No revisions available for theme-only template parts.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Validate revision belongs to this template part.
		$revision = $this->validate_revision_belongs_to_post( $revision_id, $post_id );

		if ( null === $revision ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid revision ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'      => $revision->ID,
					'parent'  => (int) $revision->post_parent,
					'title'   => $revision->post_title,
					'content' => $revision->post_content,
					'author'  => (int) $revision->post_author,
					'date'    => mysql_to_rfc3339( $revision->post_date ),
				),
			),
			200
		);
	}

	/**
	 * Restore a revision. Non-destructive: WordPress creates a new revision of the
	 * pre-restore state when revisions are enabled.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function restore_revision( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$template_id = $request['id'];
		$revision_id = (int) $request['revision_id'];

		// Fetch template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Get the post ID.
		$post_id = $this->get_template_post_id( $template );

		if ( ! $post_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No revisions available for theme-only template parts.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Validate revision belongs to this template part.
		if ( null === $this->validate_revision_belongs_to_post( $revision_id, $post_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid revision ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$restored = wp_restore_post_revision( $revision_id );

		if ( empty( $restored ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'The revision could not be restored.', 'sg-ai-studio' ),
				),
				500
			);
		}

		// The newest revision after restore is the snapshot of the pre-restore
		// state. It only exists when revisions are enabled on this site.
		$revisions_enabled = $this->are_revisions_enabled();
		$new_revision_id   = null;

		if ( $revisions_enabled ) {
			$new_revisions   = wp_get_post_revisions(
				$post_id,
				array(
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			$new_revision_id = ! empty( $new_revisions ) ? (int) reset( $new_revisions ) : null;
		}

		// Log the activity.
		/* translators: %1$s is the template part title, %2$d is the revision ID. */
		Activity_Log_Helper::add_log_entry( 'Template Parts', sprintf( __( 'Template Part Revision Restored: %1$s (Revision: %2$d)', 'sg-ai-studio' ), $template->title, $revision_id ) );

		Helper::purge_caches();

		return new WP_REST_Response(
			array(
				'success'           => true,
				'id'                => $template_id,
				'restored_from'     => $revision_id,
				'new_revision_id'   => $new_revision_id,
				'revisions_enabled' => $revisions_enabled,
			),
			200
		);
	}

	/**
	 * Permanently delete a single revision.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function delete_revision( $request ) {
		// Block theme gate.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template parts are only available in block themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Powermode check.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$template_id = $request['id'];
		$revision_id = (int) $request['revision_id'];

		// Fetch template.
		$template = get_block_template( $template_id, 'wp_template_part' );

		if ( ! $template ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template part not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Get the post ID.
		$post_id = $this->get_template_post_id( $template );

		if ( ! $post_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No revisions available for theme-only template parts.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Validate revision belongs to this template part.
		if ( null === $this->validate_revision_belongs_to_post( $revision_id, $post_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid revision ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$deleted = wp_delete_post_revision( $revision_id );

		if ( ! $deleted || is_wp_error( $deleted ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'The revision could not be deleted.', 'sg-ai-studio' ),
				),
				500
			);
		}

		// Log the activity.
		/* translators: %1$s is the template part title, %2$d is the revision ID. */
		Activity_Log_Helper::add_log_entry( 'Template Parts', sprintf( __( 'Template Part Revision Deleted: %1$s (Revision: %2$d)', 'sg-ai-studio' ), $template->title, $revision_id ) );

		Helper::purge_caches();

		return new WP_REST_Response(
			array(
				'success'             => true,
				'id'                  => $template_id,
				'deleted_revision_id' => $revision_id,
			),
			200
		);
	}

	/**
	 * Get the post ID for a template part
	 *
	 * @param object $template Template object.
	 * @return int|null Post ID or null for theme-only templates.
	 */
	private function get_template_post_id( $template ) {
		if ( 'custom' !== $template->source ) {
			return null;
		}

		if ( isset( $template->wp_id ) && $template->wp_id > 0 ) {
			return (int) $template->wp_id;
		}

		return null;
	}

	/**
	 * Get modified date for template
	 *
	 * @param object $template Template object.
	 * @return string|null Modified date in RFC3339 format or null.
	 */
	private function get_template_modified_date( $template ) {
		// Check if template is customized (stored in database).
		if ( 'custom' === $template->source ) {
			$template_post = get_page_by_path( $template->slug, OBJECT, 'wp_template_part' );
			if ( $template_post ) {
				return mysql_to_rfc3339( $template_post->post_modified );
			}
		}

		// Fallback: no modified date for theme-based templates.
		return null;
	}

	/**
	 * Validate that a revision exists and belongs to the given parent post.
	 *
	 * @param int $revision_id Revision ID.
	 * @param int $post_id     Parent post ID.
	 * @return \WP_Post|null The revision, or null when invalid / mismatched.
	 */
	protected function validate_revision_belongs_to_post( $revision_id, $post_id ) {
		$revision = get_post( $revision_id );

		if ( is_wp_error( $revision ) || ! $revision ) {
			return null;
		}

		if ( 'revision' !== $revision->post_type || (int) $revision->post_parent !== (int) $post_id ) {
			return null;
		}

		return $revision;
	}

	/**
	 * Check if revisions are enabled
	 *
	 * @return bool Whether revisions are enabled.
	 */
	private function are_revisions_enabled() {
		// Check WordPress constant.
		if ( defined( 'WP_POST_REVISIONS' ) && false === WP_POST_REVISIONS ) {
			return false;
		}

		return true;
	}
}
