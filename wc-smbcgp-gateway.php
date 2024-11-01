<?php
/**
 * Plugin Name: Payment Gateway SMBCGP for WooCommerce
 * Plugin URI: https://www.wpmarket.jp/product/wc_smbcgp_gateway/
 * Description: Take SMBCGP payments on your store of WooCommerce.
 * Author: Hiroaki Miyashita
 * Author URI: https://www.wpmarket.jp/
 * Version: 0.1
 * Requires at least: 4.4
 * Tested up to: 6.4.2
 * WC requires at least: 3.0
 * WC tested up to: 8.4.0
 * Text Domain: wc-smbcgp-gateway
 * Domain Path: /
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wcpg_smbcgp_gateway_missing_admin_notices() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'SMBCGP requires WooCommerce to be installed and active. You can download %s here.', 'wc-smbcgp-gateway' ), esc_html( '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) ) . '</strong></p></div>';
}

function wcpg_smbcgp_gateway_mode_admin_notices() {
	$url = sanitize_text_field( $_SERVER['HTTP_HOST'] );
	echo '<div class="error"><p><strong><a href="https://www.wpmarket.jp/product/wc_smbcgp_gateway/?domain=' . esc_attr( $url ) . '" target="_blank">' . esc_html__( 'In order to use SMBCGP, you have to purchase the authentication key at the following site.', 'wc-smbcgp-gateway' ) . '</a></strong></p></div>';
}

add_action( 'plugins_loaded', 'wcpg_smbcgp_gateway_plugins_loaded' );
add_filter( 'woocommerce_payment_gateways', 'wcpg_smbcgp_gateway_woocommerce_payment_gateways' );

function wcpg_smbcgp_gateway_plugins_loaded() {
	load_plugin_textdomain( 'wc-smbcgp-gateway', false, plugin_basename( dirname( __FILE__ ) ) );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wcpg_smbcgp_gateway_missing_admin_notices' );
		return;
	}

	$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
	if ( empty( $smbcgp_option['authentication_key'] ) ) :
		add_action( 'admin_notices', 'wcpg_smbcgp_gateway_mode_admin_notices' );
	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Credit' ) ) :
		class WCPG_Gateway_SMBCGP_Credit extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_credit';
				$this->method_title = __( 'SMBCGP - Credit Card', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the credit card payment by SMBCGP. You can change the other settings here.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->ShopID = $this->get_option( 'ShopID' );
				$this->ShopPass = $this->get_option( 'ShopPass' );
				$this->configid = $this->get_option( 'configid' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->mode = $this->get_option( 'mode' );
				$this->status = $this->get_option( 'status' );
				$this->logging = $this->get_option( 'logging' );
				$this->authentication_key = $this->get_option( 'authentication_key' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
				add_action( 'woocommerce_api_wc_smbcgp', array( $this, 'check_for_webhook' ) );
			}

			public function init_form_fields() {
				$url = sanitize_text_field( $_SERVER['HTTP_HOST'] );
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - Credit Card', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Credit Card', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with your credit card', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'ShopID' => array(
						'title'       => __( 'Shop ID', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'default'     => '',
					),
					'ShopPass' => array(
						'title'       => __( 'Shop Password', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'default'     => '',
					),
					'configid' => array(
						'title'       => __( 'Config ID', 'wc-smbcgp-gateway' ),
						'description' => __( 'Please input the Config ID of the Link Type Plus on the SMBCGP admin panel.', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'default'     => '',
					),
					'mode'    => array(
						'title' => __( 'Mode', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'real' => __( 'Real', 'wc-smbcgp-gateway' ),
							'sandbox'  => __( 'Sandbox', 'wc-smbcgp-gateway' ),
						),
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
					'logging'    => array(
						'title'       => __( 'Logging', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Log debug messages', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'wc-smbcgp-gateway' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					'authentication_key'    => array(
						'title' => __( 'Authentication Key', 'wc-smbcgp-gateway' ),
						'type' => 'text',
						'default' => '',
						'description' => '<a href="https://www.wpmarket.jp/product/wc_smbcgp_gateway/?domain=' . esc_attr( $url ) . '" target="_blank">' . esc_html__( 'In order to use SMBCGP, you have to purchase the authentication key at the following site.', 'wc-smbcgp-gateway' ) . '</a>',
					),
				);
			}

			public function process_admin_options() {
				$this->init_settings();

				$post_data = $this->get_post_data();

				$check_value = $this->wcpg_smbcgp_gateway_check_authentication_key( $post_data['woocommerce_smbcgp_credit_authentication_key'] );
				if ( false == $check_value ) :
					$_POST['woocommerce_smbcgp_credit_authentication_key'] = '';
				endif;

				if ( 'real' == $post_data['woocommerce_smbcgp_credit_mode'] && false == $check_value ) :
					$_POST['woocommerce_smbcgp_credit_mode'] = 'sandbox';

					$settings = new WC_Admin_Settings();
					$settings->add_error( __( 'Because Authentication Key is not valid, you can not set Real as the mode.', 'wc-smbcgp-gateway' ) );
				endif;

				return parent::process_admin_options();
			}

			public function wcpg_smbcgp_gateway_check_authentication_key( $auth_key ) {
				$url = sanitize_text_field( $_SERVER['HTTP_HOST'] );
				$request = wp_remote_get( 'https://www.wpmarket.jp/auth/?gateway=smbcgp&domain=' . $url . '&auth_key=' . $auth_key );
				if ( ! is_wp_error( $request ) && 200 == $request['response']['code'] ) :
					if ( 1 == $request['body'] ) :
						return true;
					else :
						return false;
					endif;
				else :
					return false;
				endif;
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $this->get_option( 'ShopID' );
				$this->ShopPass = $this->get_option( 'ShopPass' );
				$this->configid = $this->get_option( 'configid' );
				$this->mode = $this->get_option( 'mode' );
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'credit' );
				if ( ! empty( $data['customer_id'] ) ) :
					$param['credit']['MemberID'] = $data['customer_id'];
				endif;

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['credit']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_credit' !== $order->get_payment_method() ) :
					return;
				endif;

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );

					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}

			public function check_for_webhook() {
				$post_data = $this->get_post_data();

				echo '0';
				wcpg_smbcgp_gateway_logging( $post_data, $this->logging );
				if ( empty( $post_data['ShopID'] ) || empty( $this->ShopID ) || $post_data['ShopID'] != $this->ShopID || empty( $post_data['Status'] ) ) :
					exit;
				endif;

				if ( 'CAPTURE' == $post_data['Status'] || 'AUTH' == $post_data['Status'] || 'CHECK' == $post_data['Status'] || 'REQSUCCESS' == $post_data['Status'] || 'PAYSUCCESS' == $post_data['Status'] || 'EXPIRED' == $post_data['Status'] || 'CANCEL' == $post_data['Status'] || 'PAYFAIL' == $post_data['Status'] ) :
					$order = new WC_Order( str_replace( 'wcsmbcgp', '', $post_data['OrderID'] ) );

					if ( ! empty( $order ) ) :
						switch ( $order->get_payment_method() ) :
							case 'smbcgp_credit':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( isset( $post_data['Amount'] ) && $post_data['Amount'] == $order->get_total() ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['TranID'] ) );
								else :
									$order->update_status( 'failed', sprintf( __( 'Total amount does not match (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['TranID'] ) );
								endif;
								break;
							case 'smbcgp_cvs':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'REQSUCCESS' == $post_data['Status'] ) :
									$order->update_status( 'on-hold', sprintf( __( 'SMBCGP settlement displayed (Transaction No: %s, Detail: %s).', 'wc-smbcgp-gateway' ), $post_data['TranID'], $post_data['CvsCode'] . ' ' . $post_data['CvsConfNo'] . ' ' . $post_data['CvsReceiptNo'] ) );
								elseif ( 'PAYSUCCESS' == $post_data['Status'] ) :
									$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
								elseif ( 'EXPIRED' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement expired.', 'wc-smbcgp-gateway' ) );
								elseif ( 'CANCEL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_payeasy':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'REQSUCCESS' == $post_data['Status'] ) :
									$order->update_status( 'on-hold', sprintf( __( 'SMBCGP settlement displayed (Transaction No: %s, Detail: %s).', 'wc-smbcgp-gateway' ), $post_data['TranID'], $post_data['CustID'] . ' ' . $post_data['BkCode'] . ' ' . $post_data['ConfNo'] ) );
								elseif ( 'PAYSUCCESS' == $post_data['Status'] ) :
									$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
								elseif ( 'EXPIRED' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement expired.', 'wc-smbcgp-gateway' ) );
								elseif ( 'CANCEL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_docomo':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['DocomoSettlementCode'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] || 'UNPROCESSED' == $post_data['Status'] || 'AUTHPROCESS' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_au':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['AuPayInfoNo'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] || 'AUTHPROCESS' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_sb':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['SbTrackingId'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_epospay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['EposTradeId'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] || 'AUTHPROCESS' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_dcc':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['DccFtn'] ) );
								elseif ( 'UNPROSESSED' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_linepay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['TranID'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								elseif ( 'PAYCANCEL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_famipay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'PAYSUCCESS' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['UriageNO'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_merpay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'REQSUCCESS' == $post_data['Status'] ) :
									$order->update_status( 'on-hold', sprintf( __( 'SMBCGP settlement displayed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['MerpayInquiryCode'] ) );
								elseif ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['DocomoSettlementCode'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_rakutenpayv2':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['RakutenChargeID'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_paypay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'SALES' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['PayPayTrackingID'] ) );
								elseif ( 'CANCEL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
							case 'smbcgp_aupay':
								if ( empty( $this->status ) ) :
									$this->status = 'processing';
								endif;
								if ( 'AUTH' == $post_data['Status'] || 'CAPTURE' == $post_data['Status'] ) :
									$order->update_status( $this->status, sprintf( __( 'SMBCGP settlement completed (Transaction No: %s).', 'wc-smbcgp-gateway' ), $post_data['DocomoSettlementCode'] ) );
								elseif ( 'PAYFAIL' == $post_data['Status'] ) :
									$order->update_status( 'failed', __( 'SMBCGP settlement failed.', 'wc-smbcgp-gateway' ) );
								endif;
								break;
						endswitch;
					endif;
				endif;

				exit;
			}
		}
	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Cvs' ) ) :

		class WCPG_Gateway_SMBCGP_Cvs extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_cvs';
				$this->method_title = __( 'SMBCGP - CVS', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the CVS payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - CVS', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'CVS', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with CVS', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'cvs' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_cvs' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( 'on-hold', __( 'SMBCGP settlement requested.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Payeasy' ) ) :

		class WCPG_Gateway_SMBCGP_Payeasy extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_payeasy';
				$this->method_title = __( 'SMBCGP - Pay-easy', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the Pay-easy payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - Pay-easy', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay-easy', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with Pay-easy', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'payeasy' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_payeasy' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					else :
						$order->update_status( 'on-hold', __( 'SMBCGP settlement requested.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Docomo' ) ) :

		class WCPG_Gateway_SMBCGP_Docomo extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_docomo';
				$this->method_title = __( 'SMBCGP - Docomo', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the Docomo payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - Docomo', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Docomo', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with Docomo', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'docomo' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['docomo']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_docomo' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Au' ) ) :

		class WCPG_Gateway_SMBCGP_Au extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_au';
				$this->method_title = __( 'SMBCGP - AU', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the AU payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - AU', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'AU', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with AU', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'au' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['au']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_au' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Sb' ) ) :

		class WCPG_Gateway_SMBCGP_Sb extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_sb';
				$this->method_title = __( 'SMBCGP - SoftBank', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the SoftBank payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - SoftBank', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'SoftBank', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with SoftBank', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'sb' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['sb']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_sb' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Dcc' ) ) :

		class WCPG_Gateway_SMBCGP_Dcc extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_dcc';
				$this->method_title = __( 'SMBCGP - DCC', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the DCC payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - DCC', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'DCC', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with DCC', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'dcc' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_dcc' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Famipay' ) ) :

		class WCPG_Gateway_SMBCGP_Famipay extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_famipay';
				$this->method_title = __( 'SMBCGP - FamiPay', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the FamiPay payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - FamiPay', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'FamiPay', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with FamiPay', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'famipay' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_famipay' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Rakutenpayv2' ) ) :

		class WCPG_Gateway_SMBCGP_Rakutenpayv2 extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_rakutenpayv2';
				$this->method_title = __( 'SMBCGP - Rakuten Pay V2', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the Merpay payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - Rakuten Pay V2', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Rakuten Pay V2', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with Rakuten Pay V2', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'rakutenpayv2' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['rakutenpayv2']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_rakutenpayv2' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Paypay' ) ) :

		class WCPG_Gateway_SMBCGP_Paypay extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_paypay';
				$this->method_title = __( 'SMBCGP - PayPay', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the PayPay payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - PayPay', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'PayPay', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with PayPay', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'paypay' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_paypay' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

	if ( ! class_exists( 'WCPG_Gateway_SMBCGP_Aupay' ) ) :

		class WCPG_Gateway_SMBCGP_Aupay extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'smbcgp_aupay';
				$this->method_title = __( 'SMBCGP - au PAY', 'wc-smbcgp-gateway' );
				$this->method_description = __( 'Enable the au PAY payment by SMBCGP.', 'wc-smbcgp-gateway' );
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wcpg_smbcgp_gateway_woocommerce_thankyou' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-smbcgp-gateway' ),
						'label'       => __( 'Enable SMBCGP - au PAY', 'wc-smbcgp-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'au PAY', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-smbcgp-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-smbcgp-gateway' ),
						'default'     => __( 'Pay with au PAY', 'wc-smbcgp-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __( 'Authorization', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __( 'Capture', 'wc-smbcgp-gateway' ),
							'AUTH'  => __( 'Authorize', 'wc-smbcgp-gateway' ),
						),
					),
					'status'    => array(
						'title' => __( 'Status', 'wc-smbcgp-gateway' ),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __( 'Processing', 'wc-smbcgp-gateway' ),
							'completed' => __( 'Completed', 'wc-smbcgp-gateway' ),
						),
					),
				);
			}

			public function process_payment( $order_id ) {
				global $woocommerce, $current_user;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );

				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();

				$this->ShopID = $smbcgp_option['ShopID'];
				$this->ShopPass = $smbcgp_option['ShopPass'];
				$this->configid = $smbcgp_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $smbcgp_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );

				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();

				if ( 'real' == $this->mode ) :
					$url = 'https://p01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.smbc-gp.co.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;

				$param['transaction']['OrderID'] = 'wcsmbcgp' . $order_id;
				$param['transaction']['Amount'] = $order->get_total();
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );

				$param['transaction']['PayMethods'] = array( 'aupay' );

				if ( ! empty( $billing_email ) ) :
					$param['customer']['MailAddress'] = $billing_email;
				endif;
				if ( ! empty( $billing_phone ) ) :
					$param['customer']['TelNo'] = $billing_phone;
				endif;
				if ( ! empty( $billing_last_name ) && ! empty( $billing_first_name ) ) :
					$param['customer']['CustomerName'] = $billing_last_name . $billing_first_name;
				endif;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['aupay']['JobCd'] = $this->JobCd;

				$param_json = json_encode( $param );

				$args = array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => $param_json,
				);

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( 200 != $response_code ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wcpg_smbcgp_gateway_logging( $param, $this->logging );
					wcpg_smbcgp_gateway_logging( $param_json, $this->logging );
					wcpg_smbcgp_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;

				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl'],
				);
			}

			public function wcpg_smbcgp_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'smbcgp_aupay' !== $order->get_payment_method() ) :
					return;
				endif;

				$smbcgp_option = get_option( 'woocommerce_smbcgp_credit_settings' );
				$this->logging = $smbcgp_option['logging'];

				$post_data = $this->get_post_data();

				if ( ! empty( $post_data['result'] ) ) :
					if ( empty( $this->status ) ) :
						$this->status = 'processing';
					endif;
					list( $base64, $hash ) = explode( '.', $post_data['result'] );
					$json = wcpg_smbcgp_gateway_base64_url_decode( $base64 );
					$result = json_decode( $json, true );
					wcpg_smbcgp_gateway_logging( $result, $this->logging );
					if ( empty( $result ) || 'PAYSTART' == $result['transactionresult']['Result'] ) :
						$order->update_status( 'failed', __( 'SMBCGP settlement canceled.', 'wc-smbcgp-gateway' ) );
						wp_redirect( wc_get_checkout_url() );
						exit;
					elseif ( ! empty( $result['transactionresult']['ErrCode'] ) && ! empty( $result['transactionresult']['ErrInfo'] ) ) :
						$order->update_status( 'failed', 'ErrCode=' . esc_attr( $result['transactionresult']['ErrCode'] ) . "\n" . 'ErrInfo=' . esc_attr( $result['transactionresult']['ErrInfo'] ) );
					else :
						$order->update_status( $this->status, __( 'SMBCGP settlement completed.', 'wc-smbcgp-gateway' ) );
					endif;
				endif;
			}
		}

	endif;

}


function wcpg_smbcgp_gateway_logging( $error, $logging = false ) {
	if ( ! empty( $logging ) ) :
		$logger = wc_get_logger();
		$logger->debug( wc_print_r( $error, true ), array( 'source' => 'wc-smbcgp-gateway' ) );
	endif;
}

function wcpg_smbcgp_gateway_base64_url_encode( $input ) {
	return strtr( base64_encode( $input ), '+/=', '-._' );
}

function wcpg_smbcgp_gateway_base64_url_decode( $input ) {
	return base64_decode( strtr( $input, '-._', '+/=' ) );
}

function wcpg_smbcgp_gateway_woocommerce_payment_gateways( $methods ) {
	$methods[] = 'WCPG_Gateway_SMBCGP_Credit';
	$methods[] = 'WCPG_Gateway_SMBCGP_Cvs';
	$methods[] = 'WCPG_Gateway_SMBCGP_Payeasy';
	$methods[] = 'WCPG_Gateway_SMBCGP_Docomo';
	$methods[] = 'WCPG_Gateway_SMBCGP_Au';
	$methods[] = 'WCPG_Gateway_SMBCGP_Sb';
	$methods[] = 'WCPG_Gateway_SMBCGP_Dcc';
	$methods[] = 'WCPG_Gateway_SMBCGP_FamiPay';
	$methods[] = 'WCPG_Gateway_SMBCGP_Rakutenpayv2';
	$methods[] = 'WCPG_Gateway_SMBCGP_Paypay';
	$methods[] = 'WCPG_Gateway_SMBCGP_Aupay';
	return $methods;
}
