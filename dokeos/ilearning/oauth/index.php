<?php
//Formulario de registro de aplicación
//Es necesario tener un usuario y password registrados para poder solicitar la aplicación
//Están en la tabla oauth_user

?>
<html>
<head>
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
	Nombre: <input type="text" name="nombre"><br>
	Aplicaci&oacute;n: <input type="text" name="aplicacion"><br>
	Username: <input type="text" name="username"><br>
	Password: <input type="text" name="password"><br>
	<input type="submit" name="Aceptar"><br>
	</form>
</body>
</html>
