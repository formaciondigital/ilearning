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
                    // Secret string
			        echo 'molamazo';
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
