<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 */

$GLOBALS['THRIFT_ROOT'] = dirname(__FILE__).'/thrift/';

require_once $GLOBALS['THRIFT_ROOT'].'/Thrift.php';
require_once $GLOBALS['THRIFT_ROOT'].'/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TSocket.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/THttpClient.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TBufferedTransport.php';

require "metodos.class.php";

$GEN_DIR = dirname(__FILE__).'/thrift/packages';
require_once $GEN_DIR.'/ilearning/ILearning.php';
require_once $GEN_DIR.'/ilearning/ilearning_types.php';

try {
  //if (array_search('--http', $argv)) {
  //$socket = new THttpClient('localhost', 81, '/version08/ilearning/PhpServer.php');
  //$socket = new THttpClient('version08.formaciondigital.com', 80, '/thrift/stargate.php');
  //} else {
  //$socket = new TSocket('localhost', 9090);
  //}
  /* 
  $transport = new TBufferedTransport($socket, 1024, 1024);
  $protocol = new TBinaryProtocol($transport);
  $client = new ILearningClient($protocol);

  $transport->open();
*/
// var_dump($client->getSupportedContents(1));

$client = new ILearningHandler;
$user_id = 133;
$course_id='10688DEMO';
$launchdata = "23#65,66,67,68,69,70,71,72,73,74,75,76,77";
$pages_array = explode(",","65,66,67,68,69,70,71,72,73,74,75,76,77");
$id_video=66; 
$sco_id=1;
echo (isScoCompleted ($course_id,1,1));

 print_r ($client->getScos($course_id));


// print_r ($client->getLecturesScos($course_id,$sco_id));


// var_dump($client->getProgress('10645ED1'));
//  var_dump($client->getExams('1000'));
//  var_dump($client->getExercises('10'));
   //  $close = $transport->close();

} catch (Exception $tx) {
  print 'TException: '.$tx->getMessage()."\n";
}

?>
