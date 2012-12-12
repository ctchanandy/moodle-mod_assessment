<?php

// This file is part of Moodle - http://moodle.org/
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
 * Definition of log events
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'assessment', 'action'=>'view', 'mtable'=>'assessment', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'view all', 'mtable'=>'assessment', 'field'=>'course'),
    array('module'=>'assessment', 'action'=>'view discussion', 'mtable'=>'assessment_discussions', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'view discussion list', 'mtable'=>'assessment', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'view overview page', 'mtable'=>'assessment', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'view grade student', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'view submission', 'mtable'=>'assessment_submissions', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'view submission form', 'mtable'=>'assessment_submissions', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'add', 'mtable'=>'assessment', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'add grades (teacher)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'add grades (self)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'add grades (peer)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'add post', 'mtable'=>'assessment_posts', 'field'=>'subject'),
    array('module'=>'assessment', 'action'=>'add submission', 'mtable'=>'assessment_submissions', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'update', 'mtable'=>'assessment', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'update grades (teacher)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'update grades (self)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'update grades (peer)', 'mtable'=>'assessment_grades', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'update post', 'mtable'=>'assessment_posts', 'field'=>'subject'),
    array('module'=>'assessment', 'action'=>'update submission', 'mtable'=>'assessment_submissions', 'field'=>'userid'),
    array('module'=>'assessment', 'action'=>'delete discussion', 'mtable'=>'assessment_discussions', 'field'=>'name'),
    array('module'=>'assessment', 'action'=>'delete post', 'mtable'=>'assessment_posts', 'field'=>'subject'),
);