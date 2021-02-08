<?php
/*
 * Plugin Name: WooCommerce Netpay Payment Gateway
 * Plugin URI: https://mynetpay.be/woocommerce/
 * Description: Accept Netpay payments on your store.
 * Author: Xafax Belgium, Guy Verschuere
 * Author URI: https://www.xafax.be
 * Text Domain: netpay-payments-for-woocommerce
 * Version: 1.0
 * Requires at least: 5.3
 * Requires PHP: 7.0
 *
 */

if (in_array('woocommerce/woocommerce.php', get_option('active_plugins'))) {
	add_filter( 'woocommerce_payment_gateways', 'netpay_add_gateway_class');
	function netpay_add_gateway_class($gateways) {
		$gateways[]='WC_Netpay_Gateway';
		return $gateways;
	}
	add_action('plugins_loaded', 'netpay_init_gateway_class');
}

function netpay_init_gateway_class() {
	class WC_Netpay_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id='netpay';
			$this->icon='https://mynetpay.be/favicon.ico';
			$this->has_fields=true;
			$this->method_title='Netpay Gateway';
			$this->method_description='Accept Netpay payments on your store.';
			$this->supports=array('products');
			$this->init_form_fields();
			$this->init_settings();
			$this->title=$this->get_option('title');
			$this->description=$this->get_option('description');
			$this->enabled=$this->get_option('enabled');
			add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ));
		}
		public function init_form_fields(){
			$this->form_fields=array(
				'enabled'=>array(
					'title'=>'Enable/Disable',
					'label'=>'Enable Netpay Gateway',
					'type'=>'checkbox',
					'description'=>'',
					'default'=>'no'
				),
				'title'=>array(
					'title'=>'Title',
					'type'=>'text',
					'description'=>'This controls the title which the user sees during checkout.',
					'default'=>'Netpay Credit',
					'desc_tip'=>false,
				),
				'description'=>array(
					'title'=>'Description',
					'type'=>'text',
					'description'=>'This controls the description which the user sees during checkout.',
					'default'=>'Pay with your credit card via our super-cool payment gateway.',
				),
				'comment'=>array(
					'title'=>'Comment',
					'type'=>'text',
					'description'=>'This controls the comment sent with the Netpay transaction.',
					'default'=>'WooCommerce orderid ',
				),
				'add_order_note'=>array(
					'title'=>'Add order note',
					'type'=>'text',
					'description'=>'This note is added to the order after payment. Leave empty to disable.',
					'default'=>'Hey, your order is paid! Thank you!',
				),
				'authentication'=>array(
					'title'=>'Authentication',
					'label'=>'Authentication',
					'type'=>'select',
					'description'=>'',
					'options'=>array(
						'userpassword'=>'Username/Password',
						'cardid'=>'CardID'
					),
					'default'=>'Username/Password',
					'desc_tip'=>false,
				),
				'paymentmethod'=>array(
					'title'=>'Paymentmethod',
					'label'=>'Payment method',
					'type'=>'select',
					'description'=>'',
					'options'=>array(
						'writebalance'=>'Write balance - Only total amount',
						'recordplu'=>'Record PLU - With details about purchased items'
					),
					'default'=>'Username/Password',
					'desc_tip'=>false,
				),
				'apikey'=>array(
					'title'=>'Netpay API key',
					'type'=>'text',
					'default'=>'mVkCKbG9he65P2yE8byhG7Q5QdkSb2zJ5HXm2RoLUjpEe6Pr96uo2MvHrEGahQic78ZxWg6W8ZjeCAkSmBy6d2Pby2nRtrfUruGa'
				)
			);
		}
		public function payment_fields() {
			if ($this->description) echo wpautop(wp_kses_post($this->description));
			echo '<fieldset id="wc-'.esc_attr( $this->id ).'-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
			do_action( 'woocommerce_credit_card_form_start', $this->id);
			if ($this->settings['authentication']=='userpassword') echo '<div class="form-row form-row-wide"><label>Username <span class="required">*</span></label>
				<input name="netpay_username" id="netpay_username" type="text" autocomplete="off">
				</div>
				<div class="form-row form-row-wide">
					<label>Password <span class="required">*</span></label>
					<input name="netpay_password" id="netpay_password" type="password" autocomplete="off" placeholder="">
				</div>
				<div class="clear"></div>';
			elseif ($this->settings['authentication']=='cardid') echo '<div class="form-row form-row-wide"><label>CardID <span class="required">*</span></label>
				<input name="netpay_cardid" id="netpay_cardid" type="text" autocomplete="off">
				</div>
				<div class="clear"></div>';
			do_action( 'woocommerce_credit_card_form_end', $this->id);
			echo '<div class="clear"></div></fieldset>';
		}
		public function validate_fields(){
			if ($this->settings['authentication']=='userpassword') {
				if( empty( $_POST[ 'netpay_username' ]) ) {
					wc_add_notice(  'Netpay username is required!', 'error');
					return false;
				}
				if( empty( $_POST[ 'netpay_password' ]) ) {
					wc_add_notice(  'Netpay password is required!', 'error');
					return false;
				}
			} elseif ($this->settings['authentication']=='cardid') {
				if( empty( $_POST[ 'netpay_cardid' ]) ) {
					wc_add_notice(  'Netpay CardID is required!', 'error');
					return false;
				}
			}
			return true;
		}
		public function process_payment( $order_id ) {
			global $woocommerce;
			$order=wc_get_order( $order_id);
			$orderdata=json_decode($order, true);
			$body=array(
				'apikey'=>$this->settings['apikey'],
				'orderid'=>$orderdata['id'],
			);
			if ($this->settings['authentication']=='userpassword') {
				$body['username']=$_POST[ 'netpay_username' ];
				$body['password']=$_POST[ 'netpay_password' ];
			} elseif ($this->settings['authentication']=='cardid') {
				$body['cardid']=$_POST[ 'netpay_cardid' ];
			}
			if ($this->settings['paymentmethod']=='writebalance') {
				$body['comment']=$this->settings['comment'];
				$body['amount']=$orderdata['total'];
			} elseif ($this->settings['paymentmethod']=='recordplu') {
				$order_items=$order->get_items( array('line_item', 'fee', 'shipping') );
				foreach ( $order->get_items() as $item_id => $item ) {
					$i['product_id']=$item->get_product_id();
					//$i['variation_id']=$item->get_variation_id();
					$i['product']=$item->get_product();
					$i['name']=$item->get_name();
					$i['quantity']=$item->get_quantity();
					$i['subtotal']=$item->get_subtotal();
					//$i['total']=$item->get_total();
					$i['tax']=$item->get_subtotal_tax();
					//$i['taxclass']=$item->get_tax_class();
					//$i['taxstat']=$item->get_tax_status();
					//$i['allmeta']=$item->get_meta_data();
					//$i['type']=$item->get_type();
					$items[]=$i;
				}
				$body['items']=$items;
			}
			$args=array(
				'method'=>'POST',
				'timeout'=>30,
				'redirection'=>5,
				'httpversion'=>'1.0',
				'blocking'=>true,
				'headers'=>array(),
				'body'=>$body,
				'cookies'=>array()
			);
			$response=wp_remote_post('https://mynetpay.be/woocommerce/payment.php', $args);
			if( !is_wp_error( $response ) ) {
				$body=json_decode( $response['body'], true);
				if ($body['ResultMessage']=='OK') {
					$order->payment_complete();
					wc_reduce_stock_levels($order_id);
					if (strlen($this->settings['add_order_note'])>0) $order->add_order_note($this->settings['add_order_note'], true);
					$woocommerce->cart->empty_cart();
					return array(
						'result'=>'success',
						'redirect'=>$this->get_return_url($order)
					);
				} elseif (isset($body['ResultMessage'])) {
					wc_add_notice($body['ResultMessage'], 'error');
					return;
				} else {
					wc_add_notice('Please try again. ', 'error');
					return;
				}
			} else {
				wc_add_notice(  'Connection error.', 'error');
				return;
			}
		}
		public function webhook() {
		}
	}
}

$api_url='https://mynetpay.be/woocommerce/';
$plugin_slug=basename(dirname(__FILE__));
add_filter('pre_set_site_transient_update_plugins', 'check_for_plugin_update');
function check_for_plugin_update($checked_data) {
	global $api_url, $plugin_slug, $wp_version;
//	if (empty($checked_data->checked)) return $checked_data;
	$version=$checked_data->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
	$args=array(
		'slug' => $plugin_slug,
		'version' => $version,
	);
	$url=get_bloginfo('url');
	$request_string=array(
			'body' => array(
				'action' => 'basic_check',
				'request' => serialize($args),
				'url'=>$url,
				'version'=>$version,
				'wp_version'=>$wp_version
			),
			'user-agent' => 'WordPress/'
		);
	$raw_response=wp_remote_post($api_url, $request_string);
	if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200)) $response=unserialize($raw_response['body']);
	if (is_object($response) && !empty($response)) $checked_data->response[$plugin_slug .'/'. $plugin_slug .'.php']=$response;
	return $checked_data;
}
add_filter('plugins_api', 'plugin_api_call', 10, 3);
function plugin_api_call($def, $action, $args) {
	global $plugin_slug, $api_url, $wp_version;
	if (!isset($args->slug) || ($args->slug != $plugin_slug)) return false;
	$plugin_info=get_site_transient('update_plugins');
	$current_version=$plugin_info->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
	$args->version=$current_version;
	$url=get_bloginfo('url');
	$request_string=array(
			'body' => array(
				'action' => $action,
				'request' => serialize($args),
				'url'=>$url,
				'version'=>$current_version,
				'wp_version'=>$wp_version
			),
			'user-agent' => 'WordPress/'
		);
	$request=wp_remote_post($api_url, $request_string);
	if (is_wp_error($request)) {
		$res=new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
	} else {
		$res=unserialize($request['body']);
		if ($res === false) $res=new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
	}
	return $res;
}
?>
