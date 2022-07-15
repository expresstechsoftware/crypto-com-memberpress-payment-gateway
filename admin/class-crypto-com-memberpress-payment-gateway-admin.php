<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.expresstechsoftwares.com
 * @since      1.0.0
 *
 * @package    Crypto_Com_Memberpress_Payment_Gateway
 * @subpackage Crypto_Com_Memberpress_Payment_Gateway/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Crypto_Com_Memberpress_Payment_Gateway
 * @subpackage Crypto_Com_Memberpress_Payment_Gateway/admin
 * @author     ExpressTech Softwares Solutions Pvt Ltd <contact@expresstechsoftwares.com>
 */
require_once CRYPTO_COM_PLUGIN_DIR_PATH . '/includes/class-crypto-com-call-api.php';

class Crypto_Com_Memberpress_Payment_Gateway_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $crypto_api;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
    	include_once CRYPTO_COM_PLUGIN_DIR_PATH . '/includes/class-crypto-com-signature-verify.php';
    	$this->crypto_api = isset($crypto_api) ? $crypto_api : new Crypto_Com_Api();

    	
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Crypto_Com_Memberpress_Payment_Gateway_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Crypto_Com_Memberpress_Payment_Gateway_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/crypto-com-memberpress-payment-gateway-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Crypto_Com_Memberpress_Payment_Gateway_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Crypto_Com_Memberpress_Payment_Gateway_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/crypto-com-memberpress-payment-gateway-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function ets_memberpress_add_gateway_file($gateway_path)
	{
		if ($gateway_path && is_array($gateway_path)) {
			$my_gateway_path[] = CRYPTO_COM_PLUGIN_DIR_PATH. 'includes/';
		}
		$gateway_path = array_merge($my_gateway_path,$gateway_path);
		return $gateway_path;
	}

	public function ets_crypto_com_register_route(){
		//var_dump('here');
		register_rest_route('crypto-pay-ets/v1', '/webhook', array(
	        'methods' => 'POST',
	        'callback' => array($this, 'cpm_process_webhook'),
	        'permission_callback' => array($this, 'cpm_process_webhook_verify_signature'),
    	)
	);

	}

	public function cpm_process_webhook(WP_REST_Request $request){
	  	$json = $request->get_json_params();
      	update_option('ets_check_webhook_responce',$json);
      	update_option('ets_check_webhook_responce_request',$request);
    	$event = $json['type'];
      	update_option('ets_check_webhhok_events',$event);
		if ($event == 'subscription.activated')  {
			$sub_status = $json['data']['object']['status'];
			if ($sub_status == 'active') {
				$metadata = $json['data']['object']['metadata'];
				if ($metadata && array_key_exists('mepr_txn_id',$metadata)) {
					$txn_id = $json['data']['object']['metadata']['mepr_txn_id'];
					$txn    = new MeprTransaction($txn_id);
					if (!is_null($txn)) {
					  	$txn->status = MeprTransaction::$complete_str;
			  			if($txn->subscription_id > 0) {
  							$sub = $txn->subscription();
  							$sub->status = MeprSubscription::$active_str;
    						$sub->store();
      					}
					  	$txn->store();
					}
				}
			}
		} elseif ($event == 'invoice.paid') {
			global $wpdb;
			$subscription_id = $json['data']['object']['subscription_id'];
			$results = $wpdb->get_row( "SELECT post_id from $wpdb->postmeta WHERE meta_key='_ets_crypto_subscription_id' AND meta_value = '".$subscription_id."'");
			//only for recuuring payment
			if ($results) {
				$txn_id = $results->post_id;
        		$check_txn = get_post_meta($txn_id,'_ets_crypto_during_subscription',true);
				if ($txn_id && !$check_txn ) {
					$first_txn  = new MeprTransaction($txn_id);
  					$sub  = $first_txn->subscription();
  					$sub_num = $sub->subscr_id;
  					$sub_id = $sub->id;
  					$product_id = $sub->product_id;
  					$user_id = $sub->user_id;
  					$txn             = new MeprTransaction();
					$txn->user_id    = $sub->user_id;
					$txn->product_id = $sub->product_id;
					$txn->coupon_id  = $first_txn->coupon_id;
					$txn->total  = $first_txn->total;
          			$txn->status = MeprTransaction::$complete_str;
    				$txn->set_subtotal($first_txn->total);
      				$txn->gateway    = $first_txn->gateway;
					$txn->subscription_id  = $sub_id;
  					$sub->status = MeprSubscription::$active_str;
					$sub->store();
				  	$txn->store();
				} else {
        			delete_post_meta($txn_id, '_ets_crypto_during_subscription' );
				}
			}
		}
  		return false;
	}

	public function cpm_process_webhook_verify_signature(WP_REST_Request $request){
		$webhook_signature  = $request->get_header('Pay-Signature');
		$body = $request->get_body();
		$header_signature = MeprUtils::get_http_header('Signature');
		update_option('ets_check_webhook_signatue',$request);

		if(empty($webhook_signature) || empty($body)) {
		  return false;
		}
		$webhook_signature_secret = get_option('_ets_crypto_com_webhook_signature_key');

		//$webhook_signature_secret = 'U766pfvkMKngy+lQfoCgCwHVf8COWU8LY4WNr2CuLRY=';

		if(empty($webhook_signature_secret)) {
		  return false;
		}

		return Crypto_Com_Signature_Verify::verify_crypto_header($body, $webhook_signature, $webhook_signature_secret, null);
	}

	public function ets_crypto_com_add_product_meta_box()
	{
		global $post_id;
		$product = new MeprProduct( $post_id );
		add_meta_box("memberpress-product-start-end-date", __( 'Crypto Subscription Products', 'crypto-com-memberpress-payment-gateway' ), array ( $this, 'ets_crypto_com_display_product_meta_box' ), MeprProduct::$cpt, "normal", "default", array( 'product' => $product ) );            
	}

	public function ets_crypto_com_display_product_meta_box($product)
	{
    	$secret_key = '';
    	$secret_key = get_option('_ets_crypto_com_secret_key');
    	$crypto_products = $this->crypto_api->get_crypto_com_all_product($secret_key);
		$ets_crypto_sub_product_id = get_post_meta( $product->ID, '_ets_crypto_sub_product_id', true );
		$meta_boxes_content = '<p class="meta-options">';
		$meta_boxes_content .= '<label>' . esc_html__( 'Subscription Product Plan:' , 'crypto-com-memberpress-payment-gateway' ) . '</label><br>'; 

		$meta_boxes_content .= '<select class= "regular-text" id="ets_crypto_product_plan" name="ets_crypto_subscription_product">';

		$meta_boxes_content .= '<option value="0">'. esc_html__( 'Select Product' , 'crypto-com-memberpress-payment-gateway' ) .' </option>';
		
		if( array_key_exists('success',$crypto_products) && $crypto_products['success'] && array_key_exists('items', $crypto_products['success']) && $crypto_products['success']['items']){
			foreach ($crypto_products['success']['items'] as $key => $cproduct) {
				$selected = ($ets_crypto_sub_product_id && $ets_crypto_sub_product_id == $cproduct['id'] ) ? 'selected="selected"' : '';
				$meta_boxes_content .= '<option value="'.$cproduct['id'].'" '.$selected.'>'.$cproduct['name'].'</option>';
			}
		}
		$meta_boxes_content .= '</select></p>';
		echo $meta_boxes_content;	
	}

	public function ets_crypto_com_memberpress_save_meta_box($post_id)
	{
		$post = get_post( $post_id );
		if( ! wp_verify_nonce( ( isset( $_POST[ MeprProduct::$nonce_str ] ) )? $_POST[ MeprProduct::$nonce_str ] : '' , MeprProduct::$nonce_str.wp_salt() ) ) {
			return $post_id; //Nonce prevents meta data from being wiped on move to trash
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if( defined( 'DOING_AJAX' ) ) {
			return;
		}

		if( ! empty( $post ) && $post->post_type == MeprProduct::$cpt ) {
			$ets_crypto_subscription_product = sanitize_text_field( $_POST['ets_crypto_subscription_product'] );
			update_post_meta( $post_id, '_ets_crypto_sub_product_id', $ets_crypto_subscription_product );
		}
	}
}
