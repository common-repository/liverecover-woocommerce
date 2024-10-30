<?php

function transform_user_data($address) {
  return [
    'email' => $address['email'],
    'firstName' => $address['first_name'],
    'lastName' => $address['last_name'],
    'phone' => $address['phone'],
    'address' => $address['address_1'],
    'address2' => $address['address_2'],
    'city' => $address['city'],
    'state' => $address['state'],
    'zip' => $address['postcode'],
    'country' => $address['country'],
  ];
}

class CreateKeys extends WC_Auth {
  public function create() {
    global $wpdb;

    $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_api_keys WHERE description LIKE 'LiveRecover%'");
    return $this->create_keys('LiveRecover', get_current_user_id(), 'read_write');
  }
}
