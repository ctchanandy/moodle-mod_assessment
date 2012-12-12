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
 * assessment module local library
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/assessment/lib.php');
require_once($CFG->libdir.'/filelib.php');

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assessment null means all assessment
 * @param int $userid specific user only, 0 mean all
 */
function assessment_update_grades($assessment=null, $userid=0, $courseid=0) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }
    
    if ($assessment != null) {
        // create assessment instance so that weights are loaded
        if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
            print_error('coursemisconf', 'assessment');
        }
        if (! $cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
            print_error('errorincorrectcmid', 'assessment');
        }
        $assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
        $assessment = $assessmentinstance->assessment;
        if ($grades = assessment_get_user_grades($assessment, $userid)) {
            foreach($grades as $k=>$v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
            assessment_grade_item_update($assessment, $grades);
        } else {
            assessment_grade_item_update($assessment);
        }
    } else {
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                FROM {assessment} a, {course_modules} cm, {modules} m
                WHERE m.name='assessment' AND m.id=cm.module AND cm.instance=a.id";
        if ($courseid) $sql .= " AND a.course = ".$courseid;
        if ($rs = $DB->get_records_sql($sql)) {
            foreach ($rs as $assessment) {
                if ($assessment->grade != 0) {
                    assessment_update_grades($assessment);
                } else {
                    assessment_grade_item_update($assessment);
                }
            }
        }
    }
}

function assessment_print_url($url, $submissionid, $count, $return=NULL, $edit=1) {
    global $CFG, $OUTPUT;
    
    $image = html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('f/web'), 'class'=>'icon'));
    
    $filelinkstr = html_writer::tag('span', $url, array('id'=>$submissionid.'_'.$count.'_linkstr'));
    $url_text = $image;
    if ($return != 'icon') $url_text .= ' '.$filelinkstr;
    $html = html_writer::link($url, $url_text, array('title'=>'', 'target'=>'_blank'));
    if ($edit) {
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'id'=>'url_'.$submissionid.'_'.$count, 'name'=>'url_'.$submissionid.'_'.$count, 'value'=>'1'));
        $html .= html_writer::link('#', get_string('delete', 'assessment'), array('id'=>'deletelink_'.$submissionid.'_'.$count, 'onclick'=>'deleteFile(\'url_'.$submissionid.'_'.$count.'\');return false;', 'style'=>'color:red;'));
    }
    $html .= html_writer::empty_tag('br');
    
    return $html;
}

function is_valid_url($url) {
    $url = @parse_url($url);
    if (!$url) {return false;}
    
    $url = array_map('trim', $url);
    $url['port'] = (!isset($url['port'])) ? 80 : (int)$url['port'];
    $path = (isset($url['path'])) ? $url['path'] : '';
    if ($path == '') {$path = '/';}
    $path .= (isset($url['query'])) ? "?$url[query]" : '';
    
    if (isset($url['host']) AND $url['host'] != gethostbyname($url['host'] )) {
        if (PHP_VERSION >= 5) {
            $headers = get_headers("$url[scheme]://$url[host]:$url[port]$path");
        } else {
            $fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 30);
            if (!$fp) {return false;}
            fputs($fp, "HEAD $path HTTP/1.1\r\nHost: $url[host]\r\n\r\n");
            $headers = fread ($fp, 128);
            fclose ( $fp );
        }
        $headers = (is_array($headers)) ? implode ("\n", $headers) : $headers;
        return (bool) preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
    }
    return false;
}

function assessment_print_file($file, $sid, $context, $type='html', $edit=1) {
    global $CFG, $OUTPUT;
    
    $return = '';
    $strattachment = get_string('attachment', 'assessment');
    $filename = $file->get_filename();
    $mimetype = $file->get_mimetype();
    $iconimage = html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url(file_mimetype_icon($mimetype)), 'class'=>'icon', 'title'=>$filename, 'alt'=>$filename));
    $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_assessment/submission/'.$sid.'/'.$filename);

    if ($type == 'html') {
        $return .= html_writer::link($path, $iconimage).' ';
        $return .= html_writer::link($path, s($filename));
        $return .= html_writer::empty_tag('br');
    } else if ($type == 'icon') {
        $return .= ' '.html_writer::link($path, $iconimage);
    } else if ($type == 'text') {
        $return .= "$strattachment ".s($filename).":\n$path\n";
    } else { //'returnimages'
        if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
            // Image attachments don't get printed as links
            $imagereturn .= html_writer::empty_tag('br');
            $imagereturn .= html_writer::empty_tag('img', array('src'=>$path, 'title'=>$filename, 'alt'=>$filename));
        } else {
            $return .= html_writer::link($path, $iconimage).' ';
            $return .= format_text(html_writer::link($path, s($filename)), FORMAT_HTML, array('context'=>$context));
            $return .= html_writer::empty_tag('br');
        }
    }
    
    if ($type !== 'separateimages') {
        return $return;

    } else {
        return array($return, $imagereturn);
    }
}

function assessment_count_graded($assessment, $type=0) {
    /// Returns the count of all graded submissions by ENROLLED students (even empty)
    global $CFG, $DB;
    
    if ($type == 0) $assessmentid = $DB->get_field('assessment_types', 'id', array('assessmentid'=>$assessment->id, 'type'=>$type));
    
    $cm = get_coursemodule_from_instance('assessment', $assessment->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    // this is all the users with this capability set, in this context or higher
    if ($users = get_users_by_capability($context, 'mod/assessment:submit', '', '', '', '', 0, '', false)) {
        foreach ($users as $user) {
            $array[] = $user->id;
        }
        
        $query_params = array('assessmentid'=>$assessmentid, 'timemodified'=>0);
        list($in_sql, $in_params) = $DB->get_in_or_equal($array, SQL_PARAMS_NAMED);
        $params = array_merge($in_params, $query_params);
        
        return $DB->count_records_select("assessment_grades", "assessmentid = :assessmentid AND timemodified > :timemodified AND userid $in_sql", $params);
    } else {
        return 0; // no users enroled in course
    }
}

function print_assessment_user_submitted_files_simple($submission, $assessment, $cm, $edit=0, $type='html') {
    global $OUTPUT;
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        return get_string('error');
    }
    
    $return = '';
    
    $fs = get_file_storage();
    
    $files = $fs->get_area_files($context->id, 'mod_assessment', 'submission', $submission->id, "timemodified", false);
    
    if ($files) {
         foreach ($files as $file) {
             $return .= assessment_print_file($file, $submission->id, $context, $type, $edit);
         }
    }
    
    if (!empty($submission->url)) {
        $urls = explode('||', $submission->url);
        for ($i=0; $i<sizeof($urls); $i++) {
            $return .= assessment_print_url($urls[$i], $submission->id, $i, $type, $edit);
        }
    }
    
    if (!empty($return)) {
        return $return;
    } else {
        return html_writer::tag('span', get_string('nofilesuploaded', 'assessment'));
    }
}

function print_assessment_user_submitted_files($submission, $assessment, $cm) {
    global $OUTPUT;
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        return get_string('error');
    }
    
    $fileoutput = '';
    
    $fs = get_file_storage();
    
    $files = $fs->get_area_files($context->id, 'mod_assessment', 'submission', $submission->id, "timemodified", false);
    
    if ($files) {
         $fileoutput = count($files).' '.get_string('filessubmitted', 'assessment').':<br />';
         foreach ($files as $file) {
             $fileoutput .= assessment_print_file($file, $submission->id, $context, 'html');
         }
    }
    
    $return = $OUTPUT->box_start();
    if (!empty($fileoutput)) {
        $return .= $fileoutput.'<br />';
    } else {
        $return .= get_string('nofilesuploaded', 'assessment');
    }
    $return .= $OUTPUT->box_end();
    
    return $return;
}

function assessment_add_new_post($post,&$message) {
    global $USER, $CFG, $DB;
    
    $discussion = $DB->get_record('assessment_discussions', array('id'=>$post->discussion));
    $assessment = $DB->get_record('assessment', array('id'=>$discussion->assessmentid));
    
    $post->message = $post->message['text'];
    $post->timecreated = $post->timemodified = time();
    $post->userid = $USER->id;
    $post->assessmentid = $assessment->id;     // speedup
    $post->course = $assessment->course; // speedup
    
    if (!$post->id = $DB->insert_record("assessment_posts", $post)) {
        return false;
    }
    
    // Update discussion modified date
    $DB->set_field("assessment_discussions", "timemodified", $post->timemodified, array("id"=>$post->discussion));
    
    assessment_tp_mark_post_read($post->userid, $post, $post->assessmentid);
    
    return $post->id;
}

function assessment_update_post($post) {
    global $USER, $CFG, $DB;
    
    $assessment = $DB->get_record('assessment', array('id'=>$post->assessment));
    
    $post->message = $post->message['text'];
    $post->timemodified = time();
    
    $updatediscussion = new stdClass();
    $updatediscussion->id = $post->discussionid;
    $updatediscussion->timemodified = $post->timemodified; // last modified tracking
    
    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $updatediscussion->name = $post->subject;
    }
    
    if (!$DB->update_record('assessment_discussions', $updatediscussion)) {
        return false;
    }
    
    assessment_tp_mark_post_read($post->userid, $post, $post->assessment);
    
    return $DB->update_record('assessment_posts', $post);
}

function assessment_tp_mark_post_read($userid, $post, $assessmentid) {
    if (!assessment_tp_is_post_old($post)) {
        return assessment_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

function assessment_tp_is_post_old($post, $time=null) {
    global $CFG;
    if (is_null($time)) {
        $time = time();
    }
    return ($post->timemodified < ($time - ($CFG->forum_oldpostdays * 24 * 3600)));
}

function assessment_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = 0; // this will keep unread record forever
    
    if (!$DB->record_exists('assessment_read', array('userid'=>$userid, 'postid'=>$postid))) {
        $params = array($postid, $cutoffdate);
        $sql = "INSERT INTO {assessment_read} (userid, postid, discussionid, assessmentid, firstread, lastread)
                SELECT $userid, p.id, p.discussionid, d.assessmentid, $now, $now
                    FROM {assessment_posts} p
                       JOIN {assessment_discussions} d ON d.id = p.discussionid
                WHERE p.id = ? AND p.timemodified >= ?";
    } else {
        $params = array($now, $userid, $postid);
        $sql = "UPDATE {assessment_read}
                SET lastread = ?
                WHERE userid = ? AND postid = ?";
    }
    return $DB->execute($sql, $params);
}

function assessment_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $assessmentid=-1) {
    global $DB;
    $select = "";
    $params = array();
    if ($userid > -1) {
        if ($select != "") $select .= " AND ";
        $params[] = $userid;
        $select .= "userid = ?";
    }
    if ($postid > -1) {
        if ($select != "") $select .= " AND ";
        $params[] = $postid;
        $select .= "postid = ?";
    }
    if ($discussionid > -1) {
        if ($select != "") $select .= " AND ";
        $params[] = $discussionid;
        $select .= "discussionid = ?";
    }
    if ($assessmentid > -1) {
        if ($select != "") $select .= " AND ";
        $params[] = $assessmentid;
        $select .= "assessmentid = ?";
    }
    if ($select == "") {
        return false;
    }
    else {
        return $DB->delete_records_select('assessment_read', $select, $params);
    }
}

function assessment_tp_count_discussion_posts($userid, $discussionid) {
    global $DB;
    return $DB->count_records("assessment_posts", array("discussionid"=>$discussionid, "parent"=>0));
}

function assessment_tp_count_discussion_unread_posts($userid, $discussionid) {
    global $DB;
    
    $cutoffdate = 0; // keep unread records forever
    
    $params = array($userid, $discussionid, $cutoffdate);
    $sql = "SELECT COUNT(p.id) 
            FROM {assessment_posts} p 
            LEFT JOIN {assessment_read} r ON r.postid = p.id AND r.userid = ? 
            WHERE p.discussionid = ? AND p.timemodified >= ? AND r.id is NULL";
    return $DB->count_records_sql($sql, $params);
}

function assessment_tp_mark_discussion_read($user, $discussionid) {
    global $DB;
    
    $cutoffdate = 0; // keep unread records forever
    
    $params = array($user->id, $discussionid, $cutoffdate);
    $sql = "SELECT p.id 
            FROM {assessment_posts} p 
                LEFT JOIN {assessment_read} r ON (r.postid = p.id AND r.userid = ?) 
            WHERE p.discussionid = ? 
                AND p.timemodified >= ? AND r.id is NULL";
    
    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return assessment_tp_mark_posts_read($user, $postids);
    }
    
    return true;
}

function assessment_tp_mark_posts_read($user, $postids) {
    global $DB;

    $status = true;
    $now = time();
    $cutoffdate = 0; // keep unread records forever

    if (empty($postids)) {
        return true;
    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = assessment_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }
    
    $query_params = array('userid'=>$user->id);
    list($in_sql, $in_params) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
    $params = array_merge($in_params, $query_params);
    
    $sql = "SELECT id
            FROM {assessment_read}
            WHERE userid = :userid AND postid $in_sql";
    if ($existing = $DB->get_records_sql($sql, $params)) {
        $existing = array_keys($existing);
    } else {
        $existing = array();
    }
    
    $new = array_diff($postids, $existing);
    
    $params = array();
    if ($new) {
        $query_params = array('timemodified'=>$cutoffdate);
        list($in_sql, $in_params) = $DB->get_in_or_equal($new, SQL_PARAMS_NAMED);
        $params = array_merge($in_params, $query_params);
        
        $sql = "INSERT INTO {assessment_read} (userid, postid, discussionid, assessmentid, firstread, lastread)
                SELECT $user->id, p.id, p.discussionid, d.assessmentid, $now, $now
                FROM {assessment_posts} p
                    JOIN {assessment_discussions} d ON d.id = p.discussionid
                    JOIN {assessment} a ON a.id = d.assessmentid
                WHERE p.id $in_sql AND p.timemodified >= :timemodified";
        $status = $DB->execute($sql, $params) && $status;
    }
    
    if ($existing) {
        $query_params = array('lastread'=>$now, 'userid'=>$user->id);
        list($in_sql, $in_params) = $DB->get_in_or_equal($existing, SQL_PARAMS_NAMED);
        $params = array_merge($in_params, $query_params);
        
        $sql = "UPDATE {assessment_read}
                SET lastread = :lastread
                WHERE userid = :userid AND postid $in_sql";
        $status = $DB->execute($sql, $params) && $status;
    }
    
    return $status;
}

function assessment_tp_is_post_read($userid, $post) {
    global $DB;
    return (assessment_tp_is_post_old($post) ||
           $DB->record_exists("assessment_read", array("userid"=>$userid, "postid"=>$post->id)));
}

function assessment_user_can_post($assessment, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }
    
    // shortcut - guest and not-logged-in users cannot post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }
    
    if (!isset($discussion->groupid)) {
        debugging('Incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }
    
    if (!$cm) {
        debugging('Missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $assessment->course)) {
            print_error('errorincorrectcmid', 'assessment');
        }
    }
    
    if (!$course) {
        debugging('Missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id'=>$assessment->course))) {
            print_error('coursemisconf', 'assessment');
        }
    }
    
    if (!$context) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    }
    
    // normal users with temporary guest access can not post
    if (isguestuser($user->id)) {
        return false;
    }
    
    $capname = 'mod/assessment:replypost';
    
    if (!has_capability($capname, $context, $user->id, false)) {
        return false;
    }
    
    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }
    
    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }
    
    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);
    }
}

function assessment_print_discussion($course, $cm, $assessment, $discussion, $post, $canreply=NULL) {
    global $USER, $CFG;

    if (!empty($USER->id)) {
        $ownpost = ($USER->id == $post->userid);
    } else {
        $ownpost = false;
    }
    if ($canreply === NULL) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $reply = assessment_user_can_post($assessment, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for forum functions
    $cm->cache = new object();
    $cm->cache->groups = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    $sort = "p.timecreated ASC";

    $posts = assessment_get_all_discussion_posts($discussion->id, $sort);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    $post->assessmentid = $assessment->id;   // Add the assessment id to the post object, later used by assessment_print_post

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);

    assessment_print_post($post, $discussion, $assessment, $cm, $course, $ownpost, $reply, false, '', '', $postread, true);

    assessment_print_posts_nested($course, $cm, $assessment, $discussion, $post, $reply, $posts);
}

function assessment_print_post($post, $discussion, $assessment, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $post_read=null, $dummyifcantsee=true, $istracked=null) {
    global $USER, $CFG, $OUTPUT;

    // String cache
    static $str;
    
    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $post->course = $course->id;
    $post->assessmentid = $assessment->id;

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/assessment:viewdiscussion'] = has_capability('mod/assessment:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames'] = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/assessment:deleteownpost'] = has_capability('mod/assessment:deleteownpost', $modcontext);
        $cm->cache->caps['mod/assessment:deleteanypost'] = has_capability('mod/assessment:deleteanypost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = coursemodule_visible_for_user($cm);
    }

    if (!assessment_user_can_see_post($assessment, $discussion, $post, NULL, $cm)) {
        if (!$dummyifcantsee) {
            return;
        }
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix'));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('forumsubjecthidden','forum'), array('class'=>'subject')); // Subject
        $output .= html_writer::tag('div', get_string('forumauthorhidden','forum'), array('class'=>'author')); // author
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left side')); // Groups
        $output .= html_writer::tag('div', get_string('forumbodyhidden','forum'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // forumpost
        
        echo $output;
        return;
    }
    
    if (empty($str)) {
        $str = new stdClass;
        $str->edit         = get_string('edit', 'forum');
        $str->delete       = get_string('delete', 'forum');
        $str->reply        = get_string('reply', 'forum');
        $str->parent       = get_string('parent', 'forum');
        $str->pruneheading = get_string('pruneheading', 'forum');
        $str->prune        = get_string('prune', 'forum');
        $str->displaymode     = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);
        $str->markread     = get_string('markread', 'forum');
        $str->markunread   = get_string('markunread', 'forum');
    }
    
    $read_style = '';
    
    // ignore trackign status if not tracked or tracked param missing
    if (is_null($post_read)) {
        debugging('fetching post_read info');
        $post_read = assessment_tp_is_post_read($USER->id, $post);
    }
    
    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuser->id        = $post->userid;
    $postuser->firstname = $post->firstname;
    $postuser->lastname  = $post->lastname;
    $postuser->email     = $post->email;
    $postuser->imagealt  = $post->imagealt;
    $postuser->picture   = $post->picture;
    // Some handy things for later on
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));
    
    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }
    
    // Prepare an array of commands
    $commands = array();
    
    $discussionlink = new moodle_url('/mod/assessment/discuss.php', array('d'=>$post->discussionid));
    
    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($CFG->forum_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$post_read) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == FORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }
    
    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->timecreated;
    if ($ownpost && $age < $CFG->maxeditingtime) {
        $commands[] = array('url'=>new moodle_url('/mod/assessment/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/assessment:deleteownpost']) || $cm->cache->caps['mod/assessment:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/assessment/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if ($reply) {
        $commands[] = array('url'=>new moodle_url('/mod/assessment/post.php', array('reply'=>$post->id)), 'text'=>$str->reply);
    }
    
    // Begin output

    $output  = '';
    
    if ($post_read) {
        $forumpostclass = ' read';
    } else {
        $forumpostclass = ' unread';
        $output .= html_writer::tag('a', '', array('name'=>'unread'));
    }
    
    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }
    
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix'.$forumpostclass.$topicclass));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id));
    $output .= html_writer::end_tag('div');
    
    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));
    
    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject'));
    
    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->timemodified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'forum', $by), array('class'=>'author'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row
    
    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));
    
    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));
    
    $options = new stdClass;
    $options->para    = false;
    $options->trusted = true;
    $options->context = $modcontext;
    
    // Prepare whole post
    $postclass    = 'fullpost';
    $postcontent  = format_text($post->message, $post->format, $options, $course->id);
    if (!empty($highlight)) {
        $postcontent = highlight($highlight, $postcontent);
    }
    
    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row
    
    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));
    
    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));
    
    // Output link to post if required
    if ($link) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'forum', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'forum', $post->replies);
        }
        
        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'forum'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }
    
    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // forumpost
    
    // Mark the forum post as read if required
    if ($istracked && !$CFG->forum_usermarksread && !$postisread) {
        assessment_tp_mark_post_read($USER->id, $post, $forum->id);
    }
    
    echo $output;
    return;
}

function assessment_print_posts_nested($course, &$cm, $assessment, $discussion, $parent, $reply, $posts) {
    global $USER, $OUTPUT;

    $link  = false;
    $output = '';
    
    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {
            echo html_writer::start_tag('div', array('class'=>'indent'));
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            assessment_print_post($post, $discussion, $assessment, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true);
            assessment_print_posts_nested($course, $cm, $assessment, $discussion, $post, $reply, $posts);
            
            echo html_writer::end_tag('div'); // content
        }
    }
    return true;
}

function assessment_get_all_discussion_posts($discussionid, $sort) {
    global $USER, $DB;

    $tr_sel  = "";
    $tr_join = "";
    
    $now = time();
    $params = array($USER->id, $discussionid);
    $tr_sel  = ", ar.id AS postread";
    $tr_join = "LEFT JOIN {assessment_read} ar ON (ar.postid = p.id AND ar.userid = ?)";
    
    if (!$posts = $DB->get_records_sql($sql="SELECT p.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt $tr_sel
                                        FROM {assessment_posts} p
                                            LEFT JOIN {user} u ON p.userid = u.id
                                            $tr_join
                                        WHERE p.discussionid = $discussionid
                                        ORDER BY $sort", $params)) {
        return array();
    }
    
    foreach ($posts as $pid=>$p) {
        if (assessment_tp_is_post_old($p)) {
             $posts[$pid]->postread = true;
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }
    
    return $posts;
}

function assessment_get_post_full($postid) {
    global $DB;
    
    $params = array($postid);
    $sql = "SELECT p.*, d.assessmentid, u.firstname, u.lastname, u.email, u.picture, u.imagealt
            FROM {assessment_posts} p
                JOIN {assessment_discussions} d ON p.discussionid = d.id
                LEFT JOIN {user} u ON p.userid = u.id
            WHERE p.id = ?";
    
    $return = $DB->get_record_sql($sql, $params);
    
    return $return;
}

function assessment_count_replies($post, $children=true) {
    global $DB;
    
    $count = 0;
    if ($children) {
        if ($childposts = $DB->get_records('assessment_posts', array('parent'=>$post->id))) {
            foreach ($childposts as $childpost) {
                $count ++;                   // For this child
                $count += assessment_count_replies($childpost, true);
            }
        }
    } else {
        $count += $DB->count_records('assessment_posts', array('parent'=>$post->id));
    }
    
    return $count;
}

function assessment_add_discussion($discussion, $message) {
    global $USER, $CFG, $DB;
    
    $timenow = time();
    
    // The first post is stored as a real post, and linked to from the discuss entry.
    $post = new stdClass();
    $post->discussionid = 0;
    $post->parent = 0;
    $post->userid = $USER->id;
    $post->timecreated = $timenow;
    $post->timemodified = $timenow;
    $post->subject = $discussion->name;
    $post->message = $discussion->intro;
    $post->assessmentid = $discussion->assessmentid; // speedup
    $post->format = $discussion->format;
    
    if (!$post->id = $DB->insert_record("assessment_posts", $post) ) {
        return 0;
    }
    
    $discussion->firstpost = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->userid = $USER->id;
    
    if (!$post->discussionid = $DB->insert_record("assessment_discussions", $discussion) ) {
        $DB->delete_records("assessment_posts", array("id"=>$post->id));
        return 0;
    }
    
    if (!$DB->set_field("assessment_posts", "discussionid", $post->discussionid, array("id"=>$post->id))) {
        $DB->delete_records("assessment_posts", array("id"=>$post->id));
        $DB->delete_records("assessment_discussions", array("id"=>$post->discussionid));
        return 0;
    }
    
    assessment_tp_mark_post_read($post->userid, $post, $post->assessmentid);
    
    return $post->discussionid;
}

function assessment_delete_discussion($discussion, $fulldelete=false) {
    global $DB;
    
    $result = true;
    
    if ($posts = $DB->get_records("assessment_posts", array("discussionid"=>$discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->assessment  = $discussion->assessmentid;
            if (! assessment_delete_post($post, $fulldelete)) {
                $result = false;
            }
        }
    }

    assessment_tp_delete_read_records(-1, -1, $discussion->id);

    if (!$DB->delete_records("assessment_discussions", array("id"=>$discussion->id))) {
        $result = false;
    }

    return $result;
}

function assessment_delete_post($post, $children=false) {
    global $DB;
    if ($childposts = $DB->get_records('assessment_posts', array('parent'=>$post->id))) {
        if ($children) {
            foreach ($childposts as $childpost) {
                assessment_delete_post($childpost, true);
            }
        } else {
            return false;
        }
    }
    if ($DB->delete_records("assessment_posts", array("id"=>$post->id))) {
        assessment_tp_delete_read_records(-1, $post->id);
        // Just in case we are deleting the last post
        assessment_discussion_update_last_post($post->discussionid);
        return true;
    }
    return false;
}

function assessment_discussion_update_last_post($discussionid) {
    global $DB;
    
    // Check the given discussion exists
    if (!$DB->record_exists('assessment_discussions', array('id'=>$discussionid))) {
        return false;
    }
    
    // Use SQL to find the last post for this discussion
    $params = array($discussionid);
    $sql = "SELECT id, userid, timemodified 
            FROM {assessment_posts} 
            WHERE discussionid = ? 
            ORDER BY timemodified DESC";

    // Lets go find the last post
    if (($lastpost = $DB->get_record_sql($sql, $params))) {
        $discussionobject = new stdClass;
        $discussionobject->id = $discussionid;
        $discussionobject->timemodified = $lastpost->timemodified;
        if ($DB->update_record('assessment_discussions', $discussionobject)) {
            return $lastpost->id;
        }
    }

    // To get here either we couldn't find a post for the discussion (weird)
    // or we couldn't update the discussion record (weird x2)
    return false;
}

function assessment_user_can_see_discussion($assessment, $discussion, $context, $user=NULL) {
    global $USER, $DB;
    
    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($assessment)) {
        debugging('missing full assessment', DEBUG_DEVELOPER);
        if (!$assessment = $DB->get_record('assessment', array('id'=>$assessment))) {
            return false;
        }
    }
    
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('assessment_discussions', array('id'=>$discussion))) {
            return false;
        }
    }
    
    if (!has_capability('mod/assessment:viewdiscussion', $context)) {
        return false;
    }
    
    return true;
}

function assessment_user_can_see_post($assessment, $discussion, $post, $user=NULL, $cm=NULL) {
    global $USER, $DB;

    // retrieve objects (yuk)
    if (is_numeric($assessment)) {
        debugging('missing full forum', DEBUG_DEVELOPER);
        if (!$assessment = $DB->get_record('forum', array('id'=>$assessment))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('assessment_discussions', array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('assessment_posts', array('id'=>$post))) {
            return false;
        }
    }
    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forum', $assessment->id, $assessment->course)) {
            print_error('errorincorrectcmid', 'assessment');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    if (isset($cm->cache->caps['mod/assessment:viewdiscussion'])) {
        if (!$cm->cache->caps['mod/assessment:viewdiscussion']) {
            return false;
        }
    } else {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        if (!has_capability('mod/assessment:viewdiscussion', $modcontext, $user->id)) {
            return false;
        }
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!coursemodule_visible_for_user($cm, $user->id)) {
            return false;
        }
    }

    return true;
}

function assessment_user_can_view_post($post, $course, $cm, $assessment, $discussion, $user=NULL){
    global $USER;
    
    if (!$user){
        $user = $USER;
    }

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (!has_capability('mod/assessment:viewdiscussion', $modcontext)) {
        return false;
    }

// If it's a grouped discussion, make sure the user is a member
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $modcontext);
        }
    }
    return true;
}

function assessment_go_back_to($default) {
    return $default;
}

function assessment_get_discussions($cm, $forumsort="d.timemodified DESC", $page=-1, $perpage=0) {
    global $DB;
    
    $modcontext = null;
    $now = round(time(), -2);

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!has_capability('mod/assessment:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    $limitfrom = 0;
    $limitnum  = 0;

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "";
            }
        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (empty($forumsort)) {
        $forumsort = "d.timemodified DESC";
    }
    
    $postdata = "p.id,p.subject,p.timemodified,p.discussionid,p.userid";

    $umfields = "";
    $umtable  = "";

    $sql = "SELECT $postdata, d.name, d.timemodified, d.groupid,
                u.firstname, u.lastname, u.email, u.picture, u.imagealt $umfields
            FROM {assessment_discussions} d
                JOIN {assessment_posts} p ON p.discussionid = d.id
                JOIN {user} u ON p.userid = u.id
                $umtable
            WHERE d.assessmentid = ".$cm->instance." AND p.parent = 0
                $groupselect
            ORDER BY $forumsort";
    return $DB->get_records_sql($sql, null, $limitfrom, $limitnum);
}

function assessment_count_discussion_replies($assessmentid, $forumsort="", $limit=-1, $page=-1, $perpage=0) {
    global $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($forumsort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $forumsort";
        $groupby = ", ".strtolower($forumsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $forumsort == "") {
        $params = array($assessmentid);
        $sql = "SELECT p.discussionid, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                FROM {assessment_posts} p
                    JOIN {assessment_discussions} d ON p.discussionid = d.id
                WHERE p.parent > 0 AND d.assessmentid = ?
                GROUP BY p.discussionid";
        return $DB->get_records_sql($sql, $params);

    } else {
        $params = array($assessmentid);
        $sql = "SELECT p.discussionid, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                FROM {assessment_posts} p
                       JOIN {assessment_discussions} d ON p.discussionid = d.id
                WHERE d.assessmentid = ?
                GROUP BY p.discussionid $groupby
                $orderby";
        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }
}

function assessment_get_discussions_unread($cm) {
    global $CFG, $USER, $DB;

    $now = round(time(), -2);

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "";
            }
        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }
    
    $cutoffdate = 0;
    
    $params = array($USER->id, $cm->instance, $cutoffdate);
    $sql = "SELECT d.id, COUNT(p.id) AS unread
            FROM {assessment_discussions} d
                JOIN {assessment_posts} p ON p.discussionid = d.id
                LEFT JOIN {assessment_read} r ON (r.postid = p.id AND r.userid = ?)
            WHERE d.assessmentid = ?
                AND p.timemodified >= ? AND r.id is NULL
                $groupselect
            GROUP BY d.id";
    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

function assessment_print_discussion_header(&$post, $assessment, $group=-1, $datestring="", $canviewparticipants=true, $modcontext=NULL) {

    global $USER, $CFG, $OUTPUT;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $assessment->course)) {
            print_error('errorincorrectcmid', 'assessment');
        }
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'forum');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    echo "\n";
    echo '<tr class="discussion r'.$rowcount.'">';

    // Topic
    echo '<td class="topic starter">';
    echo '<a href="'.$CFG->wwwroot.'/mod/assessment/discuss.php?d='.$post->discussionid.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new object;
    $postuser->id = $post->userid;
    $postuser->firstname = $post->firstname;
    $postuser->lastname = $post->lastname;
    $postuser->email = $post->email;
    $postuser->imagealt = $post->imagealt;
    $postuser->picture = $post->picture;

    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$assessment->course));
    echo "</td>\n";

    // User name
    $fullname = fullname($post, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$assessment->course.'">'.$fullname.'</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            print_group_picture($group, $assessment->course, false, false, true);
        } else if (isset($group->id)) {
            if($canviewparticipants) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$assessment->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/assessment:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/assessment/discuss.php?d='.$post->discussionid.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        echo '<td class="replies">';
        if ($post->unread > 0) {
            echo '<span class="unread">';
            echo '<a href="'.$CFG->wwwroot.'/mod/assessment/discuss.php?d='.$post->discussionid.'#unread">';
            echo $post->unread;
            echo '</a>';
            echo '</span>';
        } else {
            echo '<span class="read">';
            echo $post->unread;
            echo '</span>';
        }
        echo "</td>\n";
    }

    echo '<td class="lastpost">';
    $usedate = $post->timemodified;
    $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    
    echo '<a href="'.$CFG->wwwroot.'/mod/assessment/discuss.php?d='.$post->discussionid.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    echo "</tr>\n";

}

function assessment_print_discussions($course, $assessment, $currentgroup=-1, $groupmode=-1, $cm=NULL) {
    global $CFG, $USER;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $assessment->course)) {
            print_error('errorincorrectcmid', 'assessment');
        }
    }
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $sort = "d.timemodified DESC";

    // all discussions - backwards compatibility
    $page    = -1;
    $perpage = 0;

// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

// Get all the recent discussions we're allowed to see

    if (! $discussions = assessment_get_discussions($cm, $sort, $page, $perpage) ) {
        echo '<div class="forumnodiscuss">';
        echo '('.get_string('nodiscussions', 'forum').')';
        echo "</div>\n";
        return;
    }

// If we want paging
    $replies = assessment_count_discussion_replies($assessment->id);
    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);
    $strdatestring = get_string('strftimerecentfull');

    // Check if the forum is tracked.
    $forumtracked = true;

    $unreads = assessment_get_discussions_unread($cm);
    
    echo '<table cellspacing="0" class="forumheaderlist">';
    echo '<thead>';
    echo '<tr>';
    echo '<th class="header topic" scope="col">'.get_string('discussion', 'forum').'</th>';
    echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'forum').'</th>';
    if ($groupmode > 0) {
        echo '<th class="header group" scope="col">'.get_string('group').'</th>';
    }
    if (has_capability('mod/assessment:viewdiscussion', $context)) {
        echo '<th class="header replies" scope="col">'.get_string('replies', 'forum').'</th>';
        // If the forum can be tracked, display the unread column.
        echo '<th class="header replies" scope="col">'.get_string('unread', 'forum');
        echo '</th>';
    }
    echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'forum').'</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($discussions as $discussion) {
        if (!empty($replies[$discussion->discussionid])) {
            $discussion->replies = $replies[$discussion->discussionid]->replies;
            $discussion->lastpostid = $replies[$discussion->discussionid]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        if (empty($unreads[$discussion->discussionid])) {
            $discussion->unread = 0;
        } else {
            $discussion->unread = $unreads[$discussion->discussionid];
        }

        if (!empty($USER->id)) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;
        
        if ($groupmode > 0) {
            if (isset($groups[$discussion->groupid])) {
                $group = $groups[$discussion->groupid];
            } else {
                $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
            }
        } else {
            $group = -1;
        }
        
        assessment_print_discussion_header($discussion, $assessment, $group, $strdatestring, $canviewparticipants, $context);
    }

    echo '</tbody>';
    echo '</table>';
}

function assessment_check_is_ilap_theme() {
    global $CFG, $COURSE;
    if ($COURSE->theme == 'iLAP_admin')
        return true;
    if ($CFG->theme == 'iLAP_admin') {
        if (!$COURSE->theme) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
?>