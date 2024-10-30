<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

define("CUSTOMER_ID_COOKIE_NAME", "liverecover_customer_id");
define("CART_ID_COOKIE_NAME", "liverecover_cart_id");
define("CONVERTED_COOKIE_NAME", "liverecover_converted");
define("SHOULD_REDIRECT_COOKIE_NAME", "liverecover_should_redirect");

class LRApi {
  public $cookie_expiry;
  public $api_key;
  public $admin_api_key;
  public $client;

  public function __construct($apiUrl, $api_key, $admin_api_key) {
    $this->client = new Client([
      'base_uri' => $apiUrl,
      'timeout'  => 10.0,
      'headers' => ['liverecover-api-key' => $admin_api_key]
    ]);
    $this->api_key = $api_key;
    $this->admin_api_key = $admin_api_key;
    $this->cookie_expiry = 3600 * 24 * 30; //30 days
  }

  public function sendLog($message, $level = 'info') {
    try {
      $this->client->post('/logs', [
        'json' => [
          'level' => $level,
          'message' => $message,
        ]
      ]);
    } catch(RequestException $e) {
      error_log($e);
    }
  }

  public function update_shop($data) {
    try {
      $this->client->post('/shop/meta', [
        'json' => $data
      ]);
      return true;
    } catch(RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function uninstall() {
    try {
      $this->client->post('/shop/uninstall', [
        'json' => array()
      ]);
      return true;
    } catch(RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function get_customer_id() {
    return $_COOKIE[CUSTOMER_ID_COOKIE_NAME];
  }

  public function set_customer_id($id) {
    setcookie(CUSTOMER_ID_COOKIE_NAME, $id, time() + $this->cookie_expiry, '/');
  }

  public function get_cart_id() {
    return $_COOKIE[CART_ID_COOKIE_NAME];
  }

  public function set_cart_id($id) {
    setcookie(CART_ID_COOKIE_NAME, $id, time() + $this->cookie_expiry, '/');
  }

  public function get_converted() {
    return $_COOKIE[CONVERTED_COOKIE_NAME];
  }

  public function set_converted($value) {
    setcookie(CONVERTED_COOKIE_NAME, $value, time() + 3600 * 24 * 10, '/');
  }

  public function delete_converted_cookie() {
    if (isset($_COOKIE[CONVERTED_COOKIE_NAME])) {
      unset($_COOKIE[CONVERTED_COOKIE_NAME]);
    }
    setcookie(CONVERTED_COOKIE_NAME, null, -1, '/');
  }

  public function delete_cart_cookie() {
    if (isset($_COOKIE[CART_ID_COOKIE_NAME])) {
      unset($_COOKIE[CART_ID_COOKIE_NAME]);
    }
    setcookie(CART_ID_COOKIE_NAME, null, -1, '/');
    $this->delete_converted_cookie();
  }

  public function set_should_redirect($value) {
    setcookie(SHOULD_REDIRECT_COOKIE_NAME, $value, time() + 3600 * 24 * 10, '/');
  }

  public function get_customer($id) {
    try {
      $response = $this->client->get("/customers/{$id}");

      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function create_customer($customer_data) {
    try {
      $response = $this->client->post('/customers', [
        'json' => $customer_data
      ]);
      $body = $response->getBody();
      $this->delete_converted_cookie();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function update_customer($customer_id, $customer_data) {
    try {
      $response = $this->client->put("/customers/{$customer_id}", [
        'json' => $customer_data
      ]);
      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function set_customer($address) {
    $customer_id = $this->get_customer_id();
    $cart_id = $this->get_cart_id();

    if (!$customer_id) {
      $customer = $this->create_customer($address);
    }

    if ($customer_id) {
      $customer = $this->update_customer($customer_id, $address);
    }

    if ($customer && $customer_id != $customer->id) {
      $customer_id = $customer->id;
      $this->set_customer_id($customer_id);
      if ($cart_id) {
        $this->update_cart($cart_id, ['customerId' => $customer_id]);
      }
    }

    return $customer;
  }

  public function get_cart($cart_id) {
    try {
      $response = $this->client->get("/carts/{$cart_id}");

      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function create_cart($cart_data) {
    try {
      $response = $this->client->post('/carts', [
        'json' => $cart_data,
        'headers' => [
          'X-Plugin-Version' => LIVERECOVER_VERSION
        ]
      ]);

      $this->delete_converted_cookie();
      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function update_cart($cart_id, $cart_data) {
    try {
      $response = $this->client->put("/carts/{$cart_id}", [
        'json' => $cart_data
      ]);

      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      $errorBody = $e->getResponse()->getBody();
      if ($errorBody == '{"message":"Cart already purchased"}') {
        return $this->create_cart($cart_data);
      }
      error_log($e);
      return false;
    }
  }

  public function visit($cart_id) {
    try {
      $this->client->get("/carts/{$cart_id}/visit");
      return true;
    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function set_cart($cart_data) {
    $cart_id = $this->get_cart_id();
    $customer_id = $this->get_customer_id();

    if ($_GET['cartId']) {
      $cart_id = $_GET['cartId'];
    }

    if ($customer_id) {
      $cart_data['customerId'] = $customer_id;
    }

    if (!$cart_id) {
      $cart = $this->create_cart($cart_data);
    }

    if ($cart_id) {
      $cart = $this->update_cart($cart_id, $cart_data);
    }

    if ($cart) {
      $cart_id = $cart->id;
      $this->set_cart_id($cart_id);
    }

    return $cart;
  }

  public function purchase_cart($order, $customer_id) {
    try {
      $cart_id = $this->get_cart_id();
      $converted = $this->get_converted();

      if (!$cart_id) {
        // payment hook triggered by PayPal
        return $this->purchase_cart_by_hash_and_phone($order);
      }
      $data = [
        'customerId' => $customer_id,
        'orderId' => $order->get_id(),
        'user-agent' => $order->get_customer_user_agent(),
      ];
      if ($converted) {
        $data["converted"] = true;
      } else {
        $data["converted"] = false;
      }

      $response = $this->client->put("/carts/{$cart_id}/purchase", [
        'json' => $data
      ]);

      $body = $response->getBody();
      $cart = json_decode($body);

      $this->delete_cart_cookie();

      return $cart;
    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }

  public function purchase_cart_by_hash_and_phone($order) {
    try {
      $data = [
        'cart_hash' => $order->get_cart_hash(),
        'phone' => $order->get_billing_phone(),
        'orderId' => $order->get_id(),
        'user-agent' => $order->get_customer_user_agent(),
      ];

      $response = $this->client->post("/woocommerce/purchase", [
        'json' => $data
      ]);

      $body = $response->getBody();
      return json_decode($body);

    } catch (RequestException $e) {
      error_log($e);
      return false;
    }
  }
}
