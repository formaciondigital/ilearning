<?php
include_once "config.php";
    // Returns information about pages that can be used.
	$info = '{"token_access_url": "'.$url_plataforma.'ilearning/oauth/consumer_request_access.php",
  "token_request_url": "'.$url_plataforma.'ilearning/oauth/consumer_request_token.php",
  "token_authorization_url": "'.$url_plataforma.'ilearning/oauth/authorize.php",
  "thrift_url": "'.$url_plataforma.'ilearning/thrift/stargate.php",
  "title": "'. $title .'"}';
	echo ($info);
?>
