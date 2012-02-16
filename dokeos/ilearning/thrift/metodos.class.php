<?php
    
    /*
     CABECERA
     
     */
    
    // Including some php from platform for basic configuration
    include_once(dirname ( __FILE__ )."/../../main/inc/lib/database.lib.php");
    include_once(dirname ( __FILE__ )."/../../main/inc/lib/main_api.lib.php");
    include(dirname ( __FILE__ )."/../../main/inc/conf/configuration.php");
    //include_once(dirname ( __FILE__ )."/../../main/inc/lib/course.lib.php");
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
    $db_main_database = $_configuration['main_database'];
    $db_prefix = $_configuration['db_prefix'];
    $ruta_web = $_configuration['root_web'];
    $db_stats_database = $_configuration['statistics_database'];
    $single_database = $_configuration['single_database'];
    $table_prefix= $_configuration['table_prefix'];
    $db_glue = $_configuration['db_glue'] ;
    
    
    
    // Thrift class
    class ILearningHandler implements ILearningIf {
        protected $log = array();
        
        public function checkVersion($par_version) {
            
            // check if a new version is available.
            // Posible values are: checkVersionNew and checkVersionNewCritical
            $version= new RetVersion();
            $version->needUpdate=False;
            $version->msg="";
            
            return $version;
        }
        
        public function getLecturesScos($course_id,$sco_id) {
            
        }
        
        public function getScos($course_id) {
            
        }
        
        public function saveLecturesScos($course_id,$sco_id,$video_id) {
            
        }
        
        public function getLectures($course_id) {

        }
        
        public function logoutCourse($course_id) {
            
            if (IsUserAllowed($course_id)) { 
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
                
                if (mysql_num_rows($rs)>0) {
                    // We have a previous login, update the row to add logout date.
                    $sql = "update track_e_course_access set logout_course_date=now()
                    where course_access_id=" .$row["access_id"];
                    $rs = mysql_query($sql,$conn);
                }
                return True;	
            } else { 
                return False;
            }
        }
        
        public function loginCourse($course_id) {
            
            if (IsUserAllowed($course_id)) { 
                // Platform login
                // Save login date on track_e_course_access table. Logout is null.
                global $conn;
                global $db_prefix;
                global $user_id;
                global $db_stats_database;
            
                $sql = "insert into track_e_course_access (course_code,user_id,login_course_date,counter) 
                values ('".$course_id."',".$user_id.",now(),1)";
                mysql_select_db($db_stats_database);
                $rs = mysql_query($sql,$conn);
                return True;
            } else {
                return False;
            }
        }
        
        public function saveLecture($course_id,$lecture_id,$time,$score,$status) {
            

        }
        
        
        public function getSupportedContents($course_id) {
            
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
            global $db_stats_database;
            $course_content = array();
            
            if (IsUserAllowed($course_id)) {
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
                if ($row["sco"] > 0) {
                    // We have SCO's
                    $course_content[] = 0;
                }
                
                // Exams. Is a quiz that was inserted on lp_item. Normaly with visibility=0 on quiz tool.
                $sql = "select count(id) as quiz from ".tableName($course_id,'lp_item')." where item_type='quiz'";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                if ($row["quiz"] > 0) {
                    $course_content[] = 1;
                }
                
                // Videos... Always active, at this moment.
                //$course_content[] = 3;
                
                // Other tools. Look for visibility status on course.
                $sql = "select name from ".tableName($course_id,'tool')." where visibility=1 and admin=0 order by id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    switch ($row["name"]) {
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
                            // Not implemented on Chamilo
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
                            //Not implemented in Chamilo
                            //$course_content[] = 11;
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
                        case "Servicio tecnico":
                            $course_content[] = 15;
                            break;
                    }
                }
                
                // Progress.
                // There is no a Progress tool in Dokeos. Always active.
                $course_content[] = 14;
                
                sort($course_content);
            }
            return $course_content;
        }
        
        public function getSupportedContentsVersion($course_id,$version) {
            
            // Return available tools for a course and app version.
            // No version use at this moment.
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_videomodel;
            $course_content = array();
            
            if (IsUserAllowed($course_id)) {
                // first, we login.
                
                $login = self::loginCourse($course_id);
                
                // Exams. Is a quiz that was inserted on lp_item. Normaly with visibility=0 on quiz tool.
                $sql = "select count(id) as quiz from ".tableName($course_id,'quiz')." where feedback_type = 2";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                if ($row["quiz"] > 0) {
                    $course_content[] = 1;
                }
                
                // Videos... Always active, at this moment.
                $course_content[] = 3;
                
                // Other tools. Look for visibility status on course.
                $sql = "select name from ".tableName($course_id,'tool')." where visibility=1 and admin=0 order by id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    switch ($row["name"]) {
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
                            //Not implemented in Chamilo
                            //$course_content[] = 7;
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
                            //Not implemented in chamilo
                            //$course_content[] = 11;
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
                        case "Servicio tecnico":
                            $course_content[] = 15;
                            break;
                    }
                }
                
                // Progress.
                // There is no a Progress tool in Dokeos. Always active.
                $course_content[] = 14;
                
                sort($course_content);
            }
            return $course_content;
        }
        
        public function getCourses() {
            
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
            c.title,c.description,c.course_language
            from course as c, course_rel_user r, user u
            where c.code=r.course_code and u.user_id=r.user_id and u.user_id = ".$user_id." order by c.title";
            mysql_select_db($db_main_database);
            $rs = mysql_query($sql,$conn);
            while ($row = mysql_fetch_array($rs)) {      
                $course = new Course();
                $course->code = $row["code"];
                $course->directory = $row["directory"];
                $course->db_name = $row["db_name"];
                $course->course_language = $row["course_language"];
                $course->title = $row["title"];
                $course->coursedescription = $row["description"];
                
                // Look at ff, if it is a positive value the course subscription has expired.	
                $course->active = 1; 
                $courses[] = $course;
            }
            return $courses;
        }
        
        public function getExams($course_id) {
            
            // Select al Exams from course. In dokeos we consider a exam a quiz that it's inactive (active=0) and has 
            // a value "random" higher than 10.
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_stats_database;
            
            $Exams = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id, title, description, type from ".tableName($course_id,'quiz')." where feedback_type = 2";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                
                while ($row = mysql_fetch_array($rs)) {
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
                    if (mysql_num_rows($rs2) > 0) {
                        $Exam->score = round($row2["exe_result"]*10/$row2["exe_weighting"],1);
                        $Exam->num_intentos = 1;
                    } else {
                        // without score. Not realized.
                        $Exam->score = -1;
                        $Exam->num_intentos  = 0;
                    }
                    $Exam->max_intentos = 1; 
                    
                    $Exams[] = $Exam;
                }
            }
            return $Exams;
        }
        
        public function getExercises($course_id) { 
            
            // Select al quizs from course. In dokeos we consider a quiz if it's active (active=1) and has 
            // 0 value on "random".
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_stats_database;
            
            $Exercises = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id, title, description, type from ".tableName($course_id,'quiz')." where active=1 and type<>6 ";
                // Type 6 not implemented on App.
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
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
                    if (mysql_num_rows($rs2) > 0 ) {
                        $Exercise->score = round($row2["exe_result"]*10/$row2["exe_weighting"],1);
                    } else {
                        // without score. Not realized.
                        $Exercise->score = -1;
                    }
                    $Exercises[] = $Exercise;
                }
            }
            return $Exercises;
        }
        
        public function getQuestions($course_id, $quiz_id) {
            
            // Get 10 random questions from a quiz
            global $conn;
            global $db_prefix;
            $Questions = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select qq.id,qq.question,qq.description,qq.ponderation,qq.position,qq.type,qq.picture from ".tableName($course_id,'quiz')." q, ".tableName($course_id,'quiz_question')." qq, ".tableName($course_id,'quiz_rel_question')." qrq where qrq.question_id = qq.id and qrq.exercice_id= q.id and q.id=". $quiz_id ." ORDER BY RAND() LIMIT 10";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
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
        }
        
        public function getQuestionsExercices($course_id, $quiz_id) {
            
            // Get all questions for an Exercice
            global $conn;
            global $db_prefix;
            $Questions = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select qq.id,qq.question,qq.description,qq.ponderation,qq.position,qq.type,qq.picture from ".tableName($course_id,'quiz')." q,".tableName($course_id,'quiz_question')." qq,".tableName($course_id,'quiz_rel_question')." qrq where qrq.question_id = qq.id and qrq.exercice_id= q.id and q.id=". $quiz_id ." and qq.type<>6 ORDER BY q.id";
                
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
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
        }
        
        public function getAnswers($course_id, $quiz_id) {
            
            // Get answers for a quiz or Exam.
            global $conn;
            global $db_prefix;
            $Answers = array();
            if (IsUserAllowed($course_id)) {
                // First of all we get the questions
                $Questions = self::getQuestions($course_id, $quiz_id);
                foreach ($Questions as $Question) {
                    // Now for each question...
                    $tmpAnswers = array();
                    $sql = "select qa.* from ".tableName($course_id,'quiz')." q,".tableName($course_id,'quiz_question')." qq,".tableName($course_id,'quiz_rel_question')." qrq , ".tableName($course_id,'quiz_answer')." qa where qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." order by qa.question_id, qa.position";
                    mysql_select_db(databaseName($course_id));
                    $rs = mysql_query($sql,$conn);
                    while ($row = mysql_fetch_array($rs)) {
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
            }
            return $Answers;
        }
        
        public function getCourseDescription($course_id) {
            
            // Get course description
            global $conn;
            global $db_prefix;
            $CD = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id,title,content from ".tableName($course_id,'course_description')." order by id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
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
            }
            return $CD;
        }
        
        public function getLinkCategories($course_id) {
            
            // Get link categories
            global $conn;
            global $db_prefix;
            $LinkCategories = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id,category_title, description,display_order from ".tableName($course_id,'link_category')." order by display_order";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                
                //Main Categorie does not exist in Dokeos. So we create it here.
                $LinkCategorie = new LinkCategory();
                $LinkCategorie->id = 0;
                $LinkCategorie->title = 'General';
                $LinkCategorie->categorydescription = 'Categoría principal';
                $LinkCategories[] = $LinkCategorie;
                
                while ($row = mysql_fetch_array($rs)) {	
                    $LinkCategorie = new LinkCategory();	
                    $LinkCategorie->id = $row["id"];
                    $LinkCategorie->title = $row["category_title"];
                    $LinkCategorie->categorydescription = $row["description"];
                    $LinkCategories[] = $LinkCategorie;
                }
            }
            return $LinkCategories;
            
        }
        
        public function getLinks($course_id) {
            
            // Get links from a course, first of all look for categories.
            $LinkCategories = self::getLinkCategories($course_id);
            global $conn;
            global $db_prefix;
            $recolector = array();
            if (IsUserAllowed($course_id)) {
                foreach ($LinkCategories as $category) {
                    $Links =  array() ;
                    $sql = "select id,title,url,description from ".tableName($course_id,'link')." where category_id=" . $category->id . " order by display_order";
                    mysql_select_db(databaseName($course_id));
                    $rs = mysql_query($sql,$conn);
                    while ($row = mysql_fetch_array($rs)) {
                        $Link = new Link();
                        $Link->id = $row["id"];
                        $Link->title = $row["title"];
                        $Link->url = $row["url"];
                        $Link->linkdescription = $row["description"];
                        $Links[] = $Link;
                    }
                    $recolector[] = $Links;
                }
            }
            return $recolector;
        }
        
        public function getChatItems($course_id, $timestamp) {
            
        }
        
        public function newChatItem($course_id, $who, $type, $msg) {
            
        }
        
        public function getCalendarItems($course_id) {
            
            // Get Calendar events.
            global $conn;
            global $db_prefix;
            $Calendar = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id,title,content,start_date,end_date from ".tableName($course_id,'calendar_event')." order by start_date";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $event = new CalendarItem();
                    $event->id = $row["id"];
                    $event->title = $row["title"];
                    $event->content = limpiarcadena(strip_tags($row["content"]));
                    $event->startdate = strtotime($row["start_date"]);
                    $event->enddate = strtotime($row["end_date"]);
                    $Calendar[] = $event;
                }
            } 
            return $Calendar;
            
        }
        
        public function getForumCategorys($course_id) {
            
            // Get course forum categories
            // Each categorie has an array with the forums
            global $conn;
            global $db_prefix;
            $categories = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select cat_id,cat_title from ".tableName($course_id,'forum_category')." order by cat_order";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $category = new ForumCategory();
                    $category->id = $row["cat_id"];
                    $category->title = $row["cat_title"];	
                    $sql = "select forum_id,forum_title from ".tableName($course_id,'forum_forum')." where forum_category=" . $category->id;
                    $rs2 = mysql_query($sql,$conn);
                    $forums = array();
                    while ($row2 = mysql_fetch_array($rs2)) {
                        $forum = new ForumForum();
                        $forum->id = $row2["forum_id"];
                        $forum->title = $row2["forum_title"];
                        $forums[] = $forum;
                    }
                    $category->forums =  $forums;
                    $categories[] = $category;
                }
            }
            return $categories;
        }
        
        public function getForumThreads($course_id, $forum_id) {
            
            // Get forum threads.
            global $conn;
            global $db_prefix;
            $threads = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select thread_id,thread_title,thread_date from ".tableName($course_id,'forum_thread')." where forum_id=". $forum_id. " order by thread_date desc";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $thread = new ForumThread();
                    $thread->id = $row["thread_id"];
                    $thread->title = $row["thread_title"];
                    $thread->date = strtotime($row["thread_date"]);
                    $threads[] = $thread;
                }
            }
            return $threads;
        }
        
        public function getForumPosts($course_id, $thread_id) {
            
            // Get post of the expecified thread and search for poster fistname and lastname.
            global $conn;
            global $db_prefix;
            global $db_main_database;
            $posts = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select post_id, post_title, post_text,post_date, poster_id from ".tableName($course_id,'forum_post')." where thread_id=". $thread_id. " order by post_parent_id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $post = new ForumPost();
                    $post->id = $row["post_id"];
                    $post->title = $row["post_title"];
                    $post->text = limpiarcadena(strip_tags($row["post_text"]));
                    // look for user values.
                    mysql_select_db($db_main_database);
                    $sql2 = "select firstname,lastname from user where user_id=" . $row["poster_id"];
                    $rs2 = mysql_query($sql2,$conn);
                    $row2 = mysql_fetch_array($rs2);
                    $post->poster_name = $row2["firstname"] . ' '. $row2["lastname"];
                    $post->date = strtotime($row["post_date"]);
                    $posts[] = $post;
                }
            }
            return $posts;
        }
        
        public function setForumThread($course_id, $forum_id, $thread_title, $post_title, $post_text) {
            
            // Insert a new thread... new thread with one post.
            global $user_id;
            global $conn;	
            global $db_prefix;
            global $db_main_database;
            if (IsUserAllowed($course_id)) {
                $sql = "insert into ".tableName($course_id,'forum_thread')." (thread_title,forum_id,thread_replies,thread_poster_id,thread_poster_name,thread_views,thread_last_post,thread_date,thread_sticky,locked)
                values ('" . $thread_title . "'," . $forum_id . ",0," .  $user_id . ",'',0,0,now(),0,0)";
                mysql_select_db(databaseName($course_id));
                mysql_query($sql,$conn);
                // select inserted thread_id
                $sql = "select max(thread_id) as thread_id from ".tableName($course_id,'forum_thread');
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                $thread_id = $row["thread_id"];
                // Finally insert new post, calling setForumPost.
                
                return self::setForumPost($course_id, $thread_id, $post_title, $post_text);
            } else {
                return False;
            }
        }
        
        public function setForumPost($course_id, $thread_id, $post_title, $post_text) {
            
            // Insert new post
            global $user_id;
            global $conn;	
            global $db_prefix;
            global $db_main_database;
            if (IsUserAllowed($course_id)) {
                // First of all we need the forum_id
                $sql = "select forum_id from ".tableName($course_id,'forum_thread')." where thread_id=" . $thread_id;
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                $forum_id = $row["forum_id"];
                // Now we need last post
                $sql = "select max(post_id) as post_id from ".tableName($course_id,'forum_post')." where forum_id=" . $forum_id ." and thread_id=" . $thread_id;
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                //It possible that there is no post. Perhaps we came from setForumThread.
                if (isset($row["post_id"])) {
                    $post_parent_id = $row["post_id"];
                } else {
                    // No parent. first post.
                    $post_parent_id = 0;
                }
                // And finally insert new post
                $sql = "insert into ".tableName($course_id,'forum_post')." (post_title, post_text, thread_id, forum_id, poster_id, poster_name, post_date, post_notification, post_parent_id,visible) values ('". $post_title. "','". $post_text. "'," . $thread_id . "," . $forum_id . "," . $user_id . ",'',now(),0,". $post_parent_id .",1)" ;
                return mysql_query($sql,$conn);
            } else {
                return False;
            }
        }
        
        public function getAnnouncements($course_id) {
            
            // Get announcements
            global $conn;
            global $db_prefix;
            $announcements = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select id,title,end_date from ".tableName($course_id,'announcement')." order by display_order";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $announcement = new Announcement();
                    $announcement->id = $row["id"];
                    $announcement->title = $row["title"];
                    $announcement->date = strtotime($row["end_date"]);
                    $announcements[] = $announcement;
                }
            }
            return $announcements;
        }
        
        public function get_tags() {
            
            return array('((user_name))','((teacher_name))','((teacher_email))','((course_title))', '((course_link))');
        }
        
        public function getDetailAnnouncements($course_id, $id) {
            
            // Get announcement values.
            global $conn;
            global $db_prefix;
            global $user_id;
            
            $announcements = array();
            if (IsUserAllowed($course_id)) {
                /*
                 $reader_info  = api_get_user_info($user_id);
                 $course_info  = api_get_course_info($course_id);
                 $teacher_list = Coursemanager::get_teacher_list_from_course_code($course_info['code']);
                 
                 $teacher_name = '';
                 if (!empty($teacher_list)) {
                 foreach($teacher_list as $teacher_data) {
                 $teacher_name  = api_get_person_name($teacher_data['firstname'], $teacher_data['lastname']);
                 $teacher_email = $teacher_data['email'];
                 break;
                 }
                 }
                 $course_link = api_get_course_url();
                 
                 $data['username']        = $reader_info['username'];
                 $data['teacher_name']    = $teacher_name;
                 $data['teacher_email']   = $teacher_email;
                 $data['course_title']    = $course_info['name'];
                 $data['course_link']     = Display::url($course_link, $course_link);
                 */
                
                
                
                $sql = "select title,content,end_date from ".tableName($course_id,'announcement')." where id=" . $id ;
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $announcement = new DetailAnnouncement();
                    $announcement->title = $row["title"];
                    //$content = str_replace(self::get_tags(), $data, $row['content']);
                    //$announcement->content = limpiarcadena(strip_tags($content));
                    $announcement->content = limpiarcadena(strip_tags($row['content']));
                    $announcement->date = strtotime($row["end_date"]);
                    $announcements[] = $announcement;
                }
            }
            return $announcements;
        }
        
        public function getProgress($course_id) {
            
            // Get all values relative to the learner progress
            global $conn;
            global $db_prefix;
            global $db_main_database;
            global $user_id;
            global $db_stats_database;
            $Progress = new Progress();
            if (IsUserAllowed($course_id)) {
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
                while ($row = mysql_fetch_array($rs)) {
                    // Calculating time connected
                    $diferencia = $row["od"] - $row["id"];
                    $diferencia_segundos = $diferencia_segundos + $diferencia;
                    if ( $c == 0 ) {
                        // First connection
                        $primer_acceso = $row["id"];
                    } else {
                        if ($c == mysql_num_rows($rs)-1) {
                            //Last connection
                            $ultimo_acceso = $row["id"];
                        }
                    }
                    $c = $c + 1;
                }
                // Get total number of forum's post
                $sql = "select count(poster_id) as total from ".tableName($course_id,'forum_post')." where poster_id=" . $user_id;
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                $mensajes_foros = $row["total"];
                $mensajes_correo = 0;
                // Global evaluation inserted by the course tutor
                $nota_global = -1;
                //Get Exams
                $sql = "select id,title from ".tableName($course_id,'quiz')." where feedback_type = 2 order by id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $GradesExams = new GradesExams();
                    $GradesExams->id = $row["id"];
                    $GradesExams->title = $row["title"];
                    $GradesExams->grades = 0;
                    // For each Exam we need the learner's score
                    $sql = "select exe_exo_id,exe_result,exe_weighting from track_e_exercices where exe_user_id=" . $user_id . " and exe_cours_id='" . $course_id . "' and exe_exo_id=" .$row["id"] . " order by exe_date DESC";
                    mysql_select_db($db_stats_database);
                    
                    $rs2 = mysql_query($sql,$conn);
                    $row2 = mysql_fetch_array($rs2);
                    if ( mysql_num_rows($rs2) > 0) {
                        /*
                         Now we search for the Exam score. If there are more than one score we take the last one.
                         Real score (0-10) is based on this formula: 
                         (exe_result*10)/exe_weighting
                         */
                        $GradesExams->grades = round( ($row2["exe_result"] * 10 )/$row2["exe_weighting"]);
                        $GradesExams->num_intentos = 1;
                        
                    } else {
                        //Don't have score.
                        $GradesExams->grades = -1;
                        $GradesExams->num_intentos = 0;
                    }
                    $GradesExams->max_intentos = 1;               
                    $GradesExamsList[] = $GradesExams;
                }
                // Get Exercices
                $sql = "select id,title from ".tableName($course_id,'quiz')." where active=1 and type<>6  order by id";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $GradesExercises = new GradesExercises();
                    $GradesExercises->id = $row["id"];
                    $GradesExercises->title = $row["title"];
                    $GradesExercises->attempt = 0;
                    $GradesExercises->grades = -1;
                    // For each Exercice we need the learner's score and attempts  
                    $sql = "select exe_exo_id,exe_result,exe_weighting from track_e_exercices where exe_user_id=" . $user_id . " and exe_cours_id='" . $course_id . "' and exe_exo_id=" .$GradesExercises->id . " order by exe_date";
                    mysql_select_db($db_stats_database);
                    $rs2 = mysql_query($sql,$conn);
                    while ($row2 = mysql_fetch_array($rs2)) {
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
                $sql = "select i.id, i.title, iv.score, iv.status from ".tableName($course_id,'lp_item')." as i, ".tableName($course_id,'lp_view')." v, ".tableName($course_id,'lp_item_view')." iv where i.item_type='sco' and i.id = iv.lp_item_id and v.user_id=". $user_id ." and v.id=iv.lp_view_id order by i.display_order";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $ProgressCourse = new ProgressCourse();
                    $ProgressCourse->id = $row["id"];
                    $ProgressCourse->title = $row["title"];
                    $ProgressCourse->status = $row["status"];
                    $ProgressCourse->progress = $row["score"];
                    $ProgressCourseList[] = $ProgressCourse;
                }
                // Join all data
                
                $Progress->time = $diferencia_segundos;
                $Progress->msg_forum = $mensajes_foros;
                $Progress->msg_mail = $mensaje_correo;
                $Progress->first_access = $primer_acceso;
                $Progress->last_access = $ultimo_acceso;
                $Progress->evaluation = $nota_global;
                $Progress->grades_exams = $GradesExamsList;
                $Progress->grades_exercises = $GradesExercisesList;
                $Progress->progress_course = $ProgressCourseList;
            }
            return $Progress;	
        }
        
        public function getPolls($course_id) {
            
            // Get pools from course
            global $conn;
            global $db_prefix;
            global $user_id;
            
            $SurveyList = array();
            
            if (IsUserAllowed($course_id)) {
                $sql = "select survey_id,title,subtitle,DATEDIFF(CURDATE(),avail_from) as inicio, DATEDIFF(CURDATE(),avail_till) as fin, si.answered  from ".tableName($course_id,'survey')." s, ".tableName($course_id,'survey_invitation')." si where survey_code=code and user=" . $user_id;
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $Survey = new SurveyQuiz();
                    $Survey->id = $row["survey_id"];
                    $Survey->title = limpiarcadena(strip_tags($row["title"]));
                    $Survey->quizdescription = limpiarcadena(strip_tags($row["subtitle"]));
                    // look if Pool it's open and not answered
                    if ($row["inicio"]>=0 && $row["fin"]<=0 && $row["answered"]==0) {
                        $Survey->open = 1;
                    } else {
                        $Survey->open = 0;
                    }
                    $SurveyList[] = $Survey;
                }
            }
            return $SurveyList;
        }
        
        public function getPollsQuestions($course_id, $quiz_id) {
            
            // Select pool's questions
            global $conn;
            global $db_prefix;
            $Questions = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select question_id,survey_question,sort,type,max_value from ".tableName($course_id,'survey_question')." where survey_id=" . $quiz_id . " order by sort";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $Question = new SurveyQuestion();
                    $Question->id = $row["question_id"];
                    $Question->question = limpiarcadena(strip_tags($row["survey_question"]));
                    $Question->sort = $row["sort"];
                    $Question->type = $row["type"];
                    $Question->max_value = $row["max_value"];
                    $Questions[] = $Question;
                }
            } 
            return $Questions;     
        }
        
        public function getPollsAnswers($course_id, $quiz_id) {
            
            // Get pool's questions with answers
            global $conn;
            global $db_prefix;
            $Answers = array();
            if (IsUserAllowed($course_id)) {
                // First of all we get the questions
                $Questions = self::getPollsQuestions($course_id, $quiz_id);
                foreach ($Questions as $Question) {
                    // For each question
                    $tmpAnswers = array();
                    $sql = "select question_option_id, option_text,sort from  ".tableName($course_id,'survey_question_option')." where question_id=" . $Question->id . " order by sort";
                    mysql_select_db(databaseName($course_id));
                    $rs = mysql_query($sql,$conn);
                    while ($row = mysql_fetch_array($rs)) {
                        $tmp = new SurveyAnswer();	
                        $tmp->id = $row["question_option_id"];
                        $tmp->answer = limpiarcadena(strip_tags($row["option_text"]));
                        $tmp->sort = $row["sort"];
                        $tmpAnswers[]= $tmp;
                    }
                    $Answers[] = $tmpAnswers;
                }
            }
            return $Answers;
        }
        
        public function setPollAnswer($answers) {
            
            // Save pool
            global $conn;
            global $db_prefix;
            global $user_id;
            if (IsUserAllowed($course_id)) {
                if (count($answers)>0) {	
                    foreach ( $answers as $answer) {
                        if ($course_id == "") {
                            $course_id = $answer->course_id;
                        }
                        $sql="insert into ".tableName($course_id,'survey_answer')." (survey_id,question_id,option_id,value,user) values (".$answer->survey_id.",".$answer->question_id.",'".$answer->option."',".$answer->value.",".$user_id.")";
                        mysql_select_db(databaseName($course_id));
                        $rs = mysql_query($sql,$conn);
                    } 
                    $sql= "update ".tableName($course_id,'survey_invitation')." set answered=1 where user=".$user_id." and survey_code in (select code from survey where survey_id=". $answer->survey_id.")";
                    $rs = mysql_query($sql,$conn);
                }  
                
                return True;
            } else{
                return False;
            }
        }
        
        public function setAnswer($course_id, $exam_id, $responses, $score, $total) {
            
            // Save answers from exercice.
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_stats_database;
            if (IsUserAllowed($course_id)) {
                $sql = "insert into track_e_exercices(exe_user_id, exe_cours_id, exe_exo_id, exe_result, exe_weighting,exe_date) values (".$user_id.", '".$course_id."', ".$exam_id.", ".$score.", ".$total.",now())";
                mysql_select_db($db_stats_database);
                $rs = mysql_query($sql,$conn);
                $sql = "select max(exe_id) as maximo from track_e_exercices where exe_user_id=".$user_id." and exe_cours_id='".$course_id."' and exe_exo_id=" . $exam_id;
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                $exe_id = $row["maximo"];
                foreach ($responses as $response) {
                    $sql= "insert into track_e_attempt (exe_id,user_id,question_id,answer,course_code,position,tms) 
                    values (".$exe_id.",".$user_id.",".$response->question_id.",'".$response->option."','".$course_id."',".$response->value.",now())";
                    $rs = mysql_query($sql,$conn);
                }
                return True;
            } else {
                return False;
            }
        }
        
        public function getMails($course_id, $id_folder, $info) {
            
        }
        
        public function getDescriptionAttachment($course_id, $id_attachment) {
        }
        
        public function getContentAttachment($course_id, $id_attachment) {
            
        }
        
        public function getContacts($course_id) {
        }
        
        public function getFolders($course_id) {
            
        }
        
        public function setFolderMail($course_id, $name_folder) {  
            
        }
        
        public function changeFolder($course_id, $id_folder, $name_folder, $del_folder) {
            
        }  
        
        public function setLectureMail($course_id, $id_mail, $lecture_mail) {
        }
        
        public function setImportantMail($course_id, $id_mail, $important_mail) {
            
        }
        
        public function setDeleteMail($course_id, $id_mail, $del_mail) {
            
        }
        
        public function changeFolderMail($course_id, $id_mail, $id_folder) {
            
            
        }
        
        public function setMail($course_id, $subject, $id_to, $content, $id_attachment) {
            
        }
        
        public function getPodcast($course_id) {
            
            // Get Podcast from course.
            // Tool on the way to be deprecated.
            global $conn;
            global $db_prefix;
            global $ruta_web;
            $PodcastList = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select title,comment,size,path from ".tableName($course_id,'podcast')." order by title";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    $Podcast = new Podcast();
                    $Podcast->title = $row["title"];
                    $Podcast->comment = $row["comment"];
                    $Podcast->size = $row["size"];
                    $Podcast->path =  $row["path"];
                    $PodcastList[] = $Podcast;
                }
            }
            return $PodcastList;
        }
        
        public function getDocuments($course_id) {
            
            // Get course documents
            global $conn;
            global $db_prefix;
            global $ruta_web;
            // Valid extensions
            $lista_extensiones = array('htm','jpg','gif','html','png','xls','doc','ppt','pdf','htm','key','pages','numbers');
            // Crap folders... Nothing important there.
            $lista_folders = array('audio','images','flash','HotPotatoes_files','css');
            // Init Vars
            $c=0;
            $num_items=0;
            $DocumentList = array();
            $FinalDocumentList = array();
            if (IsUserAllowed($course_id)) {
                $sql = "select path,comment,title,filetype,size,readonly from ".tableName($course_id,'document')." order by path";
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                while ($row = mysql_fetch_array($rs)) {
                    // Discriminate not useful folders and subfolders.
                    $valido=0;
                    $resultado = explode ("/",$row["path"]);
                    if (in_array($resultado[1],$lista_folders) || in_array($resultado[2],$lista_folders) || in_array($resultado[3],$lista_folders)) {
                        // Default folder not useful for learners
                        $valido=0;
                    } else {	
                        $resultado = explode (".",$row["path"]);
                        if  (in_array($resultado[1],$lista_extensiones) || $row["filetype"] == 'folder') {
                            // Valid extensión or folder created by tutor or gestor
                            if ($row["title"] == "") {
                                // No title... rare but posible
                                $valido=0;				
                            } else {
                                // Valid one.
                                $valido=1;
                            }
                        } else {
                            $valido=0;
                        }
                    }
                    
                    if ($valido == 1) {
                        $Document = new Documents();
                        $Document->dir =  $row["path"];
                        $Document->title = $row["title"];
                        $Document->filetype = $row["filetype"];
                        // El nivel en la app debe empezar en 2. :?
                        $Document->nivel = substr_count ($row["path"],'/') +1;
                        $Document->id = $c;
                        $DocumentList[] = $Document;
                        $c = $c + 1;
                    }
                    
                }
                
                // Delete empty folders
                while (count($DocumentList) != $num_items) {
                    $c = 0;
                    $num_items = count($DocumentList);
                    while ($c < count($DocumentList)) {
                        if (($c == count($DocumentList)) && ($DocumentList[$c]->filetype == "folder")) {
                            unset($DocumentList[$c]);
                            $c = count($DocumentList);
                        } else {
                            if (($DocumentList[$c]->filetype == "folder") && ($DocumentList[$c+1]->nivel<= $DocumentList[$c]->nivel) ) {
                                unset($DocumentList[$c]);
                                $c = count($DocumentList);
                            }
                        }
                        $c = $c + 1;
                    }
                }
                // constructing array
                $c = 0;
                foreach ($DocumentList as $row) {			
                    $row->id = $c;
                    $FinalDocumentList[] = $row;
                    $c = $c + 1;
                }
            }
            return $FinalDocumentList;
        }
        
        public function markExamAsCompletedInScorm($course_id, $quiz_id) {
            
            // Save exam (SCORM)
            global $conn;
            global $db_prefix;
            global $user_id;
            if (IsUserAllowed($course_id)) {
                // Look for necesary id's on lp and lp_item tables
                $sql = "select id,lp_id from ".tableName($course_id,'lp_item')." where path=" . $quiz_id;
                mysql_select_db(databaseName($course_id));
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                $lp_item_id = $row["id"];
                $lp_id = $row["lp_id"];
                
                // Look for id on lp_view table.
                $sql = "select id from ".tableName($course_id,'lp_view')." where user_id=" . $user_id ." and lp_id=" . $lp_id;
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                if (mysql_num_rows($rs) > 0) {
                    $lp_view_id = $row["id"];
                } else {
                    // There is no lp_view id, first access. Adding it.
                    $sql = "insert into  ".tableName($course_id,'lp_view')." (lp_id, user_id, view_count) values (".$lp_id.",".$user_id.", 1)";
                    $rs = mysql_query($sql,$conn);
                    // Get inserted id
                    $sql = "select id from  ".tableName($course_id,'lp_view')." where user_id=" . $user_id ." and lp_id=" . $lp_id;
                    $rs = mysql_query($sql,$conn);
                    $row = mysql_fetch_array($rs);
                    $lp_view_id = $row["id"];
                }
                // Look for rows on lp_item_view table.
                $sql = "select id,start_time from  ".tableName($course_id,'lp_item_view')." where lp_view_id=".$lp_view_id." and lp_item_id=" . $lp_item_id;
                $rs = mysql_query($sql,$conn);
                $row = mysql_fetch_array($rs);
                
                if (mysql_num_rows($rs) > 0) {
                    // Update values
                    $start_time = "";
                    if ($row["start_time"] == 0 ) {
                        $start_time= ", start_time=" . time();		
                    }
                    $sql = "update  ".tableName($course_id,'lp_item_view')." set status='completed' " . $start_time . " where lp_view_id=".$lp_view_id." and lp_item_id=" . $lp_item_id;
                    $rs = mysql_query($sql,$conn);
                } else {
                    // There is no row, insert it.
                    $sql = "insert into  ".tableName($course_id,'lp_item_view')." (lp_item_id, lp_view_id, view_count, start_time, status, score, suspend_data) values (".$lp_item_id.", ".$lp_view_id.", 1,".time().", 'completed',0, '')";
                    $rs = mysql_query($sql,$conn);
                }
                return True;
            } else {
                return False;
            }
            
        }
        
        public function saveExamScore($course_id, $quiz_id, $questions, $answers) {
            
            // Save Exam score
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_stats_database;
            if (IsUserAllowed($course_id)) {
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
                foreach ($questions as $question) {			
                    $weighting = $weighting + $question->ponderation;
                    foreach ( $answers[$i] as $answer) {
                        if ($answer->selected == true) {
                            $sql = "insert into track_e_attempt (exe_id, user_id, question_id, answer, teacher_comment, marks, course_code,tms) values (".$new_exe_id.",".$user_id.",".$question->id. ",".$answer->id.",'',".$answer->ponderation.",'".$course_id."',now() )";
                            $rs = mysql_query($sql,$conn);
                            if ($answer->correct) {
                                $result = $result + $answer->ponderation;
                            }
                        }			
                    }
                    $i = $i + 1;
                }
                
                // And finaly update score
                $sql = "update track_e_exercices set exe_result=".$result.",exe_weighting=".$weighting.", exe_date= now() where exe_id=" . $new_exe_id;
                mysql_select_db($db_stats_database);
                $rs = mysql_query($sql,$conn);
                return true;
            } else {
                return False;
            }
        }
        
        public function getChatReferenceTime() {
            
            // Return time.
            return time();
        }
        
        public function getInfo() {
            
            // Return username
            global $conn;
            global $db_prefix;
            global $user_id;
            global $db_main_database;
            $sql = "Select username from user where user_id=" . $user_id;
            mysql_select_db($db_main_database);
            $rs = mysql_query($sql,$conn);
            $row = mysql_fetch_array($rs);
            if ( mysql_num_rows($rs) > 0) {
                $diccionario = array();
                $diccionario["username"]= $row["username"];
            }
            return $diccionario;
        }
        
        public function getQuestionAndAnswers($course_id, $quiz_id) {
            
            // Get questions and answers from a quiz.
            global $conn;
            global $db_prefix;
            $Answers = array();
            $QuestionAndAnswers = array();
            if (IsUserAllowed($course_id)) {
                // First of all we need questions.
                $Questions = self::getQuestions($course_id, $quiz_id);
                
                foreach ($Questions as $Question) {
                    // Now for each question... Look for answers.
                    $tmpAnswers = array();
                    $sql = "select qa.* from  ".tableName($course_id,'quiz')." q, ".tableName($course_id,'quiz_question')." qq, ".tableName($course_id,'quiz_rel_question')." qrq,  ".tableName($course_id,'quiz_answer')." qa where qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." and q.id= " . $quiz_id ." order by qa.question_id, qa.position";
                    mysql_select_db(databaseName($course_id));
                    $rs = mysql_query($sql,$conn);
                    while ($row = mysql_fetch_array($rs)) {
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
            } 
            return $QuestionAndAnswers;      
        }
        
        public function getQuestionAndAnswersExer($course_id, $quiz_id) {
            
            // Get questions and answers from a Exercice.
            global $conn;
            global $db_prefix;
            $Answers = array();
            $QuestionAndAnswers = array();
            if (IsUserAllowed($course_id)) {
                // First of all we need questions.
                $Questions = self::getQuestionsExercices ($course_id, $quiz_id);
                foreach ($Questions as $Question) {
                    // Now for each question... Look for answers.
                    $tmpAnswers = array();
                    $sql = "select qa.* from  ".tableName($course_id,'quiz')." q, ".tableName($course_id,'quiz_question')." qq, ".tableName($course_id,'quiz_rel_question')." qrq,  ".tableName($course_id,'quiz_answer')." qa where  qrq.question_id = qq.id and qrq.exercice_id=q.id and qa.question_id=qq.id and qq.id=". $Question->id ." order by qa.question_id, qa.position";
                    mysql_select_db(databaseName($course_id));
                    $rs = mysql_query($sql,$conn);
                    while ($row = mysql_fetch_array($rs)) {
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
            }
            return $QuestionAndAnswers;      
        }
        
        public function registerDeviceId ($deviceId,$appVersion,$model,$name,$systemName,$systemVersion,$localizedModel,$userInterfaceIdiom) {
            
            return true;
        }

        public function getMultimedia($course_id) {
            return new Multimedia();
        }
        public function getGlosario($course_id) {
            return true;
        }
    }
    
    function limpiarcadena($cadena) { 
        
        // Clear a string
        $login = $cadena;
        $b     = array("&rdquo;","&rlquo;","&#8230;","&acute;","&nbsp;","&aacute;","&eacute;","&iacute;","&oacute;","&uacute;","&Aacute;","&Eacute;","&Iacute;","&Oacute;","&Uacute;","&ntilde;","&Ntilde;","&uumñ;","&Uuml;","&#8220;","&#8221;","&quot;","&middot;","&rdquo;", "&ldquo;","&raquo;","&laquo;","&iquest;","&iexcl;","&ordf;","&amp;","&#8230;","&rsquo;","&lsquo;"); 
        
        $c     = array('"','"',"","'"," ","á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","Ñ","ü","Ü","'","'",'"','_', '>','<','»','«','¿','¡','ª','&','_',"'","'"); 
        $login = str_replace($b,$c,$login);
        $l=0;
        while ($l != strlen($login)) {
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
    
       
    function isScoCompleted ($course_id,$id,$sco_count) {
        
        global $conn;
        global $db_prefix;
        global $user_id;
        // Must have files on lp_view and lp_item_view tables.
        $sql = "select id from  ".tableName($course_id,'lp_view')." where user_id=" . $user_id ." and lp_id=" . $id;
        mysql_select_db(databaseName($course_id));
        $rs = mysql_query($sql,$conn);
        $row = mysql_fetch_array($rs);
        if (mysql_num_rows($rs) > 0) {
            $lp_view_id = $row["id"];
            $sql = "select id,suspend_data from  ".tableName($course_id,'lp_item_view')." where lp_view_id=".$lp_view_id." and lp_item_id=" . $id;
            $rs2 = mysql_query($sql,$conn);
            $row2 = mysql_fetch_array($rs2);
            if (mysql_num_rows($rs2) > 0) {
                // It's all right, lets see if sco is completed
                $count_visited = explode("#",$row2["suspend_data"]);
                if ( count($count_visited) == $sco_count) {
                    // Completed
                    return True;
                }
            }
        }   
        // Incompleted.
        return False;
    }
    
    function isVideoCompleted ($course_id,$sco_id,$id_video) {
        
        global $conn;
        global $db_prefix;
        global $user_id;
        // First of all we need launch_data values from lp_item table
        $sql = 'select launch_data from '.tableName($course_id,'lp_view').' where id=' . $sco_id ;
        mysql_select_db(databaseName($course_id));    
        $rs = mysql_query($sql,$conn);
        $row = mysql_fetch_array($rs);   
        // Now we need to see if video is completed
        // Get Launch_data array
        $pages_array = getVideomodelPagesArray ($row["launch_data"]); 
        // Get video Key from array
        $key = GetKeyFromPagesArray ($pages_array,$id_video);	 
        // Get suspend_data from lp_item_view
        $sql = "select suspend_data from ".tableName($course_id,'lp_item_view')." iv, ".tableName($course_id,'lp_view')." v where v.user_id=" . $user_id . " and v.id=iv.lp_view_id and iv.lp_item_id=" .$sco_id;
        $rs = mysql_query($sql,$conn);
        $row = mysql_fetch_array($rs);
        $suspend_data = explode("#",$row["suspend_data"]);
        // Look if located key is in suspend_data
        if (in_array($key,$suspend_data)) {
            return True;
        } else {
            return False;
        }
    }
    
    function GetKeyFromPagesArray ($pages_array,$id_video) {
        
        // Look for the video key in the array
        while ($page = current($pages_array)) {
            if ($page == $id_video) {
                $key = key($pages_array) +1;
            }
            next($pages_array);
        }
        return $key;
    }    
    
    function getVideomodelScoId ($launchdata) {
        
        // return sco id extracted from launchdata
        $temp = explode("#",$launchdata);
        return $temp[0];   
    }
    
    function getVideomodelPageslist($launchdata) {
        
        // return viodeos id extracted from launchdata
        $temp = explode("#",$launchdata);
        return $temp[1];   
    }
    
    function getVideomodelPagesArray ($launchdata) {
        
        // return sco id extracted from launchdata
        $temp = explode("#",$launchdata);
        $pagesArray = explode(",",$temp[1]);   
        return $pagesArray;
    }
    
    function tableName($course,$table) {
        
        global $table_prefix;
        global $db_prefix;
        global $db_glue;
        global $single_database;
        $table_name = $table;
        if ($single_database == true) {
            $table_name = $table_prefix.$db_prefix.$course.$db_glue.$table;
        }
        
        return $table_name;
    }
    
    function databaseName($course) {

        global $single_database;
        global $db_prefix;
        global $db_main_database;
        $database_name = $db_prefix.$course;
        if ($single_database == true) {
            $database_name = $db_main_database;
        }
        
        return $database_name;
    }    
    function IsUserAllowed($course_code)
    {
        //Check if user is allowed on course
        global $conn;
        global $db_main_database;
        global $user_id;
        
        $sql = "select count(user_id) as total from course_rel_user where user_id=$user_id and course_code='".$course_code."'";
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
