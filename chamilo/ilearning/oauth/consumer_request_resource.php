<?php
include_once "library/OAuthRequester.php";
include_once "library/OAuthStore.php";
include_once "config.php";

$store   = OAuthStore::instance('MySQL', $dboptions);

// Recibiriamos el access token por POST
// $access_token = 'b05c4be324206c3ac47814515f851f0704cc6f0b7';
// $consumer_key = 'eccb540eded9daf80acec0e46ff952e404cc591a1'; // this is your consumer key
// $consumer_secret = 'f374b1b098be284b7094b5bf9d1f7a04'; // this is your secret key

$consumer_key = $_POST['consumer_key'];
$consumer_secret = $_POST['consumer_secret'];
$access_token = $_POST['access_token'];

// Obtenemos el alu_id_alumno de la tabla oauth_user por medio del access_token
$user_id = GetUserIdByAccessToken ($access_token);

// Con el consumer_key y el consumer_secret obtenemos el id de la tabla oauth_app_user (config.php)
$id = GetUserByCsAndCk ($consumer_key,$consumer_secret);

//URL 
$request_uri = 'http://localhost/dokeos/oauth/resource';

// Parameters, appended to the request depending on the request method.
// Will become the POST body or the GET query string.

$params = array('method' => 'POST','consulta' => 'GetCourseList','user_id' => $user_id);

// Obtain a request object for the request we want to make
$req = new OAuthRequester($request_uri, 'POST', $params);

// Sign the request, perform a curl request and return the results, throws OAuthException exception on an error

$result = $req->doRequest($id,null,$params);

// $result is an array of the form: array ('code'=>int, 'headers'=>array(), 'body'=>string)

print_r ($result['body']);

?>
