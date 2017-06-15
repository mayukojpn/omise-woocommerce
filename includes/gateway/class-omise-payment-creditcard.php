<?php
defined( 'ABSPATH' ) or die( 'No direct script access allowed.' );

function register_omise_creditcard() {
	require_once dirname( __FILE__ ) . '/class-omise-payment.php';

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	if ( class_exists( 'Omise_Payment_Creditcard' ) ) {
		return;
	}

	class Omise_Payment_Creditcard extends Omise_Payment {
		public function __construct() {
			parent::__construct();

			$this->id                 = 'omise';
			$this->has_fields         = true;
			$this->method_title       = 'Omise Credit Card';
			$this->method_description = 'Accept payment through Credit Card via Omise payment gateway.';

			$this->init_form_fields();
			$this->init_settings();

			$this->title          = $this->get_option( 'title' );
			$this->omise_3ds      = $this->get_option( 'omise_3ds', false ) == 'yes';
			$this->payment_action = $this->get_option( 'payment_action' );

			add_action( 'woocommerce_api_' . $this->id . '_callback', array( $this, 'callback' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'omise_assets' ) );

			/** @deprecated 2.0 */
			add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'callback' ) );
		}

		/**
		 * @see WC_Settings_API::init_form_fields()
		 * @see woocommerce/includes/abstracts/abstract-wc-settings-api.php
		 */
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'omise' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Omise Payment Module.', 'omise' ),
					'default'     => 'no'
				),
				'sandbox' => array(
					'title'       => __( 'Sandbox', 'omise' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enabling sandbox means that all your transactions will be in TEST mode.', 'omise' ),
					'default'     => 'yes'
				),
				'test_public_key' => array(
					'title'       => __( 'Public key for test', 'omise' ),
					'type'        => 'text',
					'description' => __( 'The "Test" mode public key can be found in Omise Dashboard.', 'omise' )
				),
				'test_private_key' => array(
					'title'       => __( 'Secret key for test', 'omise' ),
					'type'        => 'password',
					'description' => __( 'The "Test" mode secret key can be found in Omise Dashboard.', 'omise' )
				),
				'live_public_key' => array(
					'title'       => __( 'Public key for live', 'omise' ),
					'type'        => 'text',
					'description' => __( 'The "Live" mode public key can be found in Omise Dashboard.', 'omise' )
				),
				'live_private_key' => array(
					'title'       => __( 'Secret key for live', 'omise' ),
					'type'        => 'password',
					'description' => __( 'The "Live" mode secret key can be found in Omise Dashboard.', 'omise' )
				),
				'advanced' => array(
					'title'       => __( 'Advance Settings', 'omise' ),
					'type'        => 'title',
					'description' => '',
				),
				'accept_visa' => array(
					'title'       => __( 'Supported card icons', 'omise' ),
					'type'        => 'checkbox',
					'label'       => Omise_Card_Image::get_visa_image(),
					'css'         => Omise_Card_Image::get_css(),
					'default'     => Omise_Card_Image::get_visa_default_display()
				),
				'accept_mastercard' => array(
					'type'        => 'checkbox',
					'label'       => Omise_Card_Image::get_mastercard_image(),
					'css'         => Omise_Card_Image::get_css(),
					'default'     => Omise_Card_Image::get_mastercard_default_display()
				),
				'accept_jcb' => array(
					'type'        => 'checkbox',
					'label'       => Omise_Card_Image::get_jcb_image(),
					'css'         => Omise_Card_Image::get_css(),
					'default'     => Omise_Card_Image::get_jcb_default_display()
				),
				'accept_diners' => array(
					'type'        => 'checkbox',
					'label'       => Omise_Card_Image::get_diners_image(),
					'css'         => Omise_Card_Image::get_css(),
					'default'     => Omise_Card_Image::get_diners_default_display()
				),
				'accept_amex' => array(
					'type'        => 'checkbox',
					'label'       => Omise_Card_Image::get_amex_image(),
					'css'         => Omise_Card_Image::get_css(),
					'default'     => Omise_Card_Image::get_amex_default_display(),
					'description' => __( 'This only controls the icons displayed on the checkout page.<br />It is not related to card processing on Omise payment gateway.', 'omise' )
				),
				'title' => array(
					'title'       => _x( 'Title', 'Label for setting of checkout form title', 'omise' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'omise' ),
					'default'     => _x( 'Omise Payment Gateway', 'Default title at checkout form', 'omise' )
				),
				'payment_action' => array(
					'title'       => __( 'Payment action', 'omise' ),
					'type'        => 'select',
					'description' => __( 'Manual Capture or Capture Automatically', 'omise' ),
					'default'     => 'auto_capture',
					'class'       => 'wc-enhanced-select',
					'options'     => array(
						'auto_capture'   => _x( 'Auto Capture', 'Setting auto capture', 'omise' ),
						'manual_capture' => _x( 'Manual Capture', 'Setting manual capture', 'omise' )
					),
					'desc_tip'    => true
				),
				'omise_3ds' => array(
					'title'       => __( '3-D Secure support', 'omise' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable or disable 3-D Secure for the account. (Japan-based accounts are not eligible for the service.)', 'omise' ),
					'default'     => 'no'
				)
			);
		}

		/**
		 * Settings on Admin page
		 *
		 * @see WC_Settings_API::admin_options()
		 */
		public function admin_options() {
			echo '<h3>' . _x( 'Omise Payment Gateway', 'Header at setting page', 'omise' ) . '</h3>';
			echo '<table class="form-table">';
				$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 * @see WC_Payment_Gateway::payment_fields()
		 * @see woocommerce/includes/abstracts/abstract-wc-payment-gateway.php
		 */
		function payment_fields() {
			if ( is_user_logged_in() ) {
				$viewData['user_logged_in'] = true;

				$current_user      = wp_get_current_user();
				$omise_customer_id = $this->is_test() ? $current_user->test_omise_customer_id : $current_user->live_omise_customer_id;
				if ( ! empty( $omise_customer_id ) ) {
					$viewData['existingCards'] = Omise::get_customer_cards( $this->secret_key(), $omise_customer_id );;
				}
			} else {
				$viewData['user_logged_in'] = false;
			}

			Omise_Util::render_view( 'includes/templates/omise-payment-form.php', $viewData );
		}

		/**
		 * @param  int $order_id
		 *
		 * @see    WC_Payment_Gateway::process_payment( $order_id )
		 * @see    woocommerce/includes/abstracts/abstract-wc-payment-gateway.php
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			if ( ! $order = $this->load_order( $order_id ) ) {
				wc_add_notice( __( 'Order not found: ', 'omise' ) . sprintf( 'cannot find order id %s.', $order_id ), 'error' );
				return;
			}

			$order->add_order_note( __( 'Omise: processing a payment..', 'omise' ) );

			try {
				$token   = isset( $_POST['omise_token'] ) ? wc_clean( $_POST['omise_token'] ) : '';
				$card_id = isset( $_POST['card_id'] ) ? wc_clean( $_POST['card_id'] ) : '';

				if ( empty( $token ) && empty( $card_id ) ) {
					throw new Exception( __( 'Please select a card or enter new payment information.', 'omise' ) );
				}

				$user              = $order->get_user();
				$omise_customer_id = $this->is_test() ? $user->test_omise_customer_id : $user->live_omise_customer_id;

				if ( isset( $_POST['omise_save_customer_card'] ) && empty( $card_id ) ) {
					if ( empty( $token ) ) {
						throw new Exception( __( 'Omise card token is required.', 'omise' ) );
					}

					if ( ! empty( $omise_customer_id ) ) {
						// attach a new card to customer
						$omise_customer = Omise::create_card( $this->secret_key(), $omise_customer_id, $token );

						if ( $omise_customer->object == "error" ) {
							throw new Exception( $omise_customer->message );
						}

						$card_id = $omise_customer->cards->data[$omise_customer->cards->total - 1]->id;
					} else {
						$description   = "WooCommerce customer " . $user->id;
						$customer_data = array(
							"description" => $description,
							"card"        => $token
						);

						$omise_customer = Omise::create_customer( $this->secret_key(), $customer_data );

						if ( $omise_customer->object == "error" ) {
							throw new Exception( $omise_customer->message );
						}

						$omise_customer_id = $omise_customer->id;
						if ( $this->is_test() ) {
							update_user_meta( $user->ID, 'test_omise_customer_id', $omise_customer_id );
						} else {
							update_user_meta( $user->ID, 'live_omise_customer_id', $omise_customer_id );
						}

						if ( 0 == sizeof( $omise_customer->cards->data ) ) {
							throw new Exception( __( 'Something wrong with Omise gateway. No card available for creating a charge.', 'omise' ) );
						}
						$card    = $omise_customer->cards->data [0]; //use the latest card
						$card_id = $card->id;
					}
				}

				$success        = false;
				$order_currency = $order->get_order_currency();
				if ( 'THB' === strtoupper( $order_currency ) )
					$amount = $order->get_total() * 100;
				else
					$amount = $order->get_total();

				$data = array(
					"amount"      => $amount,
					"currency"    => $order_currency,
					"description" => "WooCommerce Order id " . $order_id,
					"return_uri"  => add_query_arg( 'order_id', $order_id, site_url() . "?wc-api=omise_callback" )
				);

				if ( ! empty( $card_id ) && ! empty( $omise_customer_id ) ) {
					// create charge with a specific card of customer
					$data["customer"] = $omise_customer_id;
					$data["card"]     = $card_id;
				} else if ( ! empty( $token ) ) {
					$data["card"] = $token;
				} else {
					throw new Exception ( __( 'Please select a card or enter new payment information.', 'omise' ) );
				}

				// Set capture status (otherwise, use API's default behaviour)
				if ( 'AUTO_CAPTURE' === strtoupper( $this->payment_action ) ) {
					$data['capture'] = true;
				} else if ( 'MANUAL_CAPTURE' === strtoupper( $this->payment_action ) ) {
					$data['capture'] = false;
				}

				$charge = OmiseCharge::create( $data, '', $this->secret_key() );
				if ( ! Omise_Charge::is_charge_object( $charge ) )
					throw new Exception( __( 'Charge was failed, please contact our support', 'omise' ) );

				// Register new post
				$this->register_omise_charge_post( $charge, $order, $order_id );

				if ( Omise_Charge::is_failed( $charge ) )
					throw new Exception( Omise_Charge::get_error_message( $charge ) );

				if ( $this->omise_3ds ) {
					$order->add_order_note( __( 'Processing payment with Omise 3D-Secure', 'omise' ) );
					return array (
						'result'   => 'success',
						'redirect' => $charge['authorize_uri'],
					);
				} else {
					switch ( strtoupper( $this->payment_action ) ) {
						case 'MANUAL_CAPTURE':
							$success = Omise_Charge::is_authorized( $charge );
							if ( $success ) {
								$order->add_order_note( __( 'Authorize with Omise successful', 'omise' ) );
							}

							break;

						case 'AUTO_CAPTURE':
							$success = Omise_Charge::is_paid( $charge );
							if ( $success ) {
								$order->payment_complete();
								$order->add_order_note( __( 'Payment with Omise successful', 'omise' ) );
							}

							break;

						default:
							// Default behaviour is, check if it paid first.
							$success = Omise_Charge::is_paid( $charge );

							// Then, check is authorized after if the first condition is false.
							if ( ! $success )
								$success = Omise_Charge::is_authorized( $charge );

							break;
					}

					if ( ! $success )
						throw new Exception( __( 'This charge cannot authorize or capture, please contact our support.', 'omise' ) );

					// Remove cart
					WC()->cart->empty_cart();
					return array (
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
			} catch( Exception $e ) {
				wc_add_notice( __( 'Payment failed: ', 'omise' ) . $e->getMessage(), 'error' );

				$order->add_order_note( __( 'Omise: payment failed, ', 'omise' ) . $e->getMessage() );

				return;
			}
		}

		public function callback() {
			if ( ! isset( $_GET['order_id'] ) || ! $order = $this->load_order( $_GET['order_id'] ) ) {
				wc_add_notice( __( 'Order not found: ', 'omise' ) . __( 'Your card might be charged already, please contact our support team if you have any questions.', 'omise' ), 'error' );

				header( 'Location: ' . WC()->cart->get_checkout_url() );
				die();
			}

			$order->add_order_note( __( 'Omise: validating a payment result..', 'omise' ) );

			try {
				// Looking for WP_Post object
				$post = Omise_Charge::get_post_charge( $_GET['order_id'] );
				if ( ! $post )
					throw new Exception( __( 'Order id was not found', 'omise' ) );

				// Looking for Omise's charge id
				$charge_id = Omise_Charge::get_charge_id_from_post( $post );
				if ( $charge_id === '' )
					throw new Exception( __( 'Charge id was not found', 'omise' ) );

				// Looking for WC's confirm url
				$confirmed_url = Omise_Charge::get_confirmed_url_from_post( $post );
				if ( $confirmed_url === '' )
					throw new Exception( __( 'Confirm url was not found', 'omise' ) );

				$charge = OmiseCharge::retrieve( $charge_id, '', $this->secret_key() );
				switch ( strtoupper( $this->payment_action ) ) {
					case 'MANUAL_CAPTURE':
						$success = Omise_Charge::is_authorized( $charge );
						if ( $success ) {
							$order->add_order_note( __( 'Authorize with Omise successful', 'omise' ) );
						}

						break;

					case 'AUTO_CAPTURE':
						$success = Omise_Charge::is_paid( $charge );
						if ( $success ) {
							$order->payment_complete();
							$order->add_order_note( __( 'Payment with Omise successful', 'omise' ) );
						}

						break;

					default:
						// Default behaviour is, check if it paid first.
						$success = Omise_Charge::is_paid( $charge );

						// Then, check is authorized after if the first condition is false.
						if ( ! $success )
							$success = Omise_Charge::is_authorized( $charge );

						break;
				}

				if ( ! $success )
					throw new Exception( Omise_Charge::get_error_message( $charge ) );

				// Remove cart
				WC()->cart->empty_cart();
				header( "Location: " . $confirmed_url );
				die();
			} catch ( Exception $e ) {
				wc_add_notice( __( 'Payment failed: ', 'omise' ) . $e->getMessage(), 'error' );

				$order->add_order_note( __( 'Omise: payment failed, ', 'omise' ) . $e->getMessage() );

				header( 'Location: ' . WC()->cart->get_checkout_url() );
				die();
			}

			wp_die( 'Access denied', 'Access Denied', array( 'response' => 401 ) );
			die();
		}

		/**
		 * @param OmiseCharge $charge
		 * @param WC_Order    $order
		 * @param string      $order_id
		 */
		private function register_omise_charge_post( $charge, $order, $order_id ) {
			$post_id = wp_insert_post(
				array(
					'post_title'  => 'Omise Charge Id ' . $charge['id'],
					'post_type'   => 'omise_charge_items',
					'post_status' => 'publish'
				)
			);

			add_post_meta( $post_id, '_omise_charge_id', $charge['id'] );
			add_post_meta( $post_id, '_wc_order_id', $order_id );
			add_post_meta( $post_id, '_wc_confirmed_url', $this->get_return_url( $order ) );
		}

		/**
		 * Get icons of all supported card types
		 *
		 * @see WC_Payment_Gateway::get_icon()
		 */
		public function get_icon() {
			$icon = '';

			// TODO: Refactor 'Omise_Card_Image' class that we don't need to pass
			//       these options to check outside this class.
			$card_icons['accept_amex']       = $this->get_option( 'accept_amex' );
			$card_icons['accept_diners']     = $this->get_option( 'accept_diners' );
			$card_icons['accept_jcb']        = $this->get_option( 'accept_jcb' );
			$card_icons['accept_mastercard'] = $this->get_option( 'accept_mastercard' );
			$card_icons['accept_visa']       = $this->get_option( 'accept_visa' );

			if ( Omise_Card_Image::is_visa_enabled( $card_icons ) ) {
				$icon .= Omise_Card_Image::get_visa_image();
			}

			if ( Omise_Card_Image::is_mastercard_enabled( $card_icons ) ) {
				$icon .= Omise_Card_Image::get_mastercard_image();
			}

			if ( Omise_Card_Image::is_jcb_enabled( $card_icons ) ) {
				$icon .= Omise_Card_Image::get_jcb_image();
			}

			if ( Omise_Card_Image::is_diners_enabled( $card_icons ) ) {
				$icon .= Omise_Card_Image::get_diners_image();
			}

			if ( Omise_Card_Image::is_amex_enabled( $card_icons ) ) {
				$icon .= Omise_Card_Image::get_amex_image();
			}

			return empty( $icon ) ? '' : apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		/**
		 * Register all required javascripts
		 */
		public function omise_assets() {
			if ( ! is_checkout() || ! $this->is_available() ) {
				return;
			}

			wp_enqueue_style( 'omise-css', plugins_url( '../../assets/css/omise-css.css', __FILE__ ), array(), OMISE_WOOCOMMERCE_PLUGIN_VERSION );

			wp_enqueue_script( 'omise-js', 'https://cdn.omise.co/omise.js', array( 'jquery' ), OMISE_WOOCOMMERCE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'omise-util', plugins_url( '../../assets/javascripts/omise-util.js', __FILE__ ), array( 'omise-js' ), OMISE_WOOCOMMERCE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'omise-payment-form-handler', plugins_url( '../../assets/javascripts/omise-payment-form-handler.js', __FILE__ ), array( 'omise-js', 'omise-util' ), OMISE_WOOCOMMERCE_PLUGIN_VERSION, true );

			wp_localize_script( 'omise-payment-form-handler', 'omise_params', array(
				'key'       => $this->public_key(),
				'vault_url' => OMISE_VAULT_HOST
			) );
		}
	}

	if ( ! function_exists( 'add_omise_creditcard' ) ) {
		/**
		 * @param  array $methods
		 *
		 * @return array
		 */
		function add_omise_creditcard( $methods ) {
			$methods[] = 'Omise_Payment_Creditcard';
			return $methods;
		}

		add_filter( 'woocommerce_payment_gateways', 'add_omise_creditcard' );
	}

	if ( ! function_exists( 'add_omise_creditcard_manual_capture_action' ) ) {
		/**
		 * @param  array $order_actions
		 *
		 * @return array
		 */
		function add_omise_creditcard_manual_capture_action( $order_actions ) {
			$order_actions['omise_charge_capture'] = __( "Capture charge (via Omise)" );
			return $order_actions;
		}

		add_filter( 'woocommerce_order_actions', 'add_omise_creditcard_manual_capture_action' );
	}
}

function register_omise_wc_gateway_post_type() {
	register_post_type(
		'omise_charge_items',
		array(
			'supports' => array('title','custom-fields'),
			'label'    => 'Omise Charge Items',
			'labels'   => array(
				'name'          => 'Omise Charge Items',
				'singular_name' => 'Omise Charge Item'
			)
		)
	);
}
