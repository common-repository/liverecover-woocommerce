<?php
/**
 * Plugin Name: LiveRecover for WooCommerce
 * Plugin URI: http://www.liverecover.com
 * Description: Powered by real people, LiveRecover contacts, engages, and converts your WooCommerce abandoned carts into sales for you.
 * Author: Liverecover
 * Author URI: http://www.liverecover.com/contact
 * Version: 1.1.7
 */

define ( 'LIVERECOVER_VERSION', '1.1.7' );

if ( ! class_exists( 'WC_liverecover_plugin' ) ):

require 'vendor/autoload.php';

class WC_liverecover_plugin {
  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
  }

  public function init() {
    if ( class_exists( 'WC_Integration' ) ) {
      include_once 'class-integration.php';

      add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
    }
  }

  public function add_integration( $integrations ) {
    $integrations[] = 'WC_woocommerce_liverecover_integration';
    return $integrations;
  }
}

$WC_liverecover_plugin = new WC_liverecover_plugin();

function woocommerce_not_installed() {
  ?>
    <div class="notice notice-error">
       <p>
       LiveRecover plugin requires WooCommerce to be installed and active.
       You can download <a href="https://woocommerce.com">WooCommerce</a> here.
       </p>
    </div>
  <?php
}

function permalinks_disabled() {
  ?>
    <div class="notice notice-error">
      <p>
      LiveRecover requires pretty permalinks enabled.
      Select "Post name" under Common Settings <a href="<?php echo admin_url('options-permalink.php') ?>">here</a> to enable.
      </p>
    </div>
  <?php
}

function activate() {
  if ( class_exists( 'WC_Integration' ) ) {
    include_once 'class-integration.php';

    $plugin = new WC_woocommerce_liverecover_integration();
    $data = $plugin->update_shop_data();

    // log installation on server, even no api key set yet
    $api = new LRApi(WC_woocommerce_liverecover_integration::API_URL, null, null);
    $level = isset($data['error'])? 'error': 'info';
    $api->sendLog('Plugin installed: ' . json_encode($data), $level);

    if (!$plugin->api_key) {
      add_option('liverecover_setup', true);
    }
  }
}

function load_plugin() {
  if (is_admin()) {
    if (!class_exists( 'WC_Integration' )) {
      add_action('admin_notices', 'woocommerce_not_installed');
    }
    if (!get_option('permalink_structure')) {
      add_action('admin_notices', 'permalinks_disabled');
    }
    if (get_option('liverecover_setup') == true) {
      delete_option('liverecover_setup');

      wp_redirect(admin_url('/options-general.php?page=liverecover'));
      exit;
    }
  }
}

function deactivate() {
  if ( class_exists( 'WC_Integration' ) ) {
    include_once 'class-integration.php';

    $plugin = new WC_woocommerce_liverecover_integration();
    $plugin->uninstall();
  }
}

register_activation_hook( __FILE__,  'activate' );
register_deactivation_hook( __FILE__, 'deactivate' );
add_action( 'admin_init', 'load_plugin' );

endif;

?>
