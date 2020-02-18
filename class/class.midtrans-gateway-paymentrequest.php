<?php
    /**
     * Midtrans Payment Request Gateway Class
     * Duplicated from `class.midtrans-gateway.php`
     * Check `class.midtrans-gateway.php` file for proper function comments
     *
     * Developed specifically for Chrome Payment Request API
     * In collaboration with Google Dev representative
     * Which function as Browser level credit card storage function
     */
    class WC_Gateway_Midtrans_Paymentrequest extends WC_Payment_Gateway {

      /**
       * Constructor
       */
      function __construct() {
        $this->id           = 'midtrans_paymentrequest';
        $this->icon         = apply_filters( 'woocommerce_midtrans_icon', '' );
        $this->method_title = __( 'Midtrans Credit Card Direct', 'woocommerce' );
        $this->has_fields   = true;
        $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Midtrans_Paymentrequest', home_url( '/' ) ) );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get Settings
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->client_key_v2_sandbox         = 
          get_option('woocommerce_midtrans_settings' , ['client_key_v2_sandbox' => ''])['client_key_v2_sandbox'];
        $this->server_key_v2_sandbox         = 
          get_option('woocommerce_midtrans_settings' , ['server_key_v2_sandbox' => ''])['server_key_v2_sandbox'];
        $this->client_key_v2_production         = 
          get_option('woocommerce_midtrans_settings' , ['client_key_v2_production' => ''])['client_key_v2_production'];
        $this->server_key_v2_production         = 
          get_option('woocommerce_midtrans_settings' , ['server_key_v2_production' => ''])['server_key_v2_production'];
        $this->api_version        = 2;
        $this->environment        = 
          get_option('woocommerce_midtrans_settings' , ['select_midtrans_environment' => ''])['select_midtrans_environment'];
        $this->merchant_id        = 
          get_option('woocommerce_midtrans_settings' , ['merchant_id' => ''])['merchant_id'];
        
        $this->to_idr_rate        = $this->get_option( 'to_idr_rate' );

        $this->enable_3d_secure   = $this->get_option( 'enable_3d_secure' );
        $this->enable_savecard   = $this->get_option( 'enable_savecard' );
        $this->enable_redirect   = 'no';
        $this->custom_expiry   = $this->get_option( 'custom_expiry' );
        $this->custom_fields   = $this->get_option( 'custom_fields' );
        $this->enable_map_finish_url   = $this->get_option( 'enable_map_finish_url' );
        $this->ganalytics_id   = $this->get_option( 'ganalytics_id' );
        $this->enable_immediate_reduce_stock   = $this->get_option( 'enable_immediate_reduce_stock' );
        // $this->enable_sanitization = $this->get_option( 'enable_sanitization' );
        $this->bin_number         = $this->get_option( 'bin_number' );
        $this->acquring_bank         = $this->get_option( 'acquring_bank' );
        
        $this->client_key         = ($this->environment == 'production')
          ? $this->client_key_v2_production
          : $this->client_key_v2_sandbox;

        $this->log = new WC_Logger();
        $this->supports = array(
          'refunds'
        );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); 
        add_action( 'admin_print_scripts-woocommerce_page_woocommerce_settings', array( &$this, 'midtrans_admin_scripts' ));
        add_action( 'admin_print_scripts-woocommerce_page_wc-settings', array( &$this, 'midtrans_admin_scripts' ));
        add_action( 'valid-midtrans-web-request', array( $this, 'successful_request' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );// Payment page hook
      }

      /**
       * Enqueue Javascripts
       */
      function midtrans_admin_scripts() {
        wp_enqueue_script( 'admin-midtrans', MT_PLUGIN_DIR . 'js/admin-scripts.js', array('jquery') );
      }

      // Backward compatibility WC v3 & v2
      function getOrderProperty($order, $property){
        $functionName = "get_".$property;
        if (method_exists($order, $functionName)){ // WC v3
          return (string)$order->{$functionName}();
        } else { // WC v2
          return (string)$order->{$property};
        }
      }

      function init_form_fields() {
        
        $v2_sandbox_key_url = 'https://dashboard.sandbox.midtrans.com/settings/config_info';
        $v2_production_key_url = 'https://dashboard.midtrans.com/settings/config_info';

        $this->form_fields = array(
          'enabled' => array(
            'title' => __( 'Enable/Disable', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Midtrans CC Direct Payment', 'woocommerce' ),
            'default' => 'no'
          ),
          'title' => array(
            'title' => __( 'Title', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default' => __( 'Credit Card via Midtrans', 'woocommerce' ),
            'desc_tip'      => true,
          ),
          'description' => array(
            'title' => __( 'Customer Message', 'woocommerce' ),
            'type' => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout', 'woocommerce' ),
            'default' => ''
          ),
          'acquring_bank' => array(
            'title' => __( 'Acquring Bank', 'woocommerce'),
            'type' => 'text',
            'label' => __( 'Acquring Bank', 'woocommerce' ),
            'description' => __( 'Leave blank for default. </br> Specify your acquiring bank for this payment option. </br> Options: BCA, BRI, DANAMON, MAYBANK, BNI, MANDIRI, CIMB, etc (Only choose 1 bank).' , 'woocommerce' ),
            'default' => ''
          ),
          'enable_3d_secure' => array(
            'title' => __( 'Enable 3D Secure', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable 3D Secure?', 'woocommerce' ),
            'description' => __( 'You must enable 3D Secure.
                Please contact us if you wish to disable this feature in the Production environment.', 'woocommerce' ),
            'default' => 'yes'
          ),
          // 'enable_sanitization' => array(
          //   'title' => __( 'Enable Sanitization', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Sanitization?', 'woocommerce' ),
          //   'default' => 'yes'
          // ),
          'bin_number' => array(
            'title' => __( 'Allowed CC BINs', 'woocommerce'),
            'type' => 'text',
            'label' => __( 'Allowed CC BINs', 'woocommerce' ),
            'description' => __( 'Leave this blank if you dont understand!</br> Fill with CC BIN numbers (or bank name) that you want to allow to use this payment button. </br> Separate BIN number with coma Example: 4,5,4811,bni,mandiri', 'woocommerce' ),
            'default' => ''
          ),
          'enable_savecard' => array(
            'title' => __( 'Enable Save Card', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Save Card?', 'woocommerce' ),
            'description' => __( 'This will allow your customer to save their card on the payment popup, for faster payment flow on the following purchase', 'woocommerce' ),
            'class' => 'toggle-advanced',
            'default' => 'no'
          ),
          'custom_expiry' => array(
            'title' => __( 'Custom Expiry', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This will allow you to set custom duration on how long the transaction available to be paid.<br> example: 45 minutes', 'woocommerce' ),
            'default' => 'disabled'
          ),
          'custom_fields' => array(
            'title' => __( 'Custom Fields', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This will allow you to set custom fields that will be displayed on Midtrans dashboard. <br>Up to 3 fields are available, separate by coma (,) <br> Example:  Order from web, Woocommerce, Processed', 'woocommerce' ),
            'default' => ''
          ),
          'enable_map_finish_url' => array(
            'title' => __( 'Use Dashboard Finish url', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => 'Use dashboard configured payment finish url?',
            'description' => __( 'This will allow use of Dashboard configured payment finish url instead of auto configured url', 'woocommerce' ),
            'default' => 'no'
          ),
          'ganalytics_id' => array(
            'title' => __( 'Google Analytics ID', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This will allow you to use Google Analytics tracking on woocommerce payment page. <br>Input your tracking ID ("UA-XXXXX-Y") <br> Leave it blank if you are not sure', 'woocommerce' ),
            'default' => ''
          ),
          'enable_immediate_reduce_stock' => array(
            'title' => __( 'Immediate Reduce Stock', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => 'Immediately reduce item stock on Midtrans payment pop-up?',
            'description' => __( 'By default, item stock only reduced if payment status on Midtrans reach pending/success (customer choose payment channel and click pay on payment pop-up). Enable this if you want to immediately reduce item stock when payment pop-up generated/displayed.', 'woocommerce' ),
            'default' => 'no'
          )
        );

        if (get_woocommerce_currency() != 'IDR')
        {
          $this->form_fields['to_idr_rate'] = array(
            'title' => __("Current Currency to IDR Rate", 'woocommerce'),
            'type' => 'text',
            'description' => 'The current currency to IDR rate',
            'default' => '10000',
          );
        }
      }

      function create_snap_transaction( $order_id){
        require_once(dirname(__FILE__) . '/../lib/midtrans/Midtrans.php');
        require_once(dirname(__FILE__) . '/class.midtrans-utils.php');
        
        global $woocommerce;
        $order_items = array();
        $cart = $woocommerce->cart;

        $order = new WC_Order( $order_id );     
      
        \Midtrans\Config::$isProduction = ($this->environment == 'production') ? true : false;
        \Midtrans\Config::$serverKey = (\Midtrans\Config::$isProduction) ? $this->server_key_v2_production : $this->server_key_v2_sandbox;     
        \Midtrans\Config::$is3ds = ($this->enable_3d_secure == 'yes') ? true : false;
        \Midtrans\Config::$isSanitized = true;
        
        $params = array(
          'transaction_details' => array(
            'order_id' => $order_id,
            'gross_amount' => 0,
          ),
          'vtweb' => array()
        );

        $enabled_payments[] = 'credit_card';
        $params['enabled_payments'] = $enabled_payments; // Disable customize payment method from config

        $customer_details = array();
        $customer_details['first_name'] = $this->getOrderProperty($order,'billing_first_name');
        $customer_details['last_name'] = $this->getOrderProperty($order,'billing_last_name');
        $customer_details['email'] = $this->getOrderProperty($order,'billing_email');
        $customer_details['phone'] = $this->getOrderProperty($order,'billing_phone');

        $billing_address = array();
        $billing_address['first_name'] = $this->getOrderProperty($order,'billing_first_name');
        $billing_address['last_name'] = $this->getOrderProperty($order,'billing_last_name');
        $billing_address['address'] = $this->getOrderProperty($order,'billing_address_1');
        $billing_address['city'] = $this->getOrderProperty($order,'billing_city');
        $billing_address['postal_code'] = $this->getOrderProperty($order,'billing_postcode');
        $billing_address['phone'] = $this->getOrderProperty($order,'billing_phone');
        $converted_country_code = Midtrans_Utils::convert_country_code($this->getOrderProperty($order,'billing_country'));
        $billing_address['country_code'] = (strlen($converted_country_code) != 3 ) ? 'IDN' : $converted_country_code ;

        $customer_details['billing_address'] = $billing_address;
        $customer_details['shipping_address'] = $billing_address;
        
        if ( isset ( $_POST['ship_to_different_address'] ) ) {
          $shipping_address = array();
          $shipping_address['first_name'] = $this->getOrderProperty($order,'shipping_first_name');
          $shipping_address['last_name'] = $this->getOrderProperty($order,'shipping_last_name');
          $shipping_address['address'] = $this->getOrderProperty($order,'shipping_address_1');
          $shipping_address['city'] = $this->getOrderProperty($order,'shipping_city');
          $shipping_address['postal_code'] = $this->getOrderProperty($order,'shipping_postcode');
          $shipping_address['phone'] = $this->getOrderProperty($order,'billing_phone');
          $converted_country_code = Midtrans_Utils::convert_country_code($this->getOrderProperty($order,'shipping_country'));
          $shipping_address['country_code'] = (strlen($converted_country_code) != 3 ) ? 'IDN' : $converted_country_code;
          
          $customer_details['shipping_address'] = $shipping_address;
        }
        
        $params['customer_details'] = $customer_details;
        //error_log(print_r($params,true));

        $items = array();

        if( sizeof( $order->get_items() ) > 0 ) {
          foreach( $order->get_items() as $item ) {
            if ( $item['qty'] ) {
              $product = $order->get_product_from_item( $item );

              $midtrans_item = array();

              $midtrans_item['id']    = $item['product_id'];
              $midtrans_item['price']      = ceil($order->get_item_subtotal( $item, false ));
              $midtrans_item['quantity']   = $item['qty'];
              $midtrans_item['name'] = $item['name'];
              
              $items[] = $midtrans_item;
            }
          }
        }

        // Shipping fee
        if( $order->get_total_shipping() > 0 ) {
          $items[] = array(
            'id' => 'shippingfee',
            'price' => ceil($order->get_total_shipping()),
            'quantity' => 1,
            'name' => 'Shipping Fee',
          );
        }

        // Tax
        if( $order->get_total_tax() > 0 ) {
          $items[] = array(
            'id' => 'taxfee',
            'price' => ceil($order->get_total_tax()),
            'quantity' => 1,
            'name' => 'Tax',
          );
        }

        // Discount
        if ( $order->get_total_discount() > 0) {
          $items[] = array(
            'id' => 'totaldiscount',
            'price' => ceil($order->get_total_discount())  * -1,
            'quantity' => 1,
            'name' => 'Total Discount'
          );
        }

        // Fees
        if ( sizeof( $order->get_fees() ) > 0 ) {
          $fees = $order->get_fees();
          $i = 0;
          foreach( $fees as $item ) {
            $items[] = array(
              'id' => 'itemfee' . $i,
              'price' => ceil($item['line_total']),
              'quantity' => 1,
              'name' => $item['name'],
            );
            $i++;
          }
        }

        // iterate through the entire item to ensure that currency conversion is applied
        if (get_woocommerce_currency() != 'IDR')
        {
          foreach ($items as &$item) {
            $item['price'] = $item['price'] * $this->to_idr_rate;
            $item['price'] = intval($item['price']);
          }

          unset($item);

          $params['transaction_details']['gross_amount'] *= $this->to_idr_rate;
        }

        $total_amount=0;

        foreach ($items as $item) {
          $total_amount+=($item['price']*$item['quantity']);
        }

        $params['transaction_details']['gross_amount'] = $total_amount;

        $params['item_details'] = $items;

        // add custom expiry params
        $custom_expiry_params = explode(" ",$this->custom_expiry);
        if ( !empty($custom_expiry_params[1]) && !empty($custom_expiry_params[0]) ){
          $time = time();
          $time += 30; // add 30 seconds to allow margin of error
          $params['expiry'] = array(
            'start_time' => date("Y-m-d H:i:s O",$time), 
            'unit' => $custom_expiry_params[1], 
            'duration'  => (int)$custom_expiry_params[0],
          );
        }
        // add custom fields params
        $custom_fields_params = explode(",",$this->custom_fields);
        if ( !empty($custom_fields_params[0]) ){
          $params['custom_field1'] = $custom_fields_params[0];
          $params['custom_field2'] = !empty($custom_fields_params[1]) ? $custom_fields_params[1] : null;
          $params['custom_field3'] = !empty($custom_fields_params[2]) ? $custom_fields_params[2] : null;
        }
        // add savecard params
        if ($this->enable_savecard =='yes' && is_user_logged_in()){
          $params['user_id'] = crypt( $customer_details['email'].$customer_details['phone'] , \Midtrans\Config::$serverKey );
          $params['credit_card']['save_card'] = true;
        }
        
        // add bank & channel migs params
        if (strlen($this->acquring_bank)>0)
          $params['credit_card']['bank'] = strtoupper ($this->acquring_bank);

        if (strlen($this->bin_number) > 0){
          // add bin params
          $bins = explode(',', $this->bin_number);
          $params['credit_card']['whitelist_bins'] = $bins;
        }

        $woocommerce->cart->empty_cart();
        
        try {
          $snapResponse = \Midtrans\Snap::createTransaction($params);
        } catch (Exception $e) {
          $this->json_print_exception($e);
          exit();
        }
        return $snapResponse;
      }

      function json_print_exception ($e) {
        $errorObj = array(
          'result' => "failure", 
          'messages' => '<div class="woocommerce-error" role="alert"> Midtrans Exception: '.$e->getMessage().'. <br>Plugin Title: '.esc_html($this->method_title).'</div>',
          'refresh' => false, 
          'reload' => false
        );
        $errorJson = json_encode($errorObj);
        echo $errorJson;
      }

      function process_payment( $order_id ) {
        global $woocommerce;
        
        //create the order object
        $order = new WC_Order( $order_id );
        $successResponse = array(
          'result'  => 'success',
          'redirect' => ''
        );

        // if snap token exists, reuse it
        if ($order->meta_exists('_mt_payment_snap_token')){
          $successResponse['redirect'] = $order->get_checkout_payment_url( true )."&snap_token=".$order->get_meta('_mt_payment_snap_token');
          return $successResponse;
        }

        $snapResponse = $this->create_snap_transaction($order_id);
        if(property_exists($this,'enable_redirect') && $this->enable_redirect == 'yes'){
          $redirectUrl = $snapResponse->redirect_url;
        }else{
          $redirectUrl = $order->get_checkout_payment_url( true )."&snap_token=".$snapResponse->token;
        }

        // Add snap token & snap redirect url to $order metadata
        $order->update_meta_data('_mt_payment_snap_token',$snapResponse->token);
        $order->update_meta_data('_mt_payment_url',$snapResponse->redirect_url);
        $order->save();

        if(property_exists($this,'enable_immediate_reduce_stock') && $this->enable_immediate_reduce_stock == 'yes'){
          wc_reduce_stock_levels($order);
        }

        $successResponse['redirect'] = $redirectUrl;
        return $successResponse;
      }

      /**
       * Refund a charge
       * @param  int $order_id
       * @param  float $amount
       * @return bool
       */
      function process_refund($order_id, $amount = null, $reason = '', $duplicated = false){
        require_once(dirname(__FILE__) . '/../lib/midtrans/Midtrans.php');
        require_once(dirname(__FILE__) . '/class.midtrans-utils.php');

        \Midtrans\Config::$isProduction = ($this->environment == 'production') ? true : false;
        \Midtrans\Config::$serverKey = (\Midtrans\Config::$isProduction) ? $this->server_key_v2_production : $this->server_key_v2_sandbox;

        $order = wc_get_order( $order_id );
        $params = array(
            'refund_key' => 'RefundID' . $order_id . '-' . current_time('timestamp'),
            'amount' => $amount,
            'reason' => $reason
        );

        try {
          $response = \Midtrans\Transaction::refund($order_id, $params);
        } catch (Exception $e) {
          $error_message = strpos($e->getMessage(), '412') ? $e->getMessage() . ' Note: Refund via Midtrans only for specific payment method, please consult to your midtrans PIC for more information' : $e->getMessage();
          return new WP_Error( 'midtrans_refund_error', $error_message );
          return false;
        }

        if (is_wp_error($response)) {
            return false;
        } elseif ($response->status_code == 200) {
            $refund_message = sprintf(__('Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-midtrans'), wc_price($response->refund_amount), $response->refund_key, $reason);
            $order->add_order_note($refund_message);
            return true;
        }
      }

      function receipt_page( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
        $gross_amount = $order->get_total();
        $pluginName = 'cc_paymentrequest';
        require_once(dirname(__FILE__) . '/payment-page-paymentrequest.php'); 

      }
    }
