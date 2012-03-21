<?php
include_once "library/OAuthStore.php";
include_once "library/OAuthServer.php";
include_once "config.php";

// Get POST values (tokens)
$store   = OAuthStore::instance('MySQL', $dboptions);
$error='';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['oauth_token']))
{
	$oauth_token = $_POST['oauth_token'];
}
else
{
	$oauth_token = $_GET['oauth_token'];
}
// Get oauth id
$id = GetUserByOt ($oauth_token);
// Fetch the oauth store and the oauth server.
$server = new OAuthServer();
$usuario_invalido=False;
try
{
    // Check if there is a valid request token in the current request
    // Returns an array with the consumer key, consumer secret, token, token secret and token type.
	$rs = $server->authorizeVerify();
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
	    // Get POST values (username and password) and try to get user_id
		$user_id = IsDokeosUserValid ($_POST['username'],$_POST['password']);
        if ($user_id){
		// See if the user clicked the 'allow' submit button (or whatever you choose)
		$authorized = array_key_exists('allow', $_POST);
		// Set the request token to be authorized or not authorized
		// When there was a oauth_callback then this will redirect to the consumer
		// Added new parameter $user_id=NULL
		$result = $server->authorizeFinish($authorized, $id, $user_id);

		// We can get a verifier or an URL
		// If returned value is a verifier then we only need to print it.
		// If returned value is an URL, it'd for iphone app. Must make a redirection.

		if (substr($result,0,7) == "http://")
		{
			// Iphone needs redirect without http://
			$cadena = substr($result,7);
			header ("Location: $cadena");
    		// Redirects to something like http://verifier:::XXXXXXXXX 
    		// Iphone intercepts this redicetion and stores verifier
		}
		else
		{
			print_r ($cadena);
		}
      } else {
          $usuario_invalido=True;
      }
	}
}
catch (OAuthException $e)
{
    // No token to be verified in the request, show a page where the user can enter the token to be verified
    // **your code here**
}
if ($result==null) {
// No username and password received.
// Needs user interaction
?>
<html>
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<meta content="minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no" name="viewport" />
<link href="css/style.css" rel="stylesheet" media="screen" type="text/css" />
<script src="javascript/functions.js" type="text/javascript"></script>
</head> 
<body>
	<form name="f" action="<?php echo $url_plataforma; ?>ilearning/oauth/authorize.php?oauth_token=<?php echo $oauth_token;?>&oauth_callback=<?php echo $_GET['oauth_callback'];?>" method="post">
    <div id="topbar">
		<div id="title">Acceso a la plataforma</div>
	</div>
	<div id="content">
<? if ($usuario_invalido){?>
    <div id="datos_incorrectos">Usuario o password no válidos. Pruebe de nuevo</div>
<?}?>

    <fieldset>
    	<ul class="pageitem">
			<li class="bigfield"><input placeholder="Usuario" type="text" name="username"></li>
			<li class="bigfield"><input placeholder="Contraseña" type="password" name="password"></li>
            <li class="button" style="background-image:url(images/but_permitir.png); background-repeat:no-repeat; background-position:center; height: 70px;">
			<input name="allow" type="submit" value="" /></li>
    	</ul>
    </fieldset>
    </div>
	</form>
    <div id="footer">
    	<img src="images/logo.png" />
	</div>
</body>
</html>
<?php } ?>
