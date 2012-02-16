<?php
//esta pagina resgistra el servidor en el que hemos registrado nuestra aplicación.
include_once "library/OAuthStore.php";
include_once "config.php";

// Iniciamos la conexión store (MYSQL)
$store   = OAuthStore::instance('MySQL', $dboptions);

// Get the id of the current user (must be an int)
$user_id = 1;

//1 -  leer con json  el user_id, consumer_key y consumer_secret
//2 -  Solicititar al servidor las URLS (token, acces y authorize). llamando a info.php
//3 -  Leer el resultado en json y guardar las URL
//4 -  Registrar el servidor con todos los datos recibidos


// The server description
$server = array(
    'consumer_key' => 'claveconsumer1',
    'consumer_secret' => 'noneatthismoment',
    'server_uri' => 'https://localhost/dokeos/oauth/',
    'signature_methods' => array('HMAC-SHA1', 'PLAINTEXT'),
    'request_token_uri' => 'https://localhost/dokeos/oauth/request_token',
    'authorize_uri' => 'https://localhost/dokeos/oauth/authorize',
    'access_token_uri' => 'https://localhost/dokeos/oauth/access_token'
);

// Save the server in the the OAuthStore
$consumer_key = $store->updateServer($server, $user_id);
echo "servidor registrado correctamente";

?>
