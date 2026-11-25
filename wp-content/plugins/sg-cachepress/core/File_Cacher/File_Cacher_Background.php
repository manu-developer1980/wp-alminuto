<?php
namespace SiteGround_Optimizer\File_Cacher;

use SiteGround_Optimizer\Options\Options;

/**
 * Extends the background process class for the Filebased cache preload background process.
 */
class File_Cacher_Background extends \WP_Background_Process {
	/**
	 * Action
	 *
	 * (default value: 'background_process')
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'file_cacher_preload';

	/**
	 * Save queue and reset the data, so the next save doesn't
	 * duplicate the already saved items (not done in v1.0.2 of the library).
	 *
	 * @return $this
	 */
	public function save() {
		parent::save();

		$this->data = array();

		return $this;
	}

	/**
	 * Handle the queue. Clear the queue instead, if the preheat is no longer enabled.
	 *
	 * @since 7.8.5
	 */
	protected function handle() {
		if (
			! Options::is_enabled( 'siteground_optimizer_file_caching' ) ||
			! Options::is_enabled( 'siteground_optimizer_preheat_cache' )
		) {
			$this->clear_queue();
			wp_die();
		}

		parent::handle();
	}

	/**
	 * Remove all batches from the queue, the process lock and the healthcheck cron.
	 *
	 * @since 7.8.5
	 */
	public function clear_queue() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		// Get the batch keys, so they can be removed from the object cache.
		$batch_keys = $wpdb->get_col( $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE {$column} LIKE %s", $key ) ); // phpcs:ignore

		if ( ! empty( $batch_keys ) ) {
			// Delete all batches at once, as the queue can be huge.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // phpcs:ignore

			foreach ( $batch_keys as $batch_key ) {
				is_multisite() ? wp_cache_delete( get_current_network_id() . ':' . $batch_key, 'site-options' ) : wp_cache_delete( $batch_key, 'options' );
			}

			if ( ! is_multisite() ) {
				wp_cache_delete( 'alloptions', 'options' );
			}
		}

		$this->data = array();

		$this->unlock_process();

		wp_clear_scheduled_hook( $this->cron_hook_identifier );
	}

	/**
	 * Task
	 *
	 * @param array $item Array containing the class and the
	 *                    method to call in background process.
	 *
	 * @return mixed      False on process success.
	 *                    The current item on failure, which will restart the process.
	 */
	protected function task( $item ) {
		$file_cacher = new File_Cacher();
		$file_cacher->hit_url_cache( $item );

		sleep( 1 );

		// Remove the process from queue.
		return false;
	}
}
