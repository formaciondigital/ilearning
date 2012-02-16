<?php 
include_once "library/OAuthServer.php";
include_once "config.php";
$store   = OAuthStore::instance('MySQL', $dboptions);
$server = new OAuthServer();
$token = $server->accessToken();
?>
