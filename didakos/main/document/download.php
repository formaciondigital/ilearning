<?php // $Id: download.php 12218 2007-05-01 18:27:14Z yannoo $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004 Dokeos S.A.
	Copyright (c) 2003 Ghent University (UGent)
	Copyright (c) 2001 Universite catholique de Louvain (UCL)
	Copyright (c) Olivier Brouckaert
	Copyright (c) Roan Embrechts
	Copyright (c) Sergio A. Kessler aka "sak"

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact: Dokeos, 181 rue Royale, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/
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

/*
==============================================================================
		FUNCTIONS
==============================================================================
*/
/* file_html_dynamic_parsing removed */
/* other functions updated and moved to lib/document.lib.php */

/*
==============================================================================
		MAIN CODE
==============================================================================
*/

session_cache_limiter('public');

include('../inc/global.inc.php');
//librería de configuración de oauth (Para validación de usuario).
include('../../ilearning/oauth/config.php');

$this_section=SECTION_COURSES;
include(api_get_path(LIBRARY_PATH).'document.lib.php');

// IMPORTANT to avoid caching of documents
header('Expires: Wed, 01 Jan 1990 00:00:00 GMT');
header('Cache-Control: public');
header('Pragma: no-cache');

// protection
// @egarcia 09/03/2011
// Esta protección impide descargar contenido de la carpeta document si no estamos logados en la plataforma
// Tenemos que "saltarla" en caso de una petición por parte de un usuario que esté trasteando la plataforma
// mediante ouath (Con app movil por ejemplo).
$headers = apache_request_headers();
foreach ($headers as $key=>$value) {
	if ($key=='Authorization')
	{
		$authoritation = explode(',', $headers[$key]);
		foreach ($authoritation as $key2=>$value2) {
			//tienen un espacio al inicio
			if ( substr($authoritation[$key2],1,18) =='oauth_consumer_key')
			{
				//eliminamos el nombre del campo y el ="
				$cadena = substr($authoritation[$key2],21);
				//eliminamos la comilla del final
				$consumer_key = substr($authoritation[$key2],21, strlen($cadena) -1 );				
			}
			if ( substr($authoritation[$key2],1,11) =='oauth_token')
			{
				//eliminamos el nombre del campo y el ="
				$cadena = substr($authoritation[$key2],14);
				//eliminamos la comilla del final
				$oauth_token = substr($authoritation[$key2],14, strlen($cadena) -1 );				
			}
		}
	}
}

//Recibimos por los headers Consumer_key, oauth_token y verifier.
//Buscamos en las tablas de oauth_server_registry y en  oauth_server_token con consumer_key, oauth_token y verifier
//obteniendo el user_id

// Si el token viene vacío es que no tenemos tokens por lo que añadimos la protección de ficheros.
// Solo se puede descargar si se está logado como alumno
if ($oauth_token=="")
{
    api_protect_course_script();
}
else
{
    // Si tenemos token es que venimos por otro lado por lo que vamos a ver que sea el correcto
    // Con el consumer_key, el token obtenemos el id de la tabla oauth_app_user (config.php)
    // Si es correcto nos saltamos la protección
    if (!GetUserIdByAccessToken ($oauth_token))
    {
        //Si es incorrecto... creo que no llegamos ni aquí, nos salta un error de token incorrecto.
        api_protect_course_script();
    }
}    
// Ya

$doc_url = $_GET['doc_url'];

//change the '&' that got rewritten to '///' by mod_rewrite back to '&'
$doc_url = str_replace('///', '&', $doc_url);
//still a space present? it must be a '+' (that got replaced by mod_rewrite)
$doc_url = str_replace(' ', '+', $doc_url);
$doc_url = str_replace('/..', '', $doc_url); //echo $doc_url;

include(api_get_path(LIBRARY_PATH).'events.lib.inc.php');

if (! isset($_course))
{
	api_not_allowed(true);
}

//if the rewrite rule asks for a directory, we redirect to the document explorer
if(is_dir(api_get_path(SYS_COURSE_PATH).$_course['path']."/document".$doc_url)) 
{
	//remove last slash if present
	//$doc_url = ($doc_url{strlen($doc_url)-1}=='/')?substr($doc_url,0,strlen($doc_url)-1):$doc_url; 
	//mod_rewrite can change /some/path/ to /some/path// in some cases, so clean them all off (René)
	while ($doc_url{$dul = strlen($doc_url)-1}=='/') $doc_url = substr($doc_url,0,$dul);
	//group folder?
	$gid_req = ($_GET['gidReq'])?'&gidReq='.$_GET['gidReq']:'';
	//create the path
	$document_explorer = api_get_path(WEB_CODE_PATH).'document/document.php?curdirpath='.urlencode($doc_url).'&cidReq='.$_GET['cidReq'].$gid_req;
	//redirect
	header('Location: '.$document_explorer);
}

// launch event
event_download($doc_url);
$sys_course_path = api_get_path(SYS_COURSE_PATH);
$full_file_name = $sys_course_path.$_course['path'].'/document'.$doc_url;
$full_file_name = $sys_course_path.$_GET['cDir'].'/document'.$doc_url;
DocumentManager::file_send_for_download($full_file_name);
?>
