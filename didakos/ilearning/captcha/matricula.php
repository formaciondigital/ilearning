<?php
require('php-captcha.inc.php');
require('../../main/inc/conf/fd_configuration.php');

if (PhpCaptcha::Validate($_GET['captcha'])) {

    //$cursos='10085ED1,10411ED1,10448ED1,10447ED1,10459ED1';
    //$cursos='10544,10085ED1,10411ED1,10448ED1,10447ED1,10459ED1';
    $cursos='10688DEMO,10100DEMO,10720DEMO,10587DEMO,10539DEMO';
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $_configuration['root_web'] . "main/gestor/fd_matricula_post.php");
    curl_setopt($ch, CURLOPT_POST, 1); 
    $nombre=$_GET['username'];
    $apellidos=' ';
    $pass='';

    for ($i = 0; $i < 5; $i++) {
          $pass .= chr(rand(97, 122));
    }

    $f_inicio='2011-01-26 15:51:10';
    $f_fin='2032-01-26 15:51:10';

    $error='';
    $parametros= "id_device=".$_GET['deviceid']."&nombre=".$nombre."&apellidos=".$apellidos."&login=".$_GET['username']."&pass=".$pass."&curso=".$cursos."&f_inicio=".$f_inicio."&f_fin=".$f_fin;
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parametros);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result=curl_exec($ch);
    curl_close($ch);
    if (is_numeric($result)){
        $arr=array('status'=>'ok','username'=>$_GET['username'].$result,'pass'=>$pass);
    } else {
        $arr=array('status'=>'error','descripcion'=>"El usuario ya existe");
    }
    echo json_encode($arr);

} else {
    $arr=array('status'=>'error','descripcion'=>"El Captcha introducido es incorrecto");
    echo json_encode($arr);
}
?>
