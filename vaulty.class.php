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


if( !defined( 'WPINC' ) || !defined( 'VAULTY_VERSION' ) || !defined( "WP_CONTENT_DIR" ) ) {
	die;
}
if( session_status() == PHP_SESSION_NONE ) {
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

	const META_KEY_LEVEL = "vaulty_level";
	const META_KEY_LEVEL_UP = "vaulty_running";
	const META_KEY_FILES = "vaulty_files";
	const META_KEY_PROGRESS = "vaulty_progress";

	const FILTER_PREFIX = "vaulty_level-";

	const LEVEL_UNPROTECTED = "unprotected";
	const LEVEL_USER = "user";
	const LEVELS_SACRED = [ "unprotected", "user" ];
	private static $levels = [];

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
		if( !static::$_initialized ) {
			static::$_initialized = true;


			//TODO: add a filter to wp_unique_filename - to prevent overriding names

			static::add_filter( 'wp_unique_filename', [ static::me(), "_filter_wp_unique_filename" ], 10, 4 );

			//load text domain
			//static::add_action( 'plugins_loaded', [ static::me(), "load_text_domain" ] );
			static::add_filter( 'mod_rewrite_rules', [ static::me(), "_filter_rewrite_rules" ] );
			static::add_action( 'wp', [ static::me(), "_action_checkup" ] );

			static::level_add( static::LEVEL_UNPROTECTED, "Unprotected" );
			static::level_add( 'user', "Logged in User" );

			$_SESSION['vaulty_vault']   = static::_pathNorm( static::PATH, true );
			$_SESSION['vaulty_uploads'] = static::_pathUploads();


			if( is_admin() ) {
				require_once( plugin_dir_path( __FILE__ ) . '/admin/vaultyAdmin.class.php' );
				VaultyAdmin::init();
			} else {
				require_once( plugin_dir_path( __FILE__ ) . '/public/VaultyPublic.class.php' );
				VaultyPublic::init();
			}
			do_action( 'vaulty_initialized', self::me() );
		}
	}

	/**
	 * this action
	 */
	static function _action_checkup() {
		if( !is_user_logged_in() ) {
			return; //early bail if the user is not logged in
		}
		if( !key_exists( 'vaulty_check', $_SESSION ) || !$_SESSION['vaulty_check'] < ( time() - ( 60 * 5 ) ) ) {
			static::user_levels( null, true );// this will set the user_levels session array
		}
	}

	/**
	 * Test the environment
	 *
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

		if( is_multisite() ) {
			return "vaulty currently does not support multi-site installations!";
		}

		if( !got_mod_rewrite() ) {
			return "vaulty requires apache mod rewrite to work!";
		}

		// create base folder
		if( !is_dir( static::PATH ) ) {
			if( !wp_mkdir_p( static::PATH ) || !is_dir( static::PATH ) ) {
				return "vaulty is unable to create the required folder structure. please make sure php has access and permission to create folders in the wp-content area.";
			}
		}

		//create a file in the original uploads folder
		$uploads_dir = wp_upload_dir( date( 'Y/m' ) . '' )['path'];
		$uploads_dir = static::_pathNorm( $uploads_dir, true );
		$test        = $uploads_dir . "test.txt";
		if( file_put_contents( $test, "OK" ) === false ) {
			return "vaulty was unable to create a test-file in the original wordpress uploads folder";
		}

		//try to secure this test file
		$new = static::file_protect( $test, "user" );
		if( !$new || !file_exists( $new ) ) {
			return "vaulty could not secure its files!";
		}


		//rebuild .htaccess will trigger action 'mod_rewrite_rules' and execute _rewrite_rules
		flush_rewrite_rules();

		//TODO: curl test with current user session id for access


		//try to revert the original version of the test file
		$old = static::file_unprotect( $new );
		if( !$old || $old !== $test || !file_exists( $old ) ) {
			return "vaulty could not restore its test file!";
		}

		//clean up - delete test file
		if( !unlink( $old ) ) {
			return "vaulty could not delete the test file!";
		}

		return true;
	}

	/**
	 * registers a new level
	 *
	 * @param string $level  the name should be unique, lower case and normalized by sanitize_title_with_dashes
	 * @param string $label  the label for this level
	 * @param bool   $update if true and the level already exists its label will be updated
	 *
	 * @return string|false the registered name/slug of the level or false on error
	 */
	static function level_add( $level, $label, $update = false ) {
		$level = static::level_sanitize( $level, false );
		if( !$level || ( !$update && key_exists( $level, static::$levels ) ) ) {
			return false;
		}
		static::$levels[ $level ]  = $label;
		$_SESSION['vaulty_levels'] = static::$levels;
		static::$_user_levels      = [];

		return $level;
	}

	/**
	 * removes a given level by its name/slug
	 *
	 * sacred levels cannot be removed! see vaulty::LEVELS_SACRED
	 *
	 * @param string $level the name/slug of the level
	 *
	 * @return bool true if the level is removed successfully otherwise false
	 */
	static function level_remove( $level ) {
		$level = static::level_sanitize( $level );
		if( !$level || in_array( $level, static::LEVELS_SACRED ) ) {
			//given level dose not exists or is sacred
			return false;
		}
		unset( static::$_user_levels[ $level ] );

		return true;
	}

	/**
	 * gets the label for a given level
	 *
	 * @param string $level the level name/slug
	 *
	 * @return string|false returns the label of the given name
	 */
	static function level( $level ) {
		$level = static::level_sanitize( $level );
		if( !$level ) {
			return false;
		}

		return static::$levels[ $level ];
	}

	/**
	 * get all currently registered levels
	 *
	 * @return array all current registered levels
	 */
	static function levels() {
		//there must be at least the sacred levels
		foreach( static::LEVELS_SACRED as $sacred ) {
			if( !key_exists( $sacred, static::$levels ) ) {
				static::$levels[ $sacred ] = $sacred;
			}
		}

		return static::$levels;
	}


	/**
	 * Normalizes the user
	 *
	 * @param null|int|WP_User $user
	 *
	 * @return bool|WP_User
	 */
	private static function _user( $user = null ) {
		if( $user === null ) {
			$user = wp_get_current_user();
		}
		if( !( $user instanceof WP_User ) && is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if( $user instanceof WP_User ) {
			return $user;
		}

		return false;
	}

	/**
	 * will return the normalized WP_Post
	 *
	 * @param int|WP_Post|null $post
	 *
	 * @return false|WP_Post false if it isn't an attachment
	 */
	private static function _attachment( $post = null ) {
		$post = get_post( $post );
		if( !( $post instanceof WP_Post ) || $post->post_type != "attachment" ) {
			return false;
		}

		return $post;
	}


	/**
	 * checks if a user applies to the given level
	 *
	 * @param string           $level can handle inverted slugs as well
	 * @param null|int|WP_User $user  if null the currently logged in user will be used
	 * @param bool             $cache if false it will check ALL levels - use with caution, it will slow down the process significantly!
	 *
	 * @return bool
	 */
	static function level_check( $level, $user = null, $cache = true ) {
		$inverted = substr( $level, 0, 1 ) == '!';
		$level    = $inverted ? substr( $level, 1 ) : $level;

		$level = static::level_sanitize( $level );
		if( !$level ) {
			return false;
		}
		if( $level == static::LEVEL_UNPROTECTED ) {
			return true;
		}
		$user = static::_user( $user );
		if( !$user ) {
			return false;
		}

		$user_levels = static::user_levels( $user, !$cache );
		$result      = in_array( $level, $user_levels );

		return $inverted ? !$result : $result;
	}

	/**
	 * @var array $_user_levels cache of all requested user-levels
	 */
	private static $_user_levels = [];

	/**
	 * get a list of all levels given user has access to
	 *
	 * @param null|int|WP_User $user         if null the current user will be used
	 * @param bool             $force_reload if true the cache will be renewed
	 *
	 * @return array list of all passed levels
	 */
	static function user_levels( $user = null, $force_reload = false ) {
		$user = static::_user( $user );
		if( !$user ) {
			return [];
		}
		if( !$force_reload && key_exists( $user->ID, static::$_user_levels ) ) {
			return static::$_user_levels[ $user->ID ];
		}
		$levels      = static::levels();
		$user_levels = [];
		foreach( $levels as $level => $label ) {
			if( apply_filters( static::FILTER_PREFIX . $level, false, $user ) == true ) {
				$user_levels[] = $level;
			}
		}

		if( $user->ID == get_current_user_id() ) {
			$_SESSION['vaulty_user_levels'] = $user_levels;
		}

		return static::$_user_levels[ $user->ID ] = $user_levels;
	}

	/**
	 * this function will give you the possibility to clean up all attachments who are currently not accessible
	 */
	static function cleanup() {
		//TODO: clean up all files in sub folders of non existing levels
	}

	/**
	 * This function will secure or free a file on the file system level
	 *
	 * @param $path  string the path(string) of an file you want to move
	 * @param $level string|false slug of a valid security level if false, the file will be restored to the unprotected
	 *
	 * @return string|false the new path of the given file or if failed to move false
	 */
	private static function file_protect( $path, $level = false ) {
		if( !file_exists( $path ) ) {
			return false;
		}

		$level = $level ? $level : static::LEVEL_UNPROTECTED;
		$level = static::level_sanitize( $level );
		if( !$level ) {
			return false;
		}

		$path    = static::_pathNorm( $path );
		$uploads = static::_pathNorm( static::_pathUploads() );
		$base    = $level === static::LEVEL_UNPROTECTED ? static::PATH : $uploads;
		$nBase   = $level !== static::LEVEL_UNPROTECTED ? static::PATH : $uploads;
		$rel     = static::_pathRelative( $path, $base );

		//if we like to revert the file to its original location we have to remove the level as well from its relative path
		if( $level === static::LEVEL_UNPROTECTED ) {
			$rel    = explode( "/", $rel );
			$rel[0] = null;
			$rel    = implode( "/", array_filter( $rel ) );
		}

		//seems like it was not possible to retrieve the correct relative path
		if( $rel === false ) {
			return false;
		}

		$new = implode( "/", [ $nBase, $level, $rel ] );
		if( $level == static::LEVEL_UNPROTECTED ) {
			$new = implode( "/", [ $nBase, $rel ] );
		}

		//unable to create the folder structure
		if( !is_dir( dirname( $new ) ) && !wp_mkdir_p( dirname( $new ) ) ) {
			return false;
		}

		if( rename( $path, $new ) ) {
			return $new;
		}


		return false;
	}

	/**
	 * This function will lift the protection of a file on the file system level
	 *
	 * @param $path string the path(string) of an file you want to move
	 *
	 * @return false|string the new path of the given file or if failed to move false
	 */
	private static function file_unprotect( $path ) {
		return static::file_protect( $path, false );
	}


	/**
	 * Protects a wordpress attachment
	 *
	 * @param int|WP_Post|null $attachment the attachment (post-id|WP_Post) default is the current the_post
	 * @param bool             $level      if false the protection will be lifted
	 *
	 * @return bool true on success otherwise false
	 */
	static function attachment_protect( $attachment, $level = false ) {
		$post = static::_attachment( $attachment );
		if( $post === false ) {
			return false;
		}

		$level = $level ? $level : static::LEVEL_UNPROTECTED;
		$level = static::level_sanitize( $level, true );
		if( !$level ) {
			return false;
		}

		$state = absint( get_post_meta( $post->ID, static::META_KEY_LEVEL_UP, true ) );
		//is it less then about a minute ago, let the previous job finish
		if( $state && $state > ( time() - 65 ) ) {
			return false;
		}


		$old_level = get_post_meta( $post->ID, static::META_KEY_LEVEL, true );
		$old_level = static::level_sanitize( $old_level, true, true );

		update_post_meta( $post->ID, static::META_KEY_LEVEL, $level );
		update_post_meta( $post->ID, static::META_KEY_LEVEL_UP, time() );

		$files = [];


		//get all files currently protected by vaulty - if there are any
		$old_files = get_post_meta( $post->ID, static::META_KEY_FILES, true );
		$old_files = is_array( $old_files ) ? $old_files : [];
		foreach( $old_files as $old_file ) {
			if( file_exists( $old_file ) ) {
				$files[] = $old_file;
			}
		}

		//are there any lost files of a aborted task?
		$lost_files = [];
		if( $state ) {
			$lost_files = get_post_meta( $post->ID, static::META_KEY_PROGRESS, true );
			$lost_files = is_array( $old_files ) ? $old_files : [];
			foreach( $lost_files as $lost_file ) {
				if( file_exists( $lost_file ) ) {
					$files[] = $lost_file;
				}
			}
		}

		//get all files associated with this attachment
		$uploads  = static::_pathUploads();
		$metadata = wp_get_attachment_metadata( $post->ID );
		if( $metadata !== false ) {
			$file = $uploads . $metadata['file'];
			$dir  = trailingslashit( dirname( $file ) ); //includes the folder structure of the file
			if( file_exists( $file ) ) {
				$files[] = $file;
			}
			foreach( $metadata['sizes'] as $size ) {
				$file = $dir . $size['file'];
				if( file_exists( $file ) ) {
					$files[] = $file;
				}
			}
		}

		$files = array_unique( $files );
		if( !count( $files ) ) {
			return false;
		}


		$done = [];
		foreach( $files as $file ) {
			$nFile = static::file_protect( $file, $level );
			if( $nFile !== false ) {
				$done[] = $nFile;
				//keep track of the process
				update_post_meta( $post->ID, static::META_KEY_PROGRESS, $lost_files + $done );
			} else {
				//PANIC!! - try to revert all already done files back to its correct place
				foreach( $done as $revert ) {
					static::file_protect( $revert, $old_level );
				}
				update_post_meta( $post->ID, static::META_KEY_LEVEL, $old_level );
				update_post_meta( $post->ID, static::META_KEY_PROGRESS, [] );
				update_post_meta( $post->ID, static::META_KEY_LEVEL_UP, 0 );
				//TODO: save the correct values for:
				//  update_post_meta( $post->ID, static::META_KEY_FILES, $done );

				return false;
			}
		}

		//reset state to done
		update_post_meta( $post->ID, static::META_KEY_LEVEL_UP, 0 ); //0 == done

		//reset progress to done
		update_post_meta( $post->ID, static::META_KEY_PROGRESS, [] );

		//set the processed files
		update_post_meta( $post->ID, static::META_KEY_FILES, $done );


		return true;
	}


	/**
	 * lifts the protection of a given attachment
	 *
	 * @param WP_Post $attachment the attachment in WP_Post format
	 *
	 * @return bool true on success otherwise false
	 */
	static function attachment_unprotect( $attachment ) {
		return static::attachment_protect( $attachment, false );
	}


	/**
	 * sanitizes, tests and defaults a given level
	 *
	 * @param string      $level    the un-sanitized level
	 * @param bool        $test     tests if the given level exists, if so the return will be false or the default if fallback is enabled
	 * @param bool|string $fallback if it dose not exist, return the default value
	 *
	 * @return false|string returns the sanitized level, if
	 */
	static function level_sanitize( $level, $test = true, $fallback = false ) {
		$level = sanitize_title_with_dashes( strtolower( $level ) );
		if( $test ) {
			$levels = static::levels();
			if( !is_array( $levels ) || !key_exists( $level, $levels ) ) {
				$level = false;
			}
		}
		if( $fallback && !$level ) {
			$level = $fallback === true ? static::level_default() : static::level_sanitize( $fallback, true, true );
		}

		return $level;
	}

	/**
	 * returns the fallback level
	 * TODO: make this an setting, make sure the level exists if not use a sacred fallback
	 *
	 * @return string
	 */
	private static function level_default() {
		return static::LEVEL_USER;
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
		if( static::$baseUploads !== null ) {
			return static::$baseUploads;
		}
		$dir = wp_upload_dir();
		if( !isset( $dir['basedir'] ) ) {
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
		if( strpos( $path, $base ) !== 0 ) {
			return false;
		}
		$ret = ltrim( substr( $path, strlen( $base ) ), "/" );

		return $leading_slash ? "/" . $ret : $ret;
	}


	static function unique_filename( $filename, $directories ) {

		$paths = [];
		$files = [];
		$dirs  = is_array( $directories ) ? $directories : [ $directories ];

		foreach( $dirs as $dir ) {
			$dir = static::_pathNorm( $dir, true );
			if( @is_dir( $dir ) ) {
				$paths[] = $dir;
			}
		}


		//early bail if no folders are there to check
		if( !count( $paths ) ) {
			return $filename;
		}

		$name = pathinfo( $filename, PATHINFO_FILENAME );
		$ext  = pathinfo( $filename, PATHINFO_EXTENSION );
		$ext  = $ext ? '.' . $ext : $ext;
		$name = $name == $ext ? '' : $name;

		//if already suffixed with a number - remove the number, will be attached later on anyways
		$name = preg_replace( "/-\d+$/", "", $name );


		//collect all files of the folders witch could match the filename, make the array lower case for insensitive check
		foreach( $paths as $path ) {
			$items = glob( $path . $name . "*", GLOB_NOSORT );
			foreach( $items as $item ) {
				$files[] = strtolower( substr( $item, strlen( $path ) ) );
			}
		}

		//make sure there will be no conflict in the future
		$i        = preg_match( '/-(?:\d+x\d+|scaled|rotated)$/', $name ) ? 1 : 0;
		$e        = count( $files ) + 1;
		$filename = $i ? "{$name}-{$i}{$ext}" : "{$name}{$ext}";

		//early bail if no folders are there to check - after it made sure there is no conflict in the future
		if( !count( $files ) ) {
			return $filename;
		}


		//check for existing versions of the filename match all lower-cased to check case insensitive
		while( _wp_check_existing_file_names( strtolower( $filename ), $files ) && $i <= $e ) {
			$i ++;
			$filename = "{$name}-{$i}{$ext}";
		}

		return $filename;
	}

	static function _filter_wp_unique_filename( $filename, $ext, $dir, $unique_filename_callback ) {

		$dir    = static::_pathNorm( $dir, true );
		$paths  = [ $dir ];
		$relDir = static::_pathRelative( $dir, static::_pathUploads(), true );

		//test all existing leveled directories of vaulty
		foreach( static::levels() as $level => $label ) {
			$levelPath = static::PATH . $level . $relDir;
			$paths[]   = $levelPath;
		}


		return static::unique_filename( $filename, $paths );
	}

	/**
	 * @param string $rules
	 *
	 * @return string
	 */
	static function _filter_rewrite_rules( $rules = "" ) {

		$pos = strpos( $rules, "RewriteRule" );

		//check if RewriteRule exists
		if( $pos === false ) {
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
		if( $test !== true ) {
			die( "VAULTY ACTIVATION ERROR: " . $test );
		}

		//TODO?: update users access level
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
	 * @param callable $callback        The name of the function you wish to be called.
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
	static function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		static::$actions[] = [
			'hook'          => $tag,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];

		return add_action( $tag, $callback, $priority, $accepted_args );
	}

	/**
	 * Adds a WP filter but also documents it into the filters array of this class
	 *
	 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
	 * @param callable $callback        The callback to be run when the filter is applied.
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
	static function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		static::$filters[] = [
			'hook'          => $tag,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];

		return add_filter( $tag, $callback, $priority, $accepted_args );
	}

}
