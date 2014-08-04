<?php
// This file is part of Assessment module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * assessment module library
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ('rubric/lib.php');
require_once($CFG->libdir.'/formslib.php');

class assessment_base {
    var $cm;
    var $course;
    var $activitydetail;
    var $assessment;

    var $strassessment;
    var $strassessments;
    var $strlastmodified;

    var $pagetitle;
    var $usehtmleditor;
    var $defaultformat;
    var $context;
    var $type;
    var $rubric;
    
    /**
    * Constructor
    */
    function assessment_base($cmid='staticonly', $assessment=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;
        
        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }
        
        global $CFG;
        
        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('assessment', $cmid)) {
            print_error('invalidcoursemodule');
        }
        
        $this->context = context_module::instance($this->cm->id);
        
        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('coursemisconf', 'assessment');
        }
        
        if ($assessment) {
            $this->assessment = $assessment;
        } else if (! $this->assessment = $DB->get_record('assessment', array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'assessment');
        }
        
        if ($teacherassessment = $DB->get_record('assessment_types', array('assessmentid'=>$this->assessment->id, 'type'=>0))) {
            $this->assessment->teacher = $teacherassessment->id;
            $this->assessment->teacherweight = $teacherassessment->weight;
            $this->assessment->teachertimestart = $teacherassessment->timestart;
            $this->assessment->teachertimeend = $teacherassessment->timeend;
            $this->assessment->teachertimepublish = $teacherassessment->timepublish;
        }
        
        if ($selfassessment = $DB->get_record('assessment_types', array('assessmentid'=>$this->assessment->id, 'type'=>1))) {
            $this->assessment->self = $selfassessment->id;
            $this->assessment->selfweight = $selfassessment->weight;
            $this->assessment->selftimestart = $selfassessment->timestart;
            $this->assessment->selftimeend = $selfassessment->timeend;
            $this->assessment->selftimepublish = $selfassessment->timepublish;
        }
        
        if ($peerassessment = $DB->get_record('assessment_types', array('assessmentid'=>$this->assessment->id, 'type'=>2))) {
            $this->assessment->peer = $peerassessment->id;
            $this->assessment->peerweight = $peerassessment->weight;
            $this->assessment->peernum = $peerassessment->peernum;
            $this->assessment->peergroupmode = $peerassessment->peergroupmode;
            $this->assessment->peertimestart = $peerassessment->timestart;
            $this->assessment->peertimeend = $peerassessment->timeend;
            $this->assessment->peertimepublish = $peerassessment->timepublish;
        }
        
        $this->assessment->cmidnumber = $this->cm->id;     // compatibility with modedit assessment obj
        $this->assessment->courseid   = $this->course->id; // compatibility with modedit assessment obj
        
        $this->strassessment = get_string('modulename', 'assessment');
        $this->strassessments = get_string('modulenameplural', 'assessment');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strassessment.': '.format_string($this->assessment->name,true));

        $this->rubric = new rubric($this->assessment->rubricid, $this->assessment);
      
        if ($this->usehtmleditor) {
            $this->defaultformat = FORMAT_HTML;
        } else {
            $this->defaultformat = FORMAT_MOODLE;
        }
    }
    
    function add_instance($assessment) {
        global $CFG, $COURSE, $DB;
        
        $assessment->timemodified = time();
        
        if ($assessment->rubricid != 0) {
            $selected_rubric = new rubric($assessment->rubricid);
            $assessment->grade = $selected_rubric->points;
        }
        
        $assessment->id = $DB->insert_record("assessment", $assessment);

        if ($assessment->teacher == 1) {
            $assessment_teacher = new stdClass();
            $assessment_teacher->assessmentid = $assessment->id;
            $assessment_teacher->type = 0;
            $assessment_teacher->timestart = $assessment->teacherstart;
            $assessment_teacher->timeend = $assessment->teacherend;
            $assessment_teacher->timepublish = $assessment->teacherpublish;
            $assessment_teacher->weight = $assessment->teacherweight;
            $assessment_teacher->peernum = 0;
            $assessment_teacher->peergroupmode = 0;
            $DB->insert_record('assessment_types', $assessment_teacher);
        }

        if ($assessment->self == 1) {
            $assessment_self = new stdClass();
            $assessment_self->assessmentid = $assessment->id;
            $assessment_self->type = 1;
            $assessment_self->timestart = $assessment->selfstart;
            $assessment_self->timeend = $assessment->selfend;
            $assessment_self->timepublish = $assessment->selfpublish;
            $assessment_self->weight = $assessment->selfweight;
            $assessment_self->peernum = 0;
            $assessment_self->peergroupmode = 0;
            $DB->insert_record('assessment_types', $assessment_self);
        }

        if ($assessment->peer == 1) {
            $assessment_peer = new stdClass();
            $assessment_peer->assessmentid = $assessment->id;
            $assessment_peer->type = 2;
            $assessment_peer->timestart = $assessment->peerstart;
            $assessment_peer->timeend = $assessment->peerend;
            $assessment_peer->timepublish = $assessment->peerpublish;
            $assessment_peer->weight = $assessment->peerweight;
            $assessment_peer->peernum = $assessment->peernum;
            $assessment_peer->peergroupmode = $assessment->peergroupmode;
            $DB->insert_record('assessment_types', $assessment_peer);
        }
        
        # May have to add extra stuff in here #
        if ($assessment->forum) {
            $this->add_discussions($assessment);
        }
        
        return $assessment->id;
    }
    
    function add_discussions($assessment) {
        global $CFG, $COURSE, $DB;
        
        // If there is discussion already exist, skip adding new discussions all together
        // !!!!!May have to do it more properly by only skipping existing discussion and create new discussion for new users
        if ($DB->get_records("assessment_discussions", array("assessmentid"=>$assessment->id))) {
            return true;
        }
        
        require_once('locallib.php');
        
        if (isset($assessment->workmode) && $assessment->workmode == 1) {
            require_once($CFG->libdir.'/grouplib.php');
            $groupsincourse = groups_get_all_groups($COURSE->id);
            foreach ($groupsincourse as $singlegroup) {
                $groupmembers = groups_get_members($singlegroup->id);
                if (!empty($groupmembers)) {
                    $first_groupmember = array_shift($groupmembers);
                    $first_groupmember_id = $first_groupmember->id;
                } else {
                    continue;
                }

                $discussion = new stdClass();
                $discussion->assessmentid = $assessment->id;
                $discussionintro = get_string('discussionintro', 'assessment');
                $discussion->name = str_replace('studentname', $singlegroup->name, $discussionintro);
                $discussion->intro = get_string('pleasereply', 'assessment');
                $discussion->format = 0;
                $discussion->userid = $first_groupmember_id;
                $discussion->groupid  = $singlegroup->id;

                if (! $discussionid = assessment_add_discussion($discussion, $discussion->intro)) {
                    print_error('erroradddiscussion', 'assessment');
                }

                if (! $DB->set_field("assessment_discussions", "userid", $first_groupmember_id, array("id"=>$discussionid))) {
                    print_error('errormodifydiscussionauthor', 'assessment');
                }

                if (! $DB->set_field("assessment_posts", "userid", $first_groupmember_id, array("discussionid"=>$discussionid))) {
                    print_error('errormodifypostauthor', 'assessment');
                }
            }
        } else {
            /// Get all ppl that are allowed to submit assessment
            $context = context_course::instance($COURSE->id);
            if ($users = get_users_by_capability($context, 'mod/assessment:submit', 'u.id', '', '', '', '', '', false)) {
                $users = array_keys($users);
            } else {
                // No users!
                return true;
            }
            
            $fullnameformat = get_config('', 'fullnamedisplay');
            $prefix = 'u.';
            if ($fullnameformat == 'lastname firstname') {
                $selectfullname = $prefix.'lastname, " ", '.$prefix.'firstname';
            } else if ($fullnameformat == 'firstname lastname') {
                $selectfullname = $prefix.'firstname, " ", '.$prefix.'lastname';
            } else {
                $selectfullname = $prefix.'lastname, " ", '.$prefix.'firstname';
            }
            $selectfullname = 'CONCAT('.$selectfullname.')';
            
            list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
            
            $sql = "SELECT u.id, ".$selectfullname." AS fullname FROM {user} u ";
            $sql .= "WHERE u.id $in_sql";
            $ausers = $DB->get_records_sql($sql, $in_params);
            
            foreach ($ausers as $auser) {
                $discussion = new stdClass();
                $discussion->assessmentid = $assessment->id;
                $discussionintro = get_string('discussionintro', 'assessment');
                $discussion->name = str_replace('studentname', $auser->fullname, $discussionintro);
                $discussion->intro = get_string('pleasereply', 'assessment');
                $discussion->format = 0;
                $discussion->userid   = $auser->id;
                $discussion->groupid  = -1;

                if (! $discussionid = assessment_add_discussion($discussion, $discussion->intro)) {
                    print_error('erroradddiscussion', 'assessment');
                }

                if (! $DB->set_field("assessment_discussions", "userid", $auser->id, array("id"=>$discussionid))) {
                    print_error('errormodifydiscussionauthor', 'assessment');
                }

                if (! $DB->set_field("assessment_posts", "userid", $auser->id, array("discussionid"=>$discussionid))) {
                    print_error('errormodifypostauthor', 'assessment');
                }
            }
        }
        return true;
    }
    
    function delete_instance($assessment) {
        global $DB;
        $result = true;
        
        # Delete any dependent records here #
        
        // delete forum
        if ($assessment->forum) {
            $result = $this->delete_forum($assessment);
        }
        
        // delete all submissions with all attachments - ignore errors
        $this->delete_submission_files($assessment);
        
        // Delete grade related records
        if ($allassessmentids = $DB->get_records('assessment_types', array('assessmentid'=>$assessment->id), '', 'id')) {
            $allassessmentids = array_keys($allassessmentids);
            list($in_sql1, $in_params1) = $DB->get_in_or_equal($allassessmentids, SQL_PARAMS_NAMED);
            if ($allgradeids = $DB->get_records_select('assessment_grades', 'assessmentid '.$in_sql1, $in_params1)) {
                $allgradeids = array_keys($allgradeids);
                list($in_sql2, $in_params2) = $DB->get_in_or_equal($allgradeids, SQL_PARAMS_NAMED);
                $deletegradespec = $DB->delete_records_select('assessment_grade_specs', 'gradeid '.$in_sql2, $in_params2);
            }
            $deletegrades = $DB->delete_records_select('assessment_grades', 'assessmentid '.$in_sql1, $in_params1);
        }
        
        if (! $DB->delete_records("assessment_types", array("assessmentid"=>$assessment->id))) {
            $result = false;
        }
        
        if (! $DB->delete_records("assessment_submissions", array("assessmentid"=>$assessment->id))) {
            $result = false;
        }
        
        if (! $DB->delete_records("assessment", array("id"=>$assessment->id))) {
            $result = false;
        }
        
        assessment_grade_item_delete($assessment);
        
        return $result;
    }
    
    function delete_submission_files($assessment) {
        // now get rid of all files
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('assessment', $assessment->id)) {
            $context = context_module::instance($cm->id);
            $fs->delete_area_files($context->id);
        }
    }
   
    function delete_forum($assessment) {
        global $DB;
        $result = true;
        
        if ($discussions = $DB->get_records("assessment_discussions", array("assessmentid"=>$assessment->id))) {
            foreach ($discussions as $discussion) {
                if (! $DB->delete_records("assessment_posts", array("discussionid"=>$discussion->id))) {
                    $result = false;
                }
            }
        }
        
        if (! $DB->delete_records("assessment_discussions", array("assessmentid"=>$assessment->id))) {
            $result = false;
        }
        
        if (! $DB->delete_records("assessment_read", array("assessmentid"=>$assessment->id))) {
            $result = false;
        }
        
        return $result;
    }
    
    function update_instance($assessment) {
        global $CFG, $DB;
        
        $assessment->timemodified = time();
        $assessment->id = $assessment->instance;
        
        if ($assessment->teacher == 1) {
            $assessment_teacher = new stdClass();
            $assessment_teacher->assessmentid = $assessment->id;
            $assessment_teacher->type = 0;
            $assessment_teacher->timestart = $assessment->teacherstart;
            $assessment_teacher->timeend = $assessment->teacherend;
            $assessment_teacher->timepublish = $assessment->teacherpublish;
            $assessment_teacher->weight = $assessment->teacherweight;
            $assessment_teacher->peernum = 0;
            $assessment_teacher->peergroupmode = 0;
            
            if ($teacher = $DB->get_record("assessment_types", array("assessmentid"=>$assessment->id, "type"=>0))) {
                $assessment_teacher->id = $teacher->id;
                $DB->update_record("assessment_types", $assessment_teacher);
            } else {
                $assessment_teacher->id = $DB->insert_record("assessment_types", $assessment_teacher);
            }
            $assessment->teacher = $assessment_teacher->id;
        } else {
            $DB->delete_records("assessment_types", array("assessmentid"=>$assessment->id, "type"=>0));
        }

        if ($assessment->self == 1) {
            $assessment_self = new stdClass();
            $assessment_self->assessmentid = $assessment->id;
            $assessment_self->type = 1;
            $assessment_self->timestart = $assessment->selfstart;
            $assessment_self->timeend = $assessment->selfend;
            $assessment_self->timepublish = $assessment->selfpublish;
            $assessment_self->weight = $assessment->selfweight;
            $assessment_self->peernum = 0;
            $assessment_self->peergroupmode = 0;
            
            if ($self = $DB->get_record("assessment_types", array("assessmentid"=>$assessment->id, "type"=>1))) {
                $assessment_self->id = $self->id;
                $DB->update_record("assessment_types", $assessment_self);
            } else {
                $assessment_self->id = $DB->insert_record("assessment_types", $assessment_self);
            }
            $assessment->self = $assessment_self->id;
        } else {
            $DB->delete_records("assessment_types", array("assessmentid"=>$assessment->id, "type"=>1));
        }
        
        if ($assessment->peer == 1) { 
            $assessment_peer = new stdClass();
            $assessment_peer->assessmentid = $assessment->id;
            $assessment_peer->type = 2;
            $assessment_peer->timestart = $assessment->peerstart;
            $assessment_peer->timeend = $assessment->peerend;
            $assessment_peer->timepublish = $assessment->peerpublish;
            $assessment_peer->weight = $assessment->peerweight;
            $assessment_peer->peernum = $assessment->peernum;
            $assessment_peer->peergroupmode = $assessment->peergroupmode;
            
            if ($peer = $DB->get_record("assessment_types", array("assessmentid"=>$assessment->id, "type"=>2))) {
                $assessment_peer->id = $peer->id;
                $DB->update_record("assessment_types", $assessment_peer);
            } else {
                $assessment_peer->id = $DB->insert_record("assessment_types", $assessment_peer);
            }
            $assessment->peer = $assessment_peer->id;
        } else {
            $DB->delete_records("assessment_types", array("assessmentid"=>$assessment->id, "type"=>2));
        }
        
        if ($assessment->rubricid != 0) {
            $selected_rubric = new rubric($assessment->rubricid);
            $assessment->grade = $selected_rubric->points;
        }
        
        // Add a new forum
        if ($assessment->forum != 0) {
            $this->add_discussions($assessment);
        } else {
            $this->delete_forum($assessment);
        }
        
        assessment_grade_item_update($assessment);
        
        return $DB->update_record("assessment", $assessment);
    }
    
    function view() {
        global $CFG, $DB, $USER, $SESSION, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');
        
        $course = $this->course;
        $assessment = $this->assessment;
        $cm = $this->cm;
        
        $context = $this->context;
        require_capability('mod/assessment:view', $context);
        
        $PAGE->requires->js_init_call('initRubricStr', array(get_string('hiderubric', 'assessment'), get_string('showrubric', 'assessment')));
        $this->view_header();
        
        $course_context = context_course::instance($course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="view_gradebook.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }
        
        $is_submit_files = $assessment->numfiles;
        
        $this->view_intro();
        
        $perpage = 30;
        $pageno = optional_param('page', 0, PARAM_INT);
        $width = 800;
        
        $currentgroup = '';
        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        if (!$allowgroups = groups_get_activity_allowed_groups($cm)) $allowgroups = array();
        $groupids = array_keys($allowgroups);
        
        /// Get all ppl that are allowed to submit assessment
        if ($users = get_users_by_capability($context, 'mod/assessment:submit', 'u.id', 'u.id ASC', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }
        
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id ASC')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }
        
        /*************************************************/
        // Work mode is group instead of individual
        if ($assessment->workmode != 0) {
            $workmode = $assessment->workmode = 'group';
            $workmodeids = $groupids;
            $totalnum = empty($groupids) ? 0 : count($groupids);
            $tablecol_name = $assessment->peergroupmode == 1 ? 'fullname' : 'groupname';
        } else {
            $workmode = $assessment->workmode = 'user';
            $workmodeids = $users;
            $totalnum = empty($users) ? 0 : count($users);
            $tablecol_name = 'fullname';
        }
        /*************************************************/
        
        $assessment_status = $this->get_assessment_status();
        if (has_capability('mod/assessment:teachergrade', $context) || has_capability('mod/assessment:teacherview', $context)) {
            // Filter store in session variable
            $filter = optional_param('filter', 0, PARAM_TEXT);
            if ($filter !== 0) {
                // append assessment id so that each assessment have its own
                $SESSION->mod_assessment_teacher_view_where = $filter."_".$assessment->id;
            } else {
                if (isset($SESSION->mod_assessment_teacher_view_where)) {
                    $session_temp = explode("_", $SESSION->mod_assessment_teacher_view_where);
                    $filter = $session_temp[0];
                } else {
                    $filter = 'all';
                }
            }
         
            $viewer = 'teacher';
            
            if ($assessment->rubricid) {
                echo $OUTPUT->heading(get_string('rubric', 'assessment'), 2, 'main teacherviewheading');
                $this->rubric->view();
            }
            
            $tablecolumns = array();
            $tableheaders = array();
         
            $tablecolumns[] = $tablecol_name;
            $tableheaders[] = get_string($tablecol_name, 'assessment');
            
            $tablecolumns[] = 'class';
            $tableheaders[] = get_string('class', 'assessment');
            
            $tablecolumns[] = 'classno';
            $tableheaders[] = get_string('classno', 'assessment');
            
            if (isset($assessment->peer) && $assessment->peergroupmode == 1) {
                $tablecolumns[] = 'groupname';
                $tableheaders[] = get_string('groupname', 'assessment');
            }
            
            if (isset($assessment->teacher)) {
                $tablecolumns[] = 'agt_grade';
                $tableheaders[] = get_string('teacherassessment', 'assessment');
            }
            if (isset($assessment->self)) {
                $tablecolumns[] = 'ags_grade';
                $tableheaders[] = get_string('selfassessment', 'assessment');
            }
            if (isset($assessment->peer)) {
                $tablecolumns[] = 'avgpeergrade';
                $tableheaders[] = get_string('peerassessment', 'assessment');
            }
         
            $tablecolumns[] = 'finalgrade';
            $tableheaders[] = get_string('finalgrade', 'assessment');
            
            $tablecolumns[] = 'forum';
            $tableheaders[] = get_string('forum', 'assessment');
            
            require_once($CFG->libdir.'/tablelib.php');
            
            $table = new flexible_table('mod-assessment-grades', 'List of submissions and assessments', $filter);
            
            $table->define_columns($tablecolumns);
            $table->define_headers($tableheaders);
            $table->define_baseurl($CFG->wwwroot.'/mod/assessment/view.php?id='.$cm->id.'&amp;currentgroup='.$currentgroup);
            
            $table->sortable(true, 'lastname'); //sorted by lastname by default
            $table->collapsible(false);
            $table->initialbars(false);
            $table->column_suppress($tablecol_name);
            
            $table->column_class($tablecol_name, $tablecol_name);
            if (isset($assessment->teacher)) $table->column_class('agt_grade', 'textaligncenter grade teacherassessment');
            if (isset($assessment->self)) $table->column_class('ags_grade', 'textaligncenter grade selfassessment');
            if (isset($assessment->peer)) $table->column_class('avgpeergrade', 'textaligncenter grade peerassessment');
            $table->column_class('class', 'textaligncenter class');
            $table->column_class('classno', 'textaligncenter classno');
            $table->column_class('finalgrade', 'textaligncenter finalgrade');
            $table->column_class('forum', 'textaligncenter forum');

            $table->set_attribute('cellspacing', '0');
            $table->set_attribute('id', 'attempts');
            $table->set_attribute('class', 'assessment_grades');
            $table->set_attribute('width', '100%');
         
            $table->no_sorting('forum');

            // Start working -- this is necessary as soon as the niceties are over
            $table->setup();
         
            if ($where = $table->get_sql_where()) {
                if (is_array($where) && trim($where[0])== "")
                    $where = "";
                else
                    $where .= " AND ";
            }
            
            $peersortsql = '';
            if ($sort = $table->get_sql_sort()) {
                $sort = " ORDER BY ".$sort;
                // if sort by average grade of peer assessment, need to construct a sub-query to sort with
                if (strpos($sort, 'avgpeergrade') !== FALSE) {
                    $peersortsql = "(SELECT AVG(grade) AS avgpeergrade 
                                    FROM {assessment_grades} 
                                    WHERE 
                                        type = 2 AND 
                                        assessmentid = (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.") AND 
                                        userid = u.id 
                                    GROUP BY userid) AS avgpeergrade, ";
                }
            }
            
            // Calculate final grade on the fly with SQL,
            // to deal with sort by final grade, and replace old method of getting final grade
            $finalgradesort = array();
            $finalgradesortsql = '';
         
            // COALESCE(): used to replace NULL with zero, so that it can be multiply
            if (isset($assessment->teacher))
                $finalgradesort[] = "COALESCE(agt.grade, 0)*".$assessment->teacherweight;
            if (isset($assessment->self))
                $finalgradesort[] = "COALESCE(ags.grade, 0)*".$assessment->selfweight;
            if (isset($assessment->peer)) {
                $finalgradesort[] = "COALESCE(
                                        (SELECT AVG(grade) FROM {assessment_grades} 
                                        WHERE 
                                           type = 2 AND assessmentid = 
                                              (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.") AND 
                                           userid = u.id 
                                        GROUP BY userid)
                                     ,0)*".$assessment->peerweight;
            }
            
            if (sizeof($finalgradesort)) {
                $finalgradesortsql = ", (" . implode('+', $finalgradesort) . ") AS finalgrade ";
            }
            
            if (strpos($filter, 'peer') === FALSE)
                $where .= ' '.$this->get_sql_filter_teacher_view($filter, $assessment->id);
            
            // Get Teacher and Self assessment first, return user name even there is no assessment
            $more_names = "u.lastnamephonetic, u.firstnamephonetic, u.middlename, u.alternatename, ";
            $select = "SELECT u.id, u.firstname, u.lastname, $more_names $peersortsql
                       (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"chiname\")) as chiname,
                       (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"class\")) as class,
                       (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"classno\")) as classno,
                       agt.id AS agt_id, agt.groupid AS agt_groupid, 
                       agt.marker AS agt_marker, agt.grade AS agt_grade, agt.type AS agt_type, 
                       agt.timemodified AS agt_timemodified, agt.comment AS agt_comment, 
                       ags.id AS ags_id, ags.groupid AS ags_groupid, 
                       ags.marker AS ags_marker, ags.grade AS ags_grade, ags.type AS ags_type, 
                       ags.timemodified AS ags_timemodified, ags.comment AS ags_comment 
                       ".$finalgradesortsql;
            $sql = "FROM {user} u 
                    LEFT JOIN {assessment_grades} agt ON u.id = agt.userid
                    AND agt.type = 0 AND agt.assessmentid =  
                       (SELECT id FROM {assessment_types} WHERE type = 0 AND assessmentid = ".$assessment->id.")
                    LEFT JOIN {assessment_grades} ags ON u.id = ags.userid
                    AND ags.type = 1 AND ags.assessmentid =  
                       (SELECT id FROM {assessment_types} WHERE type = 1 AND assessmentid = ".$assessment->id.")";
            if (trim($where) != '') $sql .= ' WHERE'.$where;
            
            $table->pagesize($perpage, count($users));
            
            ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
            $offset = $pageno * $perpage;
            $strupdate = get_string('update');
            $strgrade  = get_string('grade');
            
            $teachergradednum = 0;
            $selfgradednum = 0;
            $sql_filter = '';
            $in_params = array();
            
            if ($users) {
                if (isset($assessment->teacher)) {
                    $query_params = array('assessmentid'=>$assessment->teacher, 'type'=>0);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
                    $params = array_merge($in_params, $query_params);
                    $teachergradednum = $DB->count_records_sql("SELECT COUNT(*) FROM {assessment_grades} WHERE userid $in_sql AND type = :type AND assessmentid = :assessmentid", $params);
                }
                if (isset($assessment->self)) {
                    $query_params = array('assessmentid'=>$assessment->self, 'type'=>1);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
                    $params = array_merge($in_params, $query_params);
                    $selfgradednum = $DB->count_records_sql("SELECT COUNT(*) FROM {assessment_grades} WHERE userid $in_sql AND type = :type AND assessmentid = :assessmentid", $params);
                }
                $sql_filter = ' u.id IN ('.implode(',',$users).') ';
            }
            
            if (isset($assessment->peer)) {
                // calculate the numbers for peer assessment summary
                $peergradedallnum = 0;
                $peergradedsomenum = 0;
                $peergradednonenum = 0;
                
                if ($users) {
                    foreach ($users as $singleuserid) {
                        $user_peer_marked = array();
                        $params = array();
                        $params['agp_type'] = 2;
                        $params['agp_marker'] = $singleuserid;
                        $params['agp_userid'] = $singleuserid;
                        $params['assessmentid'] = $assessment->id;
                        $peersql = "SELECT COUNT(*) AS peermarked
                                   FROM {assessment_grades} agp
                                   WHERE agp.type = :agp_type AND agp.marker = :agp_marker AND agp.userid <> :agp_userid AND agp.assessmentid =  
                                   (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = :assessmentid)";
                        $user_peer_marked = $DB->get_record_sql($peersql, $params);
                       
                        $peer_info['marker'] = 'user';
                        $peer_info['done'] = $user_peer_marked->peermarked;
                        $peer_info['total'] = $assessment->peernum;
                       
                        // counting the number for Summary display
                        if ($peer_info['done'] == $peer_info['total']) {
                            $peergradedallnum++;
                        } else if ($peer_info['done'] == 0) {
                            $peergradednonenum++;
                        } else {
                            $peergradedsomenum++;
                        }
                    }
                }
                
                if (strpos($filter, 'peer') !== FALSE) {
                    $peerfilterids = $this->get_peer_filter_teacher_view($filter, $assessment->id, $users, $this->assessment->peernum);
                    if (sizeof($peerfilterids) > 0) {
                        $sql_filter = " u.id IN (".implode(',',$peerfilterids).") ";
                    }
                }
            }
            
            if (trim($where) != '') {
                if (substr(trim($where), strlen(trim($where))-3) != 'AND')
                    $sql .= " AND";
                $sql .= $sql_filter;
            } else {
                $sql .= empty($sql_filter) ? " " : " WHERE".$sql_filter;
            }
            
            /*************************************************/
            // Work mode is group instead of individual
            $markertype = 'user';
            $markerids = $users;
            $peergroupmode_para = '';
            if ($workmode == 'group') {
                if ($peersortsql != '') {
                    $peersortsql = "(SELECT AVG(grade) AS avgpeergrade 
                                   FROM {assessment_grades} 
                                   WHERE 
                                      type = 2 AND 
                                      assessmentid = (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.") AND 
                                      groupid = g.id 
                                   GROUP BY groupid) AS avgpeergrade, ";
                }
            
                $finalgradesort = array();
                $finalgradesortsql = '';
                // COALESCE(): used to replace NULL with zero, so that it can be multiply
                if (isset($assessment->teacher))
                    $finalgradesort[] = "COALESCE(agt.grade, 0)*".$assessment->teacherweight;
                if (isset($assessment->self))
                    $finalgradesort[] = "COALESCE(ags.grade, 0)*".$assessment->selfweight;
                if (isset($assessment->peer)) {
                    $finalgradesort[] = "COALESCE(
                                           (SELECT AVG(grade) FROM {assessment_grades}
                                           WHERE 
                                              type = 2 AND assessmentid = 
                                                 (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.") AND 
                                              groupid = g.id 
                                           GROUP BY groupid)
                                        ,0)*".$assessment->peerweight;
                }
            
                if (sizeof($finalgradesort)) {
                    $finalgradesortsql = ", (" . implode('+', $finalgradesort) . ") AS finalgrade ";
                }
                
                if (isset($assessment->teacher)) {
                    $query_params = array('type'=>0, 'assessmentid'=>$assessment->teacher);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
                    $params = array_merge($in_params, $query_params);
                    $teachergradednum = $DB->count_records_sql("SELECT COUNT(*) FROM {assessment_grades} WHERE groupid $in_sql AND type = :type AND assessmentid = :assessmentid", $params);
                }
                if (isset($assessment->self)) {
                    $query_params['type'] = 1;
                    $query_params['assessmentid'] = $assessment->self;
                    $params = array_merge($in_params, $query_params);
                    $selfgradednum = $DB->count_records_sql("SELECT COUNT(*) FROM {assessment_grades} WHERE groupid $in_sql AND type = :type AND assessmentid = :assessmentid", $params);
                }
                if (isset($assessment->peer)) {
                    // calculate the numbers for peer assessment summary
                    $peergradedallnum = 0;
                    $peergradedsomenum = 0;
                    $peergradednonenum = 0;
               
                    $sql_filter = '';
               
                    //// Individual assess group: need to display list of student instead of group
                    if ($assessment->peergroupmode == 1) {
                        $select = "SELECT u.id, u.firstname, u.lastname, $more_names $peersortsql
                                    (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"chiname\")) as chiname,
                                    (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"class\")) as class,
                                    (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"classno\")) as classno,
                                    g.id AS groupid, g.name AS groupname,
                                    agt.id AS agt_id, agt.groupid AS agt_groupid, 
                                    agt.marker AS agt_marker, agt.grade AS agt_grade, agt.type AS agt_type, 
                                    agt.timemodified AS agt_timemodified, agt.comment AS agt_comment, 
                                    ags.id AS ags_id, ags.groupid AS ags_groupid, 
                                    ags.marker AS ags_marker, ags.grade AS ags_grade, ags.type AS ags_type, 
                                    ags.timemodified AS ags_timemodified, ags.comment AS ags_comment 
                                    ".$finalgradesortsql;
                        $sql = "FROM {user} u 
                                 LEFT JOIN {groups} g ON g.id = 
                                    (SELECT groupid FROM {groups_members} WHERE userid = u.id AND groupid IN (".implode(',', $groupids).")) 
                                 LEFT JOIN {assessment_grades} agt ON g.id = agt.groupid
                                 AND agt.type = 0 AND agt.assessmentid =  
                                    (SELECT id FROM {assessment_types} WHERE type = 0 AND assessmentid = ".$assessment->id.")
                                 LEFT JOIN {assessment_grades} ags ON g.id = ags.groupid
                                 AND ags.type = 1 AND ags.assessmentid =  
                                    (SELECT id FROM {assessment_types} WHERE type = 1 AND assessmentid = ".$assessment->id.")";
                        if (trim($where) != '') $sql .= ' WHERE'.$where;
                        
                        $sql_filter = " u.id IN (".implode(',',$markerids).") ";
                        if (strpos($filter, 'peer') !== FALSE) {
                            $peerfilterids = $this->get_peer_filter_teacher_view($filter, $assessment->id, $markerids, $this->assessment->peernum);
                            if (sizeof($peerfilterids) > 0) {
                                $sql_filter = " u.id IN (".implode(',',$peerfilterids).") ";
                            }
                        }
                        $peergroupmode_para = '&amp;peergroupmode=1';
                    //// Group assess group
                    } else if ($assessment->peergroupmode == 2) {
                        $select = "SELECT g.id, g.name AS groupname, ".$peersortsql."
                                    agt.id AS agt_id, agt.groupid AS agt_groupid, 
                                    agt.marker AS agt_marker, agt.grade AS agt_grade, agt.type AS agt_type, 
                                    agt.timemodified AS agt_timemodified, agt.comment AS agt_comment, 
                                    ags.id AS ags_id, ags.groupid AS ags_groupid, 
                                    ags.marker AS ags_marker, ags.grade AS ags_grade, ags.type AS ags_type, 
                                    ags.timemodified AS ags_timemodified, ags.comment AS ags_comment 
                                    ".$finalgradesortsql;
                        
                        $sql = "FROM {groups} g 
                              LEFT JOIN {assessment_grades} agt ON g.id = agt.groupid
                              AND agt.type = 0 AND agt.assessmentid =  
                                 (SELECT id FROM {assessment_types} WHERE type = 0 AND assessmentid = ".$assessment->id.")
                              LEFT JOIN {assessment_grades} ags ON g.id = ags.groupid
                              AND ags.type = 1 AND ags.assessmentid =  
                                 (SELECT id FROM {assessment_types} WHERE type = 1 AND assessmentid = ".$assessment->id.")";
                        if (trim($where) != '') $sql .= " WHERE".$where;
                  
                        $markerids = $groupids;
                        $markertype = 'group';
                        $peergroupmode_para = '&amp;peergroupmode=2';
                  
                        $sql_filter = " g.id IN (".implode(',',$markerids).") ";
                        if (strpos($filter, 'peer') !== FALSE) {
                            $peerfilterids = $this->get_peer_filter_teacher_view($filter, $assessment->id, $markerids, $this->assessment->peernum);
                            if (sizeof($peerfilterids) > 0) {
                                $sql_filter = " g.id IN (".implode(',',$peerfilterids).") ";
                            }
                        }
                  
                        $sort = str_replace(array('firstname','lastname'), 'groupname', $sort);
                    }
               
                    foreach ($markerids as $singlemarkerid) {
                        $group_peer_marked = array();
                        $peersql = "SELECT COUNT(*) AS peermarked
                                  FROM {assessment_grades} agp
                                  WHERE agp.type = 2 AND agp.marker = $singlemarkerid AND agp.".$markertype."id <> $singlemarkerid AND agp.assessmentid =  
                                  (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = $assessment->id)";
                        $group_peer_marked = $DB->get_record_sql($peersql);
                        
                        $peer_info['marker'] = $markertype;
                        $peer_info['done'] = $group_peer_marked->peermarked;
                        $peer_info['total'] = $assessment->peernum;
                        
                        // counting the number for Summary display
                        if ($peer_info['done'] == $peer_info['total']) {
                            $peergradedallnum++;
                        } else if ($peer_info['done'] == 0) {
                            $peergradednonenum++;
                        } else {
                            $peergradedsomenum++;
                        }
                    }
                    if (trim($where) != '') {
                        $sql .= $sql_filter;
                    } else {
                        $sql .= empty($sql_filter) ? " " : " WHERE".$sql_filter;
                    }
                }
            }
            /*************************************************/
            
            if (!$users) {
                // No users avaialble who can submit their work, so no need to query any data at all
            } else if (($ausers = $DB->get_records_sql($select.$sql.$sort, null, $table->get_page_start(), $table->get_page_size())) !== false) {
                // Get forum discussion links for each student
                if ($assessment->forum) {
                    $ausers_arr = array_keys($ausers);
                    if ($workmode == 'group' && $assessment->peergroupmode == 1) $ausers_arr = $groupids;
                    if ($assessment->forum > 1) {
                        $discussionids = $this->get_forum_discussions_old($ausers_arr);
                        $discussion_url = '/mod/forum/discuss.php?d=';
                    } else {
                        $discussionids = $this->get_forum_discussions($ausers_arr);
                        $discussion_url = '/mod/assessment/discuss.php?d=';
                    }
                }
                foreach ($ausers as $auser) {
                    $workmodeid = $auser->id;
                    if (isset($assessment->peer) && $assessment->peer) {
                        // only students belong to group can do this activity if it's groupwork
                        if ($assessment->peergroupmode==1) {
                            if (!$auser->groupid) continue;
                            $workmodeid = $auser->groupid;
                            $markertype = 'user';
                        }
                        
                        // get peer assessment grade (user as marker)
                        $auser_peer_marked = array();
                        $sql = "SELECT
                                COUNT(*) AS peermarked
                                FROM {assessment_grades} agp
                                WHERE agp.type = 2 AND agp.marker = ".$auser->id." AND agp.".$workmode."id <> ".$workmodeid." AND agp.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.")";
                        $auser_peer_marked = $DB->get_record_sql($sql);
                  
                        // get peer assessment grade (marked by other peers)
                        $auser_peer_markedby = array();
                        $sql = "SELECT
                                COUNT(*) AS peermarkedby, AVG(grade) AS avggrade
                                FROM {assessment_grades} agp
                                WHERE agp.type = 2 AND agp.".$workmode."id = ".$workmodeid." AND agp.marker <> ".$auser->id." AND agp.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ".$assessment->id.")
                              ";
                        $auser_peer_markedby = $DB->get_record_sql($sql);
                        
                        $peer_info = array('user'=>$auser->id, 'course'=>$course->id);
                        $peer_info['marker'] = 'user';
                        $peer_info['done'] = $auser_peer_marked->peermarked;
                        $peer_info['total'] = $this->assessment->peernum;
                  
                        $peer_grade_marked = $this->display_grade('unknown', 0, false, $peer_info, NULL);
                        $popup_url = '/mod/assessment/assessment_grades.php?id='.$this->cm->id
                                    .'&amp;'.$workmode.'id='.$workmodeid. '&amp;'.$markertype.'id='.$auser->id.$peergroupmode_para.'&amp;mode=peersummary&amp;type=2';
                        $peer_grade_marked_text = get_string('topeers', 'assessment').': ';
                        if ($auser_peer_marked->peermarked == 0) {
                            $peer_grade_marked = get_string('topeers', 'assessment').': '.$peer_grade_marked;
                            $peer_grade_marked_text .= get_string('notgraded', 'assessment').'">'.$peer_grade_marked.'</a>'.chr(13);
                            $peer_grade_marked_text = '<a href="javascript://" title="'.$peer_grade_marked_text;
                        } else {
                            $peer_grade_marked_text .= $auser_peer_marked->peermarked.'/'.$this->assessment->peernum.' '.get_string('graded', 'assessment');
                            $peer_grade_marked = $peer_grade_marked_text;
                            $peer_grade_marked_text = $OUTPUT->action_link($popup_url."&amp;ismarker=1", $peer_grade_marked, 
                                                      new popup_action('click', $popup_url."&amp;ismarker=1", "grade".$auser->id, array('height' => 600, 'width' => $width)), 
                                                      array('title'=>$peer_grade_marked_text));
                        }
                  
                        $peer_grade_markedby_text = get_string('bypeers', 'assessment').': ';
                        $peer_info['marker'] = 'others';
                        $peer_info['done'] = $auser_peer_markedby->peermarkedby;
                        $peer_info['total'] = $this->assessment->peernum;
                  
                        if ($auser_peer_markedby->peermarkedby == 0) {
                            $peer_grade_markedby = $this->display_grade('unknown', 0, false, $peer_info, 'scorecard');
                            $peer_grade_markedby = get_string('bypeers', 'assessment').': '.$peer_grade_markedby;
                            $peer_grade_markedby_text .= get_string('notgraded', 'assessment').'">'.$peer_grade_markedby.'</a>'.chr(13);
                            $peer_grade_markedby_text = '<a href="javascript://" title="'.$peer_grade_markedby_text;
                        } else {
                            $peer_grade_markedby = $this->display_grade(round($auser_peer_markedby->avggrade, 1), 0, false, $peer_info, 'scorecard');
                            $peer_grade_markedby_text .= $auser_peer_markedby->peermarkedby.'/'.$this->assessment->peernum.' '.get_string('graded', 'assessment');
                            $peer_grade_markedby_text .= ' ('.$this->display_grade(round($auser_peer_markedby->avggrade, 1)).')';
                            $peer_grade_markedby = $peer_grade_markedby_text;
                            $peer_grade_markedby_text = $OUTPUT->action_link($popup_url."&amp;ismarker=0", $peer_grade_markedby, 
                                                        new popup_action('click', $popup_url."&amp;ismarker=0", "grade".$auser->id, array('height' => 600, 'width' => $width)), 
                                                        array('title'=>$peer_grade_markedby_text));
                        }
                        
                        $peer_grade = $peer_grade_marked_text;
                        $peer_grade .= '<br />';
                        $peer_grade .= $peer_grade_markedby_text;
                    }
               
                    $popup_url = '/mod/assessment/assessment_grades.php?id='.$this->cm->id
                                .'&amp;'.$workmode.'id='.$workmodeid.'&amp;mode=single'.'&amp;offset='.$offset++;
                    
                    if (isset($assessment->teacher)) {
                        $teacher_grade = chr(13);
                        $teacher_grade_text = '';
                        if ($auser->agt_id) {
                            if ($auser->agt_grade > 0) {
                                $teacher_grade_text .= $this->display_grade($auser->agt_grade, 0, true, 'teacher');
                            }
                            if (empty($auser->agt_comment)) {
                                $teacher_bubble = '<div class="assess-nocomment"></div>'.chr(13);
                                $teacher_grade_text .= ' ('.get_string('nocomment', 'assessment').')';
                            } else {
                                $teacher_bubble = '<div class="assess-comment"></div>'.chr(13);
                                $teacher_grade_text .= ' ('.get_string('commented', 'assessment').')';
                            }
                            $teacher_grade .= $this->display_grade($auser->agt_grade, 0, false, 'teacher', 'scorecard', $teacher_bubble);
                        } else {
                            $teacher_grade .= $this->display_grade('unknown', 0, false, 'teacher', 'scorecard');
                            $teacher_grade_text .= get_string('notgraded', 'assessment');
                        }
                        $teacher_grade .= chr(13);
                        $teacher_grade = $OUTPUT->action_link($popup_url."&amp;markergroupid=".$auser->agt_marker."&amp;type=0", $teacher_grade, 
                                         new popup_action('click', $popup_url."&amp;markergroupid=".$auser->agt_marker."&amp;type=0", "grade".$workmodeid, array('height' => 600, 'width' => $width)), 
                                         array('title'=>$teacher_grade_text));
                    }
               
                    if (isset($assessment->self)) {
                        $self_grade = chr(13);
                        $self_grade_text = '';
                        $self_pic = array('user'=>$auser->id, 'course'=>$course->id);
                        if ($auser->ags_id) {
                            if ($auser->ags_grade > 0) {
                                $self_grade_text .= $this->display_grade($auser->ags_grade, 0, true, $self_pic);
                            }
                            if (empty($auser->ags_comment)) {
                                $self_grade_text .= ' ('.get_string('nocomment', 'assessment').')';
                                $self_bubble = '<div class="assess-nocomment"></div>'.chr(13);
                            } else {
                                $self_grade_text .= ' ('.get_string('commented', 'assessment').')';
                                $self_bubble = '<div class="assess-comment"></div>'.chr(13);
                            }
                            $self_grade .= $this->display_grade($auser->ags_grade, 0, false, $self_pic, 'scorecard', $self_bubble );
                        } else {
                            $self_grade .= $this->display_grade('unknown', 0, false, $self_pic, 'scorecard');
                            $self_grade_text .= get_string('notgraded', 'assessment');
                        }
                        $self_grade .= chr(13);
                        $self_grade = $OUTPUT->action_link($popup_url."&amp;markergroupid=".$workmodeid."&amp;type=1", $self_grade, 
                                      new popup_action('click', $popup_url."&amp;markergroupid=".$workmodeid."&amp;type=1", "grade".$workmodeid, array('height' => 600, 'width' => $width)), 
                                      array('title'=>$self_grade_text));
                    }
               
                    // final grade calculated in query already
                    if (isset($auser->finalgrade)) {
                        $finalgrade = round($auser->finalgrade, 2);
                        $finalgrade = '<div id="finalgrade_'.$workmodeid.'">'.$this->display_grade($finalgrade, 0, false, NULL, 'scoreboard', '', 1).'</div>';
                    } else {
                        $finalgrade = 'N/A';
                    }
                    
                    if ($assessment->forum && isset($discussionids[$workmodeid])) {
                        $unread_posts_num = $this->count_discussion_unread_posts($USER->id, $discussionids[$workmodeid]->id);
                        $discussion_unread_text = $unread_posts_num.' '.get_string('unreadpost', 'assessment');
                        $discussion_unread = '<div class="';
                        if ($unread_posts_num > 0) {
                            $discussion_unread .= 'forum_newmsg">('.$unread_posts_num.')';
                        } else {
                            $discussion_unread .= 'forum_nonewmsg">&nbsp;';
                        }
                        $discussion_unread .= '</div>'.chr(13);
                        $discussion_unread = $discussion_unread_text;
                        $forum = $OUTPUT->action_link($discussion_url.$discussionids[$workmodeid]->id, $discussion_unread, 
                                 new popup_action('click', $discussion_url.$discussionids[$workmodeid]->id, "discussion".$workmodeid, array('height' => 600, 'width' => $width)), 
                                 array('title'=>$discussion_unread_text));
                    } else {
                        $forum = 'N/A';
                    }
			   
                    if ($assessment->workmode == 'group') {
                        if ($assessment->peergroupmode == 1) {
                            $submission = $this->get_submission($workmodeid);
                            $displayname = fullname($auser);
                        } else if ($assessment->peergroupmode == 2) {
                            $submission = $this->get_submission($auser->id);
                            $displayname = $auser->groupname;
                        }
                    } else {
                        $submission = $this->get_submission($workmodeid);
                        $displayname = fullname($auser);
                    }
               
                    $userlink = '<div class="">';
               
                    if (isset($submission) && $submission) {
                        if ($is_submit_files) {
                            $userlink .= '<div class="late"></div>';
                            $userlink .= '<div>';
                            $view_submission_url = '/mod/assessment/view_submission.php?id='.$cm->id.'&amp;a='.$assessment->id.'&amp;'.$workmode.'id='.$workmodeid;
                            $userlink .= $OUTPUT->action_link($view_submission_url, $displayname, 
                                         new popup_action('click', $view_submission_url, 'viewwork'.$auser->id, array('height' => 600, 'width' => $width)), 
                                         array('title'=>$displayname));
                            $userlink .= ' '.print_assessment_user_submitted_files_simple($submission, $assessment, $cm, 0, 'icon');
                            $userlink .= '<br><span class="submit-date">';
                            $userlinkclass = '';
                            if ($submission->timecreated > $assessment->submitend) {
                                $userlinkclass = ' class="notavailable_span"';
                            }
                            $userlink .= '<span'.$userlinkclass.'>'.userdate($submission->timecreated).'</span>';
                        } else {
                            $userlink .= '<div class="submitted"></div>';
                            $userlink .= '<div>';
                            $activityentryformat = $this->get_activity_entry_format($this->activitydetail->modname, $auser->id, $submission, $viewer);
                            $userlink .= $OUTPUT->action_link($activityentryformat->page, fullname($auser), 
                                         new popup_action('click', $activityentryformat->page, 'viewwork'.$auser->id, array('height' => 600, 'width' => $width)), 
                                         array('title'=>fullname($auser)));
                            $submissiontimefield = $activityentryformat->timefield;
                            $userlink .= '<br><span class="submit-date">';
                            $userlink .= userdate($submission->$submissiontimefield);
                            $userlink .= '</span>';
                        }
                        $userlink .= '</div>';
                    } else {
                        $userlink .= '<div class="not-yet"></div>';
                        $userlink .= '<div alt="'.$auser->id.'">'.$displayname.'</div>';
                    }
                    $userlink .= '</div>';
                    
                    $class = empty($auser->class) ? '-' : $auser->class;
                    $classno = empty($auser->classno) ? '-' : $auser->classno;
                    
                    // Table columns in a row
                    $row = array();
                    $row[] = $userlink;
                    $row[] = $class;
                    $row[] = $classno;
                    if (isset($assessment->peer) && $assessment->peergroupmode == 1) $row[] = $auser->groupname;
                    if (isset($assessment->teacher)) $row[] = $teacher_grade;
                    if (isset($assessment->self)) $row[] = $self_grade;
                    if (isset($assessment->peer)) $row[] = $peer_grade;
                    $row[] = $finalgrade;
                    $row[] = $forum;
               
                    $table->add_data($row);
                }
            }
            
            // Summary/Statistics of submission
            echo $OUTPUT->heading(get_string('submissionsummary', 'assessment'), 2, 'main teacherviewheading');
            $viewurl = "view.php?id=".$cm->id."&currentgroup=".$currentgroup."&filter=";
            $HTMLstr = $OUTPUT->container_start('generalbox submissionsummary');
            
            if ($is_submit_files) {
                // Submission of student works
                $count_sql = "SELECT COUNT(*) FROM {assessment_submissions} WHERE assessmentid = :assessmentid";
                if ($workmodeids) {
                    $query_params = array('assessmentid'=>$assessment->id);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($workmodeids, SQL_PARAMS_NAMED);
                    $params = array_merge($in_params, $query_params);
                    $submittednum = $DB->count_records_sql($count_sql." AND ".$workmode."id $in_sql", $params);
                    $notsubmittednum = count($workmodeids) - $submittednum;
                    $latenum = $DB->count_records_sql($count_sql." AND timecreated > ".$assessment->submitend." AND ".$workmode."id $in_sql", $params);
                } else {
                    $submittednum = 0;
                    $notsubmittednum = 0;
                    $latenum = 0;     
                }
                
                $submittedlink = $this->get_filter_link_teacher_view($filter, "submitted", $viewurl, "$submittednum / $totalnum");
                $latelink = $this->get_filter_link_teacher_view($filter, "late", $viewurl, "$latenum / $submittednum");
                $notsubmittedlink = $this->get_filter_link_teacher_view($filter, "notsubmitted", $viewurl, "$notsubmittednum / $totalnum");
            
                $HTMLstr .= html_writer::tag('div', $submittedlink.' '.get_string('submitted', 'assessment'), array('class'=>'assessment-float-left'));
                $HTMLstr .= html_writer::tag('div', $latelink.' '.get_string('latesubmission', 'assessment'), array('class'=>'assessment-float-left'));
                $HTMLstr .= html_writer::tag('div', $notsubmittedlink.' '.get_string('notsubmitted', 'assessment'), array('class'=>'assessment-float-left'));
                
                // Link to download a ZIP file of all submitted files
                if ($submittednum) {
                    $image = "<img src=\"".$OUTPUT->pix_url('f/zip')."\" class=\"icon\" title=\"".get_string('downloadall', 'assessment')."\" alt=\"".get_string('downloadall', 'assessment')."\" />";
                    $downloadalllink = "<a href='downloadall.php?id=".$cm->id."&a=".$assessment->id."&groupid=".$currentgroup."'>".get_string('downloadall', 'assessment')."</a>";
                    $HTMLstr .= '<div>'.$image.' '.$downloadalllink.'</div>';
                }
                $HTMLstr .= html_writer::tag('div', '', array('style'=>'float:none;clear:both;height:0;'));
            }
         
            if (isset($assessment->teacher)) {
                // Teacher assessment
                $teachernotgradednum = $totalnum - $teachergradednum;
                if (!$teachergradednum) $teachergradednum = "0";
                
                $teachergradedlink = $this->get_filter_link_teacher_view($filter, "teachergraded", $viewurl, "$teachergradednum / $totalnum");
                $teachernotgradedlink = $this->get_filter_link_teacher_view($filter, "teachernotgraded", $viewurl, "$teachernotgradednum / $totalnum");
                
                $HTMLstr .= html_writer::tag('div', $teachergradedlink.' '.get_string('teachergraded', 'assessment'), array('class'=>'teacher-finished'));
                $HTMLstr .= html_writer::tag('div', $teachernotgradedlink.' '.get_string('teachernotgraded', 'assessment'), array('class'=>'teacher-not-yet'));
                $HTMLstr .= html_writer::tag('div', '', array('style'=>'float:none;clear:both;height:0;'));
            }
            
            if (isset($assessment->self)) {
                // Self assessment
                $selfnotgradednum = $totalnum-$selfgradednum;
                if (!$selfgradednum) $selfgradednum = "0";
                
                $selfgradedlink = $this->get_filter_link_teacher_view($filter, "selfgraded", $viewurl, "$selfgradednum / $totalnum");
                $selfnotgradedlink = $this->get_filter_link_teacher_view($filter, "selfnotgraded", $viewurl, "$selfnotgradednum / $totalnum");
                
                $HTMLstr .= html_writer::tag('div', $selfgradedlink.' '.get_string('selfgraded', 'assessment'), array('class'=>'self-finished'));
                $HTMLstr .= html_writer::tag('div', $selfnotgradedlink.' '.get_string('selfnotgraded', 'assessment'), array('class'=>'self-not-yet'));
                $HTMLstr .= html_writer::tag('div', '', array('style'=>'float:none;clear:both;height:0;'));
            }
            
            if (isset($assessment->peer)) {
                // Peer assessment
                if ($assessment->peergroupmode == 1) { //Individual assess group
                    $totalnum = count($markerids);
                }
                
                $peergradedalllink = $this->get_filter_link_teacher_view($filter, "peergradedall", $viewurl, "$peergradedallnum / $totalnum");
                $peergradedsomelink = $this->get_filter_link_teacher_view($filter, "peergradedsome", $viewurl, "$peergradedsomenum / $totalnum");
                $peergradednonelink = $this->get_filter_link_teacher_view($filter, "peergradednone", $viewurl, "$peergradednonenum / $totalnum");
            
                $HTMLstr .= html_writer::tag('div', $peergradedalllink.' '.get_string('peergradedall', 'assessment'), array('class'=>'peer-finished'));
                $HTMLstr .= html_writer::tag('div', $peergradedsomelink.' '.get_string('peergradedsome', 'assessment'), array('class'=>'peer-in-progress'));
                $HTMLstr .= html_writer::tag('div', $peergradednonelink.' '.get_string('peergradednone', 'assessment'), array('class'=>'peer-not-yet'));
                $HTMLstr .= html_writer::tag('div', '', array('style'=>'float:none;clear:both;height:0;'));
            }
            
            // Add show all link since not using iLAP_admin
            $showalllink = $this->get_filter_link_teacher_view($filter, "all", $viewurl, get_string('showall', 'assessment'));
            $HTMLstr .= html_writer::tag('div', $showalllink, array('class'=>'showall'));
            $HTMLstr .= $OUTPUT->container_end();
            
            echo $HTMLstr;
            
            $table->print_html();  /// Print the whole table
        } else {
            ////////// Student view
            $viewer = 'student';
            if ($workmode == 'group') {
                // user must only belong to one group, since we only get the first group here
                $usergroups = groups_get_user_groups($course->id);
                $usergroup = groups_get_group($usergroups[0][0]);
                $usertograde = $usergroup;
                // the student as a marker, representing individual or group?
                $markerid = $assessment->peergroupmode==1 ?$USER->id : $usergroup->id;
            } else {
                $usertograde = $USER;
                $markerid = $USER->id;
            }
            
            // Summary Detail
            echo $OUTPUT->container_start('generalbox', 'studentviewdiv');
            echo "<table id='mod-assessment-activitydetailtable' class='studentviewtable' width='95%' border='0' cellspacing='0' cellpadding='2'>";
            if ($is_submit_files) {
                $activitytype = get_string('uploadingoffiles', 'assessment');
                $activityname = $assessment->numfiles.get_string('filesrequired', 'assessment');
            } else {
                $activitytype = $this->activitydetail->modplural;
                $activityname = $this->activitydetail->name;
            }
            $activitytype .= ' ('.get_string($workmode == 'group' ? 'groupwork' : 'individualwork', 'assessment').')';
            echo "<tr><th colspan='2' class='assessmentheader'>".get_string('summary')."</th></tr>";
            echo "<tr><th>".get_string('activitytype', 'assessment').":</th>";
            echo "<td>".$activitytype."</td></tr>";
            echo "<tr><th>".get_string('name').":</th>";
            echo "<td>".$activityname."</td></tr>";
            
            if ($submission = $this->get_submission($usertograde->id)) {
                if ($is_submit_files) {
                    $popup_url = '/mod/assessment/upload.php?id='.$cm->id.'&amp;'.$workmode.'id='.$usertograde->id;
                    $linktoupload = $OUTPUT->action_link($popup_url, get_string('clicktoupdate', 'assessment'), 
                                    new popup_action('click', $popup_url, 'upload'.$usertograde->id, array('height' => 600, 'width' => 800)), 
                                    array('title'=>get_string('clicktoupdate', 'assessment')));
                    $activityentrylink = '<span class="submittedlink">'.get_string('submitted', 'assessment').'</span>';
                    $activityentrylink .= ' >> <span class="linktoupload">'.$linktoupload.'</span>';
                } else {
                    $activityentryformat = $this->get_activity_entry_format($this->activitydetail->modname, $usertograde->id, $submission, $viewer);
                    $activityentrylink = $OUTPUT->action_link($activityentryformat->page, get_string('clicktoview', 'assessment'), 
                                         new popup_action('click', $activityentryformat->page, 'viewwork'.$usertograde->id, array('height' => 600, 'width' => 800)), 
                                         array('title'=>get_string('clicktoview', 'assessment')));
                }
            } else {
                $activityentrylink = 'N/A';
                if ($is_submit_files) {
                    $popup_url = '/mod/assessment/upload.php?id='.$cm->id.'&amp;'.$workmode.'id='.$usertograde->id;
                    $linktoupload = $OUTPUT->action_link($popup_url, get_string('clicktoupdate', 'assessment'), 
                                    new popup_action('click', $popup_url, 'upload'.$usertograde->id, array('height' => 600, 'width' => 1024)), 
                                    array('title'=>get_string('clicktoupdate', 'assessment')));
                    $activityentrylink .= ' >> <span class="linktoupload">'.$linktoupload.'</span>';
                }
            }
            echo "<tr><th>".get_string('yourwork', 'assessment').":</th>";
            echo "<td>$activityentrylink</td></tr>";
            
            if ($assessment->forum) {
                if ($assessment->forum > 1) {
                    $discussionids = $this->get_forum_discussions_old(array($usertograde->id));
                    $discussion_url = '/mod/forum/discuss.php?d='.$discussionids[$usertograde->id]->id;;
                } else {
                    $discussionids = $this->get_forum_discussions(array($usertograde->id));
                    $discussion_url = '/mod/assessment/discuss.php?d='.$discussionids[$usertograde->id]->id;;
                }
                $all_posts_num = $this->count_discussion_posts($USER->id, $discussionids[$usertograde->id]->id);
                $discussion_unread = $this->count_discussion_unread_posts($USER->id, $discussionids[$usertograde->id]->id).'/'.$all_posts_num.' '.get_string('unreadpost', 'assessment');
                $forum = $OUTPUT->action_link($discussion_url, $discussion_unread, 
                         new popup_action('click', $discussion_url, 'discussion'.$usertograde->id, array('height' => 600, 'width' => $width)), 
                         array('title'=>$discussion_unread));
                $discussionlist_url = '/mod/assessment/discusslist.php?a='.$assessment->id;
                $discussionlist = $OUTPUT->action_link($discussionlist_url, get_string('discussionlist', 'assessment'), 
                                  new popup_action('click', $discussion_url, 'discussionlist'.$assessment->id, array('height' => 600, 'width' => $width)), 
                                  array('title'=>get_string('discussionlist', 'assessment')));
                echo "<tr><th>".get_string('discussiononyourwork', 'assessment').":</th>";
                echo "<td>$forum ($discussionlist)</td></tr>";
            }
            echo '</table>';
            echo $OUTPUT->container_end();
            
            $auser_peer = array();
            
            $params = array($usertograde->id, $usertograde->id, $assessment->id);
            $sql = "SELECT
                        agp.id, agp.groupid, agp.marker, 
                        agp.grade, agp.type, agp.timemodified, agp.comment 
                    FROM {assessment_grades} agp
                    WHERE agp.type = 2 AND agp.".$workmode."id = ? AND agp.marker <> ? AND agp.assessmentid =  
                        (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
            $auser_peer = $DB->get_records_sql($sql, $params);
            
            $assessment_status = $this->get_assessment_status($usertograde->id);
            $assessment_status_desc = $this->get_assessment_status_description($assessment_status, $auser_peer);
            
            echo '<script type="text/javascript">';
            echo 'function choosepeertograde(wwwpath, userid, workmode, cmid, usertograde) {
                      if (userid == 0) return false;
                      document.getElementById("menupeertogradeselect").selectedIndex = 0;
                      
                      window.open(wwwpath+"/mod/assessment/assessment_grades.php?id="+cmid+"&markergroupid="+usertograde+"&"+workmode+"id="+userid+"&mode=single&type=2&offset=0", 
                          "grade"+userid, "menubar=0,location=0,scrollbars,resizable,width=1024,height=600");
                  }';
            echo '</script>';
            
            // Teacher Assessment
            if (isset($assessment->teacher)) {
                echo $OUTPUT->container_start('generalbox', 'studentviewdiv');
                echo "<table class='studentviewtable' width='95%' border='0' cellspacing='0' cellpadding='2'>";
                echo "<tr><th colspan='2' class='assessmentheader'>".get_string('teacherassessment', 'assessment')."</th></tr>";
                echo "<tr><th>".get_string('status').": </th>";
                echo "<td>".$assessment_status_desc['teacher']."</td></tr>";
                
                if ($assessment_status['teachermarked']) {
                    $type = 0;
                    $params = array(0, $assessment->id, $usertograde->id);
                    $select = "SELECT u.id,
                                   agt.id AS gid, agt.groupid, agt.marker, 
                                   agt.grade, agt.type, agt.timemodified, agt.comment ";
                    $sql = "FROM {user} u 
                            LEFT JOIN {assessment_grades} agt ON u.id = agt.userid
                            AND agt.type = ? AND agt.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 0 AND assessmentid = ?)
                            WHERE u.id = ?";
                    /*************************************************/
                    if ($workmode == 'group') {
                        $select = "SELECT g.id,
                                       agt.id AS gid, agt.groupid, agt.marker, 
                                       agt.grade, agt.type, agt.timemodified, agt.comment ";
                        $sql = "FROM {groups} g 
                                LEFT JOIN {assessment_grades} agt ON g.id = agt.groupid
                                AND agt.type = ? AND agt.assessmentid =  
                                    (SELECT id FROM {assessment_types} WHERE type = 0 AND assessmentid = ?)
                                WHERE g.id = ?";
                    }
                    /*************************************************/
                    if (!($teacherassessment = $DB->get_record_sql($select.$sql, $params))) {
                        print_error('errorgetteacherassessment'. 'assessment');
                    }
                    
                    echo "<tr><th>".get_string('grade').": </th>";
                    echo "<td>";
                    if (!$assessment_status['teacherpublished']) {
                        echo '<em>'.get_string('notpublished', 'assessment').'</em>';
                        echo "</td></tr>";
                    } else {
                        echo $this->display_grade($teacherassessment->grade, 1);
                        echo "</td></tr>";
                        if($this->rubric->id) {
                            $assessment_grade = $this->get_assessment_grade(0, $usertograde->id, $type);
                            echo "<tr><th>".get_string('rubric', 'assessment').": </th>";
                            echo '<td class="content">';
                            echo '<a href="#" onclick="showhiderubric(\'teacherrubric\', this, \'innerHTML\'); return false">'.get_string('showrubric', 'assessment').'</a>';
                            echo '<div id="teacherrubric" style="display:none;">';
                            $this->rubric->grade($assessment, $assessment_grade, $usertograde->id, $type, $viewer, 'view');
                            echo '</div>';
                            echo '</td></tr>';
                        }
                        
                        echo "<tr><th>".get_string('comment', 'assessment').": </th>";
                        $teacher_comment = file_rewrite_pluginfile_urls($teacherassessment->comment, 'pluginfile.php', $this->context->id, 'mod_assessment', 'grade_comment', $teacherassessment->gid);
                        echo "<td>".($teacher_comment == '' ? '<em>'.get_string('nocomment', 'assessment').'</em>' : $teacher_comment)."</td></tr>";
                    }
                }
                echo '</table>';
                echo $OUTPUT->container_end();
            }
            
            // Self Assessment
            if (isset($assessment->self)) {
                echo $OUTPUT->container_start('generalbox', 'studentviewdiv');
                echo "<table class='studentviewtable' width='95%' border='0' cellspacing='0' cellpadding='2'>";
                echo "<tr><th colspan='2' class='assessmentheader'>".get_string('selfassessment', 'assessment')."</th></tr>";
                echo "<tr><th>".get_string('status').": </th>";
                echo "<td>".$assessment_status_desc['self']."</td></tr>";
                
                if ($assessment_status['self']) {
                    $type = 1;
                    $params = array(1, $assessment->id, $usertograde->id);
                    $select = "SELECT u.id,
                                   ags.id, ags.groupid, ags.marker, 
                                   ags.grade, ags.type, ags.timemodified, ags.comment ";
                    $sql = "FROM {user} u 
                            LEFT JOIN {assessment_grades} ags ON u.id = ags.userid
                            AND ags.type = ? AND ags.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 1 AND assessmentid = ?)
                            WHERE u.id = ?";
                    /*************************************************/
                    if ($workmode == 'group') {
                        $select = "SELECT g.id,
                                       ags.id, ags.groupid, ags.marker, 
                                       ags.grade, ags.type, ags.timemodified, ags.comment ";
                        $sql = "FROM {groups} g 
                                LEFT JOIN {assessment_grades} ags ON g.id = ags.groupid
                                AND ags.type = ? AND ags.assessmentid =  
                                    (SELECT id FROM {assessment_types} WHERE type = 1 AND assessmentid = ?)
                                WHERE g.id = ?";
                    }
                    /*************************************************/
                    if (!($selfassessment = $DB->get_record_sql($select.$sql, $params))) {
                        print_error('errorgetselfassessment'. 'assessment');
                    }
                    
                    $linktograde = '';
                    if ($assessment_status['selftime'] == -1 || $assessment_status['selftime'] == 1) {
                        $popup_url = '/mod/assessment/assessment_grades.php?id='.$cm->id
                                     .'&amp;markergroupid='.$usertograde->id.'&amp;'.$workmode.'id='.$usertograde->id.'&amp;mode=single&amp;offset=0&amp;type=1';
                        $linktograde = $OUTPUT->action_link($popup_url, get_string('clicktoupdate', 'assessment'), 
                                       new popup_action('click', $popup_url, 'grade'.$usertograde->id, array('height' => 600, 'width' => 1024)), 
                                       array('title'=>get_string('clicktoupdate', 'assessment')));
                        $linktograde = ' >> <span class="linktoupload">'.$linktograde.'</span>';
                    }
                    
                    echo "<tr><th>".get_string('grade').": </th>";
                    if ($assessment_status['selfmarked']) {
                        echo "<td>".$this->display_grade($selfassessment->grade, 1).$linktograde."</td></tr>";
                        if($this->rubric->id) {
                            $assessment_grade = $this->get_assessment_grade(0, $usertograde->id, $type);
                            echo "<tr><th>".get_string('rubric', 'assessment').": </th>";
                            echo '<td class="content">';
                            echo '<a href="#" onclick="showhiderubric(\'selfrubric\', this, \'innerHTML\'); return false">'.get_string('showrubric', 'assessment').'</a>';
                            echo '<div id="selfrubric" style="display:none;">';
                            $this->rubric->grade($assessment, $assessment_grade, $usertograde->id, $type, $viewer, 'view');
                            echo '</div>';
                            echo '</td></tr>';
                        }
                    } else {
                        echo "<td>N/A ".$linktograde."</td></tr>";
                    }
                    
                    echo "<tr><th>".get_string('comment', 'assessment').": </th>";
                    echo "<td>".($selfassessment->comment == '' ? '<em>'.get_string('nocomment', 'assessment').'</em>' : format_text(stripslashes($selfassessment->comment), FORMAT_HTML))."</td></tr>";
                }
                echo '</table>';
                echo $OUTPUT->container_end();
            }
            
            // Peer Assessment
            if (isset($assessment->peer)) {
                echo $OUTPUT->container_start('generalbox', 'studentviewdiv');
                echo "<table class='studentviewtable' width='95%' border='0' cellspacing='0' cellpadding='2'>";
                echo "<tr><th colspan='2' class='assessmentheader'>".get_string('peerassessment', 'assessment')."</th></tr>";
                echo "<tr><th>".get_string('status').": </th>";
                echo "<td>".$assessment_status_desc['peer']."</td></tr>";
                echo "<tr><th>".get_string('grade')." (".get_string('youtopeers', 'assessment')."): </th>";

                $params = array(2, $usertograde->id, $usertograde->id, $assessment->id);
                $selectfullname = $this->getfullnameformat();
                $sql = "SELECT agp.id, agp.userid, agp.groupid, agp.marker, 
                            (SELECT ".$selectfullname." from {user} WHERE id = agp.userid) AS peername,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = ? AND agp.marker = ? AND agp.userid <> ? AND agp.assessmentid =  
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                /*************************************************/
                if ($workmode == 'group') {
                    $params = array($markerid, $usertograde->id, $assessment->id);
                    $sql = "SELECT agp.id, agp.userid, agp.groupid, agp.marker, 
                                (SELECT name from {groups} WHERE id = agp.groupid) AS peername,
                                agp.grade, agp.type, agp.timemodified, agp.comment 
                            FROM {assessment_grades} agp
                            WHERE agp.type = 2 AND agp.marker = ? AND agp.".$workmode."id <> ? AND agp.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                }
                /*************************************************/
                $grade_marked = array();
                
                $grade_marked = $DB->get_records_sql($sql, $params);
                
                $markedcount = empty($grade_marked) ? 0 : sizeof($grade_marked);
                echo "<td>".$markedcount."/".$assessment->peernum." ".get_string('graded', 'assessment')."&nbsp;";
                
                if ($markedcount < $assessment->peernum && ($assessment_status['peertime'] == -1 || $assessment_status['peertime'] == 1)) {
                    $params = array();
                    $query_params = array('shortname1'=>'chiname', 'shortname2'=>'class', 'u_id'=>$usertograde->id);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
                    $params = array_merge($in_params, $query_params);
                    $more_names = "u.lastnamephonetic, u.firstnamephonetic, u.middlename, u.alternatename, ";
                    $sql = "SELECT u.id, u.firstname, u.lastname, $more_names
                            (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \":shortname1\")) as chiname, 
                            (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \":shortname2\")) as class 
                            FROM {user} as u WHERE u.id $in_sql ";
                    $sql .= "AND u.id <> :u_id ORDER BY u.lastname";
                    $submissions = $DB->get_records('assessment_submissions', array('assessmentid'=>$assessment->id), '', 'userid');
                    /*************************************************/
                    if ($workmode == 'group') {
                        $params = array();
                        $query_params = array('g_id'=>$usertograde->id);
                        list($in_sql, $in_params) = $DB->get_in_or_equal($workmodeids, SQL_PARAMS_NAMED);
                        $params = array_merge($in_params, $query_params);
                        $sql = "SELECT g.id, g.name
                                FROM {groups} as g WHERE g.id $in_sql ";
                        $sql .= " AND g.id <> :g_id ORDER BY g.name";
                        $submissions = $DB->get_records('assessment_submissions', array('assessmentid'=>$assessment->id), '', 'groupid');
                    }
                    /*************************************************/
                    $submissions = array_keys($submissions);
                    $peerusers = $DB->get_records_sql($sql, $params);
                    $peeroptions = array();
                    if (!empty($peerusers)) {
                        foreach ($peerusers as $peerid => $peeruser) {
                            $symbol = in_array($peerid, $submissions) ? ' ***' : '';
                            if ($workmode=='group') {
                                $peeroptions[$peerid] = $peeruser->name;
                            } else {
                                $peeroptions[$peerid] = $peeruser->class != '' ? '['.$peeruser->class.'] '.fullname($peeruser) : fullname($peeruser);
                            }
                            $peeroptions[$peerid] .= $symbol;
                        }
                        
                        $peerselect = html_writer::select(
                                          $peeroptions, 'peertogradeselect', '', 
                                          array("0"=>get_string('choosepeertograde', 'assessment')), 
                                          array('id'=>'menupeertogradeselect', 
                                              'onchange'=>'choosepeertograde("'.$CFG->wwwroot.'", this.options[this.selectedIndex].value, 
                                              "'.$workmode.'", '.$cm->id.', '.$usertograde->id.')')
                                      );
                    } else {
                        $peerselect = 'N/A';
                    }
                    echo $peerselect.' ('.get_string('submittedsymbol', 'assessment').')';
                }
                
                // Summary of peers graded by the user
                if ($markedcount) {
                    require_once($CFG->libdir.'/tablelib.php');
                    $markedtable = new flexible_table('mod-assessment-peermarked');

                    $markedtablecolumns = array('count', 'name', 'grade', 'timemodified', 'discussion');
                    $markedtableheaders = array('#', get_string('name', 'assessment'), get_string('grade', 'assessment'), 
                                          get_string('lastmodified', 'assessment'), get_string('discussion', 'assessment'));
                    
                    $markedtable->define_columns($markedtablecolumns);
                    $markedtable->define_headers($markedtableheaders);
                    $markedtable->define_baseurl($CFG->wwwroot.'/mod/assessment/view.php?id='.$cm->id.'&amp;currentgroup='.$currentgroup);
                    $markedtable->column_class('count', 'count');
                    $markedtable->sortable(true, 'timemodified');
                    $markedtable->set_attribute('cellspacing', '0');
                    $markedtable->set_attribute('id', 'mod-assessment-peermarked');
                    $markedtable->set_attribute('class', 'assessment_peermarked');
                    $markedtable->set_attribute('width', '100%');
                    $markedtable->setup();
                    
                    $recordcount = 0;
                    foreach ($grade_marked as $gradeid => $gradeobj) {
                        $grade_marked_workid = $workmode == 'group' ? $gradeobj->groupid : $gradeobj->userid;
                        $popup_url = '/mod/assessment/assessment_grades.php?id='.$this->cm->id
                                     .'&amp;markergroupid='.$gradeobj->marker.'&amp;'.$workmode.'id='.$grade_marked_workid.'&amp;mode=single&amp;offset=0&amp;type=2&amp;by=you';
                        
                        $linktograde = $OUTPUT->action_link($popup_url, $this->display_grade($gradeobj->grade, 1), 
                                       new popup_action('click', $popup_url, 'grade'.$grade_marked_workid, array('height' => 600, 'width' => 1024)), 
                                       array('title'=>get_string('clicktoupdate', 'assessment')));
                        
                        if ($assessment->forum) {
                            if ($assessment->forum > 1) {
                                $discussionids = $this->get_forum_discussions_old(array($grade_marked_workid));
                                $discussion_url = '/mod/forum/discuss.php?d='.$discussionids[$grade_marked_workid]->id;
                            } else {
                                $discussionids = $this->get_forum_discussions(array($grade_marked_workid));
                                $discussion_url = '/mod/assessment/discuss.php?d='.$discussionids[$grade_marked_workid]->id;
                            }
                            
                            $discussion_unread = $this->count_discussion_unread_posts($USER->id, $discussionids[$grade_marked_workid]->id).' '.get_string('unreadpost', 'assessment');
                            
                            $forum = $OUTPUT->action_link($discussion_url, $discussion_unread, 
                                     new popup_action('click', $discussion_url, 'discussion'.$grade_marked_workid, array('height' => 600, 'width' => 800)), 
                                     array('title'=>$discussion_unread));
                        } else {
                            $forum = 'N/A';
                        }
                        $recordcount++;
                        $row = array($recordcount.'.', $gradeobj->peername, $linktograde, userdate($gradeobj->timemodified), $forum);
                        $markedtable->add_data($row);
                    }
                    $markedtable->print_html();
                }
                echo "</td></tr>";
                
                echo "<tr><th>".get_string('grade')." (".get_string('peerstoyou', 'assessment')."): </th>";
                $grade_markedby = array();
                $params = array(2, $usertograde->id, $usertograde->id, $assessment->id);
                $sql = "SELECT agp.id, agp.userid, agp.groupid, agp.marker, 
                            (SELECT ".$selectfullname." from {user} WHERE id = agp.marker) AS markername,
                            (SELECT data FROM {user_info_data} WHERE userid = agp.marker AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = 'chiname')) as chiname,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = ? AND agp.userid = ? AND agp.marker <> ? AND agp.assessmentid = 
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                /*************************************************/
                if ($workmode == 'group') {
                    $params = array(2, $usertograde->id, $markerid, $assessment->id);
                    $sql = "SELECT agp.id, agp.userid, agp.groupid, agp.marker, ";
                    if ($assessment->pergroupmode = 1) {
                        $sql .= "(SELECT ".$selectfullname." from {user} WHERE id = agp.marker) AS markername,
                                 (SELECT data FROM {user_info_data} WHERE userid = agp.marker AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = 'chiname')) as chiname,";
                    } else {
                        $sql .= "(SELECT name from {groups} WHERE id = agp.marker) AS markername,";
                    }
                    $sql .= "agp.grade, agp.type, agp.timemodified, agp.comment 
                            FROM {assessment_grades} agp
                            WHERE agp.type = ? AND agp.groupid = ? AND agp.marker <> ? AND agp.assessmentid =  
                                (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                }
                /*************************************************/
                $grade_markedby = $DB->get_records_sql($sql, $params);
                $markedbycount = empty($grade_markedby) ? 0 : sizeof($grade_markedby);
                echo "<td>".$markedbycount."/".$this->assessment->peernum." ".get_string('graded', 'assessment')."&nbsp;";
                
                // Summary of user's grade graded by peers
                if ($markedbycount) {
                    require_once($CFG->libdir.'/tablelib.php');
                    $markedbytable = new flexible_table('mod-assessment-peermarked');
                    
                    $markedbytablecolumns = array('count', 'marker', 'grade', 'timemodified', 'discussion');
                    $markedbytableheaders = array('#', get_string('marker', 'assessment'), get_string('grade', 'assessment'), 
                                            get_string('lastmodified', 'assessment'), get_string('discussion', 'assessment'));
                    
                    $markedbytable->define_columns($markedbytablecolumns);
                    $markedbytable->define_headers($markedbytableheaders);
                    $markedbytable->define_baseurl($CFG->wwwroot.'/mod/assessment/view.php?id='.$cm->id.'&amp;currentgroup='.$currentgroup);
                    $markedbytable->column_class('count', 'count');
                    $markedbytable->sortable(true, 'timemodified');
                    $markedbytable->set_attribute('cellspacing', '0');
                    $markedbytable->set_attribute('id', 'mod-assessment-peermarked');
                    $markedbytable->set_attribute('class', 'assessment_peermarked');
                    $markedbytable->set_attribute('width', '100%');
                    $markedbytable->setup();

                    $recordcount = 0;
                    
                    foreach ($grade_markedby as $gradeid => $gradeobj) {
                        $grade_markedby_workid = $workmode == 'group' ? $gradeobj->groupid : $gradeobj->userid;
                        if ($assessment_status['peerpublished'] == 1) {
                            $popup_url = '/mod/assessment/assessment_grades.php?id='.$this->cm->id
                                         .'&amp;markergroupid='.$gradeobj->marker.'&amp;'.$workmode.'id='.$grade_markedby_workid.'&amp;mode=single&amp;offset=0&amp;type=2&amp;by=peer';
                            
                            $linktograde = $OUTPUT->action_link($popup_url, $this->display_grade($gradeobj->grade, 1), 
                                           new popup_action('click', $popup_url, 'gradeby'.$gradeobj->marker, array('height' => 600, 'width' => 1024)), 
                                           array('title'=>get_string('clicktoview', 'assessment')));
                        } else {
                            $linktograde = get_string('notpublished', 'assessment');
                        }
                        if ($assessment->forum) {
                            if ($assessment->forum > 1) {
                                $discussionids = $this->get_forum_discussions_old(array($grade_markedby_workid));
                                $discussion_url = '/mod/forum/discuss.php?d='.$discussionids[$grade_markedby_workid]->id;
                            } else {
                                $discussionids = $this->get_forum_discussions(array($grade_markedby_workid));
                                $discussion_url = '/mod/assessment/discuss.php?d='.$discussionids[$grade_markedby_workid]->id;
                            }
                            
                            // Count unread post posted by the marker only, since it is not meaningful to just display all unread posts in the discussion
                            $discussion_unread = $this->count_discussion_unread_user_posts($USER->id, $gradeobj->marker, $discussionids[$grade_markedby_workid]->id).' '.get_string('unreadpost', 'assessment');
                            $forum = $OUTPUT->action_link($discussion_url, $discussion_unread, 
                                     new popup_action('click', $discussion_url, 'discussion'.$grade_markedby_workid, array('height' => 600, 'width' => 800)), 
                                     array('title'=>$discussion_unread));
                        } else {
                            $forum = 'N/A';
                        }
                        $recordcount++;
                        $markername_display = $workmode == 'group' ? $gradeobj->markername : $gradeobj->markername.' '.$gradeobj->chiname;
                        $row = array($recordcount.'.', $markername_display, $linktograde, userdate($gradeobj->timemodified), $forum);
                        $markedbytable->add_data($row);
                    }
                    $markedbytable->print_html();
                }
                
                echo "</td></tr>";
                echo '</table>';
                echo $OUTPUT->container_end();
            }
        }
        $this->view_dates();
        $this->view_footer();
    }
    
    function get_filter_link_teacher_view($activefilter, $filter, $viewurl, $viewtext) {
        return ($activefilter==$filter) ? html_writer::tag('span', $viewtext, array('style'=>'font-size:1.2em;font-weight:bold;')) : html_writer::link($viewurl.$filter, $viewtext);
    }
    
    function get_sql_filter_teacher_view($filter, $assessmentid) {
        global $CFG;
        $where = '';
        $workmode = $this->assessment->workmode;
        
        if ($filter == '0') return '';
        if ($workmode == 'user') {
            $id = 'u.id';
        } else if ($workmode == 'group') {
            $id = 'g.id';
        }
        
        switch ($filter) {
            case 'submitted':
                $where = $id." IN (SELECT ".$workmode."id FROM {assessment_submissions} WHERE assessmentid = ".$assessmentid.") ";
                break;
            case 'notsubmitted':
                $where = $id." NOT IN (SELECT ".$workmode."id FROM {assessment_submissions} WHERE assessmentid = ".$assessmentid.") ";
                break;
            case 'late':
                $where = $id." IN (SELECT ".$workmode."id FROM {assessment_submissions} WHERE assessmentid = ".$assessmentid." AND timecreated > ".$this->assessment->submitend.") ";
                break;
            case 'teachergraded':
                $where = $id." IN (SELECT ".$workmode."id FROM {assessment_grades} WHERE type = 0 AND assessmentid = ".$this->assessment->teacher.") ";
                break;
            case 'teachernotgraded':
                $where = $id." NOT IN (SELECT ".$workmode."id FROM {assessment_grades} WHERE type = 0 AND assessmentid = ".$this->assessment->teacher.") ";
                break;
            case 'selfgraded':
                $where = $id." IN (SELECT ".$workmode."id FROM {assessment_grades} WHERE type = 1 AND assessmentid = ".$this->assessment->self.") ";
                break;
            case 'selfnotgraded':
                $where = $id." NOT IN (SELECT ".$workmode."id FROM {assessment_grades} WHERE type = 1 AND assessmentid = ".$this->assessment->self.") ";
                break;
        }
        if ($where != '') $where .= " AND ";
        
        return $where;
    }
    
    function get_peer_filter_teacher_view($filter, $assessmentid, $users, $peernum) {
        global $CFG, $DB;
        $sql = "SELECT marker, COUNT(*) AS graded FROM {assessment_grades} 
                WHERE type = ? AND assessmentid = (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?) 
                GROUP BY marker";
        $peergradedcount = $DB->get_records_sql_menu($sql, array(2, $assessmentid));
        
        $returnuserids = array();
        if (sizeof($peergradedcount) == 0) {
            // no peer graded entries found
            return $returnuserids;
        } else {
            $allgradedids = array();
            $somegradedids = array();
            foreach ($peergradedcount as $marker => $gradedcount) {
                if ($gradedcount == $peernum) $allgradedids[] = $marker;
                else if ($gradedcount < $peernum) $somegradedids[] = $marker;
            }
            $nonegradedids = array_diff($users, $allgradedids, $somegradedids);
        }
        
        switch ($filter) {
            case 'peergradedall':
                $returnuserids = $allgradedids;
                break;
            case 'peergradedsome':
                $returnuserids = $somegradedids;
                break;
            case 'peergradednone':
                $returnuserids = $nonegradedids;
                break;
        }
        
        return $returnuserids;
    }
    
    function get_forum_discussions($id_arr) {
        global $CFG, $USER, $DB;
        $workmode = $this->assessment->workmode;
        
        // Get forum discussion links for each student/group
        if ($workmode == 'group') {
            $fields_sql = 'groupid, userid';
            $groupmemberids = array();
            foreach ($id_arr as $groupid) {
                $groupmembers =  array_keys(groups_get_members($groupid));
                $groupmemberids[$groupid] = $groupmembers;
            }
        } else {
            $fields_sql = 'userid, groupid';
        }
        
        $query_params = array('assessmentid'=>$this->assessment->id);
        if (!empty($id_arr)) {
            list($in_sql, $in_params) = $DB->get_in_or_equal($id_arr, SQL_PARAMS_NAMED);
            $params = array_merge($in_params, $query_params);
        } else {
            $params = $query_params;
        }
        
        $sql = "SELECT $fields_sql, id from {assessment_discussions} 
                WHERE assessmentid = :assessmentid AND ".$workmode."id $in_sql LIMIT 1";
        
        if (!$discussionids = $DB->get_records_sql($sql, $params)) {
            foreach ($id_arr as $id) {
                $discussion = new stdClass();
                if ($workmode=='group') {
                    $discussion->userid = $groupmemberids[$id][0];
                    $discussion->groupid = $id;
                    $usertoadd = $DB->get_record('user', array('id'=>$discussion->userid));
                } else {
                    $discussion->userid = $id;
                    $discussion->groupid = -1;
                    $usertoadd = $DB->get_record('user', array('id'=>$id));
                }
                $discussion->course = $this->assessment->course;
                $discussion->assessmentid = $this->assessment->id;
                $discussionintro = get_string('discussionintro', 'assessment');
                $discussion->name = str_replace('studentname', fullname($usertoadd), $discussionintro);
                $discussion->intro = get_string('pleasereply', 'assessment');
                $discussion->format = 0;
                
                if (! $discussionid = assessment_add_discussion($discussion, $discussion->intro)) {
                error('Could not add the discussion for this assessment');
                }
                
                $discussionids[$id] = new stdClass();
                $discussionids[$id]->id = $discussionid;
                $discussionids[$id]->userid = $discussion->userid;
                $discussionids[$id]->groupid = $discussion->groupid;
            }
        }
        
        return $discussionids;
    }
    
    function get_forum_discussions_old($ausers_arr) {
        global $CFG, $DB;
        $workmode = $this->assessment->workmode;
        // Get forum discussion links for each student/group
        if ($workmode=='group') {
            $groupmemberids = array();
            foreach ($ausers_arr as $groupid) {
                $groupmembers =  array_keys(groups_get_members($groupid));
                $temp = array_fill_keys($groupmembers, $groupid);
                $groupmemberids += $temp;
            }
            $ausers_arr = array_keys($groupmemberids);
        }
        
        $query_params = array('forum'=>$this->assessment->forum);
        list($in_sql, $in_params) = $DB->get_in_or_equal($ausers_arr, SQL_PARAMS_NAMED);
        $params = array_merge($in_params, $query_params);
        
        $sql = "SELECT userid, id from {forum_discussions} 
                WHERE forum = :forum AND userid $in_sql";
        
        if (!$discussionids = $DB->get_records_sql($sql, $params)) {
            // cannot retrieve discussion, insert one on the fly here
            include_once($CFG->dirroot."/mod/forum/lib.php");
            $forum = $DB->get_record('forum', array('id'=>$this->assessment->forum));
            foreach ($ausers_arr as $userid) {
                $usertoadd = $DB->get_record('user', array('id'=>$userid));
                $discussion = new object();
                $discussion->course   = $forum->course;
                $discussion->forum    = $forum->id;
                $discussionintro      = get_string('discussionintro', 'assessment');
                $discussion->name     = str_replace('studentname', fullname($usertoadd), $discussionintro);
                $discussion->intro    = get_string('pleasereply', 'assessment');
                $discussion->format   = $forum->type;
                $discussion->userid   = $userid;
                $discussion->mailnow  = false;
                $discussion->groupid  = -1;
                
                if (! $discussionid = forum_add_discussion($discussion, $discussion->intro)) {
                    print_error('erroradddiscussion', 'assessment');
                }
                
                if (! $DB->set_field("forum_discussions", "userid", $userid, array("id"=>$discussionid))) {
                    print_error('errormodifyauthor', 'assessment');
                }
                
                if (! $DB->set_field("forum_posts", "userid", $userid, array("discussion"=>$discussionid))) {
                    print_error('errormodifyauthorpost', 'assessment');
                }
                
                $discussionid[$userid] = new stdClass();
                $discussionid[$userid]->id = $discussionid;
                $discussionid[$userid]->userid = $userid;
            }
        }
        
        if ($workmode=='group') {
            foreach ($discussionids as $userid => $data) {
                $groupdiscussionids[$groupmemberids[$userid]] = $data;
            }
            $discussionids = $groupdiscussionids;
        }
        
        return $discussionids;
    }
    
    function count_discussion_posts($userid, $discussionid) {
        global $CFG;
        return assessment_tp_count_discussion_posts($userid, $discussionid);
    }
    
    function count_discussion_unread_posts($userid, $discussionid) {
        global $CFG;
        return assessment_tp_count_discussion_unread_posts($userid, $discussionid);
    }
    
    function count_discussion_unread_user_posts($userid, $poster, $discussionid) {
        global $CFG, $DB;
      
        $cutoffdate = isset($CFG->forum_oldpostdays) ? (time() - ($CFG->forum_oldpostdays*24*60*60)) : 0;
      
        $params = array($userid, $discussionid, $cutoffdate, $poster);
        $sql = "SELECT COUNT(p.id) 
                FROM {assessment_posts} p 
                LEFT JOIN {assessment_read} r ON r.postid = p.id AND r.userid = ? 
                WHERE p.discussionid = ? 
                AND p.timemodified >= ? AND r.id is NULL 
                AND p.userid = ?";
      
        return ($DB->count_records_sql($sql, $params));
    }
    
    function getfullnameformat($prefix='', $sql=true) {
        global $CFG;
        $fullnameformat = get_config('', 'fullnamedisplay');
        if (!$sql) return $fullnameformat;
        if ($fullnameformat == 'lastname firstname') {
            $selectfullname = $prefix.'lastname, " ", '.$prefix.'firstname';
        } else if ($fullnameformat == 'firstname lastname') {
            $selectfullname = $prefix.'firstname, " ", '.$prefix.'lastname';
        } else {
            $selectfullname = $prefix.'lastname, " ", '.$prefix.'firstname';
        }
        $selectfullname = 'CONCAT('.$selectfullname.')';
        return $selectfullname;
    }
    
    function display_scoreboard($role, $grade, $percentage, $scoreboard_style='scoreboard', $HTMLafter='', $decplace=0) {
        $htmlstr = '<div class="scoreboard">'.chr(13);
        if ($role == NULL) {} elseif (is_array($role)) {
            if (array_key_exists('marker', $role)) {
                if ($role['marker']=='others')  {
                    for ($i=0; $i<$role['done']; $i++) {
                        $htmlstr .= '<div class="marks_received"></div>'.chr(13);
                    }
                    for ($i=0; $i<$role['total']-$role['done']; $i++) {
                        $htmlstr .= '<div class="marks_to_be_received"></div>'.chr(13);
                    }
                } else if ($role['marker']=='user') {
                    for ($i=0; $i<$role['done']; $i++) {
                        $htmlstr .= '<div class="marks_given"></div>'.chr(13);
                    }
                    for ($i=0; $i<$role['total']-$role['done']; $i++) {
                        $htmlstr .= '<div class="marks_to_be_given"></div>'.chr(13);
                    }
                }
                $htmlstr .= '<div style="width:3px"></div>'.chr(13);
            } else {
                $htmlstr .= print_user_picture($role['user'], $role['course'], 1, false, true, false);
            }
        } else {
            $htmlstr .= '<div class="'.$role.'"></div>'.chr(13);
        }
        if ($scoreboard_style <> NULL) {
            $htmlstr .= '<div class="'.$scoreboard_style.'-left"></div>'.chr(13);
            if (is_numeric($grade)) {
                $gradestr = strval($grade);
                if ($decplace>0) {
                    if (strpos($gradestr,'.')===false) {
                        $gradestr .= '.';
                        for ($i=0;$i<$decplace;$i++) {
                            $gradestr .= '0';
                        }
                    } else {
                        for ($i=strlen($gradestr)-strpos($gradestr,'.')-1;$i<$decplace;$i++) {
                            $gradestr .= '0';
                        }
                    }
                }
                $dight = 0;
                $percentstrlen = strlen(strval($percentage)) + $decplace;
                if ($decplace>0) $percentstrlen ++;
                for ($i=0;$i<$percentstrlen-strlen($gradestr);$i++) {
                    if ($dight > 0) $htmlstr .= '<div class="'.$scoreboard_style.'-divider"></div>'.chr(13);
                    $htmlstr .= '<div class="'.$scoreboard_style.'-blank"></div>'.chr(13);
                    $dight++;
                }
                for ($i=0;$i<strlen($gradestr);$i++) {
                    if ($dight > 0) $htmlstr .= '<div class="'.$scoreboard_style.'-divider"></div>'.chr(13);
                    if (substr($gradestr,$i,1) == '.') {
                        $htmlstr .= '<div class="'.$scoreboard_style.'-decimal"></div>'.chr(13);
                    } else {
                        $htmlstr .= '<div class="'.$scoreboard_style.'-'.substr($gradestr,$i,1).'"></div>'.chr(13);
                    }
                    $dight++;
                }
            } else {
                $htmlstr .= '<div class="'.$scoreboard_style.'-unknown"></div>'.chr(13);
            }
            $htmlstr .= '<div class="'.$scoreboard_style.'-right1"></div>';
            if (is_numeric($grade)) $htmlstr .= '<div class="'.$scoreboard_style.'-right2">/'.$percentage.'</div>'.chr(13);
            $htmlstr .= '<div class="'.$scoreboard_style.'-right3"></div>'.chr(13);
        }
        $htmlstr .= $HTMLafter.chr(13);
        $htmlstr .= '</div>'.chr(13);
        return $htmlstr;
    }
    
    function display_grade($grade, $percent=0, $textonly=true, $role=NULL, $scoreboard_style='scoreboard', $HTMLafter='', $decplace=0) {
        // if not iLAP_admin theme, use text only for score
        global $DB;
        $textonly = true;
        if ($grade === 'unknown' && $textonly) return 'N/A';
        
        static $scalegrades = array();   // Cache scales for each assessment - they might have different scales!!
        $percentage = '';
        if ($this->assessment->grade >= 0) {    // Normal number
            if ($grade == -1) {
                return 'N/A';
            } else {
                if ($this->assessment->rubricid > 0) {
                    if ($percent) $percentage = ' ('.round(($grade/$this->rubric->points)*100, 1).'%)';
                    if ($textonly) {
                        return $grade.' / '.$this->rubric->points.$percentage;
                    } else {
                        return $this->display_scoreboard($role, $grade, $this->rubric->points.$percentage, $scoreboard_style, $HTMLafter, $decplace);
                    }
                } else {
                    if ($textonly) {
                        return $grade.' /'.$this->assessment->grade.$percentage;
                    } else {
                        return $this->display_scoreboard($role, $grade, $this->assessment->grade.$percentage, $scoreboard_style, $HTMLafter, $decplace);
                    }
                }
            }
        } else {                          // Scale
            if (empty($scalegrades[$this->assessment->id])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($this->assessment->grade)))) {
                    $scalegrades[$this->assessment->id] = make_menu_from_list($scale->scale);
                } else {
                    return 'N/A';
                }
            }
            if (isset($scalegrades[$this->assessment->id][$grade])) {
                return $scalegrades[$this->assessment->id][$grade];
            }
            return 'N/A';
        }
    }
    
    function view_header() {
        global $CFG, $PAGE, $OUTPUT;
        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();
        groups_print_activity_menu($this->cm,  $CFG->wwwroot .'/mod/assessment/view.php?id=' . $this->cm->id);
    }
    
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('description'), 2, 'main teacherviewheading');
        echo $OUTPUT->box_start();
        echo format_module_intro('assessment', $this->assessment, $this->cm->id);
        echo $OUTPUT->box_end();
    }
   
    function view_dates() {
        global $CFG, $OUTPUT;
        $assessment = $this->assessment;
        $notset = get_string('notset', 'assessment');
        
        echo $OUTPUT->heading(get_string('submissiondate', 'assessment'), 2, 'main teacherviewheading');
        echo $OUTPUT->box_start();
        
        // Change to tabular format and use date() instead of userdate();
        $HTMLstr = '<table id="viewdatetable">';
        $HTMLstr .= '<tr>
                        <td class="c0">&nbsp;</td>
                        <td class="c1">'.get_string('start','assessment').'</td>
                        <td class="c1">'.get_string('end','assessment').'</td>
                        <td class="c1">'.get_string('publish','assessment').'</td>
                        <!--<td rowspan="5"><div id="datecalendar"></div></td></tr>-->
                    </tr>';
        $HTMLstr .= '<tr id="submitdate_tr">
                        <td class="c0">'.get_string('submission','assessment').'</td>
                        <td>'.(!empty($assessment->submitstart) ? userdate($assessment->submitstart) : $notset).'</td>
                        <td>'.(!empty($assessment->submitend) ? userdate($assessment->submitend) : $notset).'</td>
                        <td>'.get_string('notapplicable', 'assessment').'</td>
                    </tr>';
        $HTMLstr .= '<tr id="teacherdate_tr">
                        <td class="c0">'.get_string('teacherassessment','assessment').'</td>
                        <td>'.(!empty($assessment->teachertimestart) ? userdate($assessment->teachertimestart) : $notset).'</td>
                        <td>'.(!empty($assessment->teachertimeend) ? userdate($assessment->teachertimeend) : $notset).'</td>
                        <td>'.(!empty($assessment->teachertimepublish) ? userdate($assessment->teachertimepublish) : $notset).'</td>
                    </tr>';
        $HTMLstr .= '<tr id="selfdate_tr">
                        <td class="c0">'.get_string('selfassessment','assessment').'</td>
                        <td>'.(!empty($assessment->selftimestart) ? userdate($assessment->selftimestart) : $notset).'</td>
                        <td>'.(!empty($assessment->selftimeend) ? userdate($assessment->selftimeend) : $notset).'</td>
                        <td>'.(!empty($assessment->selftimepublish) ? userdate($assessment->selftimepublish) : $notset).'</td>
                    </tr>';
        $HTMLstr .= '<tr id="peerdate_tr">
                        <td class="c0">'.get_string('peerassessment','assessment').'</td>
                        <td>'.(!empty($assessment->peertimestart) ? userdate($assessment->peertimestart) : $notset).'</td>
                        <td>'.(!empty($assessment->peertimeend) ? userdate($assessment->peertimeend) : $notset).'</td>
                        <td>'.(!empty($assessment->peertimepublish) ? userdate($assessment->peertimepublish) : $notset).'</td>
                   </tr>';
        $HTMLstr .= '</table>';
        echo $HTMLstr;
        echo $OUTPUT->box_end();
    }
    
    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }
    
    function process_assessment_grades($mode, $type, $by='') {
        ///The main switch is changed to facilitate
        ///1) Skip to the next one on the popup
        ///2) Save and Skip to the next one on the popup
      
        //make user global so we can use the id
        global $USER, $PAGE, $OUTPUT;
        
        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);
        if (is_null($mailinfo)) {
            $mailinfo = get_user_preferences('assessment_mailinfo', 0);
        } else {
            set_user_preference('assessment_mailinfo', $mailinfo);
        }
        
        switch ($mode) {
            case 'grade':                         // We are in a popup window grading
                if ($assessment_grade = $this->process_feedback($type)) {
                    $PAGE->set_title(get_string('feedback', 'assessment').':'.format_string($this->assessment->name));
                    $PAGE->set_heading(get_string('changessaved'));
                    echo $OUTPUT->header();
                }
                close_window(1, true); // 1 sec delay and reload opener
                break;
                
            case 'single':                        // We are in a popup window displaying assessment_grade
                $this->display_assessment_grade($type, $by);
                break;
                
            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                /// This turns out to be similar to a single case
                /// The URL used is for the next assessment_grade.
                $this->display_assessment_grade($type);
                break;
                
            case 'saveandnext':
                //We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                $assessment_grade = $this->process_feedback($type);
                //then we display the next grade
                $this->display_assessment_grade($type);
                break;
         
            case 'peersummary':
                $this->display_peer_summary($type);
                break;
         
            default:
                echo print_error('errorprocessgrade', 'assessment');
                break;
        }
    }
    
    function update_grade($assessment_grade) {
        assessment_update_grades($this->assessment, $assessment_grade->userid);
    }
    
    function compare_time($date, $end=0) {
        $timenow = time();
        if ($end) {
            $start = $date;
            if ($timenow < $start)
                return 0; // before
            else if ($timenow > $start && $timenow < $end)
                return 1; // within
            else if ($timenow > $end)
                return 2; // after
        } else {
            if ($timenow < $date)
                return 0; // before
            else
                return 1; // ongoing, no end
        }
    }
    
    function get_assessment_status($userid=0) {
        global $USER, $DB;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        
        $workmode = $this->assessment->workmode;
        $status = array('teaher'=>0, 'self'=>0, 'peer'=>0);
        
        if (isset($this->assessment->teacher)) {
            $status['teacher'] = 1;
            if ($this->assessment->teachertimestart != 0 && $this->assessment->teachertimeend != 0) {
                $status['teachertime'] = $this->compare_time($this->assessment->teachertimestart, $this->assessment->teachertimeend);
            } else {
                $status['teachertime'] = -1;
            }
            if ($this->assessment->teachertimepublish != 0) {
                $status['teacherpublished'] = $this->compare_time($this->assessment->teachertimepublish);
            }
            if (! $teachergrade = $DB->get_record('assessment_grades', array('assessmentid'=>$this->assessment->teacher, $workmode.'id'=>$userid, 'type'=>0))) {
                $status['teachermarked'] = 0;
            } else {
                $status['teachermarked'] = 1;
            }
        }
        
        if (isset($this->assessment->self)) {
            $status['self'] = 1;
            if ($this->assessment->selftimestart != 0 && $this->assessment->selftimeend != 0) {
                $status['selftime'] = $this->compare_time($this->assessment->selftimestart, $this->assessment->selftimeend);
            } else {
                $status['selftime'] = -1;
            }
            if ($this->assessment->selftimepublish != 0) {
                $status['selfpublished'] = $this->compare_time($this->assessment->selftimepublish);
            }
            if (! $selfgrade = $DB->get_record('assessment_grades', array('assessmentid'=>$this->assessment->self, $workmode.'id'=>$userid, 'type'=>1))) {
                $status['selfmarked'] = 0;
            } else {
                $status['selfmarked'] = 1;
            }
        }
        
        if (isset($this->assessment->peer)) {
            $status['peer'] = 1;
            if ($this->assessment->peertimestart != 0 && $this->assessment->peertimeend != 0) {
                $status['peertime'] = $this->compare_time($this->assessment->peertimestart, $this->assessment->peertimeend);
            } else {
                $status['peertime'] = -1;
            }
            if ($this->assessment->peertimepublish != 0) {
                $status['peerpublished'] = $this->compare_time($this->assessment->peertimepublish);
            }
            if (! $peergrade = $DB->get_records('assessment_grades', array('assessmentid'=>$this->assessment->peer, 'marker'=>$userid, 'type'=>2))) {
                $status['peermarked'] = 0;
            } else {
                $status['peermarked'] = 1;
            }
            
            if (! $peergrade = $DB->get_records('assessment_grades', array('assessmentid'=>$this->assessment->peer, $workmode.'id'=>$userid, 'type'=>2))) {
                $status['peermarkedby'] = 0;
            } else {
                $status['peermarkedby'] = 1;
            }
        }
        return $status;
    }
    
    function get_assessment_status_description($assessment_status, $auser_peer) {
        global $CFG;
        $assessment_types = array('teacher','self','peer');
        $status = array();
        
        for ($i=0; $i<sizeof($assessment_types); $i++) {
            $status[$assessment_types[$i]] = '';
            if (array_key_exists($assessment_types[$i], $assessment_status)) {
                if (array_key_exists($assessment_types[$i].'time', $assessment_status)) {
                    switch ($assessment_status[$assessment_types[$i].'time']) {
                        case 0:
                            $status[$assessment_types[$i]] .= get_string('periodnotstarted', 'assessment').'; ';
                            break;
                        case 1:
                            $status[$assessment_types[$i]] .= get_string('ongoing', 'assessment').'; ';
                            break;
                        case 2:
                            $status[$assessment_types[$i]] .= get_string('periodended', 'assessment').'; ';
                            break;
                        case -1:
                            $status[$assessment_types[$i]] .= get_string('unlimitedperiod', 'assessment').'; ';
                            break;
                    }
                }
                if ($assessment_types[$i] == 'peer' && isset($this->assessment->peernum)) {
                    if (sizeof($auser_peer) == $this->assessment->peernum) {
                        $status[$assessment_types[$i]] .= get_string('allgraded', 'assessment').'; ';
                    } else if ($auser_peer === false || sizeof($auser_peer) == 0) {
                        $status[$assessment_types[$i]] .= get_string('notgraded', 'assessment').'; ';
                    } else {
                        $status[$assessment_types[$i]] .= get_string('stillgrading', 'assessment').'; ';
                    }
                } else {
                    if (array_key_exists($assessment_types[$i].'marked', $assessment_status)) {
                        $status[$assessment_types[$i]] .= get_string(($assessment_status[$assessment_types[$i].'marked'] ? 'graded' : 'notgraded'), 'assessment').'; ';
                    }
                }
            } else {
                $status[$assessment_types[$i]] = 'N/A';
            }
        }
        return $status;
    }
    
    function get_submission($id, $createnew=false, $teachermodified=false) {
        global $COURSE, $DB;
        $workmode = $this->assessment->workmode;
        if ($workmode == 0 || $workmode == 'user') {
            $workmode = 'user';
        } else {
            $workmode = 'group';
        }
        if ($this->assessment->numfiles) {
            $submission = $DB->get_record("assessment_submissions", array("assessmentid"=>$this->assessment->id, $workmode."id"=>$id));
        } else {
            $submission = NULL;
        }
        return $submission;
    }
    
    function get_assessment_grade($markerid, $userid, $type=0, $createnew=false, $teachermodified=false) {
        global $USER, $COURSE, $DB;
        $workmode = $this->assessment->workmode;
        
        if ($type == 0) $assessmentidname = 'teacher';
        if ($type == 1) $assessmentidname = 'self';
        if ($type == 0 || $type == 1) $assessment_grade = $DB->get_record('assessment_grades', array('assessmentid'=>$this->assessment->$assessmentidname, 'type'=>$type, $workmode.'id'=>$userid));
        
        if ($type == 2) {
            $assessmentidname = 'peer';
            $params = array($this->assessment->$assessmentidname, $type, $userid, $markerid);
            $assessment_grade = $DB->get_records_select('assessment_grades', "assessmentid = ? AND type = ? AND ".$workmode."id = ? AND marker = ?", $params);
            if ($assessment_grade) $assessment_grade = array_shift($assessment_grade);
        }
        
        if ($assessment_grade || !$createnew) {
            if ($assessment_grade) $assessment_grade->comment = stripslashes($assessment_grade->comment);
                return $assessment_grade;
        }
        $new_assessment_grade = $this->prepare_new_assessment_grade($markerid, $userid, $type, $teachermodified);
        
        if (!$DB->insert_record("assessment_grades", $new_assessment_grade)) {
            print_error('errorinsertgrade', 'assessment');
        }
        
        if ($type == 2) {
            $params = array($this->assessment->$assessmentidname, $type, $userid, $markerid);
            $assessment_grade = $DB->get_records_select('assessment_grades', "assessmentid = ? AND type = ? AND ".$workmode."id = ? AND marker = ?", $params);
        } else {
            $assessment_grade = $DB->get_record('assessment_grades', array('assessmentid'=>$this->assessment->$assessmentidname, 'type'=>$type, $workmode.'id'=>$userid));
            $assessment_grade->comment = stripslashes($assessment_grade->comment);
        }
        
        return $assessment_grade;
    }
    
    function display_assessment_grade($type, $by = '') {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');
        
        $workmode = $this->assessment->workmode;
        
        $offset = required_param('offset', PARAM_INT); //offset for where to start looking for student.
        $userid = optional_param('userid', 0, PARAM_INT);
        $groupid = optional_param('groupid', 0, PARAM_INT);
        $markergroupid = optional_param('markergroupid', 0, PARAM_INT);
        
        if (($workmode=='group' && !$groupid) || ($workmode=='user' && !$userid)) {
            print_error('errorwrongparams', 'assessment');
        }
        if (!$userid && !$groupid) {
            print_error('errorwrongparams', 'assessment');
        }
        
        $rubric_display_mode = 'edit';
        $markerid = $USER->id;
        $assessment_status = $this->get_assessment_status();
        
        // self assessment to own group, NO individual group member assess own group
        if ($type == 1 && $workmode == 'group') {
            $markerid = $markergroupid;
        }
        
        if ($type == 2) {
            $peergroupmode = $this->assessment->peergroupmode;
            if ($peergroupmode == 2) { // group-assess-groups
                $markerid = $markergroupid;
            }
            
            $iseditable = ($assessment_status['peertime'] == 1 || $assessment_status['peertime'] == -1);
            if ($by == 'you') {
                if (!$iseditable) {
                    $rubric_display_mode = 'teacherview';
                }
                
                $params = array(2, $markerid, $markerid, $this->assessment->id);
                $select = "type = ? AND marker = ? AND ".$workmode."id <> ? AND assessmentid = (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                $grade_marked = $DB->count_records_select('assessment_grades', $select, $params);
                
                if ($grade_marked == 0) {
                    print_error('errornopeerassessed', 'assessment');
                }
            } else if ($by == 'peer') {
                if ($assessment_status['peerpublished'] != 1) {
                    print_error('errorpeerpublishdate', 'assessment');
                }
                // 20101006 fix
                $markerid = $markergroupid;
                // swap marker && user/group
                $rubric_display_mode = 'teacherview';
            }
        }
        
        // get marker and user/group info
        $m_params = array($markerid, $markerid);
        $markersql = "SELECT *, 
                      (SELECT data FROM {user_info_data} WHERE userid = ? AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"chiname\")) as chiname
                      FROM {user} u WHERE u.id = ?";
        $u_params = array($userid, $userid);
        $usersql = "SELECT *, 
                    (SELECT data FROM {user_info_data} WHERE userid = ? AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"chiname\")) as chiname
                    FROM {user} u WHERE u.id = ?";
        if (($type == 1 || ($type == 2 && $peergroupmode == 2)) && $workmode == 'group') {
            $m_params = array($markerid);
            $markersql = "SELECT * FROM {groups} g WHERE g.id = ?";
            $u_params = array($groupid);
            $usersql = "SELECT * FROM {groups} g WHERE g.id = ?";
        } else if (($type == 0 || ($type == 2 && $peergroupmode == 1)) && $workmode == 'group') {
            $u_params = array($groupid);
            $usersql = "SELECT * FROM {groups} g WHERE g.id = ?";
        }
        
        if (!$marker = $DB->get_record_sql($markersql, $m_params)) {
            print_error('errornomarker', 'assessment');
        }
        if (!$usertograde = $DB->get_record_sql($usersql, $u_params)) {
            print_error('errornouser', 'assessment');
        }
        
        if ($workmode == 'group') {
            if ($type == 0 || ($type == 2 && $peergroupmode == 1)) {
                $markername = fullname($marker);
                $username = $usertograde->name;
            } else if ($type == 1 || ($type == 2 && $peergroupmode == 2)) {
                $markername = $marker->name;
                $username = $usertograde->name;
            }
        } else {
            $markername = fullname($marker);
            $username = fullname($usertograde);
            // Self assessment but not the marker
            if ($type == 1 && $marker->id!=$usertograde->id) {
                $rubric_display_mode = 'view';
            } else {
                // Peer assessment and view own submission
                if ($type == 2 && $usertograde->id == $USER->id) {
                    $rubric_display_mode = 'view';
                }
            }
        }
        
        /*
        if (($type == 1 || ($type == 2 && $peergroupmode == 2)) && $workmode == 'group') {
            $markername = $marker->name;
            $username = $usertograde->name;
        } else if (($type == 0 || ($type == 2 && $peergroupmode == 1)) && $workmode == 'group') {
            $markername = fullname($marker);
            $username = $usertograde->name;
        } else {
            $markername = fullname($marker);
            $username = fullname($usertograde);
            $rubric_display_mode = ($type == 1 && $marker->id!=$usertograde->id) ? 'view' : 'edit';
        }
        */
        $submission = $this->get_submission($usertograde->id);
        if (!$assessment_grade = $this->get_assessment_grade($marker->id, $usertograde->id, $type)) {
            // prevent peer assessment if maximum no. of peer graded is reached
            if ($type == 2 && $by == 'you') {
                if ($grade_marked->peernum_assessed >= $this->assessment->peernum) {
                    print_error('errorpeermaxed', 'assessment', '', $this->assessment->peernum);
                }
            }
            $assessment_grade = $this->prepare_new_assessment_grade($marker->id, $usertograde->id, $type);
        }
        
        /// construct SQL, using current offset to find the data of the next student
        $course = $this->course;
        $assessment = $this->assessment;
        $cm = $this->cm;
        $context = $this->context;
        
        /// Get all ppl that can submit assessments
        $currentgroup = groups_get_activity_group($cm);
        $allowgroups = groups_get_activity_allowed_groups($cm);
        $groupids = array_keys($allowgroups);
        if ($users = get_users_by_capability($context, 'mod/assessment:submit', 'u.id', '', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }
        
        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }
        
        switch ($type) {
            case 0:
                $assessmentidname = 'teacher';
                $header_text = get_string('teacherassessment', 'assessment');
                break;
            case 1:
                $assessmentidname = 'self';
                $header_text = get_string('selfassessment', 'assessment');
                break;
            case 2:
                $assessmentidname = 'peer';
                $header_text = get_string('peerassessment', 'assessment');
                break;
        }
        
        $markername .= ' ('.get_string($assessmentidname.'assessment', 'assessment').')';
        
        $nextid = 0;
        $params = array();
        if ($workmode == 'group') {
            if ($groupids) {
                $query_params = array('assessmentid'=>$this->assessment->$assessmentidname);
                list($in_sql, $in_params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
                $params = array_merge($in_params, $query_params);
                
                $params['assessmentid'] = $this->assessment->$assessmentidname;
                $sql = "SELECT g.id FROM {groups} g 
                        LEFT JOIN {assessment_grades} s ON g.id = s.groupid
                        AND s.assessmentid = :assessmentid
                        WHERE g.id $in_sql";
            }
        } else {
            if ($users) {
                $query_params = array('assessmentid'=>$this->assessment->$assessmentidname);
                list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
                $params = array_merge($in_params, $query_params);
                
                $sql = "SELECT u.id,
                        (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"class\")) as class,
                        (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = (SELECT id FROM {user_info_field} WHERE shortname = \"classno\")) as classno 
                        FROM {user} u 
                        LEFT JOIN {assessment_grades} s ON u.id = s.userid
                            AND s.assessmentid = :assessmentid
                        WHERE u.id $in_sql";
            }
        }
        
        if ($sort = flexible_table::get_sort_for_table('mod-assessment-grades')) {
            if ($workmode == 'group' && (strstr($sort, 'firstname') || strstr($sort, 'lastname'))) {
                $sort = '';
            } else {
                $search = array('agt_grade', 'ags_grade', 'firstname', 'lastname');
                $replace = array('s.grade', 's.grade', 'u.firstname', 'u.lastname');
                $sort = str_replace($search, $replace, $sort);
                $sort = 'ORDER BY '.$sort.' ';
            }
        }
        
        if ($auser = $DB->get_records_sql($sql." ".$sort, $params, $offset+1, 1)) {
            $nextuser = array_shift($auser);
            $nextid = $nextuser->id;
        }
        
        $log_others = array($workmode.'id'=>$usertograde->id);
        $log_others['offset'] = $offset;
        $log_others['type'] = $type;
        $log_others['peergroupmode'] = ($type == 2) ? $peergroupmode : '';
        $event = \mod_assessment\event\grade_student_viewed::create(array(
            'objectid' => $cm->id,
            'courseid' => $course->id,
            'context' => context_module::instance($cm->id),
            'other' => $log_others
        ));
        $event->add_record_snapshot('assessment_grades', $assessment_grade);
        $event->trigger();
        
        /*
        $log_url = 'assessment_grades.php?id='.$cm->id.'&'.$workmode.'id='.$usertograde->id.'&mode=single&offset='.$offset.'&type='.$type;
        if ($type == 2) $log_url .= '&peergroupmode='.$peergroupmode;
        add_to_log($course->id, "assessment", "view grade student", $log_url, $assessmentidname.': '.$username, $cm->id);
        */
        
        $style = "<style type=\"text/css\">
                      body {margin: 0px;}
                      .studentviewtable th {text-align: right;}
                      .div_hide {display: none;}
                  </style>";
        print $style;
        
        if (has_capability('mod/assessment:teachergrade', $context)) {
            $viewer = 'teacher';
        } else {
            $viewer = 'student';
        }
        
        $is_submit_files = $this->assessment->numfiles;
        
        $url = new moodle_url('/mod/assessment/assessment_grades.php');
        $url->param('id', $cm->id);
        $url->param($workmode.'id', $usertograde->id);
        $url->param('mode', 'single');
        $url->param('offset', $offset);
        $url->param('type', $type);
        $PAGE->set_url($url);
        $PAGE->set_title(format_string($this->assessment->name).": $username ($header_text)");
        echo $OUTPUT->header();
        
        echo html_writer::start_tag('div', array('style'=>'width:100%; margin-bottom:20px;'));
        echo html_writer::start_tag('table', array('width'=>'100%', 'class'=>'studentviewtable', 'border'=>'0', 'cellspacing'=>'0', 'cellpadding'=>'2'));
        
        if ($is_submit_files) {
            $activitytype = get_string('uploadingoffiles', 'assessment');
            $activityname = $this->assessment->numfiles.get_string('filesrequired', 'assessment');
        }
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('activity').':');
        echo html_writer::tag('td', $activitytype);
        echo html_writer::end_tag('tr');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('name').':');
        echo html_writer::tag('td', $activityname);
        echo html_writer::end_tag('tr');
        
        if ($submission = $this->get_submission($usertograde->id)) {
            $link_text = get_string('submitted', 'assessment').'<br/>';
            $timcreated = userdate($submission->timecreated);
            if ($submission->timecreated > $assessment->submitend) {
                $timcreated .= ' <span style="color:red">'.get_string('latesubmission', 'assessment').'</span>';
            }
            if ($is_submit_files) {
                if ($viewer == 'student' && $type == 1) {
                    $popup_url = '/mod/assessment/upload.php?id='.$cm->id.'&amp;'.$workmode.'id='.$usertograde->id;
                    $activityentrylink = $OUTPUT->action_link($popup_url, $link_text, 
                                         new popup_action('click', $popup_url, 'upload'.$usertograde->id, array('height' => 600, 'width' => 1024)), 
                                         array('title'=>$link_text));
                } else {
                    $view_submission_url = '/mod/assessment/view_submission.php?id='.$cm->id.'&amp;a='.$assessment->id.'&amp;'.$workmode.'id='.$usertograde->id;
                    $activityentrylink = $OUTPUT->action_link($view_submission_url, $link_text, 
                                         new popup_action('click', $view_submission_url, 'viewwork'.$usertograde->id, array('height' => 600, 'width' => 800)), 
                                         array('title'=>$link_text));
                }
                $activityentrylink .= $timcreated.(html_writer::empty_tag('br'));
                if ($assessment->numfiles) {
                    $activityentrylink .=(html_writer::empty_tag('br')).print_assessment_user_submitted_files_simple($submission, $assessment, $cm);
                }
            }
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', $username.get_string('work', 'assessment').':');
            echo html_writer::tag('td', $activityentrylink);
            echo html_writer::end_tag('tr');
        } else {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', $username.get_string('work', 'assessment').':');
            echo html_writer::tag('td', get_string('notavailable', 'assessment'));
            echo html_writer::end_tag('tr');
        }
        
        if ($assessment->forum) {
            $discussionids = $this->get_forum_discussions(array($usertograde->id));
            $discussion_url = '/mod/assessment/discuss.php?d='.$discussionids[$usertograde->id]->id;
            $discussion_unread = $this->count_discussion_unread_posts($USER->id, $discussionids[$usertograde->id]->id).' '.get_string('unreadpost', 'assessment');
            $forum = $OUTPUT->action_link($discussion_url, $discussion_unread,  
                     new popup_action('click', $discussion_url, 'discussion'.$usertograde->id, array('height' => 600, 'width' => 800)), 
                     array('title'=>$discussion_unread));
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', get_string('discussion', 'assessment').':');
            echo html_writer::tag('td', $forum);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
        
        if (isset($submission->teacher) && $submission->teacher) {
            $teacher = $DB->get_record('user', array('id'=>$submission->teacher));
        } else {
            $teacher = $USER;
        }
        
        $mformdata = new stdClass();
        $mformdata->context = $this->context;
        $mformdata->offset = $offset;
        $mformdata->markerid = $marker->id;
        $mformdata->cm = $this->cm;
        $mformdata->type = $type;
        $mformdata->markergroupid = $markergroupid;
        $mformdata->peergroupmode = isset($peergroupmode) ? $peergroupmode:0;
        $mformdata->markername = $markername;
        $mformdata->assessment_grade = $assessment_grade;
        $mformdata->rubric_obj = $this->rubric;
        $mformdata->assessment = $assessment;
        $mformdata->usertograde = $usertograde;
        $mformdata->viewer = $viewer;
        $mformdata->rubric_display_mode = $rubric_display_mode;
        $mformdata->workmode = $workmode;
        $mformdata->nextid = $nextid;
        
        $draftid_editor = file_get_submitted_draft_itemid('grade_comment');
        $currenttext = file_prepare_draft_area($draftid_editor, $this->context->id, 'mod_assessment', 'grade_comment', $usertograde->id, array('subdirs'=>0), $assessment_grade->comment);
        $mformdata->comment = $currenttext;
        $mformdata->comment_editor = array('text'=>$currenttext,
                                    'format'=>editors_get_preferred_format(),
                                    'itemid'=>$draftid_editor);
        
        $submitform = new mod_assessment_grading_form(null, $mformdata);
        $submitform->set_data($mformdata);
        $submitform->display();
        
        echo $OUTPUT->footer();
    }
    
    function display_peer_summary($type) {
        global $DB, $PAGE, $OUTPUT;
        
        $ismarker = required_param('ismarker', PARAM_INT);
        $workmode = $this->assessment->workmode;
        
        if ($workmode == 'group') {
            $groupid = required_param('groupid', PARAM_INT);
            if (!$group = $DB->get_record('groups', array('id'=>$groupid))) {
                print_error('errornogroup', 'assessment');
            }
            if ($this->assessment->peergroupmode == 1) {
                $userid = required_param('userid', PARAM_INT);
                if (!$user = $DB->get_record('user', array('id'=>$userid))) {
                    print_error('errornouser', 'assessment');
                }
            }
        } else {
            $userid = required_param('userid', PARAM_INT);
            if (!$user = $DB->get_record('user', array('id'=>$userid))) {
                print_error('errornouser', 'assessment');
            }
        }
        
        /// construct SQL, using current offset to find the data of the next student
        $assessment = $this->assessment;
        $context = $this->context;
        
        if (has_capability('mod/assessment:teachergrade', $context)) {
            $viewer = 'teacher';
        } else {
            print_error('errorcannotviewcontent', 'assessment');
        }
        
        $selectfullname = $this->getfullnameformat();
        
        if ($ismarker) {
            // get peer assessment grade (user as marker)
            $auser_peer_marked = array();
            if ($workmode == 'group') {
                if ($assessment->peergroupmode == 1) $peermarkerid = $user->id;
                else $peermarkerid = $group->id;
                $params = array($peermarkerid, $group->id, $assessment->id);
                $sql = "SELECT agp.id, agp.groupid AS workid, agp.marker, agp.userid, 
                            (SELECT name from {groups} WHERE id = agp.groupid) AS peername,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = 2 AND agp.marker = ? AND agp.groupid <> ? AND agp.assessmentid =  
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                $markername = $group->name;
            } else {
                $params = array($user->id, $user->id, $assessment->id);
                $sql = "SELECT
                            agp.id, agp.groupid, agp.marker, agp.userid as workid, 
                            (SELECT ".$selectfullname." from {user} WHERE id = agp.userid) AS peername,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = 2 AND agp.marker = ? AND agp.userid <> ? AND agp.assessmentid =  
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                $markername = fullname($user, true);
            }
            $auser_peer_marked = $DB->get_records_sql($sql, $params);
            
            $PAGE->set_title(get_string('assessment', 'assessment').': '.format_string($assessment->name).'; '.get_string('marker', 'assessment').': '.$markername);
            
            echo $OUTPUT->header();
            echo $OUTPUT->box_start();
            if (sizeof($auser_peer_marked) > 0) {
                //echo '<ol class="peersummary">';
                foreach ($auser_peer_marked as $gradeid => $gradeobj) {
                    $linktograde = $this->display_grade($gradeobj->grade, 1).', '.userdate($gradeobj->timemodified);
                    if ($this->rubric->id) {
                        //echo '<li>'.$gradeobj->peername.' - <a href="#" onclick="showhiderubric(\'peerrubric'.$gradeobj->workid.'\', this, \'title\')" title="'.get_string('showrubric', 'assessment').'">'.$linktograde.'</a>';
                        echo '<div class="peercommentbubble">';
                        echo '<blockquote><p>';
                        echo (empty($gradeobj->comment)?'N/A':format_text(stripslashes($gradeobj->comment), FORMAT_HTML));
                        echo '</p></blockquote>';
                        echo '<p class="peercommentuser">';
                        echo '<strong>'.$gradeobj->peername.'</strong> - <a href="#" onclick="showhiderubric(\'peerrubric'.$gradeobj->workid.'\', this, \'title\')" title="'.get_string('showrubric', 'assessment').'">'.$linktograde.'</a>';
                        echo '</p>';
                        echo '</div>';
                    } else {
                        echo '<li>'.$gradeobj->peername.' - '.$linktograde;
                    }
                    echo '<div class="peercommentseparator">&nbsp;</div>';
                    //echo '<div><strong>'.get_string('comment', 'assessment').'</strong>: ';
                    //echo (empty($gradeobj->comment)?'N/A':format_text(stripslashes($gradeobj->comment), FORMAT_HTML)).'</div>';
                    if ($this->rubric->id) {
                        echo '<div id="peerrubric'.$gradeobj->workid.'" style="display:none;z-index:100;">';
                        $this->rubric->grade($assessment, $gradeobj, $gradeobj->workid, 2, 'student', 'teacherview', $gradeobj->marker);
                        echo '</div>';
                    }
                    echo '</li>';
                }
                //echo '</ol>';
            }
            echo $OUTPUT->box_end();
        } else {
            // get peer assessment grade (marked by other peers)
            $auser_peer_markedby = array();
            
            if ($workmode == 'group') {
                if ($assessment->peergroupmode == 2) {
                    $markername_sql = "SELECT name from {groups} WHERE id = agp.marker";
                } else {
                    $markername_sql = "SELECT $selectfullname from {user} WHERE id = agp.marker";
                }
                $params = array(2, $group->id, $group->id, $assessment->id);
                $sql = "SELECT
                            agp.id, agp.groupid as workid, agp.marker, agp.userid, 
                            ($markername_sql) AS markername,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = ? AND agp.groupid = ? AND agp.marker <> ? AND agp.assessmentid =  
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                $auser_peer_markedby = $DB->get_records_sql($sql, $params);
                $peername = $group->name;
            } else {
                $params = array(2, $user->id, $user->id, $assessment->id);
                $sql = "SELECT
                            agp.id, agp.groupid, agp.marker, agp.userid as workid, 
                            (SELECT $selectfullname from {user} WHERE id = agp.marker) AS markername,
                            agp.grade, agp.type, agp.timemodified, agp.comment 
                        FROM {assessment_grades} agp
                        WHERE agp.type = ? AND agp.userid = ? AND agp.marker <> ? AND agp.assessmentid =  
                            (SELECT id FROM {assessment_types} WHERE type = 2 AND assessmentid = ?)";
                $auser_peer_markedby = $DB->get_records_sql($sql, $params);
                $peername = fullname($user, true);
            }
            
            $PAGE->set_title(get_string('assessment', 'assessment').': '.format_string($assessment->name).'; '.get_string('markby', 'assessment').': '.$peername);
            
            echo $OUTPUT->header();
            echo $OUTPUT->box_start();
            if (sizeof($auser_peer_markedby) > 0) {
                echo '<ol class="peersummary">';
                foreach ($auser_peer_markedby as $gradeid => $gradeobj) {
                    $linktograde = $this->display_grade($gradeobj->grade, 1).', '.userdate($gradeobj->timemodified);
                    if ($this->rubric->id) {
                        //echo '<li>'.$gradeobj->markername.' - <a href="#" onclick="showhiderubric(\'peerrubric'.$gradeobj->marker.'\', this, \'title\'); return false" title="'.get_string('showrubric', 'assessment').'">'.$linktograde.'</a>';
                        echo '<div class="peercommentbubble">';
                        echo '<blockquote><p>';
                        echo (empty($gradeobj->comment)?'N/A':format_text(stripslashes($gradeobj->comment), FORMAT_HTML));
                        echo '</p></blockquote>';
                        echo '<p class="peercommentuser">';
                        echo '<strong>'.$gradeobj->markername.'</strong> - <a href="#" onclick="showhiderubric(\'peerrubric'.$gradeobj->marker.'\', this, \'title\')" title="'.get_string('showrubric', 'assessment').'">'.$linktograde.'</a>';
                        echo '</p>';
                        echo '</div>';
                    } else {
                        echo '<li>'.$gradeobj->markername.' - '.$linktograde;
                    }
                    echo '<div class="peercommentseparator">&nbsp;</div>';
                    //echo '<div><strong>'.get_string('comment', 'assessment').'</strong>: ';
                    //echo (empty($gradeobj->comment)?'N/A':format_text(stripslashes($gradeobj->comment), FORMAT_HTML)).'</div>';
                    if ($this->rubric->id) {
                        echo '<div id="peerrubric'.$gradeobj->marker.'" style="display:none;z-index:100;">';
                        $this->rubric->grade($assessment, $gradeobj, $gradeobj->workid, 2, 'student', 'teacherview', $gradeobj->marker);
                        echo '</div>';
                    }
                    echo '</li>';
                }
                echo '</ol>';
            }
            echo $OUTPUT->box_end();
        }
        
        $PAGE->requires->js_init_call('initRubricStr', array(get_string('hiderubric', 'assessment'), get_string('showrubric', 'assessment')));
        
        echo $OUTPUT->box_start();
        echo html_writer::empty_tag('input', array('type'=>'button', 'name'=>'cancel', 'value'=>get_string('cancel'), 'onclick'=>'window.close()'));
        echo $OUTPUT->box_end();
        
        echo $OUTPUT->footer();
    }
    
    function view_submission($id) {
        global $PAGE, $OUTPUT, $DB;
        
        $workmode = $this->assessment->workmode ? 'group' : 'user';
        
        if ($workmode == 'user') {
            if (!$user = $DB->get_record('user', array('id'=>$id))) {
                print_error('errornouser', 'assessment');
            }
            $work = $user;
            $workname = fullname($work, true);
        } else if ($workmode == 'group') {
            if (!$group = $DB->get_record('groups', array('id'=>$id))) {
                print_error('errornogroup', 'assessment');
            }
            $work = $group;
            $workname = $work->name;
        }
        
        /// construct SQL, using current offset to find the data of the next student
        $assessment = $this->assessment;
        $submission = $this->get_submission($id);
        
        $log_others = array($workmode.'id'=>$work->id, 'assessmentid'=>$assessment->id);
        $event = \mod_assessment\event\submission_viewed::create(array(
            'objectid' => $submission->id,
            'courseid' => $course->id,
            'context' => context_module::instance($cm->id),
            'other' => $log_others
        ));
        $event->add_record_snapshot('assessment_submissions', $submission);
        $event->trigger();
        //add_to_log($this->course->id, "assessment", "view submission", 'view_submission.php?id='.$this->cm->id.'&a='.$assessment->id.'&'.$workmode.'id='.$work->id, $workname, $this->cm->id);
        
        print "<style type=\"text/css\">
                   body {min-width: 700px;}
                   #submission_table .theader {width:25%;font-weight:bold;};
               </style>";
        
        $PAGE->set_title(format_string($this->assessment->name).': '.get_string('viewsubmissionof', 'assessment').$workname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('viewsubmissionof', 'assessment').$workname);
        echo $OUTPUT->box_start();
        $this->print_submission($submission);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
    }
    
    function print_submission($submission) {
        echo html_writer::start_tag('table', array('width'=>'100%', 'cellpadding'=>'5', 'id'=>'submission_table'));
        
        // row 1
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', get_string("title", "assessment"), array('class'=>'boldformlabel'));
        echo html_writer::tag('td', $submission->title);
        echo html_writer::end_tag('tr');
        
        // row 2
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', get_string("description"), array('colspan'=>'2', 'class'=>'boldformlabel'));
        echo html_writer::end_tag('tr');
        
        // row 3
        $submission->description = file_rewrite_pluginfile_urls($submission->description, 'pluginfile.php', $this->context->id, 'mod_assessment', 'submission_description', $submission->id);
        
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $submission->description, array('colspan'=>'2'));
        echo html_writer::end_tag('tr');
        
        if (!empty($submission->url)) {
            // row 4
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', get_string("link", "assessment"), array('class'=>'boldformlabel'));
            echo html_writer::start_tag('td');
            $urls = explode('||', $submission->url);
            for ($i=0; $i<sizeof($urls); $i++) {
                echo assessment_print_url($urls[$i], $submission->id, $i, null, 0);
            }
            echo html_writer::end_tag('td');
            echo html_writer::end_tag('tr');
        }
        
        if ($this->assessment->numfiles) {
            // row 5
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', get_string("filesuploaded", "assessment"), array('colspan'=>'2', 'class'=>'boldformlabel'));
            echo html_writer::end_tag('tr');
            
            // row 6
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', print_assessment_user_submitted_files($submission, $this->assessment, $this->cm), array('colspan'=>'2'));
            echo html_writer::end_tag('tr');
        }
        
        // row 7
        $late_remark = $submission->timecreated > $this->assessment->submitend ? html_writer::tag('span', get_string('latesubmission', 'assessment'), array('style'=>'color:red')) : '';
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', get_string("submissiondate", "assessment"), array('class'=>'boldformlabel'));
        echo html_writer::tag('td', userdate($submission->timecreated).' '.$late_remark);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('table');
    }
    
    function prepare_new_assessment_grade($markerid, $userid, $type=0, $teachermodified=false) {
        if ($type == 0) $assessmentidname = 'teacher';
        if ($type == 1) $assessmentidname = 'self';
        if ($type == 2) $assessmentidname = 'peer';
        $assessment_grade = new stdClass();
        $assessment_grade->assessmentid = $this->assessment->$assessmentidname;
        if ($this->assessment->workmode == 'group' || $this->assessment->workmode == 1) {
            $assessment_grade->userid = 0;
            $assessment_grade->groupid = $userid;
        } else {
            $assessment_grade->userid = $userid;
            $assessment_grade->groupid = 0;
        }
        $assessment_grade->marker = $markerid;
        $assessment_grade->grade = -1;
        $assessment_grade->type = $type;
        $assessment_grade->timemodified = '';
        $assessment_grade->comment = '';
        return $assessment_grade;
    }
    
    function process_feedback($type) {
        global $DB;
        if (!$feedback = data_submitted()) {      // No incoming data?
            return false;
        }
        
        $workmode = $this->assessment->workmode;
        $markerid = optional_param('markerid', 0, PARAM_INT);
        if (!isset($feedback->userid)) $feedback->userid = 0;
        
        ///For save and next, we need to know the userid to save, and the userid to go
        ///We use a new hidden field in the form, and set it to -1. If it's set, we use this
        ///as the userid to store
        if ((int)$feedback->saveuserid !== -1){
            if ($workmode == 'group') $feedback->groupid = $feedback->saveuserid;
            else $feedback->userid = $feedback->saveuserid;
        }
        
        $workerid = $workmode == 'group' ? $feedback->groupid : $feedback->userid;
        
        if (!empty($feedback->cancel) || !empty($feedback->cancelbutton)) {          // User hit cancel button
           return false;
        }
        
        $assessment_grade = $this->get_assessment_grade($markerid, $workerid, $type, true);  // Get or make one
        
        if (is_array($assessment_grade)) {
           $assessment_grade = array_shift($assessment_grade);
        }
        
        if ($assessment_grade) {
            // added on 2010-11-30, before this day "add" also record as "update"
            if ($assessment_grade->timemodified == '')
                $log_action = 'add';
            else
                $log_action = 'update';
            
            $assessment_grade->marker = $markerid;
            $assessment_grade->grade = $feedback->grade;
            $assessment_grade->type = $feedback->type;
            $assessment_grade->timemodified = time();
            $assessment_grade->itemid = $feedback->comment_editor['itemid'];
            $assessment_grade->comment = $feedback->comment_editor['text'];
            
            //save new files.
            $assessment_grade->comment = file_save_draft_area_files($assessment_grade->itemid, $this->context->id, 'mod_assessment', 'grade_comment', $assessment_grade->id, array('subdirs'=>0), $assessment_grade->comment);
            
            if ($this->rubric->id) {
               $this->rubric->process_assessment_grade($feedback, $assessment_grade->id);
            }
            
            if (!$DB->update_record('assessment_grades', $assessment_grade)) {
                return false;
            }
            
            $log_others = array($workmode.'id'=>$feedback->userid);
            $log_others['markergroupid'] = $markerid;
            $log_others['type'] = $type;
            $event_array = array(
                'objectid' => $this->assessment->id,
                'courseid' => $this->course->id,
                'context' => context_module::instance($this->cm->id),
                'other' => $log_others
            );
            if ($type == 0) {
                if ($log_action == 'add') $event = \mod_assessment\event\teacher_grade_added::create($event_array);
                if ($log_action == 'update') $event = \mod_assessment\event\teacher_grade_updated::create($event_array);
            } else if ($type == 1) {
                if ($log_action == 'add') $event = \mod_assessment\event\self_grade_added::create($event_array);
                if ($log_action == 'update') $event = \mod_assessment\event\self_grade_updated::create($event_array);
            } else if ($type == 2) {
                if ($log_action == 'add') $event = \mod_assessment\event\peer_grade_added::create($event_array);
                if ($log_action == 'update') $event = \mod_assessment\event\peer_grade_updated::create($event_array);
            }
            $event->add_record_snapshot('assessment_grades', $assessment_grade);
            $event->trigger();
            /*
            // get user/group for add_to_log
            if ($workmode == 'user') {
               $user = $DB->get_record('user', array('id'=>$workerid));
               $name = fullname($user);
            } else {
               $group = $DB->get_record('groups', array('id'=>$workerid));
               $name = $group->name;
            }
            
            $assessment_types = array('teacher','self','peer');
            $log_message = $log_action.' grades ('.$assessment_types[$type].')';
            $log_url = 'assessment_grades.php?'.
                        'id='.$this->assessment->id.'&'.
                        'markergroupid='.$markerid.'&'.
                        $workmode.'id='.$feedback->userid.'&'.
                        '&mode=single&type='.$type;
            add_to_log($this->course->id, 'assessment', $log_message, $log_url, $name, $this->cm->id);
            */
        }
        return $assessment_grade;
    }
    
    function user_outline($grade) {
        $result = new stdClass();
        $result->info = get_string('grade').': '.$grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }
    
    function user_complete($user, $grade=null) {
        global $OUTPUT;
        if ($grade) {
            echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        }

        if ($submission = $this->get_submission($user->id)) {
            print_assessment_user_submitted_files($submission, $this->assessment);
        } else {
            print_string("notsubmitted", "assessment");
        }
    }
} ////// End of the assessment_base class

class mod_assessment_grading_form extends moodleform {

    function definition() {
        global $OUTPUT, $USER;
        $mform =& $this->_form;
        
        $formattr = $mform->getAttributes();
        $formattr['id'] = 'submitform';
        $mform->setAttributes($formattr);
        // hidden params
        $mform->addElement('hidden', 'offset', ($this->_customdata->offset+1));
        $mform->setType('offset', PARAM_INT);
        if ($this->_customdata->workmode == 'user') {
            $mform->addElement('hidden', 'userid', $this->_customdata->usertograde->id);
            $mform->setType('userid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'groupid', $this->_customdata->usertograde->id);
            $mform->setType('groupid', PARAM_INT);
        }
        $mform->addElement('hidden', 'markerid', $this->_customdata->markerid);
        $mform->setType('markerid', PARAM_INT);
        $mform->addElement('hidden', 'id', $this->_customdata->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'mode', 'grade');
        $mform->setType('mode', PARAM_TEXT);
        $mform->addElement('hidden', 'type', $this->_customdata->type);
        $mform->setType('type', PARAM_TEXT);
        $mform->addElement('hidden', 'menuindex', "0");
        $mform->setType('menuindex', PARAM_INT);
        $mform->addElement('hidden', 'saveuserid', "-1");
        $mform->setType('saveuserid', PARAM_INT);
        $mform->addElement('hidden', 'markergroupid', $this->_customdata->markergroupid);
        $mform->setType('markergroupid', PARAM_INT);
        if ($this->_customdata->type == 2) {
            $mform->addElement('hidden', 'peergroupmode', $this->_customdata->peergroupmode);
            $mform->setType('peergroupmode', PARAM_INT);
        }
        
        $mform->addElement('static', 'markername', get_string('marker', 'assessment'), $this->_customdata->markername);
        if ($this->_customdata->assessment_grade->timemodified) {
            $mform->addElement('static', 'timemodified', get_string('timemodified', 'assessment'), userdate($this->_customdata->assessment_grade->timemodified));
        }
        
        // If this assessment has a rubric, then use that to grade
        if($this->_customdata->rubric_obj->id){
            $mform->addElement('static', 'rubric', get_string('rubric', 'assessment'), 
                $this->_customdata->rubric_obj->grade(
                    $this->_customdata->assessment, $this->_customdata->assessment_grade, $this->_customdata->usertograde->id, 
                    $this->_customdata->type, $this->_customdata->viewer, $this->_customdata->rubric_display_mode, 
                    $this->_customdata->markerid, 0, true
                )
            );
        } else {
            $grademenu = make_grades_menu($this->_customdata->assessment->grade);
            $mform->addElement('select', 'grade', get_string('grade'), $grademenu);
        }
        
        $editoroptions = array('maxfiles'=>EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$this->_customdata->context);
        if ($this->_customdata->type == 0) {
            if ($this->_customdata->viewer == 'teacher') {
                $mform->addElement('editor', 'comment_editor', get_string('comment', 'assessment'), null, $editoroptions);
                $mform->setType('comment_editor', PARAM_RAW); // to be cleaned before display
            } else {
                $comment = trim($this->_customdata->assessment_grade->comment) == '' ? 'N/A': format_text($this->_customdata->comment, FORMAT_HTML);
                $mform->addElement('static', 'comment', get_string('comment', 'assessment'), $comment);
            }
        } else if ($this->_customdata->type == 1 || $this->_customdata->type == 2) {
            if ($this->_customdata->viewer == 'teacher' || $this->_customdata->rubric_display_mode == 'teacherview' || $this->_customdata->markerid != $USER->id) {
                $comment = trim($this->_customdata->assessment_grade->comment) == '' ? 'N/A': format_text($this->_customdata->comment, FORMAT_HTML);
                $mform->addElement('static', 'comment', get_string('comment', 'assessment'), $comment);
            } else {
                $mform->addElement('editor', 'comment_editor', get_string('comment', 'assessment'), null, $editoroptions);
                $mform->setType('comment_editor', PARAM_RAW); // to be cleaned before display
            }
        }
        
        // buttons
        $this->add_action_buttons();
    }
    
    function add_action_buttons($cancel = true, $submitlabel = NULL) {
        global $PAGE;
        $mform =& $this->_form;
        $type = $this->_customdata->type;
        $viewer = $this->_customdata->viewer;
        $rubric_display_mode = $this->_customdata->rubric_display_mode;
        $nextid = $this->_customdata->nextid;
        $workmode = $this->_customdata->workmode;
        $usertograde = $this->_customdata->usertograde->id;
        
        $PAGE->requires->js_init_call('M.mod_assessment.init_next', array($nextid, $workmode, $usertograde));
        
        $buttonarray=array();
        if (($type == 0 && $viewer == 'teacher') || (($type == 1 || $type == 2) && $viewer == 'student' && $rubric_display_mode != 'teacherview'))
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'), array('onclick' => "setMenuIndex()"));
        //if there are more to be graded.
        if ($nextid && $type == 0 && $viewer == 'teacher') {
            //@todo: fix accessibility: javascript dependency not necessary
            $buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext'), array('onclick' => "saveNext()"));
            $buttonarray[] = &$mform->createElement('button', 'next', get_string('next'), array('onclick' => "setNext();"));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }
}

/**
 * Return grade for given user or all users.
 * Calculate the final grade from 1-3 kinds of assessment(s)
 *
 * @param int $assessmentid id of assessment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function assessment_get_user_grades($assessment, $userid=0) {
    global $DB, $USER;
    
    $usersql = $userid ? "AND u.id = $userid" : "";
    
    $assessmenttype = array();
    if (isset($assessment->teacher)) $assessmenttype[] = $assessment->teacher;
    if (isset($assessment->self)) $assessmenttype[] = $assessment->self;
    if (isset($assessment->peer)) $assessmenttype[] = $assessment->peer;
    $assessmenttype = !empty($assessmenttype) ? implode(',', $assessmenttype) : -1;
   
    // Get all userid who have been graded and can produce final grade
    $sql = "SELECT u.id 
            FROM {user} u, {assessment_grades} s
            WHERE u.id = s.userid AND s.assessmentid IN ($assessmenttype)
                $usersql GROUP BY u.id";
    $users_graded = array_keys($DB->get_records_sql($sql));
    
    $teacher_comment = '';
    if (isset($assessment->teacher)) {
        $sql = "SELECT u.id, s.comment AS feedback, s.marker AS usermodified
                FROM {user} u, {assessment_grades} s
                WHERE u.id = s.userid AND s.assessmentid = ".$assessment->teacher."
                    AND type = 0 $usersql";
        
        $teacher_comment = $DB->get_records_sql($sql);
    }
    
    $gradearr = array();
    if (!empty($users_graded)) {
        for ($i=0; $i<sizeof($users_graded); $i++) {
            $gradeobj = new object();
            $gradeobj->userid = $users_graded[$i];
            $gradeobj->rawgrade = assessment_calculate_user_final_grade($assessment, $users_graded[$i]);
            $gradeobj->feedback = '';
            $gradeobj->usermodified = $USER->id;
            if (!empty($teacher_comment)) {
                if (isset($teacher_comment[$users_graded[$i]])) {
                    $gradeobj->feedback = $teacher_comment[$users_graded[$i]]->feedback;
                    $gradeobj->usermodified = $teacher_comment[$users_graded[$i]]->usermodified;
                }
            }
            $gradeobj->datesubmitted = time();
            $gradearr[$users_graded[$i]] = $gradeobj;
        }
    }
    return $gradearr;
}

function assessment_calculate_user_final_grade($assessment, $userid) {
    global $DB;
    $final_grade = 0;
    if (isset($assessment->teacher)) {
        $params = array($assessment->teacher, 0, $userid);
        $sql = "SELECT u.id, s.grade 
                FROM {user} u, {assessment_grades} s
                WHERE u.id = s.userid AND s.assessmentid = ?
                    AND type = ? AND u.id = ?";
        $teacher_asssessment = $DB->get_record_sql($sql, $params);
        if ($teacher_asssessment && isset($assessment->teacherweight)) {
            $teacher_grade = $teacher_asssessment->grade;
            $final_grade += $teacher_grade*$assessment->teacherweight;
        }
    }
    
    if (isset($assessment->self)) {
        $params = array($assessment->self, 1, $userid);
        $sql = "SELECT u.id, s.grade 
                FROM {user} u, {assessment_grades} s
                WHERE u.id = s.userid AND s.assessmentid = ?
                    AND type = ? AND u.id = ?";
        $self_asssessment = $DB->get_record_sql($sql, $params);
        if ($self_asssessment && isset($assessment->selfweight)) {
            $self_grade = $self_asssessment->grade;
            $final_grade += $self_grade*$assessment->selfweight;
        }
    }
    
    if (isset($assessment->peer)) {
        $peer_grade = assessment_calculate_user_peer_average($assessment, $userid);
        if ($peer_grade && isset($assessment->peerweight)) $final_grade += $peer_grade*$assessment->peerweight;
    }
    
    return $final_grade;
}

function assessment_calculate_user_peer_average($assessment, $userid) {
    global $DB;
    $params = array($assessment->peer, 2, $userid);
    $sql = "SELECT u.id, s.grade, s.marker, s.timemodified
            FROM {user} u, {assessment_grades} s
            WHERE u.id = s.userid AND s.assessmentid = ?
                AND s.marker <> u.id AND type = ? AND u.id = ?";
    $peer_assessment = $DB->get_records_sql($sql, $params);
    
    $total_peer_grade = 0;
    if (!empty($peer_assessment)) {
        $total = 0;
        foreach ($peer_assessment as $peer_grade) {
            if ($peer_grade->grade >= 0) $total += $peer_grade->grade;
        }
        $total_peer_grade = round($total/sizeof($peer_assessment), 2);
    }
    
    return $total_peer_grade;
}

// Use add_instance() defined in the assessment_base class
function assessment_add_instance($assessment) {
    global $CFG;
    require_once($CFG->dirroot."/mod/assessment/lib.php");
    $ass = new assessment_base();
    return $ass->add_instance($assessment);
}

// Use update_instance() defined in the assessment_base class
function assessment_update_instance($assessment){
    global $CFG;
    require_once($CFG->dirroot."/mod/assessment/lib.php");
    $ass = new assessment_base();
    return $ass->update_instance($assessment);
}

// Use delete_instance() defined in the assessment_base class
function assessment_delete_instance($id){
    global $CFG, $DB;
    // Normal module deletion only required parameter $id, need to get $assessment object first
    if (! $assessment = $DB->get_record('assessment', array('id'=>$id))) {
        return false;
    }
    require_once($CFG->dirroot."/mod/assessment/lib.php");
    $ass = new assessment_base();
    return $ass->delete_instance($assessment);
}

function assessment_grade_item_delete($assessment) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    
    if (!isset($assessment->courseid)) {
        $assessment->courseid = $assessment->course;
    }
    
    return grade_update('mod/assessment', $assessment->courseid, 'mod', 'assessment', $assessment->id, 0, NULL, array('deleted'=>1));
}

/**
 * Returns an outline of a user interaction with an assessment
 *
 * This is done by calling the user_outline() method of the assessment class
 */
function assessment_user_outline($course, $user, $mod, $assessment) {
    global $CFG;
    
    require_once("$CFG->libdir/gradelib.php");
    $ass = new assessment_base($mod->id, $assessment, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'assessment', $assessment->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        return $ass->user_outline(reset($grades->items[0]->grades));
    } else {
        return null;
    }
}

/**
 * Prints the complete info about a user's interaction with an assessment
 *
 * This is done by calling the user_complete() method of the assessment type class
 */
function assessment_user_complete($course, $user, $mod, $assessment) {
    global $CFG;
    
    require_once("$CFG->libdir/gradelib.php");
    $ass = new assessment_base($mod->id, $assessment, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'assessment', $assessment->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }
    return $ass->user_complete($user, $grade);
}

/**
 * Print recent activity from all assessments in a given course
 *
 * This is used by the recent activity block
 */
function assessment_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false 
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * Nothing is needed here.
 */
function assessment_cron () {
    global $CFG;

    return true;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of assessment. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $assessmentid ID of an instance of this module
 * @return mixed boolean/array of students
 **/
function assessment_get_participants($assessmentid) {
    return false;
}

/**
 * This function returns if a scale is being used by one assessment
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $assessmentid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function assessment_scale_used ($assessmentid,$scaleid) {
    $return = false;
   
    return $return;
}

/**
 * Checks if scale is being used by any instance of assessment.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any assessment
 */
function assessment_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('assessment', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Create grade item for given assessment
 *
 * @param object $assessment object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function assessment_grade_item_update($assessment, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }
    
    if (!isset($assessment->courseid)) {
        $assessment->courseid = $assessment->course;
    }

    $params = array('itemname'=>$assessment->name, 'idnumber'=>$assessment->cmidnumber);

    if ($assessment->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $assessment->grade;
        $params['grademin']  = 0;
    } else if ($assessment->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$assessment->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/assessment', $assessment->courseid, 'mod', 'assessment', $assessment->id, 0, $grades, $params);
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function assessment_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        //case FEATURE_ADVANCED_GRADING:        return true;

        default: return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array
 */
function assessment_grading_areas_list() {
    $areas = array();
    $areas['teacherassessment'] = get_string('teacherassessment', 'mod_assessment');
    $areas['selfassessment'] = get_string('selfassessment', 'mod_assessment');
    $areas['peerassessment'] = get_string('peerassessment', 'mod_assessment');
    return $areas;
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other assessment functions go here.  Each of them must have a name that 
/// starts with assessment_
/// Remember (see note in first lines) that, if this section grows, it's HIGHLY
/// recommended to move all funcions below to a new "localib.php" file.

/**
 * Serves the assessment submission files. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function assessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = array('submission', 'submission_description', 'grade_comment', 'rubric_description', 'message');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }
    
    if (!$assessment = $DB->get_record('assessment', array('id'=>$cm->instance))) {
        return false;
    }
    
    if ($filearea == 'submission' || $filearea == 'submission_description') {
        $submissionid = (int)array_shift($args);
        if (!$submission = $DB->get_record('assessment_submissions', array('id'=>$submissionid))) {
            return false;
        }
        $itemid = $submissionid;
        
        // Make sure groups allow this user to see this file
        if ($submission->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
            if (!groups_group_exists($submission->groupid)) { // Can't find group
                return false;                           // Be safe and don't send it to anyone
            }

            if (!groups_is_member($submission->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                // do not send submission from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
                return false;
            }
        }
    }
    
    if ($filearea == 'grade_comment') {
        $gradeid = (int)array_shift($args);
        if (!$grade = $DB->get_record('assessment_grades', array('id'=>$gradeid))) {
            return false;
        }
        $itemid = $gradeid;
    }
    
    if ($filearea == 'rubric_description') {
        $rubricid = (int)array_shift($args);
        if (!$rubric = $DB->get_record('assessment_rubrics', array('id'=>$rubricid))) {
            return false;
        }
        $itemid = $rubricid;
    }
    
    if ($filearea == 'message') {
        $postid = (int)array_shift($args);
        if (!$rubric = $DB->get_record('assessment_posts', array('id'=>$postid))) {
            return false;
        }
        $itemid = $postid;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_assessment/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
}

/**
 * generate zip file from array of given files
 * @param array $filesforzipping - array of files to pass into archive_to_pathname
 * @return path of temp file - note this returned file does not have a .zip extension - it is a temp file.
 */
function assessment_pack_files($filesforzipping) {
    global $CFG;
    //create path for new zip file.
    $tempzip = tempnam($CFG->tempdir.'/', 'assessment_');
    //zip files
    $zipper = new zip_packer();
    if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
        return $tempzip;
    }
    return false;
}
