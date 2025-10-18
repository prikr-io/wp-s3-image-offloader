<?php

trait s3MediaOffloaderBatchProcessingLogger
{

	/**
	 * Really long running process
	 *
	 * @return int
	 */
	public function really_long_running_task($image)
	{
		$this->mediaOffloader = new s3MediaOffloaderInit();
		$this->mediaOffloader = $this->mediaOffloader->init();
		$this->mediaOffloader->offloadMedia($image);
		return sleep(1);
	}

	/**
	 * Log
	 *
	 * @param string $message
	 */
	public function log($message)
	{
		error_log('processed image: ' . print_r($message, true));
	}

	/**
	 * Get lorem
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected function get_id($id)
	{
		return $id;
	}
}
