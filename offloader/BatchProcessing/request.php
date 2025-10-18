<?php

class s3MediaOffloaderBatchProcessingRequest extends WP_Async_Request {

	use s3MediaOffloaderBatchProcessingLogger;

	/**
	 * @var string
	 */
	protected $action = 'offload_media_request';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		$image = $this->get_id( $_POST );
		$this->really_long_running_task($image);
		$this->log( $image );
	}

}