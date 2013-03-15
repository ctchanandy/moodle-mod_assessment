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

//  Edit and save a new post to a discussion

require_once('../../config.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$assessment = optional_param('assessment', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$url = new moodle_url('/mod/assessment/post.php');
$url->param('reply', $reply);
$url->param('assessment', $assessment);
$url->param('edit', $edit);
$url->param('delete', $delete);
$url->param('name', $name);
$url->param('confirm', $confirm);
$url->param('groupid', $groupid);

$PAGE->set_url($url);

require_once('locallib.php');

//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'assessment'=>$assessment, 'edit'=>$edit);

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($reply)) {      // User is writing a new reply

    if (! $parent = assessment_get_post_full($reply)) {
        print_error('incorrectparentpostid', 'assessment');
    }
    if (! $discussion = $DB->get_record("assessment_discussions", array("id"=>$parent->discussionid))) {
        print_error('postnotdiscussion', 'assessment');
    }
    if (! $assessment = $DB->get_record("assessment", array("id"=>$discussion->assessmentid))) {
        print_error('invalidid', 'assessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (! $cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
    $modcontext    = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (! assessment_user_can_post($assessment, $discussion, $USER, $cm, $course, $modcontext)) {
        if (has_capability('moodle/legacy:guest', $coursecontext, NULL, false)) {  // User is a guest here!
            $SESSION->wantsurl = $FULLME;
            $SESSION->enrolcancel = $_SERVER['HTTP_REFERER'];
            redirect($CFG->wwwroot.'/course/enrol.php?id='.$course->id, get_string('youneedtoenrol'));
        } else {
            print_error('nopostforum', 'forum');
        }
    }
    
    // For separate groups, let user post on other group members' discussion page
    if (groupmode($course, $cm) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid != -1 && !groups_is_member($discussion->groupid)) {
            print_error('nopostforum', 'forum');
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.
    $post = new object();
    $post->course      = $course->id;
    $post->assessment  = $assessment->id;
    $post->discussionid  = $parent->discussionid;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'forum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = assessment_get_post_full($edit)) {
        print_error("incorrectpostid", "assessment");
    }
    if ($post->parent) {
        if (! $parent = assessment_get_post_full($post->parent)) {
            print_error("incorrectparentpostid", "assessment");
        }
    }

    if (! $discussion = $DB->get_record("assessment_discussions", array("id"=>$post->discussionid))) {
        print_error('postnotdiscussion', 'assessment');
    }
    if (! $assessment = $DB->get_record("assessment", array("id"=>$discussion->assessmentid))) {
        print_error('invalidid', 'assessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (!$cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    }
    if (!$post->parent) {
        if ((time() - $post->timecreated) > $CFG->maxeditingtime) {
            print_error('maxtimehaspassed', 'forum', '' , format_time($CFG->maxeditingtime));
        }
    }
    if ($post->userid <> $USER->id) {
        print_error('cannoteditotherpost', 'assessment');
    }

    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->assessment  = $assessment->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;
    $post->messagetrust = trusttext_trusted($modcontext);
    $post->messageformat = $post->format;
    
    $post = trusttext_pre_edit($post, 'message', $modcontext);

} else if (!empty($delete)) {  // User is deleting a post

    if (! $post = assessment_get_post_full($delete)) {
        print_error("incorrectpostid", "assessment");
    }
    if (! $discussion = $DB->get_record("assessment_discussions", array("id"=>$post->discussionid))) {
        print_error('postnotdiscussion', 'assessment');
    }
    if (! $assessment = $DB->get_record("assessment", array("id"=>$discussion->assessmentid))) {
        print_error('invalidid', 'assessment');
    }
    if (!$cm = get_coursemodule_from_instance("assessment", $assessment->id, $assessment->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id'=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }

    require_login($course, false, $cm);
    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/assessment:deleteownpost', $modcontext))
            || has_capability('mod/forum:deleteanypost', $modcontext))) {
        print_error('cannotdeletepost', 'assessment');
    }

    $replycount = assessment_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        if ($replycount) {
            print_error("couldnotdeletereplies", "forum", assessment_go_back_to("discuss.php?d=$post->discussionid"));
        } else {
            if (!$post->parent) {  // post is a discussion topic as well, so delete discussion
                assessment_delete_discussion($discussion);
                add_to_log($assessment->course, "assessment", "delete discussion",
                           "view.php?id=$cm->id", "$assessment->id", $cm->id);
                redirect("view.php?id=$cm->id");

            } else if (assessment_delete_post($post, has_capability('mod/assessment:deleteanypost', $modcontext))) {
                $discussionurl = "discuss.php?d=$post->discussionid";
                add_to_log($assessment->course, "assessment", "delete post", $discussionurl, "$post->id", $cm->id);
                redirect(assessment_go_back_to($discussionurl));
            } else {
                print_error('errordeletingpost', 'assessment', '', $post->id);
            }
        }
    } else { // User just asked to delete something
        if ($replycount) {
            if (!has_capability('mod/assessment:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "forum", assessment_go_back_to("discuss.php?d=$post->discussionid"));
            }
            
            $PAGE->set_title(get_string("modulenameplural", "assessment"));
            $PAGE->set_heading($course->fullname);
            echo $OUTPUT->header();
            
            notice_yesno(get_string("deletesureplural", "forum", $replycount+1),
                         "post.php?delete=$delete&amp;confirm=$delete&amp;sesskey=".sesskey(),
                         $CFG->wwwroot.'/mod/assessment/discuss.php?d='.$post->discussionid.'#p'.$post->id);

            assessment_print_post($post, $discussion, $assessment, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $posts = assessment_get_all_discussion_posts($discussion->id, "timecreated ASC");
                assessment_print_posts_nested($course, $cm, $assessment, $discussion, $post, false, $posts);
            }
        } else {
            $PAGE->set_title(get_string("modulenameplural", "assessment"));
            $PAGE->set_heading($course->fullname);
            echo $OUTPUT->header();
            
            $continuelink = new moodle_url('/mod/assessment/post.php');
            $continuelink->param('delete', $delete);
            $continuelink->param('confirm', $delete);
            $continuelink->param('sesskey', sesskey());
            
            $cancellink = new moodle_url('/mod/assessment/discuss.php');
            $cancellink->param('d', $post->discussionid);
            $cancellink->set_anchor('p'.$post->id);
            
            echo $OUTPUT->confirm(get_string("deletesure", "forum", $replycount), $continuelink, $cancellink);
            assessment_print_post($post, $discussion, $assessment, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;
} else {
    print_error('nooperationspecified', 'assessment');
}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = get_context_instance(CONTEXT_COURSE, $assessment->course);
}

if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
//$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

require_login($course->id, false, $cm);

require_once('post_form.php');

$mform_post = new mod_assessment_post_form('post.php', array('course'=>$course, 'cm'=>$cm, 'coursecontext'=>$coursecontext, 'modcontext'=>$modcontext, 'assessment'=>$assessment, 'post'=>$post));

if ($fromform = $mform_post->get_data()) {
    require_login($course, false, $cm);

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/assessment/view.php?id=$cm->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }
    
    $fromform->discussionid = $fromform->discussion;

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';
        
        //fix for bug #4314
        if (!$realpost = $DB->get_record('assessment_posts', array('id'=>$fromform->id))) {
            $realpost = new stdClass;
            $realpost->userid = -1;
        }
        
        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && has_capability('mod/assessment:replypost', $modcontext)))) {
            print_error('cannotupdatepost', 'assessment');
        }
        
        $updatepost = $fromform; //realpost
        $updatepost->assessment = $assessment->id;
        if (!assessment_update_post($updatepost)) {
            print_error("couldnotupdate", "forum", $errordestination);
        }
        
        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }
        $message .= '<br />'.get_string("postupdated", "forum");
        
        $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        
        add_to_log($course->id, "assessment", "update post",
                "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);
        
        redirect(assessment_go_back_to("$discussionurl"), $message, $timemessage);
        
        exit;
    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->assessment = $assessment->id;
        if ($fromform->id = assessment_add_new_post($addpost, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            $message .= '<p>'.get_string("postaddedsuccess", "forum").'</p>';
            $message .= '<p>'.get_string("postaddedtimeleft", "forum", format_time($CFG->maxeditingtime)).'</p>';

            $discussionurl = "discuss.php?d=$discussion->id";
            
            add_to_log($course->id, "assessment", "add post", "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

            redirect(assessment_go_back_to("$discussionurl#p$fromform->id"), $message, $timemessage);

        } else {
            print_error("couldnotadd", "forum", $errordestination);
        }
        exit;
    }
}

// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $assessment are defined.  $discussion is for edit and reply only.

$cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id);

//$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

if ($post->discussionid) {
    if (! $toppost = $DB->get_record("assessment_posts", array('discussionid'=>$post->discussionid, 'parent'=>0), "parent, subject")) {
        print_error('notfindtopparent', 'assessment', '', $post->id);
    }
} else {
    $toppost->subject = get_string("addanewdiscussion", "forum");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new object;
    }
    $discussion->name = $assessment->name;
}

// Show the discussion name in the breadcrumbs.
$strdiscussionname = format_string($discussion->name).':';

$PAGE->set_context($modcontext);
$PAGE->set_pagelayout('assessmentpost');

$PAGE->navbar->add(get_string('discussionlist', 'assessment'), new moodle_url("discusslist.php?a=$assessment->id"));
if ($post->parent) {
    $PAGE->navbar->add(format_string($toppost->subject, true), new moodle_url("discuss.php?d=$discussion->id"));
    $PAGE->navbar->add(get_string('editing', 'forum'));
} else {
    $PAGE->navbar->add(format_string($toppost->subject, true));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// checkup
if (!empty($parent) && !assessment_user_can_see_post($assessment, $discussion, $post, null, $cm)) {
    print_error('cannotreplypost', 'assessment');
}

if (!empty($parent)) {
    if (! $discussion = $DB->get_record('assessment_discussions', array('id'=>$parent->discussionid))) {
        print_error('notpartofdiscussion', 'assessment');
    }

    assessment_print_post($parent, $discussion, $assessment, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if (assessment_user_can_see_discussion($assessment, $discussion, $modcontext)) {
            $posts = assessment_get_all_discussion_posts($discussion->id, "timecreated ASC");
            //assessment_print_posts_threaded($course, $cm, $forum, $discussion, $parent, 0, false, false, $forumtracked, $posts);
            assessment_print_posts_nested($course, $cm, $assessment, $discussion, $parent, false, $posts);
        }
    }
    $heading = get_string("yourreply", "forum");
} else {
    $assessment->intro = trim($assessment->intro);
    if (!empty($assessment->intro)) {
        print_box(format_text($assessment->intro), 'generalbox', 'intro');
    }
    $heading = get_string('yournewtopic', 'forum');
}

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data->date = userdate($post->modified);
    if ($post->format == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p>(<span class="edited">'.get_string('editedby', 'forum', $data).'</span>)</p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'forum', $data).')';
    }
}

//load data into form

// HACK ALERT: this is very wrong, the defaults should be always initialized before calling $mform->get_data() !!!
$mform_post->set_data(array('general'=>$heading,
                            'subject'=>$post->subject,
                            'message'=>$post->message,
                            'userid'=>$post->userid,
                            'parent'=>$post->parent,
                            'discussionid'=>$post->discussionid,
                            'course'=>$course->id) +
                            $page_params+
                            (isset($post->format) ? array('format'=>$post->format):array()) +
                            (isset($post->groupid) ? array('groupid'=>$post->groupid):array()) +
                            (isset($discussion->id)? array('discussion'=>$discussion->id):array())
                     );

$mform_post->display();

echo $OUTPUT->footer();
?>