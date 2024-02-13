<?php

// This snippet due to the braintree_php.
if (version_compare(PHP_VERSION, '5.2.1', '<')) {
  throw new Exception('PHP version >= 5.2.1 required');
}

// This snippet (and some of the curl code) due to the Facebook SDK.
if (!function_exists('curl_init')) {
  throw new Exception('Paylabs needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
  throw new Exception('Paylabs needs the JSON PHP extension.');
}

// Configurations
require_once('Paylabs/Config.php');

// Plumbing
require_once('Paylabs/ApiRequestor.php');
require_once('Paylabs/VtWeb.php');
