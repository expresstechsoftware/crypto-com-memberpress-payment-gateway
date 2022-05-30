<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.expresstechsoftwares.com
 * @since             1.0.0
 * @package           Crypto_Com_Memberpress_Payment_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       Crypto.com MemberPress Payment Gateway
 * Plugin URI:         https://www.expresstechsoftwares.com/crypto-com-membe…-payment-gateway
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            ExpressTech Softwares Solutions Pvt Ltd
 * Author URI:        https://www.expresstechsoftwares.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       crypto-com-memberpress-payment-gateway
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CRYPTO_COM_MEMBERPRESS_PAYMENT_GATEWAY_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-crypto-com-memberpress-payment-gateway-activator.php
 */
function activate_crypto_com_memberpress_payment_gateway() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-crypto-com-memberpress-payment-gateway-activator.php';
	Crypto_Com_Memberpress_Payment_Gateway_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-crypto-com-memberpress-payment-gateway-deactivator.php
 */
function deactivate_crypto_com_memberpress_payment_gateway() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-crypto-com-memberpress-payment-gateway-deactivator.php';
	Crypto_Com_Memberpress_Payment_Gateway_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_crypto_com_memberpress_payment_gateway' );
register_deactivation_hook( __FILE__, 'deactivate_crypto_com_memberpress_payment_gateway' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-crypto-com-memberpress-payment-gateway.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_crypto_com_memberpress_payment_gateway() {

	$plugin = new Crypto_Com_Memberpress_Payment_Gateway();
	$plugin->run();

}
run_crypto_com_memberpress_payment_gateway();
