<?php
//Iniciamos la sesión
session_start();

// Librerías necesarias
require_once 'library/OAuthRequest.php';
require_once 'library/OAuthRequester.php';
require_once 'library/OAuthRequestSigner.php';
require_once 'library/OAuthRequestVerifier.php';
include_once "config.php";

// Iniciamos la conexión store (MYSQL)
$store   = OAuthStore::instance('MySQL', $dboptions);

// Vamos a ver el user_id en la tabla oauth_app_user (config.php)
$id = isAppUserRegistered ($_POST["username"],$_POST["password"]);

//Guardamos datos en las variables de sesión
$_SESSION['id'] = $id['id'];
$_SESSION['nombre'] = $_POST['nombre'];
$_SESSION['aplicacion'] = $_POST['aplicacion'];
// This should come from a form filled in by the requesting user
$consumer = array(
    // These two are required
    'requester_name' => $_SESSION["nombre"],
    'requester_email' => 'example@example.com',

    // These are all optional
    'callback_uri' => 'http://www.myconsumersite.com/oauth_callback',
    'application_uri' => 'http://www.myconsumersite.com/',
    'application_title' => $_SESSION["aplicacion"],
    'application_descr' => 'Make nice graphs of all your data',
    'application_notes' => 'Bladibla',
    'application_type' => 'website',
    'application_commercial' => 0
);		

//Guardamos datos del consumer (aplicación que hemos registrado)
$key   = $store->updateConsumer($consumer, $_SESSION['id']);

// Get the complete consumer from the store
$consumer = $store->getConsumer($key,$_SESSION['id']);

// Some interesting fields, the user will need the key and secret
$consumer_id = $consumer['id'];
$consumer_key = $consumer['consumer_key'];
$consumer_secret = $consumer['consumer_secret'];

//Mostramos los datos.
echo "Bienvenido <b>" . $consumer['requester_name'] . "</b><br>Su aplicaci&oacute;n <b>" 
. $consumer['application_title']  . "</b> ha sido registrada.<br>Su consumer_key: <b>" .
 $consumer_key . "</b><br>Su consumer_secret: <b>" . $consumer_secret . "</b>";
?>
