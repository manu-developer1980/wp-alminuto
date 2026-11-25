<?php
namespace SG_AI_Studio\Install_Service;

use SG_AI_Studio\Activity_Log\Activity_Log;

class Install_1_3_2 extends Install {

	/**
	 * The default install version. Overridden by the installation packages.
	 *
	 * @since 1.3.2
	 *
	 * @access protected
	 *
	 * @var string $version The install version.
	 */
	protected static $version = '1.3.2';

	/**
	 * Run the install procedure.
	 *
	 * Adds the visitor_login column to the activity log table, so each entry
	 * keeps the login of the user who made the change even after that user
	 * is deleted.
	 *
	 * @since 1.3.2
	 */
	public function install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sg_ai_log_events';

		// The table is created with the column when it is first needed.
		if ( ! Activity_Log::table_exists( $table_name ) ) {
			return;
		}

		// Fresh installs already have the column.
		if ( Activity_Log::has_column( 'visitor_login' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . "` ADD COLUMN `visitor_login` varchar(60) NOT NULL DEFAULT ''" );

		Activity_Log::reset_column_cache();
	}

}
