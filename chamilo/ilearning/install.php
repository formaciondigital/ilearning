<?

function step_active($par_paso){
    if (!isset($_GET['paso'])){
        $paso = 1;
    } else {
        $paso=$_GET['paso'];
    }
    if ($paso==$par_paso){
        echo 'class="current_step" ';

    }

}
function split_sql_file(&$ret, $sql) {
    // do not trim, see bug #1030644
    //$sql          = trim($sql);
    $sql          = rtrim($sql, "\n\r");
    $sql_len      = strlen($sql);
    $char         = '';
    $string_start = '';
    $in_string    = false;
    $nothing      = true;
    $time0        = time();

    for ($i = 0; $i < $sql_len; ++$i) {
        $char = $sql[$i];

        // We are in a string, check for not escaped end of strings except for
        // backquotes that can't be escaped
        if ($in_string) {
            for (;;) {
                $i         = strpos($sql, $string_start, $i);
                // No end of string found -> add the current substring to the
                // returned array
                if (!$i) {
                    $ret[] = $sql;
                    return true;
                }
                // Backquotes or no backslashes before quotes: it's indeed the
                // end of the string -> exit the loop
                elseif ($string_start == '`' || $sql[$i - 1] != '\\') {
                    $string_start      = '';
                    $in_string         = false;
                    break;
                }
                // one or more Backslashes before the presumed end of string...
                else {
                    // ... first checks for escaped backslashes
                    $j                     = 2;
                    $escaped_backslash     = false;
                    while ($i - $j > 0 && $sql[$i - $j] == '\\') {
                        $escaped_backslash = !$escaped_backslash;
                        $j++;
                    }
                    // ... if escaped backslashes: it's really the end of the
                    // string -> exit the loop
                    if ($escaped_backslash) {
                        $string_start  = '';
                        $in_string     = false;
                        break;
                    }
                    // ... else loop
                    else {
                        $i++;
                    }
                } // end if...elseif...else
            } // end for
        } // end if (in string)

        // lets skip comments (/*, -- and #)
        elseif (($char == '-' && $sql_len > $i + 2 && $sql[$i + 1] == '-' && $sql[$i + 2] <= ' ') || $char == '#' || ($char == '/' && $sql_len > $i + 1 && $sql[$i + 1] == '*')) {
            $i = strpos($sql, $char == '/' ? '*/' : "\n", $i);
            // didn't we hit end of string?
            if ($i === false) {
                break;
            }
            if ($char == '/') $i++;
        }

        // We are not in a string, first check for delimiter...
        elseif ($char == ';') {
            // if delimiter found, add the parsed part to the returned array
            $ret[]      = array('query' => substr($sql, 0, $i), 'empty' => $nothing);
            $nothing    = true;
            $sql        = ltrim(substr($sql, min($i + 1, $sql_len)));
            $sql_len    = strlen($sql);
            if ($sql_len) {
                $i      = -1;
            } else {
                // The submited statement(s) end(s) here
                return true;
            }
        } // end elseif (is delimiter)

        // ... then check for start of a string,...
        elseif (($char == '"') || ($char == '\'') || ($char == '`')) {
            $in_string    = true;
            $nothing      = false;
            $string_start = $char;
        } // end elseif (is start of string)

        elseif ($nothing) {
            $nothing = false;
        }

        // loic1: send a fake header each 30 sec. to bypass browser timeout
        $time1     = time();
        if ($time1 >= $time0 + 30) {
            $time0 = $time1;
            header('X-pmaPing: Pong');
        } // end if
    } // end for

    // add any rest to the returned array
    if (!empty($sql) && preg_match('@[^[:space:]]+@', $sql)) {
        $ret[] = array('query' => $sql, 'empty' => $nothing);
    }

    return true;
} // end of the 'split_sql_file()' function
if (!isset($_GET['paso'])){
    $paso = 1;
} else {
    $paso=$_GET['paso'];
}
$title="Proceso de instalacion de ILearning";
$powered_by="Formacion Digital S.L.";

switch ($paso) {
    case 1:
$section="Instalacion ILearning";
$description=" Pulse siguiente para instalar, o lea la guia de instalacion";
$description.='<form action="install.php?paso=2" method="post" name="form1"><button class="save" type="submit" value="&nbsp;&nbsp; Click para instalar &nbsp;&nbsp;" >Click para instalar</button></form><br />
                    <a href="guia_instalacion.html" target="_blank">Guia de instalacion</a>';

break;

case 2:
//comprobamos que todo esta correcot
$section="Comprobando datos";
$file_config='../main/inc/conf/configuration.php';
$config_exist=False;

if (file_exists($file_config)) {
    $config_exist=True;
    require_once $file_config;
}
if ($config_exist) {
    $description="Comprobamos la conexion a la bbdd<br>";
    $db_host = $_configuration['db_host'];
    $db_user = $_configuration['db_user'];
    $db_pass = $_configuration['db_password'];
    $db_prefix = $_configuration['db_prefix'];
    $conn = mysql_connect($db_host,$db_user,$db_pass,true);
    mysql_select_db($db_prefix.'main');
    $description.="Conexion a la bbdd OK<br>";
    $description.='<form action="install.php?paso=3" method="post" name="form1"><button class="save" type="submit" value="&nbsp;&nbsp; Continuar &nbsp;&nbsp;" >Continuar</button></form><br />';

} else {
    $description="Esta instalado Chamilo correctamente?";

}


break;
case 3:
//Ejecutamos y felicitamos.
$section="Instalando ILearning";
$file_config='../main/inc/conf/configuration.php';
require_once $file_config;
$db_host = $_configuration['db_host'];
$db_user = $_configuration['db_user'];
$db_pass = $_configuration['db_password'];
$db_prefix = $_configuration['db_prefix'];
$main_database=$_configuration['main_database'];
$url_platform=$_configuration['root_web'];

$conn = mysql_connect($db_host,$db_user,$db_pass,true);
mysql_select_db($main_database);

   $db_script = 'ilearning.sql';
    if (file_exists($db_script)) {
        $sql_text = file_get_contents($db_script);
    }

    //split in array of sql strings
    $sql_instructions = array();
    $success = split_sql_file($sql_instructions, $sql_text);

    //execute the sql instructions
    $count = count($sql_instructions);
    for ($i = 0; $i < $count; $i++) {
        $this_sql_query = $sql_instructions[$i]['query'];
        mysql_query($this_sql_query,$conn);
    }

    $sql="INSERT INTO oauth_consumer_registry VALUES (1,1,'claveconsumer1','noneatthismoment','HMAC-SHA1,PLAINTEXT','".$url_platform."ilearning/oauth/','".$url_platform."','ilearning/oauth/','".$url_platform."ilearning/oauth/request_token.php','".$url_platform."ilearning/oauth/authorize.php','".$url_platform."ilearning/oauth/access_token.php','2011-01-28 11:15:03')";
    mysql_query($sql,$conn);
    $sql="insert into oauth_server_registry values (1,1,'claveconsumer1','noneatthismoment',1,'active','Ilearning Iphone app','ilearning@formaciondigital.com','','','Ilearning Iphone app','','','',0,'','')";
    mysql_query($sql,$conn);



    $description.= "Instalacion finalizada con exito<br> Recordar que hay que borrar el install.php";





break;
}


         
?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>&mdash; Instalacion Ilearning</title>
    <style type="text/css" media="screen, projection">
        /*<![CDATA[*/
        @import "default.css";
        /*]]>*/
    </style>
    <script language="javascript">
        init_visibility=0;
        function show_hide_option()
        {
            if(init_visibility == 0)
            {
                document.getElementById('optional_param1').style.display = '';
                document.getElementById('optional_param2').style.display = '';
                if(document.getElementById('optional_param3'))
                {
                    document.getElementById('optional_param3').style.display = '';
                }
                document.getElementById('optional_param4').style.display = '';
                document.getElementById('optional_param5').style.display = '';
                document.getElementById('optional_param6').style.display = '';
                init_visibility = 1;
            }
            else
            {
                document.getElementById('optional_param1').style.display = 'none';
                document.getElementById('optional_param2').style.display = 'none';
                if(document.getElementById('optional_param3'))
                {
                    document.getElementById('optional_param3').style.display = 'none';
                }
                document.getElementById('optional_param4').style.display = 'none';
                document.getElementById('optional_param5').style.display = 'none';
                document.getElementById('optional_param6').style.display = 'none';
                init_visibility = 0;                
            }
        }
    </script>
<?php if(!empty($charset)){ ?>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset ?>" />
<?php } ?>
</head>
<body dir="<?php echo $text_dir ?>">
<div id="container">

<div id="header">
    <div id="header1"><div id="logosHeader1"></div></div>
    <div id="header2">Instalacion Ilearning</div>
    <div id="header3">&nbsp;</div>
</div>


<div id="installation_steps">
    
    <ol>
        <li <?php step_active('1'); ?>>Introduccion</li>
        <li <?php step_active('2'); ?>>Comprobacion BBDD</li>
        <li <?php step_active('3'); ?>>Finalizacion</li>
    </ol>
</div>
<table align="center" width="50%">
<tr><td>
<br/><br/>
<br/><br/>
                    <? echo $description?>
</td></tr></table>

  </td>
</tr>
</table>



</form>
<br style="clear:both;" />
<div id="footer">
</div>
</div>


</body>
</html>

