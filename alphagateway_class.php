<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Test Gateway.
 *
 * Provides a test Payment Gateway.
 *
 * @class       WC_Gateway_Alpha
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Antreas Gribas
 */
class WC_Gateway_Alpha extends WC_Payment_Gateway {
	

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
		//$icon = WC_HTTPS::force_https_url( WC()->plugin_url() . '/includes/gateways/paypal/assets/images/paypal.png' );
        $this->id                 = 'alpha';
        $this->icon               = apply_filters( 'woocommerce_cod_icon', '' );
        $this->method_title       = __( 'Alpha Bank', 'woocommerce' );
        $this->method_description = __( 'Alpha bank web payment system.', 'woocommerce' );
        $this->has_fields         = false;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->instructions       = $this->get_option( 'instructions', $this->description );
		$this->MerchantId = $this->get_option('MerchantId');
		$this->Secret = $this->get_option('Secret');
		
		$this->AlphaBankUrl = $this->get_option('testmode') === 'yes' ? "https://alpha.test.modirum.com/vpos/shophandlermpi" : "https://www.alphaecommerce.gr/vpos/shophandlermpi";
		
		$this->InstallmentsActive = $this->get_option('installmentsActive') === 'yes' ? true : false;
		
		$this->autosubmitPaymentForm = $this->get_option('autosubmitPaymentForm') === 'yes' ? true : false;

        // Customer Emails
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		
		//Actions
		add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_alpha', array( $this, 'thankyou_page' ) );
		// Payment listener/API hook
		add_action('woocommerce_api_wc_gateway_alpha', array($this, 'check_response'));

		// Set the installments array
		$this->installmentsArray = Array(100 => 4, 200 => 8, 300 => 12);
    }
	
		/**
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( ! $this->MerchantId || ! $this->Secret ) {
			return false;
		}

		return true;
	}
    
	 /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
    	$shipping_methods = array();

    	if ( is_admin() )
	    	foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
		    	$shipping_methods[ $method->id ] = $method->get_title();
	    	}

    	$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable Alpha Bank', 'woocommerce' ),
				'label'       => __( 'Enabled', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Alpha Bank', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
				'default'     => __( 'Πληρωμή μέσω Alpha Bank', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
				'default'     => __( 'Πληρωμή μέσω Alpha Bank', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'testmode' => array(
				'title'       => __( 'Test mode', 'woocommerce' ),
				'label'       => __( 'Enable test mode', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'uncheck this to disable test mode',
				'default'     => 'yes'
			),
			'MerchantId' => array(
                    'title' => __('Alpha Bank Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter Your Alpha Bank Merchant ID', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true
			),
			'Secret' => array(
                    'title' => __('Alpha Bank Secret Code', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter Your Alpha Bank Secret Code', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true
			),
			'installmentsActive' => array(
                    'title' => __('Enable installments?', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => __('Check this to enable installments', 'woocommerce'),
                    'default' => 'no'
			),
			'autosubmitPaymentForm' => array(
				'title'       => __( 'Auto-submit payment form', 'woocommerce' ),
				'label'       => __( 'Enable', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'If you check this, buyers will be re-directed to the payment gateway automatically. ',
				'default'     => 'no'
			)
 	   );
    }
	
	
	protected function get_alpha_args( $order, $uniqid, $installments ) {
		// WC_Gateway_Paypal::log( 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );
		$return = WC()->api_request_url( 'WC_Gateway_Alpha' );
		$address = array(
				'address_1'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_1() : $order->billing_address_1,
                'address_2'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_2() : $order->billing_address_2,
                'city'          => ( WC()->version >= '3.0.0' ) ? $order->get_billing_city() : $order->billing_city,
                'state'         => ( WC()->version >= '3.0.0' ) ? $order->get_billing_state() : $order->billing_state,
                'postcode'      => ( WC()->version >= '3.0.0' ) ? $order->get_billing_postcode() : $order->billing_postcode,
                'country'       => ( WC()->version >= '3.0.0' ) ? $order->get_billing_country() : $order->billing_country
				);
		
		$lang = 'el';
		if (substr(get_locale(), 0, 2) == 'en') {
			$lang = 'en';
		}
		
		$args = array(
			'mid'         => $this->MerchantId,
			'lang'        => $lang,
			'orderid'     => $uniqid . 'AlphaBankOrder' .  ( ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id ),
			'orderDesc'   => 'Name: ' . $order->get_formatted_billing_full_name() . ' Address: ' . implode(",", $address) ,
			'orderAmount' => wc_format_decimal($order->get_total(), 2, false),
			'currency'    => 'EUR',
			'payerEmail'  => ( WC()->version >= '3.0.0' ) ? $order->get_billing_email() : $order->billing_email,
			'billCountry' => $address['country'],
			'billState'   => $address['state'],
			'billZip'     => $address['postcode'],
			'billCity'    => $address['city'],
			'billAddress' => $address['address_1']
		);
		
		if ($installments > 0) {
			$args['extInstallmentoffset'] = 0;
			$args['extInstallmentperiod'] = $installments;
		};
		
		$args = array_merge($args, array(
			'confirmUrl' => add_query_arg( 'confirm', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return),
			'cancelUrl'  => add_query_arg( 'cancel', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return), 
		));
				
		return apply_filters( 'woocommerce_alpha_args', $args , $order );
	}
	
	/**
	* Output for the order received page.
	* */
	public function receipt_page($order_id) {
		echo '<p>' . __('Thank you - your order is now pending payment. Please click the button below to proceed.', 'woocommerce') . '</p>';
		$order = wc_get_order( $order_id );
		$uniqid = uniqid();
						
		$form_data = $this->get_alpha_args($order, $uniqid, 0);
		$digest = base64_encode(sha1(implode("", array_merge($form_data, array('secret' => $this->Secret))), true));

		$html_form_fields = array();
		foreach ($form_data as $key => $value) {
			$html_form_fields[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr($value).'" />';
		}
		
		?>

		<?php if ( $this->autosubmitPaymentForm ) :?>

		<script type="text/javascript">

		jQuery(document).ready(function(){
  
		    var alphabank_payment_form = document.getElementById('shopform1');
			alphabank_payment_form.style.visibility="hidden";
			alphabank_payment_form.submit();

		});

		<?php endif;?>

		</script>
				<form id="shopform1" name="shopform1" method="POST" action="<?php echo $this->AlphaBankUrl ?>" accept-charset="UTF-8" >
			<?php foreach($html_form_fields as $field)
				echo $field;
			?>
			<input type="hidden" name="digest" value="<?php echo $digest ?>"/>
			
			<?php	
				if ($this->InstallmentsActive) {
					$this->installments(wc_format_decimal($order->get_total(), 2, false), $uniqid, $order); 
				}
			?>
			
			<input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="<?php echo __( 'Pay via Alpha bank', 'woocommerce' ) ?>" /> 
			<a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() )?>"><?php echo __( 'Cancel order &amp; restore cart', 'woocommerce' )?></a>
			
		</form>		
		<?php
		
		
		$order->update_status( 'pending', __( 'Sent request to Alpha bank with orderID: ' . $form_data['orderid'] , 'woocommerce' ) );
	}
    
    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		 return array(
		 	'result' 	=> 'success',
		 	'redirect'	=> $order->get_checkout_payment_url( true ) // $this->get_return_url( $order )
		);
	}
	
	/**
		* Verify a successful Payment!
	* */
	public function check_response() { 
		$required_response = array(
			'mid' => '',
			'orderid' => '',
			'status' => '',
			'orderAmount' => '',
			'currency' => '',
			'paymentTotal' => ''
		);
		
		$notrequired_response = array(
			'message' => '',
			'riskScore' => '',
			'payMethod' => '',
			'txId' => '',
			'sequence' => '',
			'seqTxId' => '',
			'paymentRef' => '' 
		);
		
		if (!isset($_REQUEST['digest'])){
			wp_die( 'Alpha Bank Request Failure', 'Alpha Bank Gateway', array( 'response' => 500 ) );
		}
		
		foreach ($required_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
				// required parameter not set 
				wp_die( 'Alpha Bank Request Failure', 'Alpha Bank Gateway', array( 'response' => 500 ) );
			}
		}
		
		foreach ($notrequired_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
			}
		}

		$string_form_data = array_merge($required_response, array('secret' => $this->Secret));
		$digest = base64_encode(sha1(implode("", $string_form_data), true));
		
		if ($digest != $_REQUEST['digest']){
			wp_die( 'Alpha Bank Digest Error', 'Alpha Bank Gateway', array( 'response' => 500 ) );
		}
		
		if(isset($_REQUEST['cancel'])){
			$order = wc_get_order(wc_clean($_REQUEST['cancel']));
			if (isset($order)){
				$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. ' . $required_response['message'] );
				wp_redirect( $order->get_cancel_order_url_raw());
				exit();
			}
		}
		else if (isset($_REQUEST['confirm'])){
			$order = wc_get_order(wc_clean($_REQUEST['confirm']));
			if (isset($order)){
				if ($required_response['orderAmount'] == wc_format_decimal($order->get_total(), 2, false)){
					$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. payMethod: ' . $required_response['payMethod']. '. paymentRef: ' . $required_response['paymentRef'] . '. ' . $required_response['message'] );
					$order->payment_complete('Alpha Bank Payment ' . $required_response['status'] . '. txId: ' . $required_response['txId'] );
					wp_redirect($this->get_return_url( $order ));
					exit();
				}
				else{
					$order->add_order_note('Payment received with incorrect amount. Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. '. $required_response['message'] );
				}
			}
		}
		
		// something went wrong so die
		wp_die( 'Unspecified Error', 'Payment Gateway error', array( 'response' => 500 ) );
	}

    /**
     * Output for the order received page.
     */
	public function thankyou_page() {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) );
		}
	}

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}

	private function installments($price, $uniqid, $order) {
		$installments = 0;

		foreach($this->installmentsArray as $priceRange => $numOfInstallments){
			if ($price > $priceRange) {
				continue;
			}
			else{
				$installments = $numOfInstallments;
				break;
			}
		}

		$installMentsField = '';
		if ($installments > 0 && is_int($installments)) {
			$installMentsField = '<select name="extInstallmentperiod">';
			
			for ($i = 0; $i <= $installments; $i++) {
				
				$form_data = $this->get_alpha_args($order, $uniqid, $i);
				$digest = base64_encode(sha1(implode("", array_merge($form_data, array('secret' => $this->Secret))), true));

				$installMentsField .= '<option value="' . $i . '" data-digest="' . $digest . '">' . $i . '</option>';
			}

			$installMentsField .= '</select>';
			
			$installMentsField .= "<input type='hidden' value='0' name='extInstallmentoffset' />";

			echo 	'<fieldset class="wc-payment-form">
						<p class="form-row form-row-wide">
							<label for="extInstallmentperiod">' . __( 'Άτοκες Δόσεις ', 'woocommerce' ) . ' </label>
							' . $installMentsField   
						. '</p>
						<div class="clear"></div>
					</fieldset>';

			wc_enqueue_js('
				var max = ' . $installments . ';
				jQuery("#shopform1").submit(function (e) {
					var i = parseInt(this.extInstallmentperiod.value);

					if (isNaN(i) || i <= 0 || i > max){
						$(this.extInstallmentperiod).attr("disabled", "disabled");
						$(this.extInstallmentoffset).attr("disabled", "disabled");
					}
					
					this.digest.value = $(this.extInstallmentperiod).find(":selected").data("digest");
				});
			');
			
		}
	}
}