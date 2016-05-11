<?php
// generic botscout function for PHP (WordPress, MyBB, etc)
// by Jimmy Peña, http://www.jimmyscode.com/
function JPBotScoutMatch($email, $ip, $name, $apiKey) {
  // BotScout API base url
  $baseURL = 'http://botscout.com/test/?';

  // are we checking multiple parameters?
  if ((strlen($email) > 0 && (strlen($ip) > 0 || strlen($name) > 0)) || (strlen($name) > 0 && (strlen($email) > 0 || strlen($ip) > 0))) {
    $multicheck = true;
  }
  // start with botscout URL
  $apiquery = $baseURL;
  if ($multicheck) { // must use 'multi' keyword
    $apiquery .= 'multi';
  }
  if (strlen($email) > 0) {
    $apiquery .= ($multicheck ? '&' : '') . 'mail=' . $email;
  }
  if (strlen($ip) > 0) {
    $apiquery .= ($multicheck ? '&' : '') . 'ip=' . $ip;
  }
  if (strlen($name) > 0) {
    $apiquery .= ($multicheck ? '&' : '') . 'name=' . $name;
  }
  if (strlen($apiKey) > 0) {
    $apiquery .= '&key=' . $apiKey;
  }

  // call API
  $returned_data = checkBotScout($apiquery);
  // if API returns a 'Y' as first char, we found a spammer, return true
  return (substr($returned_data, 0, 1) == 'Y');
} // end botscout function

// begin helper functions

function is_cURL_installed() {
  // http://cleverwp.com/function-curl-php-extension-loaded/
  return (function_exists('curl_init'));
}
function is_fgc_installed() {
  return function_exists('file_get_contents');
}
function use_curl($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $returnedvalues = curl_exec($ch);
  curl_close($ch);
  return $returnedvalues;
}
function checkBotScout($url) {
  if (is_cURL_installed()) { // cURL is supposed to be faster
    return use_curl($url);
  } elseif (is_fgc_installed()) { // file_get_contents is supposed to be slightly slower
    return file_get_contents($url);
  }
}
?>