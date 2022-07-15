<?php

/**
 * 
 */
class Crypto_Com_Api
{
    public $secret_key;
    protected $webhook_signature_key;


    function __construct($settings = array())
    {
        if ($settings) {
            $this->secret_key = $settings->signature_key;
            $this->webhook_signature_key = $settings->webhook_signature_key;
        }
    }
    public function get_http_crypto_response($api_url, $crpto_secret_key, $api_method = 'get', $data = '') {
        if ('get' === $api_method) {
            $response = wp_remote_get($api_url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $crpto_secret_key,
                    ),
                )
            );
        } else {
            $response = wp_remote_post($api_url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $crpto_secret_key,
                        'content-type' => 'application/json',
                        'Accept' => 'application/json'
                    ),
                    'body' => json_encode($data),
                )
            );
        }

        $result = array();

        // if wordpress error
        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            $result['request'] = $data;
            return $result;
        }

        $response = wp_remote_retrieve_body($response);
        $response_json = json_decode($response, true);

        // if outgoing request get back a normal response, but containing an error field in JSON body
        if (array_key_exists('error', $response_json) && $response_json['error']) {
            $result['error'] = $response_json['error'];
            $result['error']['message'] = $result['error']['param'];
            $result['error']['code'] = $result['error']['code'];
            $result['request'] = $data;
            return $result;
        }

        // if everything normal
        $result['success'] = $response_json;
        return $result;
    }

    public function create_crypto_customer($email, $customer_name, $secret_key){
        $crypto_api_url = 'https://pay.crypto.com/api/customers';    
        $data = array(
          'email' => $email,
          'name'  => $customer_name,
        );
        return self::get_http_crypto_response($crypto_api_url, $secret_key, 'post', $data);
    }

    public function create_crypto_subscription($txn_id, $customer_id, $plan_id, $secret_key){
        $billing_begin_time = strtotime(date('Y-m-d'));
        $crypto_api_url = 'https://pay.crypto.com/api/subscriptions';    
        $api_data = array(
            'customer_id'          => $customer_id,
            'billing_cycle_anchor' => $billing_begin_time,
            'metadata' => array(
                'mepr_txn_id' => $txn_id,
            ),
            'items' => array(
                array(
                    'plan_id'  => $plan_id,
                    'quantity' => 1,
                )
            )
        );
        return self::get_http_crypto_response($crypto_api_url, $secret_key, 'post', $api_data);
    }

    public function get_crypto_product($membership_id, $secret_key){
        $product_id = get_post_meta($membership_id, '_ets_crypto_sub_product_id', true );
        //$product_id = '65e51105-3c11-4b35-bde5-c89eb435bbab';
        if ($product_id) {        
            $crypto_api_url = 'https://pay.crypto.com/api/products/'.$product_id;    
            return self::get_http_crypto_response($crypto_api_url, $secret_key, 'get');
        }
        return false;
    }

    public function get_crypto_com_all_product($secret_key){  
        $crypto_api_url = 'https://pay.crypto.com/api/products';    
        return self::get_http_crypto_response($crypto_api_url, $secret_key, 'get');
    }

    public function ets_create_crypto_subscription_product($membership_id, $sub_product_name, $amount, $secret_key)
    {
        $mepr_option = MeprOptions::fetch();
        $currency = strtolower($mepr_option->currency_code);
        $crypto_api_url = 'https://pay.crypto.com/api/products';
        $data = array(
            "name"          => $sub_product_name,
            "active"        => true,
            "description"   => $sub_product_name,
            "pricing_plans" => [
                array(
                    "amount"         => $amount,
                    "currency"       => $currency,
                    "active"         => true,
                    "description"    => $sub_product_name,
                    "interval"       => "month",
                    "interval_count" => 1,
                    "purchase_type"  => "recurring"
                ),
            ]
        );
        return self::get_http_crypto_response($crypto_api_url, $secret_key, 'post', $data);

    }

    public function create_crypto_payment($txn_id, $amount, $customer_name, $return_url, $cancel_url, $secret_key){
        $mepr_option = MeprOptions::fetch();
        $currency = strtolower($mepr_option->currency_code);
        $crypto_api_payment_url = 'https://pay.crypto.com/api/payments/';    
        $data = array(
          'order_id'         => $txn_id,
          'currency'         => $currency,
          'amount'           => $amount,
          'description'      => 'Memberpress Transaction ID: ' . $txn_id,
          'metadata'         => array(
              'customer_name'=> $customer_name,
              'plugin_name'  => 'woocommerce',
              'plugin_flow'  => 'redirect'
          ),
          'return_url'       => $return_url,
          'cancel_url'       => $cancel_url
        );

        return self::get_http_crypto_response($crypto_api_payment_url, $secret_key, 'post', $data);
    }
}