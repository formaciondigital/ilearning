<?php
// For tests
//  $filename = 'salida.txt'; // testeo
//  $file = fopen($filename,'a'); // testeo
//  fwrite($file, $cadena); // testeo	
//  fclose($file); // testeo	
// Look on headers for oauth_consumer_key
$headers = apache_request_headers();
foreach ($headers as $key=>$value) {
	if ($key=='Authorization')
	{
		$authoritation = explode(',', $headers[$key]);
		foreach ($authoritation as $key2=>$value2) {
			if ( substr($authoritation[$key2],1,18) =='oauth_consumer_key')
			{
				$cadena = substr($authoritation[$key2],21);
				$cadena = substr($authoritation[$key2],21, strlen($cadena) -1 );				

			}
		}
	}
}

include_once "library/OAuthStore.php";
include_once "library/OAuthRequester.php";
include_once "config.php";

$consumer_key = $cadena;
$store   = OAuthStore::instance('MySQL',$dboptions);
// Get oauth id with consumer_key, consumer_secret is optional
$id = GetUserByCsAndCk ($consumer_key);
// Obtain a request token from the server
$token = OAuthRequester::requestRequestToken($consumer_key, $id);
// test
//  fwrite($file, "oauth_token_secret=" . $token['oauth_token_secret'] . "&oauth_token=" . $token['oauth_token'] . "&oauth_callback_confirmed=true"); 
// Return tokens
echo "oauth_token_secret=" . $token['oauth_token_secret'] . "&oauth_token=" . $token['oauth_token'] . "&oauth_callback_confirmed=true";
// Consumer must call authorize with tokens
?>
