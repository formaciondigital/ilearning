<?php
//Formulario de registro de aplicación
//Es necesario tener un usuario y password registrados para poder solicitar la aplicación
//Están en la tabla oauth_user

?>
<html>
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<meta content="minimum-scale=1.0, width=device-width, maximum-scale=0.6667, user-scalable=no" name="viewport" />
<link href="css/style.css" rel="stylesheet" media="screen" type="text/css" />
<script src="javascript/functions.js" type="text/javascript"></script>
<script languaje="javascript">
	function validar(){
		if (document.f.username.value== "" || document.f.password.value== "") 
		{
		window.alert("Inserte username y password, son obligatorios");
		return false;
		} 
	return true;
	}
</script>
</head> 
<body>
	<form name="f" action="oauth_register.php" method="post"  onsubmit="javascript: return validar();">
	<div id="topbar">
		<div id="title">Datos</div>
	</div>
	<div id="content">
    <fieldset>
    	<ul class="pageitem">
			<li class="bigfield"><input placeholder="Nombre" type="text" name="nombre"></li>
            <li class="bigfield"><input placeholder="Aplicación" type="text" name="aplicacion"></li>
            <li class="bigfield"><input placeholder="Usuario" type="text" name="username"></li>
			<li class="bigfield"><input placeholder="Contraseña" type="password" name="password"></li>
            <li class="button" style="background-image:url(images/but_aceptar.png); background-repeat:no-repeat; background-position:center; height: 70px;">
			<input name="Aceptar" type="submit" value="" /></li>
    	</ul>
    </fieldset>
    </div>
	</form>
    <div id="footer">
    	<img src="images/logo.png" />
	</div>
</body>
</html>
