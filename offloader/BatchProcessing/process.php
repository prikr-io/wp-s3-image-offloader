<?php

class WP_s3Media_Process extends WP_Background_Process {

	use WP_s3Media_Logger;

	/**
	 * @var string
	 */
	protected $action = 'offload_media_process';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$this->really_long_running_task($item);
		$this->log( $item );

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		error_log('Processs complete');

		// Show notice to user or perform some other arbitrary task...
		if ( is_admin() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="updated"><p>WP Background Processing task complete!</p></div>';
			} );
		}
	}

}