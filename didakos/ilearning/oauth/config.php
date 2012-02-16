<?php
require_once ('../../main/inc/global.inc.php');
include_once "../../main/inc/conf/configuration.php";
include_once "../../main/inc/lib/main_api.lib.php";

// DB configuration
$hostoauth = $_configuration['db_host'];
$username = $_configuration['db_user'];
$password = $_configuration['db_password'];
// oauth DB
$database = $_configuration['main_database'];
// plattform main DB
$database_main = $_configuration['main_database'];
// dispositive Constants
define("IPHONE", 1);
define("ANDROID", 2);
// Plattform url
$url_plataforma = $_configuration['root_web'];
// title
$pieces = explode("_", $_configuration['db_prefix']);
$title = $pieces[1];
// Icon
$icono = "icono.png";

$dboptions = array('server' => $hostoauth, 'username' => $username,
                 'password' => $password,  'database' => $database);

/**
 * Verify valid user
 * 
 * @param string username
 * @param string password
 */

function isAppUserRegistered ( $username, $password)
{
	global $database;
	$sql =  "select id from " . $database . ".oauth_app_user where username='" . $username . "' and password='" . $password . "'";

	$result =mysql_query($sql); 
	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['id']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>Usuario no registrado.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}	

/**
 * returns oauth_app_user id
 * 
 * @param string consumer_key
 * @param string consumer_secret (No obligatorio)
 */
function GetUserByCsAndCk ($consumer_key, $consumer_secret=null)
{
	global $database;
	if ($consumer_secret!=null)
	{
		$sql = "select osr_usa_id_ref from ". $database . ".oauth_server_registry where osr_consumer_key='" . $consumer_key . "' and osr_consumer_secret='". $consumer_secret . "'";
	}
	else
	{
		$sql = "select osr_usa_id_ref from ". $database . ".oauth_server_registry where osr_consumer_key='" . $consumer_key . "'";
	}

	$result =mysql_query($sql); 
	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['osr_usa_id_ref']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>Datos consumer_key y consumer secret inv&aacute;lidos.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}

/**
 * returns main db user id
 * 
 * @param string username
 * @param string password
 */
function IsDokeosUserValid ($username,$password)
{
	global $database_main;
	$sql = "select user_id from " . $database_main . ".user where active=1 and username='" . $username . "' and password='". $password. "'";
	$result =mysql_query($sql); 

	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['user_id']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>Datos de acceso del alumno incorrectos o alumno no activo.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}

function GetUserByCsOtV ($consumer_key,$oauth_token,$verifier)
{
	global $database;
	$sql = "select osr.osr_usa_id_ref from ". $database . ".oauth_server_registry osr,"
	. $database . ".oauth_server_token ost	
	 where osr.osr_consumer_key='" . $consumer_key . "' 
	and ost.ost_token='" . $oauth_token . "'
	and ost.ost_verifier='" . $verifier . "'
	and osr.osr_id=ost.ost_osr_id_ref";

	$result =mysql_query($sql); 
	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['osr_usa_id_ref']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>Datos consumer_key, token o verifier inv&aacute;lidos.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}

function GetUserByOt ($oauth_token)
{
	global $database;
	$sql = "select ost_usa_id_ref from " . $database . ".oauth_server_token	
	 where ost_token='" . $oauth_token . "'";

	$result =mysql_query($sql); 
	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['ost_usa_id_ref']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>oauth_token inv&aacute;lido.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}

/**
 * Update user access token
 * 
 * @param string oauth_token
 * @param string verifier
 * @param string access_token
 */
function SetAccessUserOauth ($oauth_token,$verifier,$access_token)
{
	global $database_main;
	$sql = "update " . $database_main . ".user_oauth set access_token='".$access_token. "' where request_token='" . $oauth_token . "' and verifier='". $verifier. "'";
	mysql_query($sql); 
}

/**
 * return plattform user_id
 * 
 * @param string access_token
 */

function GetUserIdByAccessToken ($access_token)
{
global $database_main;
	$sql = "select user_id from " . $database_main . ".user_oauth where access_token='". $access_token . "'";
	$result =mysql_query($sql); 
	if ($row = mysql_fetch_array($result)) 
	{
		return ($row['user_id']);
	}
	else
	{
		die("<html><head><meta content='text/html; charset=utf-8' http-equiv='Content-Type' /><meta content='minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no' name='viewport' /><link href='css/style.css' rel='stylesheet' media='screen' type='text/css' /></head><body><div id='topbar'><div id='title'></div></div><div id='content'><ul class='pageitem'><li class='textbox'><span class='header'>Error</span>Access token del alumno incorrecto.</li></ul></div><div id='footer'><img src='images/logo.png' /></div></body></html>");
	}
}
?>
