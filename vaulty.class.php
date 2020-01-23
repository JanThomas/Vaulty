<?php


/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://unionping.com
 * @since      1.0.0
 *
 * @package    Vaulty
 * @subpackage Vaulty/includes
 */


if ( ! defined( 'WPINC' ) || ! defined( 'VAULTY_VERSION' ) || ! defined( "WP_CONTENT_DIR" ) ) {
	die;
}
if ( session_status() == PHP_SESSION_NONE ) {
	session_start();
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Vaulty
 * @subpackage Vaulty/includes
 * @author     Jan Thomas <thomas@unionping.com>
 */
final class Vaulty {

	const VERSION = VAULTY_VERSION;
	const NAME = "vaulty";
	const PATH = WP_CONTENT_DIR . "/_vault_/";

	const UN_SECURE_SLUG = "unsecured";
	private static $baseLevels = [ "unsecured" => "", "user" => "" ];
	private static $baseUploads = null;

	protected static $_initialized = false;

	/**
	 * This array will hold all filters set by Vaulty
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var array $filters = [
	 *      'hook'          => string,
	 *      'callback'      => callable,
	 *      'priority'      => int,
	 *      'accepted_args' => int
	 * ] */
	protected static $filters = [];

	/**
	 * This array will hold all actions set by Vaulty
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var array $actions = [
	 *      'hook'          => string,
	 *      'callback'      => callable,
	 *      'priority'      => int,
	 *      'accepted_args' => int
	 * ] */
	protected static $actions = [];

	/**
	 * gets the called class name
	 *
	 * @return string
	 */
	static function me() {
		return get_called_class();
	}

	/**
	 * Initialize the plugin or returns the current instance
	 *
	 * @since 1.0.0
	 */
	static function init() {
		if ( ! static::$_initialized ) {
			static::$_initialized = true;
			//load text domain
			static::add_action( 'plugins_loaded', [ static::me(), "load_text_domain" ] );
			static::add_filter( 'mod_rewrite_rules', [ static::me(), "_rewrite_rules" ] );


			if ( is_admin() ) {
				require_once( plugin_dir_path( __FILE__ ) . 'admin/vaultyAdmin.class.php' );
				VaultyAdmin::init();
			} else {
				require_once( plugin_dir_path( __FILE__ ) . 'public/VaultyPublic.class.php' );
				VaultyAdmin::init();
			}

		}
	}

	/** Test the environment
	 *      - create folder "_vault_" beside "uploads" Important keep both folder names the same length
	 *      - create _vault_/.htaccess
	 *      - create intermediary _vault_/index.php
	 *      - create test-files: _vault_/access.txt _vault_/user.txt  _vault_/admin.txt  _vault_/restricted.txt
	 *      - access test-files: make curl-calls to the test-files and check the response code and the 200 body
	 *      - access test-file partials: this will test the user.txt and if it is possible to get partials of it - this functionality is required for video playback
	 *      - delete test-files
	 *      - replace intermediary _vault_/index.php with final version
	 *
	 * @return true | string contains the error message or is true if tested successfully
	 */
	static function test() {

		if ( is_multisite() ) {
			return "vaulty currently does not support multi-site installations!";
		}

		if ( ! got_mod_rewrite() ) {
			return "vaulty requires apache mod rewrite to work!";
		}

		// create base folder
		if ( ! is_dir( static::PATH ) ) {
			if ( ! wp_mkdir_p( static::PATH ) || ! is_dir( static::PATH ) ) {
				return "vaulty is unable to create the required folder structure. please make sure php has access and permission to create folders in the wp-content area.";
			}
		}

		//create a file in the original uploads folder
		$uploads_dir = wp_upload_dir( date( 'Y/m' ) . '' )['path'];
		$uploads_dir = static::_pathNorm( $uploads_dir, true );
		$test        = $uploads_dir . "test.txt";
		if ( file_put_contents( $test, "OK" ) === false ) {
			return "vaulty was unable to create a test-file in the original wordpress uploads folder";
		}

		//try to secure this test file
		$new = static::file_secure( $test, "user" );
		if ( ! $new || ! file_exists( $new ) ) {
			return "vaulty could not secure its files!";
		}

		//TODO: test for access


		//try to revert the original version of this file
		$old = static::file_un_secure( $new );
		if ( ! $old || $old !== $test || ! file_exists( $old ) ) {
			return "vaulty could not restore its test file!";
		}

		//clean up - delete test file
		if ( ! unlink( $old ) ) {
			return "vaulty could not delete the test file!";
		}

		die( 'file test?' );


		//rebuild .htaccess will trigger action 'mod_rewrite_rules' and execute _rewrite_rules
		flush_rewrite_rules();
		die( 'flushed' );


		//try to move the test.txt file to the _vault_


		die( static::PATH );

		return true;
	}

	/**
	 * @param $path  string the path(string) of an file you want to move
	 * @param $level string|false slug of a valid security level if false, the file will be restored to the unsecured
	 *
	 * @return string|false the new path of the given file or if failed to move false
	 */
	static function file_secure( $path, $level = false ) {
		if ( ! file_exists( $path ) ) {
			return false;
		}
		if ( ! $level || $level == static::UN_SECURE_SLUG ) {
			$level = false;
		}
		if ( $level !== false ) {
			$level  = sanitize_title_with_dashes( $level );
			$levels = static::levels();
			if ( ! is_array( $levels ) || ! key_exists( $level, $levels ) ) {
				return false;
			}
		}

		$path    = static::_pathNorm( $path );
		$uploads = static::_pathNorm( static::_pathUploads() );
		$base    = $level === false ? static::PATH : $uploads;
		$nBase   = $level !== false ? static::PATH : $uploads;
		$rel     = static::_pathRelative( $path, $base );

		//if we like to revert the file to its original location we have to remove the level as well from its relative path
		if ( $level === false ) {
			$rel    = explode( "/", $rel );
			$rel[0] = null;
			$rel    = implode( "/", array_filter( $rel ) );
		}

		//seems like it was not possible to retrieve the correct relative path
		if ( $rel === false ) {
			return false;
		}

		$new = implode( "/", array_filter( [ $nBase, $level, $rel ] ) );

		if ( ! is_dir( dirname( $new ) ) && ! wp_mkdir_p( dirname( $new ) ) ) {
			//unable to create the folder structure
			return false;
		}

		if ( rename( $path, $new ) ) {
			return $new;
		}

		return false;
	}

	/**
	 * get all valid security levels and makes sure they are also saved in the current session
	 *
	 * @return array keys are slugs and the value are labels for the
	 */
	static function levels() {
		$levels = apply_filters( "vaulty_levels", static::$baseLevels );
		$out    = [];
		foreach ( $levels as $key => $val ) {
			$out[ sanitize_title_with_dashes( $key ) ] = $val;
		}
		$_SESSION['vaulty_levels'] = $out;

		return $out;
	}

	static function file_un_secure( $path ) {
		return static::file_secure( $path, false );
	}

	/**
	 * Normalizes paths for linux and windows systems to use the / as directory separator
	 *
	 * @param string $path           this path will be normalized
	 * @param bool   $trailing_slash will add a trailing slash if true otherwise a trailing slash will be removed if given
	 *
	 * @return string the normalized $path
	 */
	private static function _pathNorm( $path, $trailing_slash = false ) {
		$path = rtrim( str_replace( "\\", "/", $path ), "/" );

		return $trailing_slash ? $path . "/" : $path;
	}

	/**
	 * Get the current uploads folder with trailing slash
	 *
	 * @return string|false base path - without sub folders and corrected structure
	 */
	private static function _pathUploads() {
		if ( static::$baseUploads !== null ) {
			return static::$baseUploads;
		}
		$dir = wp_upload_dir();
		if ( ! isset( $dir['basedir'] ) ) {
			new WP_Error( "wp_upload_dir", __( "I've fallen and can't get up", "vaulty" ) );

			return false;
		}
		static::$baseUploads = static::_pathNorm( $dir['basedir'], true );

		return static::$baseUploads;
	}

	/**
	 * generates the relative path to the given base - if non is given, the WP root dir will be used
	 *
	 * @param string      $path          the path witch should be shortened to its relative version
	 * @param string|null $base          the base witch should be part of the given $base
	 * @param bool        $leading_slash defines if the returned path should have a leading slash
	 *
	 * @return string|false the relative path based on $base
	 */
	private static function _pathRelative( $path, $base = null, $leading_slash = false ) {
		$path = static::_pathNorm( $path );
		$base = static::_pathNorm( $base ? $base : ABSPATH );
		if ( strpos( $path, $base ) !== 0 ) {
			return false;
		}
		$ret = ltrim( substr( $path, strlen( $base ) ), "/" );

		return $leading_slash ? "/" . $ret : $ret;
	}


	/**
	 * @param string $rules
	 *
	 * @return string
	 */
	static function _rewrite_rules( $rules = "" ) {

		$pos = strpos( $rules, "RewriteRule" );

		//check if RewriteRule exists
		if ( $pos === false ) {
			return $rules;
		}
		//split before the first rule
		$start = substr( $rules, 0, $pos );
		$end   = substr( $rules, $pos );

		$uploads_rel  = static::_pathRelative( static::_pathUploads() );
		$uploads_preg = preg_quote( $uploads_rel, '/' );
		$vault_rel    = static::_pathRelative( static::PATH );
		$vault_preg   = preg_quote( $vault_rel, '/' );
		$handler_rel  = static::_pathRelative( plugin_dir_path( __FILE__ ) . "handler.php" );
		$handler_preg = preg_quote( $handler_rel, '/' );


		$myrules = "\n
			### make sure handler is accessible!
			RewriteRule ^{$handler_preg}$ - [L]
		
			### All requests to files who are not found in the uploads folder
			RewriteCond %{REQUEST_FILENAME} !-f
			RewriteRule ^{$uploads_preg}\/(.+)$ /{$handler_rel}?s=$0 [NC,QSA,L]
			
			### Match all files that are present in the vaulty folder to the handler
			RewriteRule ^{$vault_preg}\/(.+)$ /{$handler_rel}?d=$0 [NC,QSA,L]
		\n";

		$myrules = str_replace( "\t", "", $myrules );

		return $start . $myrules . $end;
	}

	static function plugin_activate() {
		static::$_initialized = true; //this will prevent the plugin init

		//TODO: prepare upgrade path (store plugin version on activation in options)

		$test = static::test();
		if ( $test !== true ) {
			die( "VAULTY ACTIVATION ERROR: " . $test );
		}

		die( "DONE?!" );

		//TODO?: update users
	}

	static function plugin_deactivate() {
		static::$_initialized = true; //this will prevent the plugin init

	}

	static function plugin_uninstall() {
		static::$_initialized = true; //this will prevent the plugin init
		//TODO: delete the _vault_ folder IF empty!
		//TODO: make sure no attachment is corrupted by vaulty (test all for accessibility?)
	}

	/**
	 * Adds a WP action but also documents it into the actions array of this class
	 *
	 * @param string   $tag             The name of the action to which the $function_to_add is hooked.
	 * @param callable $function_to_add The name of the function you wish to be called.
	 * @param int      $priority        Optional. Used to specify the order in which the functions
	 *                                  associated with a particular action are executed. Default 10.
	 *                                  Lower numbers correspond with earlier execution,
	 *                                  and functions with the same priority are executed
	 *                                  in the order in which they were added to the action.
	 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
	 *
	 * @return true Will always return true.
	 * @uses  add_action
	 *
	 * @since 1.0.0
	 *
	 */
	static function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		static::$actions[] = [
			'hook'          => $tag,
			'callback'      => $function_to_add,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];

		return add_action( $tag, $function_to_add, $priority, $accepted_args );
	}

	/**
	 * Adds a WP filter but also documents it into the filters array of this class
	 *
	 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
	 * @param callable $function_to_add The callback to be run when the filter is applied.
	 * @param int      $priority        Optional. Used to specify the order in which the functions
	 *                                  associated with a particular action are executed. Default 10.
	 *                                  Lower numbers correspond with earlier execution,
	 *                                  and functions with the same priority are executed
	 *                                  in the order in which they were added to the action.
	 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
	 *
	 * @return true
	 * @uses  add_filter
	 *
	 * @since 1.0.0
	 *
	 */
	static function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		static::$filters[] = [
			'hook'          => $tag,
			'callback'      => $function_to_add,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];

		return add_filter( $tag, $function_to_add, $priority, $accepted_args );
	}

}
