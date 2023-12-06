<?php

class s3MediaOffloaderBatchProcessing
{
	protected $process_all;
	private $mediaOffloader;
	private $items;
	private $itemCount;

	/**
	 * s3MediaOffloaderBatchProcessing constructor.
	 */
	public function __construct()
	{
		$offloaderClass = new s3MediaOffloaderInit();
		$this->mediaOffloader = $offloaderClass->init();
		if (!$this->mediaOffloader) return;
		add_action('plugins_loaded', array($this, 'init'));
		add_action('admin_init', array($this, 'admin_actions'), 10);
		add_action('init', array($this, 'process_handler'));
		add_action('admin_notices', array($this, 'show_admin_notices'));
	}

	/**
	 * Init
	 */
	public function init()
	{
		require_once WPS3_PATH . 'offloader/BatchProcessing/logger.php';
		require_once WPS3_PATH . 'offloader/BatchProcessing/request.php';
		require_once WPS3_PATH . 'offloader/BatchProcessing/process.php';
		$this->process_all = new s3MediaOffloaderBatchProcessingProcess();
	}

	/**
	 * Admin bar
	 */
	public function admin_actions()
	{
		error_log('wps3 MediaOffloaderBatchProcessing admin_actions');
		// Check if the current page is the desired admin page
		if (!isset($_GET['page']) || $_GET['page'] !== 'image-offloader') {
			return;
		}
		if (!current_user_can('manage_options')) {
			return;
		}
		// TODO: as long as the below error log is triggered twice, all processes are triggered twice. This should be fixed.
		$this->items = $this->get_items();
		$this->itemCount = count($this->items);

		add_settings_field(
			'wps3_batch_processing_offload',
			'Batch offload images',
			function () {
				$count = $this->itemCount;
				$url = wp_nonce_url(admin_url('options-general.php?page=image-offloader&process=offload-all'), 'process');
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


		add_settings_field(
			'wps3_batch_processing_remove',
			'Remove s3_url from all images',
			function () {
				$url = wp_nonce_url(admin_url('options-general.php?page=image-offloader&process=remove-all'), 'process');
				echo "<p>";
				echo "<a class='button' style='margin-right: 8px;' href='$url' onclick='return confirmAlert();'>Remove images</a>";
				echo "Warning: This will remove all image links to your CDN. Images will still exist on your CDN, but WP will no longer serve those.";
				echo "</p>";
				echo '<script>
					function confirmAlert() {
					  // Show a confirmation prompt
					  var userResponse = confirm("Weet je zeker dat je alle afbeeldingen wilt verwijderen?");
					  
					  // If the user clicks OK, return true to proceed to the link
					  if (userResponse) {
						return true;
					  } else {
						// If the user clicks Cancel, return false to cancel the link action
						return false;
					  }
					}
				  </script>';
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
		if (!isset($_GET['process']) || !isset($_GET['_wpnonce'])) {
			return;
		}

		if (!wp_verify_nonce($_GET['_wpnonce'], 'process')) {
			return;
		}


		if ('offload-all' === $_GET['process']) {
			$this->handle_all();
		}

		if ('remove-all' === $_GET['process']) {
			$this->remove_all();
		}
	}

	/**
	 * Handle all
	 */
	protected function handle_all()
	{
		error_log('wps3 MediaOffloaderBatchProcessing handle_all');

		$items = $this->get_items();
		$this->itemCount = count($items);

		add_action('admin_notices', function () {
			$count = $this->itemCount;
			echo "<div class='updated'><p>Started offloading $count items!</p></div>";
		});

		foreach ($items as $item) {
			$this->process_all->push_to_queue($item);
		}

		$this->process_all->save()->dispatch();
	}

	/**
	 * Remove all
	 */
	protected function remove_all()
	{
		// Do a query to get all images
		global $wpdb;
		$s3MetaQuery = $this->mediaOffloader->queryToDeleteAllS3Meta();
		$temp_table = $s3MetaQuery->temp_table;
		$query = $s3MetaQuery->query;

		$wpdb->query($query);
		$wpdb->query("DROP TEMPORARY TABLE IF EXISTS $temp_table;");


		add_action('admin_notices', function () {
			echo "<div class='updated'><p>Deleted all images!</p></div>";
		});
	}


	/**
	 * Get names
	 *
	 * @return array
	 */
	protected function get_items()
	{
		$images = $this->mediaOffloader->listMissingImagesBatch(99999, 0);
		foreach ($images as $key => $image) {
			$images[$key] = $image->ID;
		}
		return $images;
	}
}

new s3MediaOffloaderBatchProcessing();
