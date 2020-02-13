<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://unionping.com
 * @since      1.0.0
 *
 * @package    Vaulty
 * @subpackage Vaulty/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Vaulty
 * @subpackage Vaulty/admin
 * @author     Jan Thomas <thomas@unionping.com>
 */
final class VaultyAdmin {

	const NAME = "VaultyAdmin";
	const VERSION = "1.0.0";

	/**
	 * gets the called class name
	 *
	 * @return string
	 * @since    1.0.0
	 */
	static function me() {
		return get_called_class();
	}


	/**
	 * Initialize this static class
	 *
	 * @since    1.0.0
	 */
	static function init() {

		Vaulty::add_action( 'admin_enqueue_scripts', [ static::me(), 'enqueue_styles' ] );
		Vaulty::add_action( 'admin_enqueue_scripts', [ static::me(), 'enqueue_scripts' ] );


		Vaulty::add_filter( "bulk_actions-upload", [ static::me(), '_filter_bulk_action' ] );

		Vaulty::add_action( "wp_ajax_vaulty_secure", [ static::me(), 'ajax_secure_attachment' ] );

		Vaulty::add_filter( 'manage_media_columns', [ static::me(), '_filter_media_columns' ] );
		Vaulty::add_filter( 'manage_upload_sortable_columns', [ static::me(), '_filter_media_sortable_columns' ] );
		Vaulty::add_action( 'manage_media_custom_column', [ static::me(), '_action_media_columns' ], 10, 2 );
		Vaulty::add_action( 'pre_get_posts', [ static::me(), '_action_media_query' ] );
		Vaulty::add_action( 'restrict_manage_posts', [ static::me(), '_action_media_filter' ] );


		if( isset( $_REQUEST['attachment_id'] ) && intval( $_REQUEST['attachment_id'] ) && $_REQUEST['fetch'] && $_REQUEST['fetch'] == 3 ) {
			//SUPER uncool way to inject something into the async ajax stuff...
			Vaulty::add_filter( 'wp_get_attachment_image_src', [ static::me(), '_filter_async_upload' ] );
		}

	}

	static function _action_media_filter() {
		$scr = get_current_screen();
		if( $scr->base !== 'upload' ) {
			return;
		}

		$current = filter_input( INPUT_GET, 'vaulty_level', FILTER_SANITIZE_STRING );
		$current = $current ? $current : null;

		echo static::get_selectbox( null, $current, "", "", "vaulty_level", "All protection levels" );
		echo " ";
	}


	/**
	 * action to add the
	 *
	 * @param WP_Query $query
	 */
	static function _action_media_query( $query ) {
		$orderBy = $query->get( 'orderby' );

		if( $query->is_main_query() && $query->query_vars['post_type'] == 'attachment' ) {
			$level = filter_input( INPUT_GET, 'vaulty_level', FILTER_SANITIZE_STRING );
			$level = Vaulty::level_sanitize( $level, true );
			if( $level ) {
				$meta_querys = (array)$query->get( 'meta_query' );

				// Add your criteria

				$meta_query = array(
					'key'     => Vaulty::META_KEY_LEVEL,
					'value'   => $level,
					'compare' => 'like',
				);
				if( $level == Vaulty::LEVEL_UNPROTECTED ) {
					$meta_query = array(
						'relation' => 'OR',
						array(
							'key'     => Vaulty::META_KEY_LEVEL,
							'compare' => 'NOT EXISTS', // see note above
						),
						$meta_query,
					);
				}

				$meta_querys[] = $meta_query;

				// Set the meta query to the complete, altered query
				$query->set( 'meta_query', $meta_querys );
			}


			if( 'vaulty' == $orderBy ) {

				$meta_query = array(
					'relation' => 'OR',
					array(
						'key'     => Vaulty::META_KEY_LEVEL,
						'compare' => 'NOT EXISTS', // see note above
					),
					array(
						'key' => Vaulty::META_KEY_LEVEL,
					),
				);

				$query->set( 'meta_query', $meta_query );
				$query->set( 'orderby', 'meta_value' );
			}
		}
	}

	static function _action_media_columns( $column_name, $id ) {
		if( $column_name == 'vaulty' ) {
			$level = Vaulty::level( get_post_meta( $id, Vaulty::META_KEY_LEVEL, true ) );
			echo $level ? $level : Vaulty::level( Vaulty::LEVEL_UNPROTECTED );
		}
	}

	static function _filter_media_sortable_columns( $columns ) {
		$columns['vaulty'] = 'vaulty';

		return $columns;
	}

	static function _filter_media_columns( $old_columns ) {
		$columns = [];
		foreach( $old_columns as $column => $label ) {
			$columns[ $column ] = $label;
			if( $column == "comments" ) {
				$columns['vaulty'] = "<span title=\"Protection\" class=\"dashicons dashicons-lock\"></span>";
			}
		}

		return $columns;
	}

	static function ajax_secure_attachment() {
		if( !wp_verify_nonce( $_REQUEST['nonce'], "wp_ajax_vaulty_secure" ) ) {
			header( 'HTTP/1.0 401 Unauthorized' );
			die();
		}
		$id = isset( $_REQUEST['attachment_id'] ) ? absint( $_REQUEST['attachment_id'] ) : false;
		if( !$id ) {
			header( 'HTTP/1.0 400 Bad Request' );
			die( 'id false!' );
		}
		$level = isset( $_REQUEST['level'] ) ? Vaulty::level_sanitize( $_REQUEST['level'] ) : false;
		if( !$level ) {
			header( 'HTTP/1.0 400 Bad Request' );
			die( 'level false!' );
		}

		if( !Vaulty::attachment_protect( $id, $level ) ) {
			header( 'HTTP/1.0 500 Internal Server Error' );
			die();
		}

		$data = [
			'locked' => $level != Vaulty::LEVEL_UNPROTECTED,
			'level'  => $level,
			'nonce'  => wp_create_nonce( "wp_ajax_vaulty_secure" )
		];
		header( 'Content-Type: application/json' );
		die( json_encode( $data ) );
	}

	static function get_selectbox( $attachmentID = null, $current_level = null, $class = "", $id = "", $name = "vaulty_level", $empty = null ) {

		$onchange = "";
		if( $attachmentID ) {
			$onchange = "onChange=\"vaulty_process({$attachmentID},this.value);\"";
			$onchange .= 'data-nonce="' . wp_create_nonce( "wp_ajax_vaulty_secure" ) . '"';
		}

		$id  = $id ? "id=\"{$id}\"" : "";
		$ret = "<select name=\"{$name}\" {$id} {$onchange} class=\"vaulty-select {$class}\">";

		$ret .= $empty ? "<option>{$empty}</option>" : '';

		$current_level = $current_level ? $current_level : ( $empty ? '' : Vaulty::LEVEL_UNPROTECTED );
		$current_level = Vaulty::level_sanitize( $current_level, true );

		foreach( Vaulty::levels() as $level => $label ) {
			$selected = $level == $current_level ? "selected" : "";
			$ret      .= "<option {$selected} value=\"{$level}\">{$label}</option>";
		}
		$ret .= "</select>";

		return $ret;
	}

	static function _filter_async_upload( $foo ) {
		$id     = intval( $_REQUEST['attachment_id'] );
		$select = static::get_selectbox( $id );
		echo "
		<div class=\"vaulty-attachment vaulty-attachment_$id unlocked\">
			<div class=\"vaulty-icon\">
				<span class=\"dashicons dashicons-unlock vaulty-open\"></span>
				<span class=\"spinner vaulty-loading\"></span>
				<span class=\"dashicons dashicons-lock vaulty-closed\"></span>
			</div>
			{$select}
		</div>";
		//TODO: let vaulty handle the removal
		//remove_filter( 'wp_get_attachment_image_src', [ static::me(), '_filter_async_upload' ] );

		return $foo;
	}

	static function _filter_bulk_action( $actions ) {
		$actions['vaulty-unsecured'] = "Vault: Unsecured";

		return $actions;
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	static function enqueue_styles() {
		wp_enqueue_style( static::NAME, plugin_dir_url( __FILE__ ) . 'css/vaulty-admin.css', array(), static::VERSION, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	static function enqueue_scripts() {
		wp_enqueue_script( static::NAME, plugin_dir_url( __FILE__ ) . 'js/vaulty-admin.js', array( 'jquery' ), static::VERSION, false );
		wp_localize_script( static::NAME, 'Vaulty', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

}
