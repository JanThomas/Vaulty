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


if ( ! defined( 'WPINC' ) || ! defined( 'VAULTY_VERSION' ) ) {
	die;
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

			if ( is_admin() ) {
				require_once( plugin_dir_path( __FILE__ ) . 'admin/vaultyAdmin.class.php' );
				VaultyAdmin::init();
			} else {
				require_once( plugin_dir_path( __FILE__ ) . 'public/VaultyPublic.class.php' );
				VaultyAdmin::init();
			}

		}
	}


	static function plugin_activate() {
		static::$_initialized = true; //this will prevent the plugin init

	}

	static function plugin_deactivate() {
		static::$_initialized = true; //this will prevent the plugin init

	}

	static function plugin_uninstall() {
		static::$_initialized = true; //this will prevent the plugin init

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
