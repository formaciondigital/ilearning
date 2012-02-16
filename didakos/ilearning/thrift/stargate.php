<?php
    /*
     CABECERA
     */
    
    include_once(dirname ( __FILE__ )."/../../main/inc/lib/database.lib.php");
    include(dirname ( __FILE__ )."/../../main/inc/conf/configuration.php");
    
    /*
     --------------------------------- THRIFT -----------------------------------
     */
    
    $GLOBALS['THRIFT_ROOT'] = dirname(__FILE__).'/thrift';
    require_once $GLOBALS['THRIFT_ROOT'].'/Thrift.php';
    require_once $GLOBALS['THRIFT_ROOT'].'/protocol/TBinaryProtocol.php';
    require_once $GLOBALS['THRIFT_ROOT'].'/transport/TPhpStream.php';
    require_once $GLOBALS['THRIFT_ROOT'].'/transport/TBufferedTransport.php';
    $GEN_DIR = dirname(__FILE__).'/thrift/packages';
    require_once $GEN_DIR.'/ilearning/ILearning.php';
    require_once $GEN_DIR.'/ilearning/ilearning_types.php';
    include_once "metodos.class.php";
    
    /*
     --------------------------------- OAUTH -----------------------------------
    */
    
    include_once "../oauth/library/OAuthRequestVerifier.php";
    include_once "../oauth/library/OAuthRequester.php";
    include_once "../oauth/library/OAuthStore.php";
    include_once "../oauth/config.php";
    
    $store   = OAuthStore::instance('MySQL', $dboptions);
    if (OAuthRequestVerifier::requestIsSigned()) {
        try {
            $req = new OAuthRequestVerifier();
            $id = $req->verify();
            // If we have an user_id, then login as that user (for this request)
            if ($id) {
                //Todo correcto 
                $headers = apache_request_headers();
                
                foreach ($headers as $key=>$value) {
                    if ($key == 'Authorization') {
                        $authoritation = explode(',', $headers[$key]);
                        foreach ($authoritation as $key2=>$value2) {
                            if ( substr($authoritation[$key2],1,11) == 'oauth_token') {
                                //Delete name and ="
                                $cadena = substr($authoritation[$key2],14);
                                //delete end "
                                $access_token = substr($authoritation[$key2],14, strlen($cadena) -1 );
                            }
                        }
                    }
                }
                
                $user_id = GetUserIdByAccessToken ($access_token); 
                
                $handler = new ILearningHandler();
                $processor = new ILearningProcessor($handler);
                
                $transport = new TBufferedTransport(new TPhpStream(TPhpStream::MODE_R | TPhpStream::MODE_W));
                // strictRead_ is false, on true did not worked.
                
                $protocol = new TBinaryProtocol($transport, false, true);
                
                $transport->open();
                $processor->process($protocol, $protocol);
                $transport->close();
            }
        }
        catch (OAuthException $e)
        {
            // The request was signed, but failed verification
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: OAuth realm=""');
            header('Content-Type: text/plain; charset=utf8');
            echo $e->getMessage();
            error_log($e->getMessage());
            
            exit();
        }
    }
    else
    {
        echo "Accediendo a un recurso protegido, No puedes pasar!";
    }
    ?>
