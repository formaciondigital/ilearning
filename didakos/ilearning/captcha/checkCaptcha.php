<?php
require('php-captcha.inc.php');
if (PhpCaptcha::Validate($_GET['captcha'])) {
    echo 'Bien campeon';
} else {
    echo 'Pasas mucho tiempo con Manuel y Dani';
}
?>
