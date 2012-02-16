<?php

/*
CABECERA

*/

// Including some php from platform for basic configuration
include_once(dirname ( __FILE__ )."/../../main/inc/lib/database.lib.php");
include(dirname ( __FILE__ )."/../../main/inc/conf/configuration.php");

// Including all thrift libs
$GLOBALS['THRIFT_ROOT'] = dirname(__FILE__).'/thrift';
require_once $GLOBALS['THRIFT_ROOT'].'/Thrift.php';
require_once $GLOBALS['THRIFT_ROOT'].'/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TPhpStream.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TBufferedTransport.php';
$GEN_DIR = dirname(__FILE__).'/thrift/packages';
require_once $GEN_DIR.'/ilearning/ILearning.php';
require_once $GEN_DIR.'/ilearning/ilearning_types.php';

// All Database and URL configuration
$db_host = $_configuration['db_host'];
$db_user = $_configuration['db_user'];
$db_pass = $_configuration['db_password'];
// Be carefull if you are using two databases on the same server at the same time.  By default
// mysql_connect returns the same connection ID for multiple calls with the same server parameters.
// To prevent errors there is a parameter to mysql_connect to force the creation of a new link.
$conn = mysql_connect ($db_host,$db_user,$db_pass,true);
// DB prefixes
$db_videomodel = "fd_videomodel";
$TABLECOURSE = Database :: get_main_table(TABLE_MAIN_COURSE);
$oauth_database = $_configuration['db_prefix']. "oauth";
$db_main_database = $_configuration['main_database'];
$db_prefix = $_configuration['db_prefix'];
$ruta_web = $_configuration['root_web'];
$db_stats_database = $_configuration['statistics_database'];

// Thrift class
class ILearningHandler implements ILearningIf {
  	protected $log = array();

public function checkVersion($par_version){
    // check if a new version is available.
    // Posible values are: checkVersionNew and checkVersionNewCritical
    $version= new RetVersion();
    $version->needUpdate=False;
    $version->msg="";
 
    return $version;
}

public function getLecturesScos($course_id,$sco_id){
    // Return videos from a videomodel course.
    // $sco_id is lp_item table id       
    global $conn;
    global $db_videomodel;
    global $db_prefix;
   	global $user_id;
   	
    $videos = array();
     if (preg_match("/demo/i",$course_id ))
     {
        // If course is a "demo" version we limit visible videos to 2.
        $limit = " limit 2";
     }
    // First of all we need launch_data values from lp_item table
    $sql = 'select launch_data from lp_item where id=' . $sco_id ;
	mysql_select_db($db_prefix.$course_id);    
    $rs = mysql_query($sql,$conn);
    $row = mysql_fetch_array($rs);
    // Got it! Now Select all videos from this sco.       
    $sql = "select p.id,s.titulo as title,p.titulo,p.descripcion,p.link from cursos_scos cs, scos s, sco_paginas sp, paginas p, cursos c where c.identificadorFD=" . substr($course_id,0,5) . " and c.id=cs.curso_id and s.id=".getVideomodelScoId($row["launch_data"])." and s.id=cs.sco_id and s.id=sp.sco_id and p.id=sp.pagina_id and p.id in(". getVideomodelPageslist($row["launch_data"]). ") order by p.id" . $limit;
    
    mysql_select_db($db_videomodel);	
    $rs = mysql_query($sql,$conn);
    while($row = mysql_fetch_array($rs)){      
        $video = new LecturesScos();
        $video->lecture_id = $row["id"];
        $video->modulo = $row["title"] ;
        $video->title = limpiarcadena($row["titulo"]);
        $video->text = limpiarcadena($row["descripcion"]);
        $video->url = getSignedURL('http://videos.formaciondigital.com/' . $row["link"]. '.mp4', 3000, 'canned');
        $video->img = getSignedURL('http://videos.formaciondigital.com/' . $row["link"]. '.jpg', 3000, 'canned');
        // Searh for suspend_data to see if video is completed.
        $video->completed = isVideoCompleted ($course_id,$sco_id,$row["id"]);                
        $videos[]=$video;
    }    
    return $videos;
}

public function getScos($course_id){
    // Return scos from a videomodel course.
    global $conn;
    global $db_prefix;
   	global $user_id;
   	global $db_videomodel;
   	
    $scos = array();
    // Get values from lp_item table.
    $sql = 'select id,title,description,launch_data from lp_item where item_type="sco" order by lp_id,display_order';
	mysql_select_db($db_prefix.$course_id);    
    $rs = mysql_query($sql,$conn);
    while($row = mysql_fetch_array($rs)){      
	    $sco = new Scos();
	    $sco->id_sco = $row["id"];
	        //Now we are going to use videomodel database to obtain title and description. 
	        // Extracting sco_id from launch_data.   
	        $sql = "select s.id,s.titulo,s.descripcion from cursos_scos cs, scos s where s.id=" .getVideomodelScoId($row["launch_data"]). " and s.id=cs.sco_id";
        	mysql_select_db($db_videomodel);    
            $rs2 = mysql_query($sql,$conn);
            $row2 = mysql_fetch_array($rs2);      
	        $sco->title = $row2["titulo"];
	        $sco->text = $row2["descripcion"];
	    // Count number of videos inside.
	    // Launch_data is something like "54#1,2,3,4,5,6" so if we split it using "," as separator 
	    // we get the number of videos. First number is sco_id and the others are ordered video id's.
  	    if (preg_match("/demo/i",$course_id ))
         {
            // If course is a "demo" version we limit visible videos to 2.
            $pages_count = 2;
         } 
         else
         {
            $temp = getVideomodelPagesArray ($row["launch_data"]);
      	    $pages_count = count($temp);	   
         }
	    // Is sco completed by the user? True or False.
  	    $sco->completed = isScoCompleted($course_id,$row["id"],$pages_count);
	    $scos[] = $sco;
	}
	return $scos;
}

public function saveLecturesScos($course_id,$sco_id,$video_id){
    // Save learner score
    global $conn;
    global $db_prefix;
   	global $user_id;  	
  	
  	// If video is already completed, we don't need to do all this.
  	if (!isVideoCompleted ($course_id,$sco_id,$video_id))
  	{
       	mysql_select_db($db_prefix.$course_id);
	    // Select last lp_view row, if there is no rows on lp_view we must insert first one.
	    $sql = "select id as id_view from lp_view where user_id=" . $user_id . " and lp_id=" . $sco_id;
	    $rs = mysql_query($sql,$conn);
	    $row = mysql_fetch_array($rs);

	    if (mysql_num_rows($rs)>0)
	    {
		    $lp_view_id = $row["id_view"];
	    }
	    else
	    {
	        // Adding first row for this user
		    $sql = "insert into lp_view (lp_id, user_id, view_count) values (".$sco_id.", ". $user_id . ", 1)";
            $rs = mysql_query($sql,$conn);
	        // Look for last inserted id
		    $sql = "select max(id) as id_view from lp_view where user_id=" . $user_id . " and lp_id=" . $sco_id;;
		    $rs = mysql_query($sql,$conn);
	        $row = mysql_fetch_array($rs);
	        // Take last Id
	        $lp_view_id = $row["id_view"];		

	    }   
	    
	    // Get sco_id Launch_data and calculate pages, key of the video and score 
        $sql = 'select launch_data from lp_item where id=' . $sco_id ;
        mysql_select_db($db_prefix.$course_id);    
        $rs = mysql_query($sql,$conn);
        $row = mysql_fetch_array($rs);   
    	$pages_array = getVideomodelPagesArray ($row["launch_data"]);             
		$key = GetKeyFromPagesArray ($pages_array,$video_id);
        $total_videos = count($pages_array);
        		
        // Select lp_item_view id, if there is no rows on lp__item_view we must insert first one.
        $sql = "select id,suspend_data from lp_item_view where lp_view_id=" . $lp_view_id. " and lp_item_id=" . $sco_id;  
        $rs = mysql_query($sql,$conn);
	    $row = mysql_fetch_array($rs);
        if (mysql_num_rows($rs)>0)
	    {
	        // Update scorm information
		    $lp_item_view_id = $row["id"];
		    $suspend_data = $row["suspend_data"];	    
	        // New suspend_data string
	        if ($suspend_data=="")
	        {
	            $suspend_data = $key;
	        }
	        else
	        {
	            $suspend_data = $suspend_data . "#" . $key;
	        }
	        
	        $temp = explode("#",$suspend_data);
            $visitados = count($temp);
            $score = round(($visitados * 100) / $total_videos,2);
	        // Calculate new score.
            // Update values	        
		    $sql = "update lp_item_view set suspend_data='" .$suspend_data. "',score=" .$score. " where id=" . $lp_item_view_id;
		    $rs = mysql_query($sql,$conn);		
	    }
	    else
	    {       
	    
	        $temp = explode("#",$suspend_data);
            $visitados = count($temp);
            $score = round(($visitados * 100) / $total_videos,2);
	        //Insert Scorm information, suspend_data has only this new value ($key)
		    $sql = "insert into lp_item_view (lp_item_id, lp_view_id, view_count, start_time, status,score,suspend_data) values (".$sco_id.",". $lp_view_id .",1,unix_timestamp(CURRENT_TIMESTAMP()),'completed',".$score.",'" .$key ."')";		
           	 $rs = mysql_query($sql,$conn);			        
	    }
    }
    // End
	return True;
}

public function getLectures($course_id){
// Old version. return SCOS example videos.
// New version uses getScos and getLecturesScos
/*
    $url_canned = getSignedURL('http://videos.formaciondigital.com/fd1234.mp4', 3000, 'canned');

    $video = new Lectures();
    $video->id="1234";
    $video->title = "prueba";
    $video->text = "video de prueba texto";
    $video->url = $url_canned;
    $video->modulo = "Modulo";
    $video->size = "42";
    $videos[]=$video;
    return $videos;
*/

    $video = new Lectures();
    $video->id="20823738";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->title = "Insertar y sobrescribir texto";
    $video->text = "En este vídeo mostramos cómo podemos escribir y texto en Word 2010.";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20823738.m4v', 3000, 'canned');
    $video->size = "152.57";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20824073";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo mostramos cómo borrar texto en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20824073.m4v', 3000, 'canned');
    $video->title = "Borrar texto";
    $video->size = "";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20824701";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo mostramos la utilidad y la forma de activar las etiquetas inteligentes en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20824701.m4v', 3000, 'canned');
    $video->title = "Etiquetas inteligentes";
    $video->size = "248.36";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20825125";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre cómo crear saltos de línea y de página en Word 2010.";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20825125.m4v', 3000, 'canned');
    $video->title = "Saltos de línea y saltos de página";
    $video->size = "164.23";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20825629";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre las posibilidades de modificaciones de las fuentes que permite Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20825629.m4v', 3000, 'canned');
    $video->title = "Fuente del texto";
    $video->size = "505.31";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20826481";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre los estilos tipográficos.";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20826481.m4v', 3000, 'canned');
    $video->title = "Estilo del texto";
    $video->size = "282.30";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20827124";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre la minibarra de formato";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20827124.m4v', 3000, 'canned');
    $video->title = "Minibarra de formato";
    $video->size = "97.57";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20827413";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre cómo utilizar la herramienta de copiar formato";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20827413.m4v', 3000, 'canned');
    $video->title = "Copiar formato";
    $video->size = "142.59";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20827892";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre la revisión ortográfica y gramatical de Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20827892.m4v', 3000, 'canned');
    $video->title = "Revisión ortográfica y gramatical";
    $video->size = "421.44";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20828926";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre las posibilidades de la autocorrección en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20828926.m4v', 3000, 'canned');
    $video->title = "Autocorrección";
    $video->size = "224.62";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20829276";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre cómo buscar y reemplazar texto en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20829276.m4v', 3000, 'canned');
    $video->title = "Buscar y reemplazar texto";
    $video->size = "356.65";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20830280";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre cómo seleccionar texto en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20830280.m4v', 3000, 'canned');
    $video->title = "Seleccionar texto";
    $video->size = "280.10";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20833701";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo hablaremos sobre cómo copiar, cortar y pegar texto en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20833701.m4v', 3000, 'canned');
    $video->title = "Copiar, cortar y pegar texto";
    $video->size = "344.46";
    $videos[]=$video;

    $video = new Lectures();
    $video->id="20837729";
    $video->modulo = "Trabajar con texto en Word 2010";
    $video->text = "En este vídeo vamos a realizar un resumen de todos los conceptos básicos sobre Trabajar con texto en Word 2010";
    $video->url = getSignedURL('http://videos.formaciondigital.com/iphone/20837729.m4v', 3000, 'canned');
    $video->title = "Recuerda";
    $video->size = "440.59";
    $videos[]=$video;

    
   return $videos;
}

public function logoutCourse($course_id){

    // Logout from platform
    // Insert logout date on track_e_course_access table, search for the last login (with logout=null) for update.
	global $conn;
        global $db_prefix;
	global $user_id;
        global $db_stats_database;

	$sql = "select max(course_access_id) as access_id from track_e_course_access where course_code='" .$course_id ."' and user_id= ". $user_id . " and ISNULL(logout_course_date)";
	mysql_select_db($db_stats_database);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);

	if (mysql_num_rows($rs)>0)
	{
		// We have a previous login, update the row to add logout date.
		$sql = "update track_e_course_access set logout_course_date=now()
		 where course_access_id=" .$row["access_id"];
		mysql_select_db($db_stats_database);
		$rs = mysql_query($sql,$conn);
	}
	return True;	
}

public function loginCourse($course_id){

	// Platform login
	// Save login date on track_e_course_access table. Logout is null.
	global $conn;
   	global $db_prefix;
	global $user_id;
        global $db_stats_database;
	if (IsUserAllowed)
	{
	$sql = "insert into track_e_course_access (course_code,user_id,login_course_date,counter) 
	values ('".$course_id."',".$user_id.",now(),1)";
	mysql_select_db($db_stats_database);
	$rs = mysql_query($sql,$conn);
	return True;
	}
	else
	{return False;}
}

public function saveLecture($course_id,$lecture_id,$time,$score,$status)
	{

    // Save the learner progress on a course
	global $conn;
    global $db_prefix;
	global $user_id;

	mysql_select_db($db_prefix.$course_id);
	// Select last lp_view row, if there is no rows on lp_view we must insert first one.
	$sql = "select max(id) as id_view from lp_view where user_id=" . $user_id . " and lp_id=" . $lecture_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if (mysql_num_rows($rs)>0)
	{
		$lp_view_id = $row["id_view"];
	}
	else
	{
		// Adding first row
		$sql = "insert into lp_view (lp_id, user_id, view_count) values (1, ". $user_id . ", 1)";
		$lp_view_id = 1;
		$rs = mysql_query($sql,$conn);
	}

	// Select lp_item_view row, if there is no rows on lp__item_view we must insert first one.
    $sql = "select id,start_time from lp_item_view where lp_view_id=" . $lp_view_id. " and lp_item_id=" .       $lecture_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);

	if (mysql_num_rows($rs)>0)
	{
		// There is a row, update it
		// Time on suspend_data for video model courses
		$lp_item_view_id = $row["id"];
		$sql = "update lp_item_view set start_time=unix_timestamp(CURRENT_TIMESTAMP()), status='".$status."',total_time=" .$time. ",suspend_data=" .$time. ",score=" .$score. " where id=" . $lp_item_view_id;
		$rs = mysql_query($sql,$conn);		
	}
	else
	{
 		// No row. Insert first one
		$sql = "insert into lp_item_view (lp_item_id, lp_view_id, view_count, start_time, status,score,suspend_data,total_time) values (".$lecture_id.",". $lp_view_id .",1,unix_timestamp(CURRENT_TIMESTAMP()),'".$status."',".$score."," .$time .",".$time .")";		
       	 $rs = mysql_query($sql,$conn);		
	}
  return True;
}


public function getSupportedContents($course_id){
    /*
     Select all available tools for this course.
     Course_content is an array with values from 0 to 15.
        0 	    clases		    lp_item
	    1   	kExams		    lp_item
	    2   	kExercises	    tool
	    3    	kVideos,	    external videos
	    4    	kLinks,		    tool
	    5    	kDocuments,	    tool
	    6    	kPodcasts,	    tool
	    7    	kMailbox,	    tool
	    8    	kForums,	    tool
	    9    	kNews,		    tool
	    10    	kCalendar,	    tool
	    11    	kChat,		    tool
	    12   	kPolls,		    tool
	    13    	kDescription,   tool
	    14    	kProgress,	    tool
	    15    	kHelp		    tool
	    
	  Course_content returns only values of available tools
    */
    	    
	global $conn;
   	global $db_prefix;
	global $user_id;
	global $db_videomodel;
	$course_content = array();
	
	// first, we login.
	$login = self::loginCourse($course_id);
    // We are going to discrimine between "normal courses" html,js,flash... and new video model courses
    // so, only will return the sco tool if the course has new video model sco's
    // Need to take care of the real course code, we delete all string after first five digits.
    $course_code = substr($course_id,0,5);
    // If course_id is not well used at plattform this is not going to work properly.
    // To prevent errors using two databases on the same server there is a parameter 
    // to mysql_connect to force the creation of a new link.
    $sql = "select count(identificadorFD) as sco from cursos where identificadorFD=" . $course_code;
    mysql_select_db($db_videomodel);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ($row["sco"]>0) 
	{
	    // We have SCO's
		$course_content[] = 0;
	}

	// Exams. Is a quiz that was inserted on lp_item. Normaly with visibility=0 on quiz tool.
	$sql = "select count(id) as quiz from lp_item where item_type='quiz'";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ($row["quiz"]>0)
	{
		$course_content[] = 1;
	}
	
	// Videos... Always active, at this moment.
	$course_content[] = 3;

	// Other tools. Look for visibility status on course.
	$sql = "select name from tool where visibility=1 and admin=0 order by id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		switch ($row["name"])
		{
			case "quiz":
				$course_content[] = 2;
				break;
			case "link":
				$course_content[] = 4;
				break;
			case "document":
				$course_content[] = 5;
				break;
			case "Podcast":
				$course_content[] = 6;
				break;
			case "Dmail":
				// $course_content[] = 7;
				// Not implemented on first version
				break;
			case "forum":
				$course_content[] = 8;
				break;
			case "announcement":
				$course_content[] = 9;
				break;
			case "calendar_event":
				$course_content[] = 10;
				break;
			case "chat":
				$course_content[] = 11;
				break;
			case "survey":
				$course_content[] = 12;
				break;
			case "course_description":
				$course_content[] = 13;
				break;
			case "tracking":
				$course_content[] = 14;
				break;
			// three possible options here. first one is the valid one. Other are older versions.
			case "Serviciotecnico":
				$course_content[] = 15;
				break;
			case "Servicio tecnico":
				$course_content[] = 15;
				break;
			case "Servicio t&eacute;cnico":
				$course_content[] = 15;
				break;
		}
	}

	// Progress.
	// There is no a Progress tool in Dokeos. Always active.
	$course_content[] = 14;
	
     sort($course_content);
     return $course_content;
  }

 public function getSupportedContentsVersion($course_id,$version){
    // Return available tools for a course and app version.
    // No version use at this moment.
    global $conn;
   	global $db_prefix;
	global $user_id;
	global $db_videomodel;
	$course_content = array();
	
	// first, we login.
	$login = self::loginCourse($course_id);
    // We are going to discrimine between "normal courses" html,js,flash... and new video model courses
    // so, only will return the sco tool if the course has new video model sco's
    // Need to take care of the real course code, we delete all string after first five digits.
    $course_code = substr($course_id,0,5);
    // If course_id is not well used at plattform this is not going to work properly.
    // To prevent errors using two databases on the same server there is a parameter 
    // to mysql_connect to force the creation of a new link.
    $sql = "select count(identificadorFD) as sco from cursos where identificadorFD=" . $course_code;
    mysql_select_db($db_videomodel);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ($row["sco"]>0) 
	{
	    // We have SCO's
		$course_content["0"] = 0;
	}

	// Exams. Is a quiz that was inserted on lp_item. Normaly with visibility=0 on quiz tool.
	$sql = "select count(id) as quiz from lp_item where item_type='quiz'";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ($row["quiz"]>0)
	{
		$course_content["1"] = 1;
	}
	
	// Videos... Always active, at this moment.
	//$course_content[] = 3;

	// Other tools. Look for visibility status on course.
	$sql = "select name from tool where visibility=1 and admin=0 order by id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		switch ($row["name"])
		{
			case "quiz":
				$course_content["2"] = 2;
				break;
			case "link":
				$course_content["3"] = 4;
				break;
			case "document":
				$course_content["4"] = 5;
				break;
			case "Podcast":
				$course_content["5"] = 6;
				break;
			case "Dmail":
				 $course_content["7"] = 7;
				break;
			case "forum":
				$course_content["8"] = 8;
				break;
			case "announcement":
				$course_content["9"] = 9;
				break;
			case "calendar_event":
				$course_content["10"] = 10;
				break;
			case "chat":
				$course_content["11"] = 11;
				break;
			case "survey":
				$course_content["12"] = 12;
				break;
			case "course_description":
				$course_content["13"] = 13;
				break;
			case "tracking":
				$course_content["15"] = 14;
				break;
			// three possible options here. first one is the valid one. Other are older versions.
			case "Serviciotecnico":
				$course_content["16"] = 15;
				break;
			case "Servicio tecnico":
				$course_content["16"] = 15;
				break;
			case "Servicio t&eacute;cnico":
				$course_content["16"] = 15;
				break;
			case "multimedia":
			    // TODO if (IsAvaliableForVersion($version,"1.1.2"))
				if ( $version>"1.1.1")
				{
				  $course_content["6"] = 16;
				}
				break;
		}
	}

	// Progress.
	// There is no a Progress tool in Dokeos. Always active.
	$course_content["14"] = 14;
	ksort($course_content);
    return $course_content;
 }
 
  public function getCourses (){
	/*
	 Select all learner's courses. 
     A learner is subscribed to a course when we have a row on course_rel_user_fd table and f_finalizacion
     is not exausted.
	*/
	global $user_id;
	global $conn;
	global $db_main_database;
	$courses = array();

	$sql = "select c.code,
                c.directory,
                c.db_name,
                c.course_language,
                c.title,c.description,c.course_language, DATEDIFF(CURDATE(),r.f_finalizacion) as ff
                from course as c, course_rel_user_fd r, user u
                where c.code=r.course_code and u.user_id=r.user_id and u.user_id = ".$user_id." order by c.title";
     	mysql_select_db($db_main_database);
     	$rs = mysql_query($sql,$conn);
     	while($row = mysql_fetch_array($rs)){      
		$course = new Course();
		$course->code = $row["code"];
		$course->directory = $row["directory"];
		$course->db_name = $row["db_name"];
		$course->course_language = $row["course_language"];
		$course->title = $row["title"];
		$course->coursedescription = $row["description"];
        
        // Look at ff, if it is a positive value the course subscription has expired.	
		if ($row["ff"]!=null && $row["ff"] >=0)
		{ 
			$course->active = 0; 
		}
		else
		{
			$course->active = 1; 
		}
		$courses[] = $course;
        }
   return $courses;
  }

  public function getExams($course_id){
	
	// New version !!
	// Select al Exams from course. In dokeos we consider a exam a quiz that has values on quiz_exams table.
	global $conn;
	global $db_prefix;
	global $user_id;
        global $db_stats_database;
	$Exams = array();

   	$sql = "select id, title, description, type from quiz where random>=10 and id in (select id from quiz_exam)";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);

	    while($row = mysql_fetch_array($rs)){
		$Exam = new Quiz();
		$Exam->id = $row["id"];
		$Exam->title = $row["title"];
		$Exam->quizdescription = limpiarcadena(strip_tags($row["description"]));
		$Exam->type = $row["type"];
		/*
		    Now we search for the exam score. If there are more than one score we take the last one.
		    Real score (0-10) is based on this formula: 
		    (exe_result*10)/exe_weighting
		*/
		$sql2= "select exe_id,exe_result,exe_weighting from track_e_exercices 
		where exe_cours_id='".$course_id ."' and exe_user_id=" . $user_id . " 
		and exe_exo_id=".$row["id"]. " order by exe_date desc";
		mysql_select_db($db_stats_database);
		$rs2 = mysql_query($sql2,$conn);
		$row2 = mysql_fetch_array($rs2);
		    if (mysql_num_rows($rs2) > 0 )
		    {
			    $Exam->score = round($row2["exe_result"]*10/$row2["exe_weighting"],1);
		    }
		    else
		    {
		         // without score. Not realized.
			     $Exam->score = -1;
		    }

		// Now look for MAX exam attempt
		$sql3 = "select intentos from quiz_exam where id=". $row["id"];
		mysql_select_db($db_prefix.$course_id);
		$rs3 = mysql_query($sql3,$conn);
		$row3 = mysql_fetch_array($rs3);
		$Exam->max_intentos = $row3["intentos"];

		// Count all attempt
		$sql4 = "select count(exe_id) as total from track_e_exercices where exe_cours_id='".$course_id ."' and exe_user_id=" . $user_id . " 
		and exe_exo_id=".$row["id"];
		mysql_select_db($db_stats_database);
		$rs4 = mysql_query($sql4,$conn);
		$row4 = mysql_fetch_array($rs4);
		$Exam->num_intentos = $row4["total"];

		$Exams[] = $Exam;
	    }
	return $Exams;
  }

  public function getExercises($course_id){
  
	// Select al quizs from course. In dokeos we consider a quiz if it's active (active=1) and has 
	// 0 value on "random".
	global $conn;
	global $db_prefix;
	global $user_id;
        global $db_stats_database;
	$Exercises = array();

	$sql = "select id, title, description, type from quiz where active=1 and type<>6 and random=0";
	// Type 6 not implemented on App.
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Exercise = new Quiz();
		$Exercise->id = $row["id"];
		$Exercise->title = $row["title"];
		$Exercise->quizdescription = limpiarcadena(strip_tags($row["description"]));
		$Exercise->type = $row["type"];
        /*
		    Now we search for the quiz score. If there are more than one score we take the last one.
		    Real score (0-10) is based on this formula: 
		    (exe_result*10)/exe_weighting
		*/
		$sql2= "select exe_id,exe_result,exe_weighting from track_e_exercices 
		where exe_cours_id='".$course_id ."' and exe_user_id=" . $user_id . " 
		and exe_exo_id=".$row["id"]. " order by exe_id desc";
		mysql_select_db($db_stats_database);
		$rs2 = mysql_query($sql2,$conn);
		$row2 = mysql_fetch_array($rs2);
            if (mysql_num_rows($rs2) > 0 )
		    {
			    $Exercise->score = round($row2["exe_result"]*10/$row2["exe_weighting"],1);
		    }
		    else
		    {
        		// without score. Not realized.
			    $Exercise->score = -1;
		    }
		$Exercises[] = $Exercise;
        }
	return $Exercises;
    }

  public function getQuestions($course_id, $quiz_id){
	
	// Get 10 random questions from a quiz
	global $conn;
	global $db_prefix;
	$Questions = array();

	$sql = "select qq.id,qq.question,qq.description,qq.ponderation,qq.position,qq.type,qq.picture
	from quiz q,quiz_question qq,quiz_rel_question qrq  
	where qrq.question_id = qq.id and qrq.exercice_id= q.id and q.id=". $quiz_id ." ORDER BY RAND() LIMIT 10";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Question = new Question();
		$Question->id = $row["id"];
        $Question->question = limpiarcadena(strip_tags($row["question"]));
        $Question->questiondescription = limpiarcadena(strip_tags($row["description"]));
		$Question->ponderation = $row["ponderation"];
		$Question->position = $row["position"];
		$Question->type = $row["type"];
		$Question->picture = $row["picture"];
		$Questions[] = $Question;
	 }
	return $Questions;
  }

	public function getQuestionsExercices($course_id, $quiz_id){
    
    // Get all questions for an Exercice
    global $conn;
	global $db_prefix;
	$Questions = array();

	$sql = "select qq.id,qq.question,qq.description,qq.ponderation,qq.position,qq.type,qq.picture from quiz q,quiz_question qq,quiz_rel_question qrq where qrq.question_id = qq.id and qrq.exercice_id= q.id and q.id=". $quiz_id ." and qq.type<>6 ORDER BY q.id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Question = new Question();
		$Question->id = $row["id"];
		$Question->question = limpiarcadena(strip_tags($row["question"]));
		$Question->questiondescription = limpiarcadena($row["description"]);
		$Question->ponderation = $row["ponderation"];
		$Question->position = $row["position"];
		$Question->type = $row["type"];
		$Question->picture = $row["picture"];
		$Questions[] = $Question;
	 }
	return $Questions;
  }

  public function getAnswers($course_id, $quiz_id){

    // Get answers for a quiz or Exam.
	global $conn;
	global $db_prefix;
	$Answers = array();

	// First of all we get the questions
	$Questions = self::getQuestions($course_id, $quiz_id);
	foreach ($Questions as $Question)
	{
		// Now for each question...
		$tmpAnswers = array();
		$sql = "select qa.*
		from quiz q,quiz_question qq,quiz_rel_question qrq, quiz_answer qa
		where qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." order by qa.question_id, qa.position";
		mysql_select_db($db_prefix.$course_id);
		$rs = mysql_query($sql,$conn);
		while($row = mysql_fetch_array($rs)){
			$tmp = new Answer();	
			$tmp->id = $row["id"];
			$tmp->answer = limpiarcadena(strip_tags($row["answer"]));
			$tmp->comment = '';
			$tmp->correct = $row["correct"];
			$tmp->position = $row["position"];
			$tmp->ponderation = $row["ponderation"];
			$tmpAnswers[]= $tmp;
		 }
		$Answers[] = $tmpAnswers;
	}
	return $Answers;
  }

  public function getCourseDescription($course_id){
    
    // Get course description
	global $conn;
	global $db_prefix;
	$CD = array();
	$sql = "select id,title,content from course_description order by id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Coursedescription = new CourseDescription();
		$Coursedescription->id = $row["id"];
		
		/*
        ################### Revisar
	    estaba repetido y no debería llevar HTML
		$Coursedescription->title = "<div style='font-family:Helvetica; font-size:11pt; background-color:#FFFFFF; border: 1px solid #006699; padding: 5px; border-radius: 10px;'>".limpiarcadena(strip_tags($row["title"]))."</div>";
		$Coursedescription->content = "<div style='font-family:Helvetica; font-size:10pt; background-color:#FFFFFF; border: 1px solid #006699; padding: 5px; border-radius: 10px;'>".limpiarcadena(strip_tags($row["content"]))."</div>";*/
		$Coursedescription->title = "<div style='font-family:Helvetica; font-size:11pt; background-color:#FFFFFF; border: 1px solid #006699; padding: 5px; border-radius: 10px;'>".(strip_tags($row["title"]))."</div>";
		$Coursedescription->content = "<div style='font-family:Helvetica; font-size:10pt; background-color:#FFFFFF; border: 1px solid #006699; padding: 5px; border-radius: 10px;'>".$row["content"]."</div>";
		$CD[] = $Coursedescription;
	 }
	return $CD;
  }

  public function getLinkCategories($course_id){

    // Get link categories
	global $conn;
	global $db_prefix;
	$LinkCategories = array();
	$sql = "select id,category_title, description,display_order from link_category order by display_order";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	
	//Main Categorie does not exist in Dokeos. So we create it here.
	$LinkCategorie = new LinkCategory();
	$LinkCategorie->id = 0;
	$LinkCategorie->title = 'General';
	$LinkCategorie->categorydescription = 'Categoría principal';
	$LinkCategories[] = $LinkCategorie;

	while($row = mysql_fetch_array($rs)){	
		$LinkCategorie = new LinkCategory();	
		$LinkCategorie->id = $row["id"];
		$LinkCategorie->title = $row["category_title"];
		$LinkCategorie->categorydescription = $row["description"];
		$LinkCategories[] = $LinkCategorie;
	 }

	return $LinkCategories;

  }

  public function getLinks($course_id){

    // Get links from a course, first of all look for categories.
	$LinkCategories = self::getLinkCategories($course_id);
	global $conn;
	global $db_prefix;
	$recolector = array();
	foreach ($LinkCategories as $category)
	{
		$Links =  array() ;
		$sql = "select id,title,url,description from link where category_id=" . $category->id . " order by display_order";
		mysql_select_db($db_prefix.$course_id);
		$rs = mysql_query($sql,$conn);
		while($row = mysql_fetch_array($rs)){
			$Link = new Link();
			$Link->id = $row["id"];
			$Link->title = $row["title"];
			$Link->url = $row["url"];
			$Link->linkdescription = $row["description"];
			$Links[] = $Link;
		 }
		$recolector[] = $Links;
	}
	return $recolector;
  }

  public function getChatItems($course_id, $timestamp){
    
    // Search for stored chat conversations.
    global $conn;
	global $db_prefix;
	global $db_main_database;
	$Chat = array();
	$sql = "select leafvalue from fchat where server='" . $course_id . "' and subgroup='ch_" . $course_id ."' and timestamp>=" . $timestamp. " order by timestamp desc limit 100";
	mysql_select_db($db_main_database);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
	    // Search chat actions. We look for "leafvalue".
	    // leafvalue contains plain-text string with \t, we can split it.
		$datos =  explode("\t", $row["leafvalue"]);
		if ( count($datos)==5)
		{
		    // We are going to get values only if result count it's 5. 
		    // Connection, exit and message.
			$Chatline = new ChatItem();
			$Chatline->timestamp = $datos[1];
			$Chatline->who = $datos[2];
			$Chatline->action = $datos[3];
			$Chatline->message = utf8_decode($datos[4]);
			if ( strpos($datos[4], 'se une a'))
			{
			    // Chat connection
				$Chatline->action = 'in';
				$Chatline->message = '';
			}
			else
			{
				if ( strpos($datos[4], 'se ha desconectado'))
				{
				    // Chat exit
					$Chatline->action = 'out';
					$Chatline->message = '';
				}
			}
			
			$Chat[] = $Chatline;
		}
	 }
	return $Chat;
   }

  public function newChatItem($course_id, $who, $type, $msg){
  
    // Insert a new message on the chat
	global $conn;
	global $db_prefix;
	global $db_main_database;
    mysql_select_db($db_main_database);
    
	// Get last item on chat table
	$sql = "select leafvalue from fchat where server='" . $course_id . "' and leaf='lastmsgid'";
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	    if ($row[0]==0)
	    {
		    // Nothing in the chat, first connection.
		    $lastmsgid = 1;
		    $sql = "insert into fchat values ('".$course_id."','channelid-to-msg','ch_".$course_id."','lastmsgid','" . $lastmsgid . "','" . time() ."')";
		    mysql_query($sql,$conn);
	    }
	    else
	    {
		    // insert the massage
		    $lastmsgid = $row[0] + 1;
		    $sql = "insert into fchat values ('".$course_id."','channelid-to-msg','ch_" . $course_id ."','" . $lastmsgid . "','\n" . $lastmsgid ."\t".time()."\t".$who."\t".$type."\t".$msg."','".time()."')";
		    mysql_query($sql,$conn);
		    // Update lastmsgid
		    $sql = "update fchat set leafvalue='".$lastmsgid."' where server='".$course_id."' and leaf='lastmsgid' and subgroup='ch_".$course_id."'";
        	mysql_query($sql,$conn);
	    }
    return True;
  }
  
  public function getCalendarItems($course_id){

	// Get Calendar events.
	global $conn;
	global $db_prefix;
	$Calendar = array();
	$sql = "select id,title,content,start_date,end_date from calendar_event order by start_date";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$event = new CalendarItem();
		$event->id = $row["id"];
		$event->title = $row["title"];
		$event->content = $row["content"];
		$event->startdate = strtotime($row["start_date"]);
		$event->enddate = strtotime($row["end_date"]);
		$Calendar[] = $event;
	 }
	return $Calendar;

  }
  
  public function getForumCategorys($course_id){

    // Get course forum categories
    // Each categorie has an array with the forums
	global $conn;
	global $db_prefix;
	$categories = array();
	$sql = "select cat_id,cat_title from forum_category order by cat_order";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$category = new ForumCategory();
		$category->id = $row["cat_id"];
		$category->title = $row["cat_title"];	
		$sql = "select forum_id,forum_title from forum_forum where forum_category=" . $category->id;
		$rs2 = mysql_query($sql,$conn);
		$forums = array();
		while($row2 = mysql_fetch_array($rs2)){
			$forum = new ForumForum();
			$forum->id = $row2["forum_id"];
			$forum->title = $row2["forum_title"];
			$forums[] = $forum;
		}
		$category->forums =  $forums;
		$categories[] = $category;
	 }
	return $categories;
  }
  
  public function getForumThreads($course_id, $forum_id){
	
	// Get forum threads.
	global $conn;
	global $db_prefix;
	$threads = array();
	$sql = "select thread_id,thread_title,thread_date from forum_thread where forum_id=". $forum_id. " order by thread_date desc";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$thread = new ForumThread();
		$thread->id = $row["thread_id"];
		$thread->title = $row["thread_title"];
		$thread->date = strtotime($row["thread_date"]);
		$threads[] = $thread;
	 }
	return $threads;
  }
  
  public function getForumPosts($course_id, $thread_id){

    // Get post of the expecified thread and search for poster fistname and lastname.
    global $conn;
	global $db_prefix;
	global $db_main_database;
	$posts = array();
	$sql = "select post_id, post_title, post_text,post_date, poster_id from forum_post where thread_id=". $thread_id. " order by post_parent_id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$post = new ForumPost();
		$post->id = $row["post_id"];
		$post->title = $row["post_title"];
		$post->text = $row["post_text"];
		// look for user values.
		mysql_select_db($db_main_database);
		$sql2 = "select firstname,lastname from user where user_id=" . $row["poster_id"];
		$rs2 = mysql_query($sql2,$conn);
		$row2 = mysql_fetch_array($rs2);
		$post->poster_name = $row2["firstname"] . ' '. $row2["lastname"];
		$post->date = strtotime($row["post_date"]);
		$posts[] = $post;
	 }
	return $posts;
  }
  
  public function setForumThread($course_id, $forum_id, $thread_title, $post_title, $post_text){

    // Insert a new thread... new thread with one post.
	global $user_id;
	global $conn;	
	global $db_prefix;
	global $db_main_database;
	$sql = "insert into forum_thread (thread_title,forum_id,thread_replies,thread_poster_id,thread_poster_name,thread_views,thread_last_post,thread_date,thread_sticky,locked)
	values ('" . $thread_title . "'," . $forum_id . ",0," .  $user_id . ",'',0,0,now(),0,0)";
	mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	// select inserted thread_id
	$sql = "select max(thread_id) as thread_id from forum_thread";
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$thread_id = $row["thread_id"];
	// Finally insert new post, calling setForumPost.
    return self::setForumPost($course_id, $thread_id, $post_title, $post_text);
  }
  
  public function setForumPost($course_id, $thread_id, $post_title, $post_text){

    // Insert new post
	global $user_id;
	global $conn;	
	global $db_prefix;
	global $db_main_database;
	// First of all we need the forum_id
	$sql = "select forum_id from forum_thread where thread_id=" . $thread_id;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$forum_id = $row["forum_id"];
	// Now we need last post
	$sql = "select max(post_id) as post_id from forum_post where forum_id=" . $forum_id ." and thread_id=" . $thread_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	//It possible that there is no post. Perhaps we came from setForumThread.
	if(isset($row["post_id"]))
	{
	    $post_parent_id = $row["post_id"];
	}
	else
	{
	    // No parent. first post.
	    $post_parent_id = 0;
	}
	// And finally insert new post
	$sql = "insert into forum_post(post_title, post_text, thread_id, forum_id, poster_id, poster_name, post_date, post_notification, post_parent_id,visible) values ('". $post_title. "','". $post_text. "'," . $thread_id . "," . $forum_id . "," . $user_id . ",'',now(),0,". $post_parent_id .",1)" ;
	return mysql_query($sql,$conn);
  }
  
  public function getAnnouncements($course_id){
	
	// Get announcements
	global $conn;
	global $db_prefix;
	$announcements = array();
	$sql = "select id,title,end_date from announcement order by display_order";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$announcement = new Announcement();
		$announcement->id = $row["id"];
		$announcement->title = $row["title"];
		$announcement->date = strtotime($row["end_date"]);
		$announcements[] = $announcement;
	 }
	return $announcements;
  }

  public function getDetailAnnouncements($course_id, $id){
      
    // Get announcement values.
	global $conn;
	global $db_prefix;
	$announcements = array();
	$sql = "select title,content,end_date from announcement where id=" . $id ;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$announcement = new DetailAnnouncement();
		$announcement->title = $row["title"];
		$announcement->content = strip_tags(limpiarcadena($row["content"]));
		$announcement->date = strtotime($row["end_date"]);
		$announcements[] = $announcement;
	 }
	return $announcements;
  }
  
  public function getProgress($course_id){
 	
 	// Get all values relative to the learner progress
	global $conn;
	global $db_prefix;
	global $db_main_database;
	global $user_id;
        global $db_stats_database;
	// Iinitializing values
	$diferencia_segundos = 0 ;
	$primer_acceso = "Sin datos";
	$ultimo_acceso = "Sin datos";
	$mensajes_foros= 0;
	$mensajes_correo= 0;
	$nota_global= -1;
	$c= 0;
	$GradesExamsList = array();
	$ProgressCourseList= array();
	$GradesExercisesList= array();
	// Select connections to the course, ignore connections without logout date
	$sql = "select unix_timestamp(login_course_date) as id, unix_timestamp(logout_course_date) as od from track_e_course_access where user_id=" . $user_id . " and course_code='" . $course_id . "' and not isnull(logout_course_date) order by login_course_date";
	mysql_select_db($db_stats_database);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		// Calculating time connected
		$diferencia = $row["od"] - $row["id"];
		$diferencia_segundos = $diferencia_segundos + $diferencia;
		if ( $c==0 )
		{
			// First connection
			$primer_acceso = $row["id"];
		}
		else
		{
			if ($c == mysql_num_rows($rs)-1)
			{
				//Last connection
				$ultimo_acceso = $row["id"];
			}
		}
		$c=$c+1;
	}
	// Get total number of forum's post
	$sql = "select count(poster_id) as total from forum_post where poster_id=" . $user_id;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$mensajes_foros = $row["total"];
	// Get total number of Dmail sent
	$sql = "select count(envia) as total from dmail_main where envia=" . $user_id;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$mensajes_correo = $row["total"];
    // Global evaluation inserted by the c0urse tutor
	$sql = "select nota_global from course_rel_user_fd where course_code='".$course_id."' and user_id=". $user_id;
	mysql_select_db($db_main_database);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ( (mysql_num_rows($rs)>0) && (is_numeric($row["nota_global"]) ) )
		{
	        $nota_global = $row["nota_global"];
        }
    else
        {
            // Not inserted.
            $nota_global = -1;
        }
	

	// New version !!
	// Select al Exams from course. In dokeos we consider a exam a quiz that has values on quiz_exams table.

	$Exams = array();
   	$sql = "select id, title, description, type from quiz where random>=10 and id in (select id from quiz_exam)";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	    while($row = mysql_fetch_array($rs)){
		$Exam = new GradesExams();
		$Exam->id = $row["id"];
		$Exam->title = $row["title"];
		$Exam->grades = 0;
		
		/*
		    Now we search for the exam score. If there are more than one score we take the last one.
		    Real score (0-10) is based on this formula: 
		    (exe_result*10)/exe_weighting
		*/
		$sql2= "select exe_id,exe_result,exe_weighting from track_e_exercices 
		where exe_cours_id='".$course_id ."' and exe_user_id=" . $user_id . " 
		and exe_exo_id=".$row["id"]. " order by exe_date desc";
		mysql_select_db($db_stats_database);
		$rs2 = mysql_query($sql2,$conn);
		$row2 = mysql_fetch_array($rs2);
		    if (mysql_num_rows($rs2) > 0 )
		    {
			    $Exam->grades = round($row2["exe_result"]*10/$row2["exe_weighting"],1);
		    }
		    else
		    {
		         // without score. Not realized.
			     $Exam->grades = -1;
		    }

		// Count all attempt
		$sql4 = "select count(exe_id) as total from track_e_exercices where exe_cours_id='".$course_id ."' and exe_user_id=" . $user_id . " 
		and exe_exo_id=".$row["id"];
		mysql_select_db($db_stats_database);
		$rs4 = mysql_query($sql4,$conn);
		$row4 = mysql_fetch_array($rs4);
		$Exam->num_intentos = $row4["total"];

		// Now look for MAX exam attempt
		$sql3 = "select intentos from quiz_exam where id=". $row["id"];
		mysql_select_db($db_prefix.$course_id);
		$rs3 = mysql_query($sql3,$conn);
		$row3 = mysql_fetch_array($rs3);
		$Exam->max_intentos = $row3["intentos"];

		$GradesExamsList[] = $Exam;
	    }

	/* Las version without attempt
	//Get Exams
	$sql = "select id,title from quiz where active=0 and random=10  and id in (select path from lp_item) order by id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$GradesExams = new GradesExams();
		$GradesExams->id = $row["id"];
		$GradesExams->title = $row["title"];
		$GradesExams->grades = 0;
		// For each Exam we need the learner's score
		$sql = "select exe_exo_id,exe_result,exe_weighting from track_e_exercices where exe_user_id=" . $user_id . " and exe_cours_id='" . $course_id . "' and exe_exo_id=" .$row["id"] . " order by exe_date DESC";
		mysql_select_db($db_stats_database);
		$rs2 = mysql_query($sql,$conn);
		$row2 = mysql_fetch_array($rs2);
		if ( mysql_num_rows($rs2)>0)
		{

			$GradesExams->grades = round( ($row2["exe_result"] * 10 )/$row2["exe_weighting"]);
		}
	    else
	    {
	        //Don't have score.
	        $GradesExams->grades = -1;
		}
		$GradesExamsList[] = $GradesExams;
	}
      */

	// Get Exercices
	$sql = "select id,title from quiz where active=1 and random=0 order by id";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$GradesExercises = new GradesExercises();
		$GradesExercises->id = $row["id"];
		$GradesExercises->title = $row["title"];
		$GradesExercises->attempt = 0;
		$GradesExercises->grades = -1;
	    // For each Exercice we need the learner's score and attempts  
		$sql = "select exe_exo_id,exe_result,exe_weighting from track_e_exercices where exe_user_id=" . $user_id . " and exe_cours_id='" . $course_id . "' and exe_exo_id=" .$GradesExercises->id . " order by exe_date";
		mysql_select_db($db_stats_database);
		$rs2 = mysql_query($sql,$conn);
		while($row2 = mysql_fetch_array($rs2)){
			/*
		    Now we search for the Exercice score. If there are more than one score we take the last one.
		    Real score (0-10) is based on this formula: 
		    (exe_result*10)/exe_weighting
		    */
			$GradesExercises->attempt = $GradesExercises->attempt+ 1;
			$GradesExercises->grades = round( ($row2["exe_result"] * 10 )/$row2["exe_weighting"]);
			}
		$GradesExercisesList[] = $GradesExercises;	
		}
	// Get course score
	$sql = "select i.id, i.title, iv.score, iv.status from lp_item as i, lp_view v, lp_item_view iv where i.item_type='sco' and i.id = iv.lp_item_id and v.user_id=". $user_id ." and v.id=iv.lp_view_id order by i.display_order";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$ProgressCourse = new ProgressCourse();
		$ProgressCourse->id = $row["id"];
		$ProgressCourse->title = $row["title"];
		$ProgressCourse->status = $row["status"];
		$ProgressCourse->progress = $row["score"];
		$ProgressCourseList[] = $ProgressCourse;
	}
	// Join all data
	$Progress = new Progress();
	$Progress->time = $diferencia_segundos;
	$Progress->msg_forum = $mensajes_foros;
	$Progress->msg_mail = $mensaje_correo;
	$Progress->first_access = $primer_acceso;
	$Progress->last_access = $ultimo_acceso;
	$Progress->evaluation = $nota_global;
	$Progress->grades_exams = $GradesExamsList;
	$Progress->grades_exercises = $GradesExercisesList;
	$Progress->progress_course = $ProgressCourseList;
	return $Progress;	
  }
  
  public function getPolls($course_id){
    
    // Get pools from course
	global $conn;
    global $db_prefix;
	global $user_id;
	$SurveyList = array();
	$sql = "select survey_id,title,subtitle,DATEDIFF(CURDATE(),avail_from) as inicio, DATEDIFF(CURDATE(),avail_till) as fin, survey_invitation.answered  from survey, survey_invitation where survey_code=code and user=" . $user_id;
    mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Survey = new SurveyQuiz();
		$Survey->id = $row["survey_id"];
		$Survey->title = $row["title"];
		$Survey->quizdescription = $row["subtitle"];
		// look if Pool it's open and not answered
		if ($row["inicio"]>=0 && $row["fin"]<=0 && $row["answered"]==0)
		{
			$Survey->open = 1;
		}
		else
		{
			$Survey->open = 0;
		}
		$SurveyList[] = $Survey;
     }
     return $SurveyList;
  }

  public function getPollsQuestions($course_id, $quiz_id){
	
	// Select pool's questions
	global $conn;
	global $db_prefix;
	$Questions = array();
	$sql = "select question_id,survey_question,sort,type,max_value from survey_question where survey_id=" . $quiz_id . " order by sort";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$Question = new SurveyQuestion();
		$Question->id = $row["question_id"];
		$Question->question = strip_tags($row["survey_question"]);
		$Question->sort = $row["sort"];
		$Question->type = $row["type"];
		$Question->max_value = $row["max_value"];
		$Questions[] = $Question;
	 }
	return $Questions;     
  }

  public function getPollsAnswers($course_id, $quiz_id){
     
    // Get pool's questions with answers
	global $conn;
	global $db_prefix;
	$Answers = array();
    // First of all we get the questions
	$Questions = self::getPollsQuestions($course_id, $quiz_id);
	foreach ($Questions as $Question)
	{
		// For each question
		$tmpAnswers = array();
		$sql = "select question_option_id, option_text,sort from survey_question_option where question_id=" . $Question->id . " order by sort";
		mysql_select_db($db_prefix.$course_id);
		$rs = mysql_query($sql,$conn);
		while($row = mysql_fetch_array($rs)){
			$tmp = new SurveyAnswer();	
			$tmp->id = $row["question_option_id"];
			$tmp->answer = $row["option_text"];
			$tmp->sort = $row["sort"];
			$tmpAnswers[]= $tmp;
		 }
		$Answers[] = $tmpAnswers;
	}
	return $Answers;
  }

  public function setPollAnswer($answers){

    // Save pool
	global $conn;
	global $db_prefix;
	global $user_id;

    if ( count($answers)>0)
	{	
		foreach ( $answers as $answer)
		{
			if ($course_id=="")
			{
				$course_id = $answer->course_id;
			}
			$sql="insert into survey_answer (survey_id,question_id,option_id,value,user) values (".$answer->survey_id.",".$answer->question_id.",'".$answer->option."',".$answer->value.",".$user_id.")";
			mysql_select_db($db_prefix.$course_id);
			$rs = mysql_query($sql,$conn);
		} 
		$sql= "update survey_invitation set answered=1 where user=".$user_id." and survey_code in (select code from survey where survey_id=". $answer->survey_id.")";
		$rs = mysql_query($sql,$conn);
	}            
        return True;    
  }

  public function setAnswer($course_id, $exam_id, $responses, $score, $total){

    // Save answers from exercice.
	global $conn;
	global $db_prefix;
	global $user_id;
        global $db_stats_database;
	$sql = "insert into track_e_exercices(exe_user_id, exe_cours_id, exe_exo_id, exe_result, exe_weighting,exe_date) values (".$user_id.", '".$course_id."', ".$exam_id.", ".$score.", ".$total.",now())";
	mysql_select_db($db_stats_database);	
	$rs = mysql_query($sql,$conn);
   	$sql = "select max(exe_id) as maximo from track_e_exercices where exe_user_id=".$user_id." and exe_cours_id='".$course_id."' and exe_exo_id=" . $exam_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$exe_id = $row["maximo"];
	foreach ($responses as $response)
	{
		$sql= "insert into track_e_attempt (exe_id,user_id,question_id,answer,course_code,position,tms) 
		values (".$exe_id.",".$user_id.",".$response->question_id.",'".$response->option."','".$course_id."',".$response->value.",now())";
		mysql_select_db($db_stats_database);	
		$rs = mysql_query($sql,$conn);
	}
        return True;
  }
  
  public function getMails($course_id, $id_folder, $info){
  
    // Get al Dmail from an expecified folder
	global $conn;
	global $db_prefix;
	global $user_id;
	$DmailMailList = array();
	if ($info=="imp")
	{
	    // Dmail marked as important
		$sql = "select id_mail, asunto, envia, recibe, fecha_envio, fecha_lectura, id_carpeta, borrado, leido, importante, contenido, id_adjunto from dmail_main where recibe=" . $user_id . " and importante=1 and borrado=0 order by fecha_envio desc";
	}
	else
	{
		if ($info=="del")
		{
		    // Dmail marked as deleted
			$sql = "select id_mail, asunto, envia, recibe, fecha_envio, fecha_lectura, id_carpeta, borrado, leido, importante, contenido, id_adjunto from dmail_main where recibe=" . $user_id ." and id_carpeta=1 and borrado=1 order by fecha_envio desc";
		}
		else
		{
			if ($id_folder==2)
			{
			    // Dmail sent
				$sql = "select id_mail, asunto, envia, recibe, fecha_envio, fecha_lectura, id_carpeta, borrado, leido, importante, contenido, id_adjunto from dmail_main where envia=" . $user_id ." and borrado=0 and id_carpeta=2 order by fecha_envio desc";
			}
			else
			{
				if  ($id_folder==3) // borradores
				{
				    // Dmail marked as draft
					$sql = "select id_mail,asunto,envia,recibe,fecha_envio,fecha_lectura,id_carpeta,borrado,leido,importante,contenido,id_adjunto from dmail_main where envia=" . $user_id ." and borrado=0 and id_carpeta=3 order by fecha_envio desc";					
				}
				else
				{
				    // Personal folder Dmail 
					$sql = "select id_mail, asunto, envia, recibe, fecha_envio, fecha_lectura, id_carpeta, borrado,leido, importante, contenido, id_adjunto from dmail_main where recibe=". $user_id ." and id_carpeta=" . $id_folder ." and borrado=0 order by fecha_envio desc";
				}
			}
		}
	}
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$DmailMail = new DmailMails();
		$DmailMail->id = $row["id_mail"];
		$DmailMail->subject = $row["asunto"];
		$DmailMail->id_from = $row["envia"];
		$DmailMail->id_to = $row["recibe"];
		$DmailMail->date_send = strtotime($row["fecha_envio"]);
		$DmailMail->date_lecture = strtotime($row["fecha_lectura"]);
		$DmailMail->id_folder = $row["id_carpeta"];
		$DmailMail->del_mail = $row["borrado"];
		$DmailMail->lecture_mail = $row["leido"];
		$DmailMail->important_mail = $row["importante"];
		$DmailMail->content = $row["contenido"];
		$DmailMail->id_attachment = $row["id_adjunto"];
		$DmailMailList[] = $DmailMail;
	 }
	return $DmailMailList; 
  }
  
  public function getDescriptionAttachment($course_id, $id_attachment){

    // Get Dmail attachment description
    // Thrift definition give only one attachment. Dmail (at this moment) only can attach one file.	
	global $conn;
	global $db_prefix;
    $sql = "select id_adjunto, size, tipo, nombre from dmail_adjuntos where id_adjunto =". $id_attachment;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$DmailDescriptionAttachment = new DmailDescriptionAttachment();
	$DmailDescriptionAttachment->id = $row["id_adjunto"];
	$DmailDescriptionAttachment->type = $row["tipo"];
	$DmailDescriptionAttachment->size = $row["size"];
	$DmailDescriptionAttachment->name = $row["nombre"];
	return $DmailDescriptionAttachment; 
  }

  public function getContentAttachment($course_id, $id_attachment){

    // Get Dmail attachment
    // Thrift definition give only one attachment. Dmail (at this moment) only can attach one file.	
	global $conn;
	global $db_prefix;
	global $user_id;      
	$sql = "select a.id_adjunto, a.archivo from dmail_adjuntos as a  where a.id_adjunto = ".$id_attachment;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$DmailContentAttachment = new DmailContentAttachment();
	$DmailContentAttachment->id = $row["id_adjunto"];
	$DmailContentAttachment->attachment = $row["archivo"];
	return $DmailContentAttachment; 
  }

  public function getContacts($course_id){
	/*
	 Selecciona las contactos del curso
	*/
	global $conn;
	global $db_main_database;
	$DmailContactsList = array();
      
		$sql = "Select u.user_id,u.username,u.firstname,u.lastname from user u, course_rel_user_fd m where u.user_id = m.user_id and m.course_code='".$course_id."' and u.user_id not in (1,2) and u.username not like '%demo%' order by u.status, u.firstname, u.lastname";
	mysql_select_db($db_main_database);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$DmailContacts = new DmailContacts();
		$DmailContacts->id_user = $row["user_id"];
		$DmailContacts->username = $row["username"];
		$DmailContacts->firstname = $row["firstname"];
		$DmailContacts->lastname = $row["lastname"];
		$DmailContactsList[] = $DmailContacts;
	}
	return $DmailContactsList;
  }

  public function getFolders($course_id){

    // Get Dmail user folders     	
	global $conn;
	global $db_prefix;
	global $user_id;
	$DmailFoldersList = array();
	$sql = "select  id_carpeta,nombre from dmail_carpetas where propietario=". $user_id ." order by id_carpeta";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs)){
		$DmailFolders = new DmailFolders();
		$DmailFolders->id = $row["id_carpeta"];
		$DmailFolders->name = $row["nombre"];
		$DmailFoldersList[] = $DmailFolders;
	 }
	return $DmailFoldersList; 
  }
  
  public function setFolderMail($course_id, $name_folder){  

    // Add a new folder for Dmail
	global $conn;
	global $db_prefix;
    global $user_id;
	$sql = "insert into dmail_carpetas (nombre,propietario) values ('".$name_folder."',".$user_id.")";
    mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	return True;
  }

  public function changeFolder($course_id, $id_folder, $name_folder, $del_folder){

    // Used to change folder name or to delete a folder
    global $conn;
    global $db_prefix;
    if($del_folder==0) {
        // Modify
        $sql = "update dmail_carpetas set nombre='".$name_folder."' where id_carpeta=".$id_folder;
        mysql_select_db($db_prefix.$course_id);
        mysql_query($sql,$conn);
        return True;
    }  else {
        $folder_recibidos = 1;
        // Delete a folder. First of all we move al the content to the main folder
        $sql = "update dmail_main set id_carpeta=".$folder_recibidos." where id_carpeta=".$id_folder;
        mysql_select_db($db_prefix.$course_id);
        mysql_query($sql,$conn);     
        $sql = "delete from dmail_carpetas where id_carpeta=". $id_folder;
        mysql_query($sql,$conn);
        return True;
    }
  }  

  public function setLectureMail($course_id, $id_mail, $lecture_mail){

    // Update Dmail status (Read, Unread)
	global $conn;
	global $db_prefix;
	if ($lecture_mail==0)
	{
		$sql = "update dmail_main set leido=".$lecture_mail.", fecha_lectura=null where id_mail=" .$id_mail;
	}
	else
	{
		$sql = "update dmail_main set leido=".$lecture_mail.", fecha_lectura=now() where id_mail=" . $id_mail;
	}
	mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	return True;
  }

  public function setImportantMail($course_id, $id_mail, $important_mail){
    
    // Update Dmail status (Important)
	global $conn;
	global $db_prefix;
	$sql="update dmail_main set importante=".$important_mail." where id_mail=". $id_mail;
	mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	return True;
  }

  public function setDeleteMail($course_id, $id_mail, $del_mail){
    
    // Update Dmail status (Deleted)
	global $conn;
	global $db_prefix;
	$sql="update dmail_main set borrado=". $del_mail ." where id_mail=". $id_mail;
	mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	return True;
  }

  public function changeFolderMail($course_id, $id_mail, $id_folder){

    // Change folder of a Dmail
	global $conn;
	global $db_prefix;
	$sql="update dmail_main set id_carpeta=" .$id_folder . " where id_mail=". $id_mail;
	mysql_select_db($db_prefix.$course_id);
	mysql_query($sql,$conn);
	return True;
  }

  public function setMail($course_id, $subject, $id_to, $content, $id_attachment){
    
    // Dmail Send
	global $conn;
	global $db_prefix;
	global $user_id;
		if ($id_to==0)
		{
			// Draft Dmail (folder_id=3)
			$sql= "insert into dmail_main (asunto, envia, recibe, fecha_envio, id_carpeta, borrado, leido, importante, contenido) 
			values ('".$subject."',".$user_id.",".$id_to.", now(), 3, 0, 0, 0, '".$content."')";
			mysql_select_db($db_prefix.$course_id);
			mysql_query($sql,$conn);
		}
		else
		{
			//Normal Dmail (folder_id=1 for inbox folder_id=2 for sent)
			$sql= "insert into dmail_main (asunto, envia, recibe, fecha_envio, id_carpeta, borrado, leido, importante, contenido) values ('".$subject."',".$user_id.",".$id_to.", now(), 1, 0, 0, 0, '".$content."')";
			mysql_select_db($db_prefix.$course_id);
			mysql_query($sql,$conn);
			$sql= "insert into dmail_main (asunto, envia, recibe, fecha_envio, id_carpeta, borrado, leido, importante, contenido) values ('".$subject."',".$user_id.",".$id_to.", now(), 2, 0, 1, 0, '".$content."')";
			mysql_query($sql,$conn);
		}
    
    // Attachment not implemented yet on app.
	if ($id_attachment==0)
	{
	}
	else
	{
		/*
		else:
            if id_to == 0:
                sql='insert into dmail_main (asunto, envia, recibe, fecha_envio, id_carpeta, borrado, leido, importante, contenido, id_adjunto) values (\'%s\', %i, %i, now(), 3, 0, 0, 0, \'%s\', %i)' % (subject, id_user, id_to, content, id_attachment)
                cursor.execute(sql)
            else:
                sql='insert into dmail_main (asunto, envia, recibe, fecha_envio, id_carpeta, borrado, leido, importante, contenido, id_adjunto) values (\'%s\', %i, %i, now(), 1, 0, 0, 0, \'%s\', %i)' % (subject, id_user, id_to, content, id_attachment)
                cursor.execute(sql)
                sql='insert into dmail_main (asunto, envia, recibe, fecha_envio, fecha_lectura, id_carpeta, borrado, leido, importante, contenido, id_adjunto) values (\'%s\', %i, %i, now(), now(), 2, 0, 1, 0, \'%s\', %i)' % (subject, id_user, id_to, content, id_attachment)
                cursor.execute(sql)*/
	}    
        return True;
  }

  public function getPodcast($course_id){

    // Get Podcast from course.
    // Tool on the way to be deprecated.
	global $conn;
	global $db_prefix;
	global $ruta_web;
	$PodcastList = array();
	$sql = "select title,comment,size,path from podcast order by title";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs))
	{
		$Podcast = new Podcast();
		$Podcast->title = $row["title"];
		$Podcast->comment = $row["comment"];
		$Podcast->size = $row["size"];
		$Podcast->path =  $row["path"];
		$PodcastList[] = $Podcast;
	 }
	return $PodcastList;
  }
  
  public function getDocuments($course_id){

    // Get course documents
	global $conn;
	global $db_prefix;
	global $ruta_web;
    // Valid extensions
	$lista_extensiones = array('jpg','gif','html','png','xls','doc','ppt','pdf','htm','key','pages','numbers');
	// Crap folders... Nothing important there.
	$lista_folders = array('audio','images','flash','HotPotatoes_files','css');
	// Init Vars
	$c=0;
	$num_items=0;
	$DocumentList = array();
	$FinalDocumentList = array();

	$sql = "select path,comment,title,filetype,size,readonly from document order by path";
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	while($row = mysql_fetch_array($rs))
	{
		// Discriminate not useful folders and subfolders.
		$valido=0;
		$resultado = explode ("/",$row["path"]);
		if (in_array($resultado[1],$lista_folders) || in_array($resultado[2],$lista_folders) || in_array($resultado[3],$lista_folders))
		{
			// Default folder not useful for learners
			$valido=0;
		}	
		else
		{	
			$resultado = explode (".",$row["path"]);
			if  (in_array($resultado[1],$lista_extensiones) || $row["filetype"]=='folder')
			{
				// Valid extensión or folder created by tutor or gestor
				if ($row["title"]=="")
				{
					// No title... rare but posible
					$valido=0;				
				}
				else
				{
				    // Valid one.
					$valido=1;
				}
			}
			else
			{
				$valido=0;
			}
		}

		if ($valido==1)
		{
		$Document = new Documents();
		$Document->dir =  $row["path"];
		$Document->title = $row["title"];
		$Document->filetype = $row["filetype"];
		// El nivel en la app debe empezar en 2. :?
		$Document->nivel = substr_count ($row["path"],'/') +1;
		$Document->id = $c;
		$DocumentList[] = $Document;
		$c=$c+1;
		}
		
	 }

	// Delete empty folders
	while (count($DocumentList)!=$num_items)
	{
		$c=0;
		$num_items= count($DocumentList);
		while ($c < count($DocumentList))
		{
			if ( ($c==count($DocumentList)) && ( $DocumentList[$c]->filetype=="folder"))
				{
					unset($DocumentList[$c]);
					$c = count($DocumentList);
				}
			else
				{
					if (($DocumentList[$c]->filetype=="folder") && ($DocumentList[$c+1]->nivel<= $DocumentList[$c]->nivel) )
					{
						unset($DocumentList[$c]);
						$c = count($DocumentList);
					}
				}
			$c=$c+1;
		}
	}
	// constructing array
	$c=0;
	foreach ( $DocumentList as $row)
		{			
			$row->id = $c;
			$FinalDocumentList[] = $row;
			$c=$c+1;
		}

	return $FinalDocumentList;
  }
  
  public function markExamAsCompletedInScorm($course_id, $quiz_id){

    // Save exam (SCORM)
	global $conn;
	global $db_prefix;
	global $user_id;
    // Look for necesary id's on lp and lp_item tables
    $sql = "select id,lp_id from lp_item where path=" . $quiz_id;
	mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$lp_item_id = $row["id"];
    $lp_id = $row["lp_id"];

	// Look for id on lp_view table.
    $sql = "select id from lp_view where user_id=" . $user_id ." and lp_id=" . $lp_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if (mysql_num_rows($rs) > 0)
	{
		$lp_view_id = $row["id"];
	}
	else
	{
	    // There is no lp_view id, first access. Adding it.
		$sql = "insert into lp_view (lp_id, user_id, view_count) values (".$lp_id.",".$user_id.", 1)";
		$rs = mysql_query($sql,$conn);
		// Get inserted id
       	$sql = "select id from lp_view where user_id=" . $user_id ." and lp_id=" . $lp_id;
		$rs = mysql_query($sql,$conn);
		$row = mysql_fetch_array($rs);
		$lp_view_id = $row["id"];
	}
    // Look for rows on lp_item_view table.
    $sql = "select id,start_time from lp_item_view where lp_view_id=".$lp_view_id." and lp_item_id=" . $lp_item_id;
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);

	if (mysql_num_rows($rs) > 0)
	{
		// Update values
		$start_time = "";
		if( $row["start_time"]== 0 )
		{
			$start_time= ", start_time=" . time();		
		}
		$sql = "update lp_item_view set status='completed' " . $start_time . " where lp_view_id=".$lp_view_id." and lp_item_id=" . $lp_item_id;
		$rs = mysql_query($sql,$conn);
	}
	else
	{
		// There is no row, insert it.
        $sql = "insert into lp_item_view (lp_item_id, lp_view_id, view_count, start_time, status, score, suspend_data) values (".$lp_item_id.", ".$lp_view_id.", 1,".time().", 'completed',0, '')";
		$rs = mysql_query($sql,$conn);
	}
	return true;
	
  }
  public function saveExamScore($course_id, $quiz_id, $questions, $answers){

	// Save Exam score
	global $conn;
	global $db_prefix;
	global $user_id;
        global $db_stats_database;
    // Init vars
	$weighting = 0;
    $result = 0;
	// Insert score
        $sql = "insert into track_e_exercices(exe_user_id, exe_cours_id, exe_exo_id, exe_result, exe_weighting) values (".$user_id.", '".$course_id."', ". $quiz_id. ", -1, -1)";
	mysql_select_db($db_stats_database);
	$rs = mysql_query($sql,$conn);
	// Get exe_id
	$sql = "select exe_id from track_e_exercices where exe_user_id=".$user_id . " and exe_cours_id='". $course_id."' and exe_exo_id=".$quiz_id." and exe_result=-1 and exe_weighting=-1";
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	$new_exe_id = $row["exe_id"];
	$i=0;
	// Save attempt and calculate screo
	foreach ($questions as $question)
		{			
			$weighting = $weighting + $question->ponderation;
			foreach ( $answers[$i] as $answer)
			{
				if ($answer->selected==true)
				{
					$sql = "insert into track_e_attempt (exe_id, user_id, question_id, answer, teacher_comment, marks, course_code,tms) values (".$new_exe_id.",".$user_id.",".$question->id. ",".$answer->id.",'',".$answer->ponderation.",'".$course_id."',now() )";
					$rs = mysql_query($sql,$conn);
					if ($answer->correct)
					{
						$result = $result + $answer->ponderation;
					}
				}			
			}
			$i=$i+1;
		}

    // And finaly update score
	$sql = "update track_e_exercices set exe_result=".$result.",exe_weighting=".$weighting.", exe_date= now() where exe_id=" . $new_exe_id;
	mysql_select_db($db_stats_database);
	$rs = mysql_query($sql,$conn);
    return true;
  }
  
  public function getChatReferenceTime(){

      // Return time.
      return time();
  }
  
  public function getInfo(){

    // Return username
	global $conn;
	global $db_prefix;
	global $user_id;
        global $db_main_database;
	$sql = "Select username from user where user_id=" . $user_id;
	mysql_select_db($db_main_database);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if ( mysql_num_rows($rs)>0)
		{
			$diccionario = array();
			$diccionario["username"]= $row["username"];
		}
	return $diccionario;
  }
  
  public function getQuestionAndAnswers($course_id, $quiz_id){
    
    // Get questions and answers from a quiz.
	global $conn;
	global $db_prefix;
	$Answers = array();
	$QuestionAndAnswers = array();

	// First of all we need questions.
	$Questions = self::getQuestions($course_id, $quiz_id);

	foreach ($Questions as $Question)
	{
		// Now for each question... Look for answers.
		$tmpAnswers = array();
		$sql = "select qa.*
		from quiz q,quiz_question qq,quiz_rel_question qrq, quiz_answer qa
		where qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." and q.id= " . $quiz_id ." order by qa.question_id, qa.position";
		mysql_select_db($db_prefix.$course_id);
		$rs = mysql_query($sql,$conn);
		while($row = mysql_fetch_array($rs)){
			$tmp = new Answer();	
			$tmp->id = $row["id"];
			$tmp->answer = limpiarcadena(strip_tags($row["answer"]));
			$tmp->comment = '';
			$tmp->correct = $row["correct"];
			$tmp->position = $row["position"];
			$tmp->ponderation = $row["ponderation"];
			$tmpAnswers[]= $tmp;
		 }
		$Answers[] = $tmpAnswers;
	}
	$QuestionAndAnswers = new QuestionAndAnswers();
	$QuestionAndAnswers->questions = $Questions;
	$QuestionAndAnswers->answers = $Answers;
	return $QuestionAndAnswers;      
  }
  
  public function getQuestionAndAnswersExer($course_id, $quiz_id){

    // Get questions and answers from a Exercice.
	global $conn;
	global $db_prefix;
	$Answers = array();
	$QuestionAndAnswers = array();

	// First of all we need questions.
	$Questions = self::getQuestionsExercices ($course_id, $quiz_id);

	foreach ($Questions as $Question)
	{
		// Now for each question... Look for answers.
		$tmpAnswers = array();
		$sql = "select qa.*
		from quiz q,quiz_question qq,quiz_rel_question qrq, quiz_answer qa
		where qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." order by qa.question_id, qa.position";

		mysql_select_db($db_prefix.$course_id);
		$rs = mysql_query($sql,$conn);
		while($row = mysql_fetch_array($rs)){
			$tmp = new Answer();	
			$tmp->id = $row["id"];
			$tmp->answer = limpiarcadena(strip_tags($row["answer"]));
			$tmp->comment = '';
			$tmp->correct = $row["correct"];
			$tmp->position = $row["position"];
			$tmp->ponderation = $row["ponderation"];
			$tmpAnswers[]= $tmp;
		 }
		$Answers[] = $tmpAnswers;
	}
	$QuestionAndAnswers = new QuestionAndAnswers();
	$QuestionAndAnswers->questions = $Questions;
	$QuestionAndAnswers->answers = $Answers;
	return $QuestionAndAnswers;      
  }

    public function registerDeviceId ($deviceId,$appVersion,$model,$name,$systemName,$systemVersion,$localizedModel,$userInterfaceIdiom){
        return true;
    }
    
    public function getMultimedia($course_id) {
      // Multimedia tool
      // get only youtube videos and mp3 files on the server.
      global $conn;
      global $db_prefix;
      global $user_id;
      global $ruta_web;
      $Multimedias = array();
      $Mp3s = array();
      // $Totales = array();

      // Only sources 1 and 5 are valid now. 1 = Youtube 5 = Mp3 file
      $sql = "select title,description,source_id,target from multimedia m , multimedia_sources ms where m.source_id=ms.id and source_id in (1,5)";
      mysql_select_db($db_prefix.$course_id);
      $rs = mysql_query($sql,$conn);
      while($row = mysql_fetch_array($rs)){
	if ($row["source_id"]==1)
	{
	  $Multimedia =new Multimedia_files();
	  $Multimedia->title = $row["title"];
	  $Multimedia->text = $row["description"];
	  $Multimedia->url = "http://www.youtube.com/watch?v=".$row["target"];
	  $Multimedias[] = $Multimedia;
	}
	else
	{
	  // Only source 5 now. Mp3.
	  $Mp3 = new Multimedia_files();
	  $Mp3->title = $row["title"];
	  $Mp3->text = $row["description"];
	  $Mp3->url = $ruta_web ."courses/".$course_id."/multimedia".$row["target"];
	  $Mp3s[] = $Mp3;
	}
      }

      $Total = new Multimedia();
      $Total->audios = $Mp3s;
      $Total->videos = $Multimedias;
      // $Totales[] = $Total;

      return $Total;
    }
    public function getGlosario($course_id) {
	    return true;
    }
}

function limpiarcadena($cadena) 
{ 

    // Clear a string
    $login = $cadena;
	$b     = array("&rdquo;","&rlquo;","&#8230;","&acute;","&nbsp;","&aacute;","&eacute;","&iacute;","&oacute;","&uacute;","&Aacute;","&Eacute;","&Iacute;","&Oacute;","&Uacute;","&ntilde;","&Ntilde;","&uuml;","&Uuml;","&#8220;","&#8221;","&quot;","&middot;","&rdquo;", "&ldquo;","&raquo;","&laquo;","&iquest;","&iexcl;","&ordf;","&amp;","&#8230;","&rsquo;","&lsquo;"); 
	
    $c     = array('"','"',"","'"," ","á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","Ñ","ü","Ü","'","'",'"','_', '>','<','»','«','¿','¡','ª','&','_',"'","'"); 
    $login = str_replace($b,$c,$login);
    $l=0;
    while ($l != strlen($login)){
        $l=strlen($login);
        $login = str_replace("  "," ",$login);
    }
    return $login; 
} 


function url_safe_base64_encode($value) {

    $encoded = base64_encode($value);
    // replace unsafe characters +, = and / with the safe characters -, _ and ~
    return str_replace(
        array('+', '=', '/'),
        array('-', '_', '~'),
        $encoded);
}

function getSignedURL($resource, $timeout, $type)
{

    //$resource="http://videos.formaciondigital.com/fd1234.mp4";
    //This comes from key pair you generated for cloudfront
    $keyPairId = "APKAIM5A2GMYM7TRQQJA";
    $expires = time() + $timeout; //Time out in seconds
    if ($type == 'canned') {
        $json = '{"Statement":[{"Resource":"'.$resource.'","Condition":{"DateLessThan":{"AWS:EpochTime":'.$expires.'}}}]}';     
    } else if ($type == 'custom') {
        $json = '{"Statement":[{"Resource":"'.$resource.'","Condition":{"IpAddress":{"AWS:SourceIp":"94.125.99.129"},"DateLessThan":{"AWS:EpochTime":'.$expires.'}}}]}';     
    } else {
        $json = '{"Statement":[{"Resource":"'.$resource.'","Condition":{"IpAddress":{"AWS:SourceIp":"'.$_SERVER['REMOTE_ADDR'].'"},"DateLessThan":{"AWS:EpochTime":'.$expires.'}}}]}';     
    }
    //Read Cloudfront Private Key Pair
    //$fp=fopen("/var/www/platsitios/private/videos_private.pem","r"); 
    $fp=fopen("/var/private/videos_private.pem","r");
    $priv_key=fread($fp,8192); 
    fclose($fp); 
    //Create the private key
    $key = openssl_get_privatekey($priv_key);
    if(!$key)
    {
        echo "<p>Failed to load private key!</p>";
        return;
    }
    //Sign the policy with the private key
    if(!openssl_sign($json, $signed_policy, $key, OPENSSL_ALGO_SHA1))
    {
        echo '<p>Failed to sign policy: '.openssl_error_string().'</p>';
        return;
    }
    //Create url safe signed policy
    $base64_signed_policy = base64_encode($signed_policy);
    $signature = str_replace(array('+','=','/'), array('-','_','~'), $base64_signed_policy);
    //Construct the URL
    if ($type == 'canned') {
        $url = $resource.'?Expires='.$expires.'&Signature='.$signature.'&Key-Pair-Id='.$keyPairId;
    } else {
        $url = $resource.'?Policy='.url_safe_base64_encode($json).'&Signature='.$signature.'&Key-Pair-Id='.$keyPairId;
    }
    return $url;
}

function isScoCompleted ($course_id,$id,$sco_count)
{
    global $conn;
	global $db_prefix;
	global $user_id;
    // Must have files on lp_view and lp_item_view tables.
    $sql = "select id from lp_view where user_id=" . $user_id ." and lp_id=" . $id;
    mysql_select_db($db_prefix.$course_id);
	$rs = mysql_query($sql,$conn);
	$row = mysql_fetch_array($rs);
	if (mysql_num_rows($rs) > 0)
	{
		$lp_view_id = $row["id"];
		$sql = "select id,suspend_data from lp_item_view where lp_view_id=".$lp_view_id." and lp_item_id=" . $id;
	    $rs2 = mysql_query($sql,$conn);
	    $row2 = mysql_fetch_array($rs2);
	    if (mysql_num_rows($rs2) > 0)
	    {
		    // It's all right, lets see if sco is completed
		    $count_visited = explode("#",$row2["suspend_data"]);
            if ( count($count_visited) >= $sco_count)
            {
                // Completed
                return True;
            }
	    }
    }   
    // Incompleted.
    return False;
}

function isVideoCompleted ($course_id,$sco_id,$id_video)
{
    global $conn;
    global $db_prefix;
   	global $user_id;
    // First of all we need launch_data values from lp_item table
    $sql = 'select launch_data from lp_item where id=' . $sco_id ;
	mysql_select_db($db_prefix.$course_id);    
    $rs = mysql_query($sql,$conn);
    $row = mysql_fetch_array($rs);   
    // Now we need to see if video is completed
    // Get Launch_data array
	$pages_array = getVideomodelPagesArray ($row["launch_data"]); 
	// Get video Key from array
	$key = GetKeyFromPagesArray ($pages_array,$id_video);	 
    // Get suspend_data from lp_item_view
    $sql = "select suspend_data from lp_item_view iv, lp_view v where v.user_id=" . $user_id . " and v.id=iv.lp_view_id and iv.lp_item_id=" .$sco_id;
    mysql_select_db($db_prefix.$course_id);    
    $rs = mysql_query($sql,$conn);
    $row = mysql_fetch_array($rs);
    $suspend_data = explode("#",$row["suspend_data"]);
    // Look if located key is in suspend_data
    if (in_array($key,$suspend_data))
    {
        return True;
    }
    else
    {
        return False;
    }
}

function GetKeyFromPagesArray ($pages_array,$id_video)
{
    // Look for the video key in the array
    while ($page = current($pages_array)) {
        if ($page == $id_video) 
        {
            $key = key($pages_array) +1;
        }
        next($pages_array);
    }
    return $key;
}    
    
function getVideomodelScoId ($launchdata)
{
    // return sco id extracted from launchdata
    $temp = explode("#",$launchdata);
    return $temp[0];   
}

function getVideomodelPageslist($launchdata)
{
    // return viodeos id extracted from launchdata
    $temp = explode("#",$launchdata);
    return $temp[1];   
}

function getVideomodelPagesArray ($launchdata)
{
    // return sco id extracted from launchdata
    $temp = explode("#",$launchdata);
    $pagesArray = explode(",",$temp[1]);   
    return $pagesArray;
}

function IsUserAllowed($course_code)
{
  //Check if user is allowed on course
  global $conn;
  global $db_main_database;
  global $user_id;

  $sql = "select count(user_id) as total from course_rel_user_fd where user_id=$user_id and course_code='".$course_code."'";
  mysql_select_db($db_main_database);    
  $rs = mysql_query($sql,$conn);
  $row = mysql_fetch_array($rs);
  if ($row["total"]>0) 
    {
      return true;
    }
  else
    {
      return false;
    }
}
?>
