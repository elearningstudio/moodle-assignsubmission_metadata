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
 * This file contains the definition for the library class for metadata submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_metadata
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * File area for online text submission assignment
 */
define('assignSUBMISSION_metadata_FILEAREA', 'submissions_metadata');

/**
 * library class for metadata submission plugin extending submission plugin base class
 *
 * @package assignsubmission_metadata
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_metadata extends assign_submission_plugin {

    /**
     * Get the name of the online text submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('details', 'assignsubmission_metadata');
    }

    /**
     * Get metadata submission information from the database
     *
     * @param  int $submissionid
     * @return mixed
     */
    private function get_metadata_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_metadata', array('submission' => $submissionid));
    }

    /**
     * Add form elements for settings
     *
     * @param mixed $submission can be null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return true if elements were added to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        $elements = array();

        $submissionid = $submission ? $submission->id : 0;

        if (!isset($data->metadata)) {
            $data->metadata = '';
        }

        if ($submission) {
            $metadatasubmission = $this->get_metadata_submission($submission->id);
            if ($metadatasubmission) {
                $data->title = $metadatasubmission->title;
                $data->medium = $metadatasubmission->medium;
                $data->artist = $metadatasubmission->artist;
                $data->artwork_size = $metadatasubmission->artwork_size;
            }
        }

        $mform->addElement('text', 'title', get_string('title', 'assignsubmission_metadata'));
        $mform->addElement('text', 'medium', get_string('medium', 'assignsubmission_metadata'));
        $mform->addElement('text', 'artist', get_string('artist', 'assignsubmission_metadata'));
        $mform->addElement('text', 'artwork_size', get_string('artwork_size', 'assignsubmission_metadata'));
        return true;
    }

    /**
     * Save data to the database
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;


        $metadatasubmission = $this->get_metadata_submission($submission->id);
        if ($metadatasubmission) {

            $metadatasubmission->metadata = $data->metadata;
            $metadatasubmission->title = $data->title;
            $metadatasubmission->artist = $data->artist;
            $metadatasubmission->medium = $data->medium;
            $metadatasubmission->artwork_size = $data->artwork_size;


            return $DB->update_record('assignsubmission_metadata', $metadatasubmission);
        } else {

            $metadatasubmission = new stdClass();
            $metadatasubmission->metadata = $data->metadata;
            $metadatasubmission->title = $data->title;
            $metadatasubmission->artist = $data->artist;
            $metadatasubmission->medium = $data->medium;
            $metadatasubmission->artwork_size = $data->artwork_size;


            $metadatasubmission->submission = $submission->id;
            $metadatasubmission->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignsubmission_metadata', $metadatasubmission) > 0;
        }
    }



    /**
     * Display metadata word count in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink - If the summary has been truncated set this to true
     * @return string
     */
    public function view_summary(stdClass $submission, & $showviewlink) {

        $metadatasubmission = $this->get_metadata_submission($submission->id);
        // always show the view link
        $showviewlink = true;

        if ($metadatasubmission) {
            $text = format_text($metadatasubmission->metadata, $metadatasubmission->onlineformat, array('context' => $this->assignment->get_context()));
            $shorttext = shorten_text($text, 140);
            if ($text != $shorttext) {
                return $shorttext . get_string('numwords', 'assignsubmission_metadata', count_words($text));
            } else {
                return $shorttext;
            }
        }
        return '';
    }

    /**
     * Produce a list of files suitable for export that represent this submission
     *
     * @param stdClass $submission - For this is the submission data
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission) {
        global $DB;
        $files = array();
        $metadatasubmission = $this->get_metadata_submission($submission->id);
        if ($metadatasubmission) {
            $user = $DB->get_record("user", array("id" => $submission->userid), 'id,username,firstname,lastname', MUST_EXIST);

            $prefix = clean_filename(fullname($user) . "_" . $submission->userid . "_");
            $finaltext = str_replace('@@PLUGINFILE@@/', $prefix, $metadatasubmission->metadata);
            $submissioncontent = "<html><body>" . format_text($finaltext, $metadatasubmission->onlineformat, array('context' => $this->assignment->get_context())) . "</body></html>";      //fetched from database

            $files[get_string('metadatafilename', 'assignsubmission_metadata')] = array($submissioncontent);

            $fs = get_file_storage();

            $fsfiles = $fs->get_area_files($this->assignment->get_context()->id, 'assignsubmission_metadata', assignSUBMISSION_metadata_FILEAREA, $submission->id, "timemodified", false);

            foreach ($fsfiles as $file) {
                $files[$file->get_filename()] = $file;
            }
        }

        return $files;
    }

    /**
     * Display the saved text content from the editor in the view table
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        $result = '';

        $metadatasubmission = $this->get_metadata_submission($submission->id);


        if ($metadatasubmission) {

            // render for portfolio API
            $result .= $this->assignment->render_editor_content(assignSUBMISSION_metadata_FILEAREA, $metadatasubmission->submission, $this->get_type(), 'metadata', 'assignsubmission_metadata');
        }

        return $result;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type old assignment subtype
     * @param int $version old assignment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        if ($type == 'online' && $version >= 2011112900) {
            return true;
        }
        return false;
    }

    /**
     * Upgrade the settings from the old assignment to the new plugin based one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment - the database for the old assignment instance
     * @param string $log record log events here
     * @return bool Was it a success?
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log) {
        // first upgrade settings (nothing to do)
        return true;
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment The data record for the old assignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext, stdClass $oldassignment, stdClass $oldsubmission, stdClass $submission, & $log) {
        global $DB;

        $metadatasubmission = new stdClass();
        $metadatasubmission->metadata = $oldsubmission->data1;
        $metadatasubmission->onlineformat = $oldsubmission->data2;

        $metadatasubmission->submission = $submission->id;
        $metadatasubmission->assignment = $this->assignment->get_instance()->id;

        if ($metadatasubmission->metadata === null) {
            $metadatasubmission->metadata = '';
        }

        if ($metadatasubmission->onlineformat === null) {
            $metadatasubmission->onlineformat = editors_get_preferred_format();
        }

        if (!$DB->insert_record('assignsubmission_metadata', $metadatasubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }

        // now copy the area files
        $this->assignment->copy_area_files_for_upgrade($oldcontext->id, 'mod_assignment', 'submission', $oldsubmission->id,
                // New file area
                $this->assignment->get_context()->id, 'assignsubmission_metadata', assignSUBMISSION_metadata_FILEAREA, $submission->id);
        return true;
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The new submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // format the info for each submission plugin add_to_log
        $metadatasubmission = $this->get_metadata_submission($submission->id);
        $metadataloginfo = '';
        $text = format_text($metadatasubmission->metadata, $metadatasubmission->onlineformat, array('context' => $this->assignment->get_context()));
        $metadataloginfo .= get_string('numwordsforlog', 'assignsubmission_metadata', count_words($text));

        return $metadataloginfo;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // will throw exception on failure
        $DB->delete_records('assignsubmission_metadata', array('assignment' => $this->assignment->get_instance()->id));

        return true;
    }

    /**
     * No text is set for this plugin
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        return $this->view($submission) == '';
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(assignSUBMISSION_metadata_FILEAREA => $this->get_name());
    }

}

