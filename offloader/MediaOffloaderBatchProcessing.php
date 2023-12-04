<?php

class s3Media_Background_Processing
{
	protected $process_all;
	private $mediaOffloader;
	private $imageCount;

	/**
	 * s3Media_Background_Processing constructor.
	 */
	public function __construct()
	{
		$offloaderClass = new s3MediaOffloaderInit();
		$this->mediaOffloader = $offloaderClass->init();
		if (!$this->mediaOffloader) return;
		add_action('plugins_loaded', array($this, 'init'));
		add_action('admin_init', array($this, 'admin_bar'), 100);
		add_action('init', array($this, 'process_handler'));
		add_action('init', array($this, 'show_admin_notices'));
	}

	/**
	 * Init
	 */
	public function init()
	{
		require_once WPS3_PATH . 'offloader/BatchProcessing/logger.php';
		require_once WPS3_PATH . 'offloader/BatchProcessing/request.php';
		require_once WPS3_PATH . 'offloader/BatchProcessing/process.php';
		$this->process_all = new WP_s3Media_Process();
	}

	/**
	 * Admin bar
	 */
	public function admin_bar()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		add_settings_field(
			'wps3_batch_processing',
			'Batch offload images',
			function () {
				$count = $this->imageCount;
				$url = wp_nonce_url(admin_url('options-general.php?page=image-offloader&offload-all'), 'offload-all');
				if ($count > 0) {
					echo "<p><a class='button button-primary' href='$url'>Offload $count images</a></p>";
				} else {
					echo "<p>";
					echo "<a class='button button-primary disabled' style='margin-right: 8px;' disabled href='$url'>Offload $count images</a>";
					echo "All images are offloaded";
					echo "</p>";
				}
			},
			'wps3-image-offloader-admin',
			'wps3_developer_section'
		);
	}

	/**
	 * Show admin notices
	 */
	public function show_admin_notices()
	{
		// var_dump($this->process_all->is_queued());
		// add_action('admin_notices', function () {
		// 	echo '<div class="updated"><p>WP Background Processing task complete!</p></div>';
		// });
	}

	/**
	 * Process handler
	 */
	public function process_handler()
	{
		if (!isset($_GET['offload-all']) || !isset($_GET['_wpnonce'])) {
			return;
		}

		if (!wp_verify_nonce($_GET['_wpnonce'], 'offload-all')) {
			return;
		}

		$this->handle_all();
	}

	/**
	 * Handle all
	 */
	protected function handle_all()
	{
		$images = $this->get_items();
		$this->imageCount = count($images);

		add_action('admin_notices', function () {
			$count = $this->imageCount;
			echo "<div class='updated'><p>Started offloading $count images!</p></div>";
		});

		foreach ($images as $image) {
			$this->process_all->push_to_queue($image);
		}

		$this->process_all->save()->dispatch();
	}

	/**
	 * Get names
	 *
	 * @return array
	 */
	protected function get_items()
	{
		$images = $this->mediaOffloader->listMissingImagesBatch(20, 0);
		foreach ($images as $key => $image) {
			$images[$key] = $image->ID;
		}
		return $images;
	}
}

new s3Media_Background_Processing();
