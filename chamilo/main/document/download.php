<?php
/* For licensing terms, see /license.txt */
/**
 *	This file is responsible for  passing requested documents to the browser.
 *
 *	@package chamilo.document
 */
/**
 * Code
 * Many functions updated and moved to lib/document.lib.php
 */
session_cache_limiter('none');
require_once '../inc/global.inc.php';

//Autenticacion oauth


// hasta aqui hay que quitarlo

include(dirname ( __FILE__ )."/../../main/inc/conf/configuration.php");

include_once "../../ilearning/oauth/library/OAuthRequestVerifier.php";
include_once "../../ilearning/oauth/library/OAuthRequester.php";
include_once "../../ilearning/oauth/library/OAuthStore.php";
include_once "../../ilearning/oauth/config.php";





    $store   = OAuthStore::instance('MySQL', $dboptions);
    if (OAuthRequestVerifier::requestIsSigned()) {
        try {
            $req = new OAuthRequestVerifier();
            $id = $req->verify();
            //$id=True;
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

$this_section = SECTION_COURSES;

// Protection
api_protect_course_script();

if (!isset($_course)) {
    api_not_allowed(true);
}
    }







require_once api_get_path(LIBRARY_PATH).'document.lib.php';

$doc_url = $_GET['doc_url'];

// Change the '&' that got rewritten to '///' by mod_rewrite back to '&'
$doc_url = str_replace('///', '&', $doc_url);
// Still a space present? it must be a '+' (that got replaced by mod_rewrite)
$doc_url = str_replace(' ', '+', $doc_url);

$doc_url = str_replace(array('../', '\\..', '\\0', '..\\'), array('', '', '', ''), $doc_url); //echo $doc_url;

if (strpos($doc_url,'../') OR strpos($doc_url,'/..')) {
   $doc_url = '';
}

// Dealing with image included into survey: when users receive a link towards a
// survey while not being authenticated on the plateform.
// The administrator should probably be able to disable this code through admin
// inteface.
$refer_script = strrchr($_SERVER["HTTP_REFERER"], '/');

$sys_course_path = api_get_path(SYS_COURSE_PATH).$_course['path'].'/document';

if (substr($refer_script, 0, 15) == '/fillsurvey.php') {
	$invitation = substr(strstr($refer_script, 'invitationcode='), 15);
	$course = strstr($refer_script, 'course=');
	$course = substr($course, 7, strpos($course, '&') - 7);
	include '../survey/survey.download.inc.php';
	$_course = check_download_survey($course, $invitation, $doc_url);
	$_course['path'] = $_course['directory'];
} else {
	// If the rewrite rule asks for a directory, we redirect to the document explorer
	if (is_dir($sys_course_path.$doc_url)) {
		// Remove last slash if present
		// mod_rewrite can change /some/path/ to /some/path// in some cases, so clean them all off (René)
		while ($doc_url{$dul = strlen($doc_url) - 1} == '/') {
			$doc_url = substr($doc_url, 0, $dul);
		}
		// Group folder?
		$gid_req = ($_GET['gidReq']) ? '&gidReq='.Security::remove_XSS($_GET['gidReq']) : '';
		// Create the path
		$document_explorer = api_get_path(WEB_CODE_PATH).'document/document.php?curdirpath='.urlencode($doc_url).'&cidReq='.Security::remove_XSS($_GET['cidReq']).$gid_req;
		// Redirect
		header('Location: '.$document_explorer);
	}
	// Launch event
	event_download($doc_url);
}

if (Security::check_abs_path($sys_course_path.$doc_url, $sys_course_path.'/')) {
    $full_file_name = $sys_course_path.$doc_url;    
    // Check visibility of document and paths    doc_url
    //var_dump($document_id, api_get_course_id(), api_get_session_id(), api_get_user_id());
    $is_visible = false;
    $course_info   = api_get_course_info(api_get_course_id());
    $document_id = DocumentManager::get_document_id($course_info, $doc_url);
    
    if ($document_id) {
    	// Correct choice for strict security (only show if whole tree visible)
	//$is_visible = DocumentManager::check_visibility_tree($document_id, api_get_course_id(), api_get_session_id(), api_get_user_id());
        // Correct choice for usability
    	$is_visible = DocumentManager::is_visible($doc_url, $_course, api_get_session_id());
    }
    
    //$is_visible = DocumentManager::is_visible($doc_url, $_course, api_get_session_id());
    if (!api_is_allowed_to_edit() && !$is_visible) {
    	Display::display_error_message(get_lang('ProtectedDocument'));//api_not_allowed backbutton won't work.
    	exit; // You shouldn't be here anyway.
    }    
    DocumentManager::file_send_for_download($full_file_name);
}
exit;
