<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
/**
 * 
 */

include_once CRYPTO_COM_PLUGIN_DIR_PATH . '/includes/class-crypto-com-helper.php';
include_once CRYPTO_COM_PLUGIN_DIR_PATH . '/includes/class-crypto-com-call-api.php';

class MeprSomeOtherGateway extends MeprBaseRealGateway
{
  private $crypto_api;

  function __construct()
  {
    $this->name = __('Crypto.com', 'memberpress');
    $this->key = __('crypto', 'memberpress');
    $this->has_spc_form = true;
    $this->set_defaults();
    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'create-customer',
      //'send-cc-expirations'
    );

    $this->notifiers = array(
      //'whk' => 'listener',
      //'crypto-service-whk' => 'service_listener',
    );
    $this->message_pages = array();
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
    $this->crypto_api = isset($crypto_api) ? $crypto_api : new Crypto_Com_Api($this->settings);


  }

  protected function set_defaults() {
    if(!isset($this->settings)) {
      $this->settings = array();
    }

    $this->settings = (object)array_merge(
      array(
        'gateway' => get_class($this),
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'icon' => MEPR_IMAGES_URL . '/checkout/cards.png',
        'use_icon' => true,
        'desc' => __('Checkout with Crypto.com App.', 'memberpress'),
        'use_desc' => true,
        'manually_complete' => false,
        'always_send_welcome' => false,
        'email' => '',
        'sandbox' => false,
        'login_name' => '',
        'transaction_key' => '',
        'webhook_signature_key' => '',
        'signature_key' => '',
        'subscription_id' => '',
        'force_ssl' => false,
        'debug' => false,
        'test_mode' => false,
      ),
      (array)$this->settings
    );

    $this->id    = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->icon = $this->settings->icon;
    $this->use_icon = $this->settings->use_icon;
    $this->desc = $this->settings->desc;
    $this->use_desc = $this->settings->use_desc;
    //$this->recurrence_type = $this->settings->recurrence_type;
    $this->settings->transaction_key = trim($this->settings->transaction_key);
    $this->settings->signature_key   = trim($this->settings->signature_key);
    $this->settings->webhook_signature_key = trim($this->settings->webhook_signature_key);
    $this->settings->subscription_id = $this->settings->subscription_id;
  }

  public function spc_payment_fields() {
    return $this->settings->desc;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($txn) {
    if(isset($txn) && $txn instanceof MeprTransaction) {
      $usr = new MeprUser($txn->user_id);
      $prd = new MeprProduct($txn->product_id);
    }
    else
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );

    //$invoice = $txn->id.'-'.time();
    if( empty($usr->first_name) or empty($usr->last_name) ) {
      $usr->first_name = sanitize_text_field(wp_unslash($_POST['mepr_first_name']));
      $usr->last_name = sanitize_text_field(wp_unslash($_POST['mepr_last_name']));
      $usr->store();
    }
    $sanitized_title = sanitize_title($prd->post_title);
    $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $prd->ID);
    /*if($txn->subscription_id > 0) {
      $sub = $txn->subscription();
      $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
    }*/
    $return_url = '';
    $return_url = $mepr_options->thankyou_page_url(build_query($query_params));
    $amount = MeprUtils::format_float($txn->total); //Use $sub->total here because $txn->amount may be a trial price
    $customer_name = $usr->first_name . " " . $usr->last_name;
    $cancel_url = esc_url_raw(strtok($_POST['mepr_current_url'], "#"));
    if (empty($cancel_url)) {
      $cancel_url = home_url() . $_SERVER["REQUEST_URI"];
    }
    
    $secret_key = trim($this->settings->signature_key);

    $upgrade = $txn->is_upgrade();
    $downgrade = $txn->is_downgrade();

    $event_txn = $txn->maybe_cancel_old_sub();

    if($upgrade) {
      $this->upgraded_sub($txn, $event_txn);
    }
    elseif($downgrade) {
      $this->downgraded_sub($txn, $event_txn);
    }
    else {
      $this->new_sub($txn);
    }

    $txn->gateway = $this->id;
    $txn->trans_num = 't_' . uniqid();
    $txn->store();

    if(!$this->settings->manually_complete) {
      $txn->status = MeprTransaction::$complete_str;
      $txn->store(); //Need to store here so the event will show as "complete" when firing the hooks
      //The receipt is set when the transaction is automatically set to complete (see: capture_txn_status_for_events)
      //MeprUtils::send_transaction_receipt_notices($txn);
      MeprUtils::send_signup_notices($txn);
    }
    return $txn;
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    // Doesn't happen in test mode ... no need
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    // No need for this here
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment() {
    // This happens manually in test mode
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    // This happens manually in test mode
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() {
    // This happens manually in test mode
  }

  //Not needed in the Artificial gateway
  public function process_trial_payment($transaction) { }
  public function record_trial_payment($transaction) { }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    if(isset($txn) && $txn instanceof MeprTransaction) {
      $usr = new MeprUser($txn->user_id);
      $prd = new MeprProduct($txn->product_id);
    }
    else {
      return;
    }

    $sub = $txn->subscription();

    // Not super thrilled about this but there are literally
    // no automated recurring profiles when paying offline
    $sub->subscr_id = 'ts_' . uniqid();
    $sub->status = MeprSubscription::$active_str;
    $sub->created_at = gmdate('c');
    $sub->gateway = $this->id;

    //If this subscription has a paid trail, we need to change the price of this transaction to the trial price duh
    if($sub->trial) {
      $txn->set_subtotal(MeprUtils::format_float($sub->trial_amount));
      $expires_ts = time() + MeprUtils::days($sub->trial_days);
      $txn->expires_at = gmdate('c', $expires_ts);
    }

    // This will only work before maybe_cancel_old_sub is run
    $upgrade = $sub->is_upgrade();
    $downgrade = $sub->is_downgrade();

    $event_txn = $sub->maybe_cancel_old_sub();

    if($upgrade) {
      $this->upgraded_sub($sub, $event_txn);
    }
    else if($downgrade) {
      $this->downgraded_sub($sub, $event_txn);
    }
    else {
      $this->new_sub($sub, true);
    }

    $sub->store();

    $txn->gateway = $this->id;
    $txn->trans_num = 't_' . uniqid();
    $txn->store();

    if(!$this->settings->manually_complete) {
      $txn->status = MeprTransaction::$complete_str;
      $txn->store(); //Need to store here so the event will show as "complete" when firing the hooks
      MeprUtils::send_signup_notices($txn);
    }
    else {
      if($this->settings->always_send_welcome) {
        MeprUtils::send_signup_notices($txn, false, true);
      }
      else if (!$usr->signup_notice_sent) {
        MeprUtils::send_notices($txn, null, 'MeprAdminSignupEmail');
        $usr->signup_notice_sent = true;
        $usr->store();
      }

      // Apparently this gets sent already somewhere else
      // MeprUtils::send_notices($sub, null, 'MeprAdminNewSubEmail');
    }

    return array('subscription' => $sub, 'transaction' => $txn);
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    // No reason to separate this out without webhooks/postbacks/ipns
  }

  public function process_update_subscription($sub_id) {
    // This happens manually in test mode
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // No need for this one with the artificial gateway
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {}

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {}

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {}

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {}

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);
    $_REQUEST['sub_id'] = $sub_id;
    $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    $sub = new MeprSubscription($_REQUEST['sub_id']);

    if(!$sub) { return false; }

    // Seriously ... if sub was already cancelled what are we doing here?
    if($sub->status == MeprSubscription::$cancelled_str) { return $sub; }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if(isset($_REQUEST['expire']))
      $sub->limit_reached_actions();

    if(!isset($_REQUEST['silent']) || ($_REQUEST['silent'] == false))
      MeprUtils::send_cancelled_sub_notices($sub);

    return $sub;
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
  */
  public function process_signup_form($txn) {

    //if($txn->amount <= 0.00) {
    //  MeprTransaction::create_free_transaction($txn);
    //  return;
    //}

    // Redirect to thank you page
    //$mepr_options = MeprOptions::fetch();
    // $product = new MeprProduct($txn->product_id);
    // $sanitized_title = sanitize_title($product->post_title);
    //MeprUtils::wp_redirect($mepr_options->thankyou_page_url("membership={$sanitized_title}&trans_num={$txn->trans_num}"));
    if(isset($txn) && $txn instanceof MeprTransaction) {
      $usr = new MeprUser($txn->user_id);
      $prd = new MeprProduct($txn->product_id);
      $txn_id = $txn->id;

    } else
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );

    $mepr_options = MeprOptions::fetch();
    $membership_id = $prd->ID ;
    $first_name = $usr->first_name;
    $last_name = $usr->last_name;
    $user_email = $usr->user_email;
    $customer_name = $first_name . ' '. $last_name;
    $currency = $mepr_options->currency_code;
    $signature_key = trim($this->settings->signature_key);
    $product_result = $this->crypto_api->get_crypto_product($membership_id, $signature_key);
    $amount = $txn->amount;
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }
    if ($product_result && array_key_exists('success', $product_result ) && $product_result['success']) {
      $pricing_plan_id = $product_result['success']['pricing_plans'][0]['id'];
      $plan_amount = $product_result['success']['pricing_plans'][0]['amount'];
    }

    $crypto_customer_id = get_post_meta($txn_id,'_ets_crypto_customer_id', true);

    if (!$crypto_customer_id) {
      $customer_result = $this->crypto_api->create_crypto_customer($user_email, $customer_name, $signature_key);
      if ($customer_result && array_key_exists('success',$customer_result)) {
        $crypto_customer_id = $customer_result['success']['id'];
        update_post_meta($txn_id,'_ets_crypto_customer_id', $crypto_customer_id);
      }
    }

    if ($crypto_customer_id && $pricing_plan_id ) {
      $subs_result = $this->crypto_api->create_crypto_subscription($txn_id, $crypto_customer_id, $pricing_plan_id, $signature_key);
      if($subs_result && $subs_result['success']){
        $subscription_id = $subs_result['success']['id'];
        $this->settings->subscription_id = $subscription_id;
        update_post_meta($txn_id,'_ets_crypto_subscription_id', $subscription_id);
        update_post_meta($txn_id,'_ets_crypto_during_subscription', '1');
      }
    }
  }

  public function display_payment_page($txn) {
    // Nothing here yet

  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    // This happens manually in test mode
  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;
    $txn = new MeprTransaction($txn_id);
    if($txn){
      $usr = new MeprUser($txn->user_id);
    }
    //set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $sanitized_title = sanitize_title($prd->post_title);
    $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $prd->ID);
    if($txn->subscription_id > 0) {
      $sub = $txn->subscription();
      $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
    }
    $result_url = '';
    $result_url = $mepr_options->thankyou_page_url(build_query($query_params));
    $first_name = $usr->first_name;
    $last_name = $usr->last_name;
    $currency = $mepr_options->currency_code;
    ob_start();
    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;
    $amount =  Crypto_Currency_Helper::get_crypto_currency_in_subunit($currency, $amount);
    $payment_parameters = array(
      'publishable_key'=> trim($this->settings->transaction_key),
      'currency'       => $currency,
      'amount'         => $amount,
      'description'    => 'Memberpress Transaction ID: ' . $txn_id,
      'txn_id'         => $txn_id,
      'first_name'     => $first_name,
      'last_name'      => $last_name
    );
    $subscription_id = get_post_meta($txn_id,'_ets_crypto_subscription_id',true);
    ?>
      <div class="mp_wrapper mp_payment_form_wrapper">
        <form action="" method="post" id="payment-form" class="mepr-form" novalidate>
          <input type="hidden" name="mepr_process_payment_form" value="Y" />
          <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn_id; ?>" />
          <div class="mepr_spacer">&nbsp;</div>
          <script
            src="https://js.crypto.com/sdk?publishable-key=<?php echo esc_attr($payment_parameters['publishable_key']) ?>">
          </script>
          <script>
            cryptopay.Button({
              createSubscription: function(actions) {
                return actions.subscription.fetch(<?php echo "'".$subscription_id."'";?>);
              },
              onApprove: function(data, actions) {
                if(data && data.id) {
                  window.open('<?php echo esc_attr($result_url) ?>'+'&id='+data.id, '_self');
                }
              }
            }).render("#pay-crypto-button")
          </script>
          <div id="pay-crypto-button" type='button' data-subscription-id="<?php echo esc_attr($subscription_id);?>" data-test-sub-id="<?php echo esc_attr($subscription_id);?>"></div>

          <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
          <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
        </form>
      </div>
    <?php
    $output = ob_get_clean();
    //$output = MeprHooks::apply_filters('mepr_artificial_gateway_payment_form', ob_get_clean(), $txn);
    echo $output;
  }

  /**
   * Redirects the user to Paypal checkout
   * @param object MeprTransaction
   */
  public function process_payment_form($txn) {
    /*if($txn->amount > 0.00) {
      $gateway_payment_args = http_build_query($this->get_gateway_payment_args($txn));
      $url = $this->settings->url . '?' . $gateway_payment_args;
      MeprUtils::wp_redirect(str_replace('&amp;', '&', $url));
    }
    else {
      MeprTransaction::create_free_transaction($txn);
    }*/

  }


  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // This is done in the javascript with Stripe
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $txn_key       = trim($this->settings->transaction_key);
    $webhook_signature_key = trim($this->settings->webhook_signature_key);
    $signature_key = trim($this->settings->signature_key);
    update_option('_ets_crypto_com_secret_key',$signature_key);
    update_option('_ets_crypto_com_webhook_signature_key',$webhook_signature_key);

    $test_mode     = ($this->settings->test_mode == 'on' or $this->settings->test_mode == true);
    $debug         = ($this->settings->debug == 'on' or $this->settings->debug == true);
    ?>
      <table>
        <tr>
          <td><?php _e('Merchant Key*:', 'memberpress'); ?></td>
          <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][transaction_key]" value="<?php echo $txn_key; ?>" /></td>
        </tr>
        <tr>
          <td><?php _e('Merchant Secrate Key*:', 'memberpress'); ?></td>
          <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][signature_key]" value="<?php echo $signature_key; ?>" /></td>
        </tr>
        <tr>
          <td><?php _e('Webhook Signature Key:', 'memberpress'); ?></td>
          <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][webhook_signature_key]" value="<?php echo $webhook_signature_key; ?>" /></td>
        </tr>
        <tr>
          <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][test_mode]"<?php checked($test_mode); ?> />&nbsp;<?php _e('Use Crypto.com Sandbox', 'memberpress'); ?></td>
        </tr>
        <tr>
          <td><?php _e('Webhook URL:', 'memberpress'); ?></td>
          <td>
            <?php MeprAppHelper::clipboard_input($this->notify_url('whk')); ?>
          </td>
        </tr>
      </table>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if( !isset($_POST[$mepr_options->integrations_str][$this->id]['transaction_key']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['transaction_key']) )
      $errors[] = __("Merchant Key field cannot be blank.", 'memberpress');

    if(!isset($_POST[$mepr_options->integrations_str][$this->id]['signature_key']) ||
        empty($_POST[$mepr_options->integrations_str][$this->id]['signature_key'])) {
      $errors[] = __("Secrate Key field cannot be blank.", 'memberpress');
    }

    return $errors;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    */
  public function enqueue_user_account_scripts() {
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    // Handled Manually in test gateway
    ?>
    <p><b><?php _e('This action is not possible with the payment method used with this Subscription','memberpress'); ?></b></p>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    return $errors;
  }

  /** Used to update the credit card information on a subscription by the given gateway.
    * This method should be used by the class to record a successful cancellation from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_account_form($sub_id) {
    // Handled Manually in test gateway
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return false; // Why bother
  }

  public function force_ssl() {
    return false; // Why bother
  }

} //End class
