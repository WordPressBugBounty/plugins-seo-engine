<?php

/**
 * The "SEO Engine" panel in the block editor: SEO title and description, with the
 * defaults as placeholders (same rule as Quick Edit), plus the index status and canonical.
 * Values are saved with the post through registered meta, no custom endpoint.
 */
class Meow_MWSEO_Modules_EditorPanel
{
	private $core = null;

	public function __construct( $core )
	{
		$this->core = $core;
		if ( !$this->core->get_option( 'content_seo', false ) ) {
			return;
		}
		add_action( 'init', array( $this, 'register_fields' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	private function get_post_types()
	{
		$post_types = (array) $this->core->get_option( 'select_post_types', array( 'post', 'page' ) );
		return array_values( array_filter( $post_types, function( $post_type ) {
			$object = get_post_type_object( $post_type );
			return $object && $object->show_in_rest;
		} ) );
	}

	public function register_fields()
	{
		$auth = function( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};
		$sanitize = function( $value ) {
			return trim( wp_strip_all_tags( (string) $value ) );
		};
		foreach ( $this->get_post_types() as $post_type ) {
			// TODO: switch to _mwseo_title and _mwseo_excerpt along with the core migration.
			foreach ( array( $this->core->meta_key_seo_title, $this->core->meta_key_seo_excerpt ) as $meta_key ) {
				register_post_meta( $post_type, $meta_key, array(
					'type' => 'string',
					'single' => true,
					'default' => '',
					'show_in_rest' => true,
					'sanitize_callback' => $sanitize,
					'auth_callback' => $auth,
				) );
			}
			register_rest_field( $post_type, 'mwseo_panel', array(
				'get_callback' => array( $this, 'get_panel_data' ),
				'schema' => array( 'type' => 'object', 'context' => array( 'edit' ), 'readonly' => true ),
			) );
		}
	}

	public function get_panel_data( $data, $field_name, $request )
	{
		$post = get_post( $data['id'] ?? 0 );
		if ( !$post || $request->get_param( 'context' ) !== 'edit' || !current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}
		return array(
			'default_title' => $this->core->build_title( $post, '', true ),
			'default_excerpt' => trim( wp_strip_all_tags( $this->core->build_excerpt( $post, '', true ) ) ),
			'no_index' => $this->core->is_post_noindexed( $post->ID ),
			'canonical' => (string) get_post_meta( $post->ID, '_mwseo_canonical', true ),
		);
	}

	public function enqueue_editor_assets()
	{
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( !$screen || !in_array( $screen->post_type, $this->get_post_types(), true ) ) {
			return;
		}
		// Another SEO plugin handles the title and meta tags: don't show a second, competing box.
		if ( !$this->core->should_render_frontend_meta() ) {
			return;
		}
		$file = MWSEO_PATH . '/app/editor-panel.js';
		wp_enqueue_script( 'mwseo-editor-panel', MWSEO_URL . 'app/editor-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			file_exists( $file ) ? filemtime( $file ) : MWSEO_VERSION, true );

		$more_url = '';
		$surgical = array( 'post' => 'edit.php?page=mwseo_surgical_post', 'page' => 'edit.php?post_type=page&page=mwseo_surgical_page' );
		if ( isset( $surgical[ $screen->post_type ] ) && current_user_can( 'manage_options' ) ) {
			$more_url = admin_url( $surgical[ $screen->post_type ] );
		}
		wp_add_inline_script( 'mwseo-editor-panel', 'window.mwseoEditorPanel = ' . wp_json_encode( array(
			'titleMax' => (int) $this->core->get_option( 'title_length_max', 75 ),
			'excerptMax' => (int) $this->core->get_option( 'excerpt_length_max', 160 ),
			'moreUrl' => $more_url,
		) ) . ';', 'before' );
	}
}
