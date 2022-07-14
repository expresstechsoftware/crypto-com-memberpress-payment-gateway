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
define( 'CRYPTO_COM_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__) );


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

// Register http://example.com/wp-json/crypto-pay/v1/webhook
/*add_action('rest_api_init', function () {
    register_rest_route('crypto-pay-ets/v1', '/webhook-ets', array(
        'methods' => 'POST',
        'callback' => 'cpm_process_webhook',
        'permission_callback' => 'cpm_process_webhook_verify_signature',
        //'content-type' => 'application/json',
    ));

});
function cpm_process_webhook(WP_REST_Request $request){
    var_dump('here');
    die('ok');
      $json = $request->get_json_params();
    $event = $json['type'];

    if ($event == 'payment.captured') {

        // handle payment capture event from Crypto.com Pay server webhook
        // if payment is captured (i.e. status = 'succeeded'), set woo order status to processing (or the status that merchant defined)
        $payment_status = $json['data']['object']['status'];
        if ($payment_status == 'succeeded') {
            $txn_id = $json['data']['object']['order_id'];
            $txn    = new MeprTransaction($txn_id);
            if (!is_null($txn)) {
                $txn->status = MeprTransaction::$complete_str;
                $txn->store();
            }
        }

    } elseif ($event == 'payment.created' || $event == 'payment.refund_transferred') {
        // no need to handle
    }

    return false;
}

function cpm_process_webhook_verify_signature(WP_REST_Request $request){
  $webhook_signature  = $request->get_header('Pay-Signature');
  $body = $request->get_body();

  if(empty($webhook_signature) || empty($body)) {
      return false;
  }

  $webhook_signature_secret = 'To/enhSxAoPeqPiGuSpDgcoodLBlnuGidrSKZ9hCglE=';

  if(empty($webhook_signature_secret)) {
      return false;
  }

  return Crypto_Signature::verify_header($body, $webhook_signature, $webhook_signature_secret, null);
}*/
run_crypto_com_memberpress_payment_gateway();
