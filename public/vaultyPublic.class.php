<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://unionping.com
 * @since      1.0.0
 *
 * @package    Vaulty
 * @subpackage Vaulty/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Vaulty
 * @subpackage Vaulty/public
 * @author     Jan Thomas <thomas@unionping.com>
 */
class VaultyPublic {


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
	 * Initialize this static class and registers the hooks
	 *
	 * @since    1.0.0
	 */
	static function init() {

		Vaulty::add_action( 'wp_enqueue_scripts', [ static::me(), 'enqueue_styles' ] );
		Vaulty::add_action( 'wp_enqueue_scripts', [ static::me(), 'enqueue_scripts' ] );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	static function enqueue_styles() {
		wp_enqueue_style( static::NAME, plugin_dir_url( __FILE__ ) . 'css/vaulty-public.css', array(), static::VERSION, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	static function enqueue_scripts() {
		wp_enqueue_script( static::NAME, plugin_dir_url( __FILE__ ) . 'js/vaulty-public.js', array( 'jquery' ), static::VERSION, false );
	}
}
