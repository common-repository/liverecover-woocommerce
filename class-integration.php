<?php
/**
 * Liverecover Woocommerce Plugin.
 *
 * @package  Liverecover Woocommerce Plugin Integration
 * @category Integration
 * @author   Liverecover
 */
if ( ! class_exists( 'WC_woocommerce_liverecover_integration' ) ) :

require 'helpers.php';
require 'liverecover-api.php';

function log_object($object) {
  error_log( print_r($object, TRUE) );
}

class WC_woocommerce_liverecover_integration extends WC_Integration {

  const LIVERECOVER_HANDLE = 'liverecoverjs';
  const STATIC_URL = 'https://dash.liverecover.com';
  const API_URL = 'https://api.liverecover.com';
  public $api_key;
  public $admin_api_key;
  public $api;

  public function __construct() {
    $this->id                 = 'woocommerce_liverecover';
    $this->method_title       = __('LiveRecover Woocommerce Plugin');
    $this->method_description = __('');

    $this->init_form_fields();
    $this->init_settings();

    $keys = explode("_", $this->get_option( 'api_key' ));
    $this->api_key = $keys[0];
    $this->admin_api_key = $keys[1];

    if (is_admin()) {
      add_action('woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ));
      add_action('updated_option', array( $this, 'updated_option'), 10, 2 );

      if (!$this->api_key) {
        add_action('admin_menu', array($this, 'liverecover_page'));
        add_action('admin_notices', array($this, 'no_api_key_notice'));
        add_action('wp_ajax_nopriv_savekey', array($this, 'savekey'));
        add_action('wp_ajax_savekey', array($this, 'savekey'));
        return $this;
      } else {
        if (get_option('liverecover_connected_dismissed') == false) {
          add_action('admin_notices', array($this, 'successfull_connection_notice'));
          add_action('wp_ajax_liverecover_dismiss_notice', array($this, 'dismiss_notice'));
          add_action('admin_enqueue_scripts', array($this, 'add_dismiss_script'));
        }
      }
    }

    $this->api = new LRApi(self::API_URL, $this->api_key, $this->admin_api_key);

    $current_user = wp_get_current_user();
    $allowed_roles = array('editor', 'administrator', 'author');

    if (!is_admin()) {
      if($current_user->exists() && array_intersect($allowed_roles, $current_user->roles ) ) {
        return false;
      }

      add_filter( 'query_vars', array( $this, 'add_query_vars_filter') );

      add_action('wp_loaded', array( $this, 'get_cart_from_query' ), 1);

      $liverecover_js_src = self::STATIC_URL . "/woocommerce/script.min.js";
      wp_enqueue_script( self::LIVERECOVER_HANDLE, $liverecover_js_src, null, null, true );
      add_filter('script_loader_tag', array( &$this, 'add_script' ), 0, 3);

      add_filter('woocommerce_billing_fields', array( $this, 'add_phone_to_billing_fields' ));

      add_action('woocommerce_add_to_cart', array( $this, 'update_cart_hook' ), 30);
      add_action('woocommerce_remove_cart_item', array( $this, 'update_cart_hook' ), 30);
      add_filter('woocommerce_update_cart_action_cart_updated', array( $this, 'update_cart_hook_filter' ), 20, 1);

      add_action('woocommerce_applied_coupon', array( $this, 'update_cart_hook' ), 30);
      add_action('woocommerce_removed_coupon', array( $this, 'update_cart_hook' ), 30);
      add_action('woocommerce_checkout_update_order_review', array( $this, 'update_cart_hook' ), 30);

      add_action('woocommerce_thankyou', array( $this, 'thankyou_hook' ), 10);
      add_action('woocommerce_payment_complete', array( $this, 'payment_complete_hook' ), 10, 1);
    }
    return $this;
  }

  public function liverecover_page() {
    add_submenu_page(
      'options-general.php',
      'LiveRecover',
      'LiveRecover',
      'manage_options',
      'liverecover',
      array($this, 'liverecover_page_html')
    );
  }

  public function liverecover_page_html() {
    if (!current_user_can('manage_options')) {
      return;
    }
    $url = self::STATIC_URL . '/signup?platform=woocommerce&installed=true';
    include('install_page.php');
  }

  private function _savekey($key) {
    $keys = explode("_", $key);
    $this->api_key = $keys[0];
    $this->admin_api_key  = $keys[1];
    $this->api = new LRApi(self::API_URL, $this->api_key, $this->admin_api_key);

    $keys = (new CreateKeys())->create();
    if (!$this->update_shop_data($keys)) {
      return false;
    }
    $this->update_option('api_key', $key);
    update_option('liverecover_connected_dismissed', false);
    remove_action('admin_notices', array($this, 'no_api_key_notice'));
    add_action('admin_notices', array($this, 'successfull_connection_notice'));

    return true;
  }

  public function savekey() {
    $key = $_POST['key'];

    $res = $this->_savekey($key);
    if (!$res) {
      wp_send_json_success(['key' => 'invalid']);
      return;
    }
    wp_send_json_success(['key' => 'ok']);
  }

  public function successfull_connection_notice() {
    ?>
    <div class="notice notice-success is-dismissible lr-notice">
       <p>LiveRecover connected successfully!</p>
    </div>
    <?php
  }

  public function add_dismiss_script() {
    wp_register_script(
      'liverecover-notice-dismiss',
      plugins_url('dismiss-notice.js', __FILE__),
      array('jquery'),
      '1.0',
      false
    );
    wp_localize_script('liverecover-notice-dismiss', 'params', [
      'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
    wp_enqueue_script('liverecover-notice-dismiss');
  }

  public function dismiss_notice() {
    update_option('liverecover_connected_dismissed', true);

    wp_die();
  }

  public function add_script($tag, $handle, $src) {
    if ( $handle !== self::LIVERECOVER_HANDLE ) {
      return $tag;
    }
    $api_key = $this->api_key;

    $script_params = array(
      'isCheckout' => is_checkout()? 'true': '',
      'apiKey' => $api_key,
      'checkoutUrl' => wc_get_checkout_url()
    );
    return "<script async src='" . $src . '?' . http_build_query($script_params) . "'></script>";
  }

  public function get_shop_data() {
    try {
      $site_url = get_site_url();
      $domain = parse_url($site_url)['host'];
      if ($domain == null) {
        $domain = $site_url;
      }
      $shop_page_id = get_option('woocommerce_shop_page_id');
      $user = wp_get_current_user();
      return array(
        'name' => get_bloginfo('name'),
        'domain' => $domain,
        'url' => $shop_page_id != '' ? get_permalink($shop_page_id): $domain,
        'managerFirstName' => $user->first_name,
        'managerLastName' =>  $user->last_name,
        'pluginVersion' => LIVERECOVER_VERSION,
      );
    } catch (Exception $e) {
      return ['error' => $e->getMessage() . ': ' . $e->getTraceAsString()];
    }
  }

  public function update_shop_data($keys = null) {
    $data = $this->get_shop_data();
    if ($keys != null) {
      $data['key'] = $keys['consumer_key'];
      $data['secret'] = $keys['consumer_secret'];
    }
    if ($this->api) {
      $res = $this->api->update_shop($data);
      if (!$res) return null;
    }
    return $data;
  }

  public function updated_option($option) {
    if ($option == 'blogname' || $option == 'home' || $option == 'woocommerce_shop_page_id') {
      $this->update_shop_data();
    }
  }

  public function uninstall() {
    if (!$this->api) return;
    $this->api->uninstall();
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'api_key' => array(
        'title'             => __( 'API key' ),
        'type'              => 'text',
        'desc_tip'          => true,
        'default'           => '',
        'css'               => 'width:600px;',
      ),
    );
  }

  public function add_query_vars_filter( $vars ){
    $vars[] = "cartId";
    return $vars;
  }

  public function get_cart_from_query() {
    try {
      $cart_id = $_GET['cartId'];

      if (!$cart_id) {
        return false;
      }

      $cart = $this->api->get_cart($cart_id);

      $this->api->set_cart_id($cart_id);

      if ($cart->customerId) {
        $this->api->set_customer_id($cart->customerId);
      }

      if ($cart->orderId && $cart->purchasedAt) {
        $order = new WC_Order( $cart->orderId );

        if (!$order) {
          return false;
        }

        $url = $order->get_checkout_order_received_url();

        if ($url) {
          wp_redirect($url);
          exit;
        }
      }

      $this->api->set_converted('true');

      WC()->cart->empty_cart();

      foreach($cart->items as $item) {
        WC()->cart->add_to_cart( $item->productId, $item->quantity, $item->variantId );
      }

      foreach($cart->coupons as $coupon) {
        WC()->cart->add_discount( $coupon->code );
      }
      if ($_GET['visitor'] != 'admin') {
        $this->api->visit($cart_id);
      }
      $this->api->set_should_redirect('true');
    } catch (Exception $exception) {
      error_log($exception);
    }
    return true;
  }

  public function thankyou_hook() {
    $this->api->delete_cart_cookie();
  }

  public function payment_complete_hook($order_id) {
    try {
      $order = new WC_Order( $order_id );
      $address = $order->get_address();

      $user_data = transform_user_data($address);

      $customer = $this->api->set_customer($user_data);

      $this->api->purchase_cart(
        $order,
        $customer->id
      );
    } catch (Exception $exception) {
      error_log($exception);
    }
  }

  public function update_cart_hook() {
    try {
      if (!WC()->cart) {
        return false;
      }

      WC()->session->set('refresh_totals', true);

      // SET CART
      $cart = [
        'value' => WC()->cart->get_cart_contents_total(),
        'url' => wc_get_checkout_url(),
        'shippingMethod' => 'None',
        'shippingPrice' => WC()->cart->get_shipping_total(),
      ];

      // SET COUPONS
      $coupons = [];

      $cart_coupons = WC()->cart->get_coupons();

      if( count( $cart_coupons ) > 0 ){
        foreach ( $cart_coupons as $coupon => $values ) {
          $coupon_data = $values->get_data();
          $coupon_item = [
            'code' => $coupon_data['code'],
            'value' => $coupon_data['amount'],
            'type' => $coupon_data['discount_type'],
            'freeShipping' => $coupon_data['free_shipping']
          ];

          array_push($coupons, $coupon_item);
        }
      }

      $cart['coupons'] = $coupons;

      // DETERMINE CART SHIPPING METHOD
      if ( WC()->session->get('shipping_for_package_0') != null ) {
        foreach( WC()->session->get('shipping_for_package_0')['rates'] as $method_id => $rate ) {
          if( WC()->session->get('chosen_shipping_methods')[0] == $method_id ) {
            $rate_label = $rate->label;
            $cart['shippingMethod'] = $rate_label;
            break;
          }
        }
      }

      // SET CART ITEMS
      $cart_items = [];

      foreach ( WC()->cart->get_cart() as $item => $values ) {
        $product = $values['data']->get_data();
        $cart_item = [
          'productId' => $values['product_id'],
          'variantId' => $values['variation_id'],
          'title' => $product['name'],
          'variantTitle' => $product['name'],
          'value' => $product['price'],
          'url' => get_permalink($values['product_id']),
          'quantity' => $values['quantity'],
        ];

        array_push($cart_items, $cart_item);
      }

      $cart['items'] = $cart_items;
      $cart['meta'] = ['cart_hash' => WC()->cart->get_cart_hash()];

      $this->api->set_cart($cart);
    } catch (Exception $exception) {
      error_log($exception);
    }
    return true;
  }

  public function update_cart_hook_filter($cart_updated) {
    $this->update_cart_hook();
    return $cart_updated;
  }

  public function no_api_key_notice() {
    $lrpage = wp_make_link_relative( admin_url('options-general.php?page=liverecover') );
    if ($_SERVER['REQUEST_URI'] == $lrpage) return;
    ?>
    <div class="notice notice-error">
        <p>
          <a href="<?php echo $lrpage ?>">
            LiveRecover not connected
          </a>
        </p>
    </div>
    <?php
  }

  // triggered when saving key manually in dashboard
  public function validate_api_key_field( $key, $value ) {
    if ( isset( $value ) && 65 != strlen( $value ) ) {
      WC_Admin_Settings::add_error( esc_html__( 'LiveRecover api key is invalid.', 'woocommerce-liverecover-integration' ) );
      return;
    }
    $res = $this->_savekey($value);
    if (!$res) {
      WC_Admin_Settings::add_error( esc_html__( 'LiveRecover api key is invalid.', 'woocommerce-liverecover-integration' ) );
      return;
    }
    return $value;
  }

  public function add_phone_to_billing_fields( $fields ) {
    if (!$fields['billing_phone']) {
      $fields['billing_phone'] = array(
        'label'         => __( 'Phone - Receive texts from our team', 'woocommerce' ),
        'required'      => false,
        'class'         => array( 'form-row-wide' ),
        'clear'         => true,
        'priority'      => 100,
        'type'          => 'tel',
        'autocomplete'  => 'tel'
      );
    } else {
      $fields['billing_phone']['label'] = __( 'Phone - Receive texts from our team', 'woocommerce' );
    }

    return $fields;
  }
}
endif;

?>
