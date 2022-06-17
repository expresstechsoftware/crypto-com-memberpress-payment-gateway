<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.expresstechsoftwares.com
 * @since      1.0.0
 *
 * @package    Crypto_Com_Memberpress_Payment_Gateway
 * @subpackage Crypto_Com_Memberpress_Payment_Gateway/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Crypto_Com_Memberpress_Payment_Gateway
 * @subpackage Crypto_Com_Memberpress_Payment_Gateway/includes
 * @author     ExpressTech Softwares Solutions Pvt Ltd <contact@expresstechsoftwares.com>
 */
class Crypto_Com_Memberpress_Payment_Gateway_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        $create_dir_path = ABSPATH . 'wp-content/plugins/memberpress/app/gateways/MeprSomeOtherGateway.php';

        $exist_dir_path = ABSPATH . "wp-content/plugins/crypto-com-memberpress-payment-gateway/MeprSomeOtherGateway.php";
        

        if ( !file_exists($create_dir_path) ) {
            // Create new file On wc-multivendor-marketplace >> includes >> payment-gateways 
            $fp = fopen( $create_dir_path, 'wb' );
            fclose( $fp );

            // Copy file
            copy($exist_dir_path, $create_dir_path);
        }
	}

}
