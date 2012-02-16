<?php // $Id: download.php 22201 2009-07-17 19:57:03Z cfasanando $

/* For licensing terms, see /dokeos_license.txt */

/**
==============================================================================
*	This file is responsible for  passing requested documents to the browser.
*	Html files are parsed to fix a few problems with URLs,
*	but this code will hopefully be replaced soon by an Apache URL
*	rewrite mechanism.
*
*	@package dokeos.document
==============================================================================
*/

session_cache_limiter('none');

// include the global Dokeos file
require_once '../inc/global.inc.php';

// include additional libraries
require_once api_get_path(LIBRARY_PATH).'document.lib.php';

// section (for the tabs)
$this_section=SECTION_COURSES;


include(dirname ( __FILE__ )."/../../main/inc/conf/configuration.php");

include_once "../../ilearning/oauth/library/OAuthRequestVerifier.php";
include_once "../../ilearning/oauth/library/OAuthRequester.php";
include_once "../../ilearning/oauth/library/OAuthStore.php";
include_once "../../ilearning/oauth/config.php";




    $store   = OAuthStore::instance('MySQL', $dboptions);
    if (OAuthRequestVerifier::requestIsSigned()) {
        try {
            $req = new OAuthRequestVerifier();
            $signed = $req->verify();
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
       error_log("NO es signed");
    // Protection
    api_protect_course_script();

    if (!isset($_course)) {
        api_not_allowed(true);
    }
}








$doc_url = $_GET['doc_url'];

//change the '&' that got rewritten to '///' by mod_rewrite back to '&'
$doc_url = str_replace('///', '&', $doc_url);
//still a space present? it must be a '+' (that got replaced by mod_rewrite)
$doc_url = str_replace(' ', '+', $doc_url);

$doc_url = str_replace(array('../','\\..','\\0','..\\'),array('','','',''), $doc_url); //echo $doc_url;

// exploding the doc_url based on the '/'
$doc_url_parts = explode('/',$doc_url);

// check if the part contains only dots (.) and unset it if this is the case
foreach ($doc_url_parts as $key=>$value){
	if(empty($value)) // if empty, keep it to avoid removing /
		continue;
	$value = str_replace('.','',$value);
	if (empty($value)){
		unset($doc_url_parts[$key]);
	}
}
$doc_url = implode('/',$doc_url_parts);

// dealing with image included into survey: when users receive a link towards a
// survey while not being authenticated on the plateform.
// the administrator should probably be able to disable this code through admin
// inteface
$refer_script = strrchr($_SERVER["HTTP_REFERER"],'/');
if (substr($refer_script,0,15) == "/fillsurvey.php") {
	$invitation = substr(strstr($refer_script, 'invitationcode='),15);
	$course = strstr($refer_script, 'course=');
	$course = substr($course, 7, strpos($course, '&')-7);
	include ("../survey/survey.download.inc.php");
	$_course = check_download_survey($course, $invitation, $doc_url);
	$_course['path']=$_course['directory'];
} else {
	//protection
    
    if(!$signed){
	    api_protect_course_script();

    	if (! isset($_course))
    	{
    		api_not_allowed(true);
    	}
    }


	//if the rewrite rule asks for a directory, we redirect to the document explorer
	if(is_dir(api_get_path(SYS_COURSE_PATH).$_course['path']."/document".$doc_url))
	{
		//remove last slash if present
		//$doc_url = ($doc_url{strlen($doc_url)-1}=='/')?substr($doc_url,0,strlen($doc_url)-1):$doc_url;
		//mod_rewrite can change /some/path/ to /some/path// in some cases, so clean them all off (Ren�)
		while ($doc_url{$dul = strlen($doc_url)-1}=='/') $doc_url = substr($doc_url,0,$dul);
		//group folder?
		$gid_req = ($_GET['gidReq'])?'&gidReq='.Security::remove_XSS($_GET['gidReq']):'';
		//create the path
		$document_explorer = api_get_path(WEB_CODE_PATH).'document/document.php?curdirpath='.urlencode($doc_url).'&cidReq='.Security::remove_XSS($_GET['cidReq']).$gid_req;
		//redirect
		header('Location: '.$document_explorer);
	}

	// launch event
	event_download($doc_url);

}

$sys_course_path = api_get_path(SYS_COURSE_PATH);
//$full_file_name = $sys_course_path.$_course['path'].'/document'.$doc_url;
$full_file_name = $sys_course_path.$_course['path'].'/document'.str_replace('+',' ',$doc_url);

// check visibility of document and paths
if(!$signed) {
    $is_allowed_to_edit = api_is_allowed_to_edit();
    if (!$is_allowed_to_edit &&
        !DocumentManager::is_visible($doc_url, $_course)){
           echo "document not visible"; //api_not_allowed backbutton won't work
           exit; // you shouldn't be here anyway
    }
}
DocumentManager::file_send_for_download($full_file_name);

?>
