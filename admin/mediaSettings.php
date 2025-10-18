<?php

class s3MediaSettings {

    public function __construct() {
        add_filter('attachment_fields_to_edit', array($this, 'add_custom_text_field_to_attachment_fields_to_edit'), null, 2);
        add_filter('attachment_fields_to_save', array($this, 'save_custom_text_attachment_field'), null, 2);
        
    }

    public function add_custom_text_field_to_attachment_fields_to_edit($form_fields, $post) {
        $s3_url = get_post_meta($post->ID, 's3_url', true);
		$form_fields['media_offloader'] = array(
			'label'         => 'Offload image',
			'input'         => 'html',
			'html'          => '<a href="' . esc_url( $this->create_page_url( $post->ID ) ) . '" class="button-secondary button-large" title="' . esc_attr( __( 'Offload image', 'wps3-image-offloader' ) ) . '">' . _x( 'Offload image', 'action for a single image', 'wps3-image-offloader' ) . '</a>',
            'helps'         => '',
			'show_in_modal' => true,
			'show_in_edit'  => false,
		);
        return $form_fields;
    }
	/**
	 * Helper function to create a URL to regenerate a single image.
	 */
	public function create_page_url( $id ) {
		return add_query_arg( ['page' => 'image-offloader', 'offload' => $id], admin_url( 'options-general.php' ) );
	}
}

// Instantiate the class
new s3MediaSettings();
