<?php
require('php-captcha.inc.php');
$aFonts = array('/var/www/sitios/www.grupogdt.com/captcha/Vera.ttf');
$oVisualCaptcha = new PhpCaptcha($aFonts, 200, 60);
$oVisualCaptcha->SetBackgroundImages('/var/www/sitios/www.grupogdt.com/captcha/fondo.jpg');
$oVisualCaptcha->Create();
?>
