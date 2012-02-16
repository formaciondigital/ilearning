<?php
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
// Extends and override original class.
require_once $GEN_DIR.'/ilearning/Ilerning_override.php';
require_once $GEN_DIR.'/ilearning/ilearning_types.php';
include_once "metodos.class.php";

/* 
--------------------------------- OAUTH -----------------------------------
*/

// Extended class ILearningProcessor from ILearning.php  with ILearningProcessor_extended on Ilerning_override.php

	try{
	$handler = new ILearningHandler();
    $processor = new 	ILearningProcessor_extended($handler);
	$transport = new TBufferedTransport(new TPhpStream(TPhpStream::MODE_R | TPhpStream::MODE_W));
	$protocol = new TBinaryProtocol($transport, false, true);
	$transport->open();
    $processor->process($protocol, $protocol,false);
    $transport->close();
	}catch(Exception $e){
		echo 'Excepción capturada: ',  $e->getMessage(), "\n";
	}
?>
