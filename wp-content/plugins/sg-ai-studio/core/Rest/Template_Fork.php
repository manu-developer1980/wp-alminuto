<?php
/**
 * Shared logic for forking theme-provided templates into customized posts.
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_Error;

/**
 * Creates the DB-backed copy of a theme template/template_part that the first
 * edit requires, with the taxonomy terms WordPress needs to recognize the fork.
 *
 * Shared by every REST controller that persists template edits (the entity PATCH
 * endpoint and the template-parts endpoint) so first-time customization behaves
 * identically regardless of which endpoint made the change.
 */
trait Template_Fork {
	/**
	 * Fork a theme-provided template into a customized (DB-backed) post
	 *
	 * The first edit of a template/template_part that lives only as a theme file has
	 * to create a wp_template(_part) post. WordPress associates that post with the
	 * active theme through the wp_theme term (plus wp_template_part_area for parts);
	 * without those terms get_block_template() ignores the fork and the edit silently
	 * has no effect.
	 *
	 * Terms are assigned with wp_set_object_terms() rather than wp_insert_post()'s
	 * tax_input because tax_input only applies when current_user_can(assign_terms) is
	 * true. These endpoints authorize via JWT without a logged-in user, so tax_input
	 * would be dropped and the fork would be orphaned from the theme.
	 *
	 * @param \WP_Block_Template|null $template      Resolved source template (theme source).
	 * @param string                  $template_type Post type: wp_template or wp_template_part.
	 * @param string                  $slug          Bare template slug (post_name).
	 * @param string                  $content       Serialized block markup.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function create_template_fork( $template, $template_type, $slug, $content ) {
		$postarr = array(
			'post_type'    => $template_type,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		);

		if ( $template ) {
			$postarr['post_title']   = $template->title;
			$postarr['post_excerpt'] = $template->description;
		}

		$new_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Link the fork to the active theme so get_block_template() returns it.
		$theme = ( $template && ! empty( $template->theme ) ) ? $template->theme : get_stylesheet();
		wp_set_object_terms( $new_id, $theme, 'wp_theme' );

		// Template parts must also carry their area (header/footer/uncategorized).
		// 'uncategorized' is the value of WP_TEMPLATE_PART_AREA_UNCATEGORIZED, a stable
		// WordPress taxonomy term slug; used as a literal to avoid a hard dependency on
		// the constant being available at analysis time.
		if ( 'wp_template_part' === $template_type ) {
			$area = ( $template && ! empty( $template->area ) ) ? $template->area : 'uncategorized';
			wp_set_object_terms( $new_id, $area, 'wp_template_part_area' );
		}

		// Record the fork's origin, mirroring the core site editor.
		if ( $template && ! empty( $template->source ) ) {
			update_post_meta( $new_id, 'origin', $template->source );
		}

		return true;
	}
}
