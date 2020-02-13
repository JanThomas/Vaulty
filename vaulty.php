<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * @link              https://unionping.com
 * @since             1.0.0
 * @package           Vaulty
 *
 * @wordpress-plugin
 * Plugin Name:       Vaulty
 * Plugin URI:        https://github.com/JanThomas/Vaulty
 * Description:       This Plugin will secure your uploads. By default only selected attachments will be secured.
 * Version:           1.0.0
 * Author:            Jan Thomas
 * Author URI:        https://unionping.com/jan
 * License:           GPL-3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       vaulty
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if( !defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'VAULTY_VERSION', '1.0.0' );


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'vaulty.class.php';


register_activation_hook( __FILE__, [ Vaulty::me(), 'plugin_activate' ] );
register_deactivation_hook( __FILE__, [ Vaulty::me(), 'plugin_deactivate' ] );
register_uninstall_hook( __FILE__, [ Vaulty::me(), 'plugin_uninstall' ] );

Vaulty::init();
