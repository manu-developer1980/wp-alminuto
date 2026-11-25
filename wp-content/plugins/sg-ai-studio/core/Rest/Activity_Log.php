<?php
/**
 * Activity Log API class for managing activity logs via REST API
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use SG_AI_Studio\Activity_Log\Activity_Log as Activity_Log_Core;

/**
 * Handles REST API endpoints for activity log operations.
 */
class Activity_Log {
	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	private $namespace = 'sg-ai-studio';

	/**
	 * REST API base
	 *
	 * @var string
	 */
	private $base = 'activity-log';

	/**
	 * Entries per page
	 *
	 * @var int
	 */
	private $number_of_entries = 30;

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register endpoint for getting activity log entries.
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_activity_log' ),
				'permission_callback' => array( $this, 'get_activity_log_permissions_check' ),
				'args'                => $this->get_activity_log_args(),
				'description'         => 'Retrieves activity log entries with optional filters.',
			)
		);
	}

	/**
	 * Check if a user has permission to read activity logs
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has access to read items, WP_Error object otherwise.
	 */
	public function get_activity_log_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access activity logs.', 'sg-ai-studio' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get arguments for activity log endpoint
	 *
	 * @return array
	 */
	protected function get_activity_log_args() {
		return array(
			'page'     => array(
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'minimum'           => 1,
				'required'          => false,
			),
			'per_page' => array(
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 30,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'required'          => false,
			),
			'activity' => array(
				'description' => 'Filter by activity type.',
				'type'        => 'string',
				'required'    => false,
			),
			'user'     => array(
				'description' => 'Filter by user ID.',
				'type'        => 'integer',
				'required'    => false,
			),
			'from'     => array(
				'description' => 'Filter entries from this timestamp.',
				'type'        => 'integer',
				'required'    => false,
			),
			'to'       => array(
				'description' => 'Filter entries until this timestamp.',
				'type'        => 'integer',
				'required'    => false,
			),
		);
	}

	/**
	 * Get activity log entries
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activity_log( $request ) {
		global $wpdb;

		$page = 1;
		if ( ! empty( $request->get_param( 'page' ) ) ) {
			$page = $request->get_param( 'page' );
		}

		$per_page = $this->number_of_entries;
		if ( ! empty( $request->get_param( 'per_page' ) ) ) {
			$per_page = $request->get_param( 'per_page' );
		}

		if ( ! Activity_Log_Core::table_exists( $wpdb->prefix . 'sg_ai_log_events' ) ) {
			return new WP_REST_Response(
				array(
					'entries'     => [],
					'total'       => 0,
					'page'        => 1,
					'per_page'    => 30,
					'total_pages' => 0,
				),
				200
			);
		}

		// Build the query.
		$query = $this->build_query( $request );

		// Get the entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$entries = $wpdb->get_results( $query, ARRAY_A );

		// Get total count for pagination.
		$total_query = $this->build_count_query( $request );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = $wpdb->get_var( $total_query );

		$data = array();
		foreach ( $entries as $entry ) {
			$data[] = $this->format_log_entry( $entry );
		}

		$total_pages = ceil( $total / $per_page );

		return new WP_REST_Response(
			array(
				'entries'     => $data,
				'total'       => intval( $total ),
				'page'        => intval( $page ),
				'per_page'    => intval( $per_page ),
				'total_pages' => intval( $total_pages ),
			),
			200
		);
	}

	/**
	 * Build the SQL query for activity log
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return string The SQL query.
	 */
	private function build_query( $request ) {
		global $wpdb;

		$page = $request->get_param( 'page' );
		if ( ! $page ) {
			$page = 1;
		}
		$per_page = $request->get_param( 'per_page' );
		if ( ! $per_page ) {
			$per_page = $this->number_of_entries;
		}

		$table_name = $wpdb->prefix . 'sg_ai_log_events';

		$select = "SELECT * FROM {$table_name}";
		$where  = $this->build_where( $request );
		$order  = ' ORDER BY ts DESC';
		$limit  = ' LIMIT ' . intval( $per_page );
		$offset = ' OFFSET ' . intval( ( $page - 1 ) * $per_page );

		return $select . $where . $order . $limit . $offset;
	}

	/**
	 * Build the count query for pagination
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return string The SQL query.
	 */
	private function build_count_query( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sg_ai_log_events';

		$select = "SELECT COUNT(*) FROM {$table_name}";

		return $select . $this->build_where( $request );
	}

	/**
	 * Build the WHERE clause shared by the entries and count queries
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return string The WHERE clause.
	 */
	private function build_where( $request ) {
		global $wpdb;

		$activity = $request->get_param( 'activity' );
		$user     = $request->get_param( 'user' );
		$from     = $request->get_param( 'from' );
		$to       = $request->get_param( 'to' );

		$where = ' WHERE 1=1';

		// Add filters.
		if ( ! empty( $activity ) ) {
			$where .= $wpdb->prepare( ' AND activity = %s', $activity );
		}

		if ( ! empty( $user ) ) {
			$where .= $wpdb->prepare( ' AND visitor_id = %d', intval( $user ) );
		}

		if ( ! empty( $from ) ) {
			$where .= $wpdb->prepare( ' AND ts >= %d', intval( $from ) );
		}

		if ( ! empty( $to ) ) {
			$where .= $wpdb->prepare( ' AND ts <= %d', intval( $to ) );
		}

		return $where;
	}

	/**
	 * Format a log entry for the response
	 *
	 * @param array $entry Raw log entry from database.
	 * @return array Formatted log entry.
	 */
	private function format_log_entry( $entry ) {
		return array(
			'id'       => intval( $entry['id'] ),
			'date'     => $entry['ts'],
			'activity' => $entry['activity'],
			'message'  => \htmlspecialchars_decode( $entry['description'] ),
			'user'     => $this->format_log_user( $entry ),
		);
	}

	/**
	 * Describe the user who made a log entry
	 *
	 * Uses the live user when they still exist, the login stored with the
	 * entry when they were deleted, and "Unknown" for entries without a user.
	 *
	 * @param array $entry Raw log entry from database.
	 * @return array The user's id, name, login and role.
	 */
	private function format_log_user( $entry ) {
		$visitor_id = intval( $entry['visitor_id'] );
		$user       = $visitor_id ? get_userdata( $visitor_id ) : false;

		if ( $user ) {
			$role      = '';
			$user_role = reset( $user->roles );

			if ( $user_role && isset( wp_roles()->role_names[ $user_role ] ) ) {
				$role = translate_user_role( wp_roles()->role_names[ $user_role ] );
			}

			return array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'login' => $user->user_login,
				'role'  => $role,
			);
		}

		// Entries logged before 1.3.2, or before the column was added, have no login.
		$login = isset( $entry['visitor_login'] ) ? $entry['visitor_login'] : '';

		return array(
			'id'    => $visitor_id,
			'name'  => '' !== $login ? $login : __( 'Unknown', 'sg-ai-studio' ),
			'login' => $login,
			'role'  => '',
		);
	}
}
