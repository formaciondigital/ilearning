<?php
include_once "library/OAuthRequester.php";
include_once "library/OAuthStore.php";
include_once "config.php";

// We get on headers something like this:
// OAuth realm="" oauth_consumer_key="claveconsumer1" oauth_token="58617f924428b3dd50e1dcf6b740fa6c04cebb36d" oauth_verifier="16cdbec75a" oauth_signature_method="HMAC-SHA1" oauth_signature="YjIayORYv%2FcE7KmiJUG1Xc0Elew%3D" oauth_timestamp="79119" oauth_nonce="E856C1C5-B517-4A50-B0C7-CCD8A21FAD3C" oauth_version="1.0"

// For tests
//  $filename = 'salida.txt';
//  $file = fopen($filename,'a');
//  fwrite($file, $consumer_key);
//  fclose($file); // testeo

// Look on the headers for oauth_consumer_key, oauth_token and oauth_verifier		
$headers = apache_request_headers();
foreach ($headers as $key=>$value) {
	if ($key=='Authorization')
	{
		$authoritation = explode(',', $headers[$key]);
		foreach ($authoritation as $key2=>$value2) {
			if ( substr($authoritation[$key2],1,18) =='oauth_consumer_key')
			{
				$cadena = substr($authoritation[$key2],21);
				$consumer_key = substr($authoritation[$key2],21, strlen($cadena) -1 );				
			}
			if ( substr($authoritation[$key2],1,11) =='oauth_token')
			{
				$cadena = substr($authoritation[$key2],14);
				$oauth_token = substr($authoritation[$key2],14, strlen($cadena) -1 );				
			}
			if ( substr($authoritation[$key2],1,14) =='oauth_verifier')
			{
				$cadena = substr($authoritation[$key2],17);			
				$oauth_verifier = substr($authoritation[$key2],17, strlen($cadena) -1 );				
			}
		}
	}
}

$store   = OAuthStore::instance('MySQL', $dboptions);
// Get oauth id
$id = GetUserByCsOtV ($consumer_key,$oauth_token,$oauth_verifier);
try
{	
    $options= array();
    $options['oauth_verifier'] = $oauth_verifier;
   	$token = OAuthRequester::requestAccessToken($consumer_key, $oauth_token, $id,'POST',$options);
	// Update values on user_oauth table
	SetAccessUserOauth ($oauth_token,$oauth_verifier,$token['oauth_token']);
	// return tokens
	echo "oauth_token_secret=" . $token['oauth_token_secret'] . "&oauth_token=" . $token['oauth_token'];
}
catch (OAuthException $e)
{
    // Something wrong with the oauth_token.
    // Could be:
    // 1. Was already ok
    // 2. We were not authorized
}
?>
