<?php

include_once "library/OAuthRequestVerifier.php";
include_once "library/OAuthRequester.php";
include_once "library/OAuthStore.php";
include_once "config.php";

$store   = OAuthStore::instance('MySQL', $dboptions);

if (OAuthRequestVerifier::requestIsSigned())
{
        try
        {
               $req = new OAuthRequestVerifier();
               $id = $req->verify();
               // If we have an user_id, then login as that user (for this request)
               if ($id)
               {
			$sql = "select code,title from " . $database_main . ".course c," . $database_main . ".course_rel_user m where m.user_id=". $_POST['user_id'] . " and c.code=m.course_code"; 
			$result =mysql_query($sql); 
			echo "Obteniendo consulta " . $_POST['consulta'] . " de Dokeos<br>";
			while ($row = mysql_fetch_array($result)) 
			{
				print_r ($row['code'] . "->" . $row['title']);
				echo "<br>";
			}
               }
		
        }
        catch (OAuthException $e)
        {
                // The request was signed, but failed verification
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: OAuth realm=""');
                header('Content-Type: text/plain; charset=utf8');
                                        
                echo $e->getMessage();
                exit();
        }
}
else
{
	echo "Accediendo a un recurso protegido, No puedes pasar!";
}

?>
