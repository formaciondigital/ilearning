<?php
/*
 This class overrides ILearningProcessor class on ILerning.php
 It's used to dodge oauth validation
 ¿Why?
 Because we dont have oauth validation when app starts and try to see if
 an update is needed.
*/

class ILearningProcessor_extended extends ILearningProcessor {
  protected $handler_ = null;
  public function __construct($handler) {
    $this->handler_ = $handler;
  }

  public function process($input, $output,$oauth_auth=true) {
    $rseqid = 0;
    $fname = null;
    $mtype = 0;

    $input->readMessageBegin($fname, $mtype, $rseqid);
    
    if (oauth_auth==false){
        if ( ($fname!='checkVersion') || ($fname!='registerDeviceId')){
            return false;
        }

    }
    
    $methodname = 'process_'.$fname;
    if (!method_exists($this, $methodname)) {
      $input->skip(TType::STRUCT);
      $input->readMessageEnd();
      $x = new TApplicationException('Function '.$fname.' not implemented.', TApplicationException::UNKNOWN_METHOD);
      $output->writeMessageBegin($fname, TMessageType::EXCEPTION, $rseqid);
      $x->write($output);
      $output->writeMessageEnd();
      $output->getTransport()->flush();
      return;
    }
    $this->$methodname($rseqid, $input, $output);
    return true;
  }
}

?>
