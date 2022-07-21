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
 * Groups database sync plugin.
 *
 * This plugin synchronises course groups and group membership with external database table.
 *
 * @package   tool_groupsdatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');

/**
 * groupsdatabase tool class
 *
 * @package   tool_groupsdatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_groupsdatabase_sync {

    /**
     * @var The idnumber used for groupings created by this plugin.
     */
    const GLOBAL_GROUPING_IDNUMBER = 'tool_groupsdatabase';

    /**
     * @var stdClass config for this plugin
     */
    protected $config;

    /**
     * @var array Courses in the external database.
     */
    protected $coursementions = [];

    /**
     * @var array The current groups.
     */
    protected $groupmembers = [];

    /**
     * Performs a full sync with external database.
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 4 db read failure
     */
    public function sync(progress_trace $trace) {
        global $DB;

        $this->config = get_config('tool_groupsdatabase');

        // Check if it is configured.
        if (empty($this->config->dbtype) || empty($this->config->dbhost)) {
            $trace->finished();
            return 1;
        }

        $trace->output('Starting groups synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        // Set some vars for better code readability.
        $localcoursefield = trim($this->config->localcoursefield);
        $localuserfield   = trim($this->config->localuserfield);
        $groupstable      = trim($this->config->remotegroupstable);
        $coursefield      = strtolower(trim($this->config->remotecoursefield));
        $groupcodefield   = strtolower(trim($this->config->remotegroupcodefield));
        $groupnamefield   = strtolower(trim($this->config->remotegroupnamefield));
        $userfield        = strtolower(trim($this->config->remoteuserfield));
        $removeaction     = trim($this->config->removeaction); // 0 = remove, 1 = keep.

        if (empty($coursefield) || empty($groupcodefield) || empty($groupnamefield) ||
            empty($localuserfield) || empty($userfield)) {
            $trace->output('Plugin config not complete.');
            $trace->finished();
            return 1;
        }

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external groups database');
            $trace->finished();
            return 1;
        }

        // Sanity check - make sure external table has the expected number of records before we trigger the sync.
        $hasenoughrecords = false;
        $count = 0;
        $minrecords = $this->config->minrecords;
        if (!empty($minrecords)) {
            $sql = "SELECT count(*) FROM $groupstable";
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $count = array_pop($fields);
                        if ($count >= $minrecords) {
                            $hasenoughrecords = true;
                        }
                    }
                }
            }
        }
        if (!$hasenoughrecords) {
            $trace->output("Failed to sync because the external db returned $count records and the minimum required is $minrecords");
            $trace->finished();
            return 1;
        }

        $trace->output('Fetching list of current groups and memberships.');
        // Load in the current group memberships.
        $sql = "SELECT g.courseid, g.idnumber as groupidnumber, gm.userid
                  FROM {groups} g
            INNER JOIN {groups_members} gm ON gm.groupid = g.id
            INNER JOIN {groupings} gr ON gr.courseid = g.courseid
            INNER JOIN {groupings_groups} gg ON gg.groupingid = gr.id AND gg.groupid = g.id
                 WHERE gr.idnumber = :idnumber";
        $rs = $DB->get_recordset_sql($sql, array('idnumber' => static::GLOBAL_GROUPING_IDNUMBER));
        // Cache the group members in an associative array.
        foreach ($rs as $row) {
            $this->groupmembers[$row->courseid][$row->groupidnumber][$row->userid] = $row->userid;
        }
        $rs->close();

        // Get records from the external database and assign groups.
        $trace->output('Starting groups database user sync');
        $sql = $this->db_get_sql($groupstable);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);

                    // Check that all the required fields are present.
                    if (empty($fields[$coursefield]) || empty($fields[$groupcodefield]) ||
                        empty($fields[$groupnamefield]) || empty($fields[$userfield])) {
                        $trace->output('error: invalid external groups record, one or more required fields are empty: '
                            . json_encode($fields), 1);
                        continue;
                    }

                    // Check that the course exists.
                    if (!$course = $DB->get_record('course', array($localcoursefield => $fields[$coursefield]), 'id,visible')) {
                        $trace->output("error: skipping row due to unknown course $localcoursefield
                            '$fields[$coursefield]'", 1);
                        continue;
                    }

                    // Check that the user exists.
                    $usersearch[$localuserfield] = $fields[$userfield];
                    if (!$user = $DB->get_record('user', $usersearch, 'id', IGNORE_MULTIPLE)) {
                        $trace->output("error: skipping row due to unknown user $localuserfield
                            '$fields[$userfield]'", 1);
                        continue;
                    }

                    // Add the course to the coursementions.
                    $this->coursementions[] = $course->id;

                    // Set group vars for better readability.
                    $groupidnumber = $fields[$groupcodefield];
                    $groupname = $fields[$groupnamefield];

                    if (isset($this->groupmembers[$course->id][$groupidnumber][$user->id])) {
                        // The group membership already exists.
                        $trace->output("Group membership already exists: courseid($course->id) => groupidnumber($groupidnumber) " .
                            "=> userid($user->id)");
                        unset($this->groupmembers[$course->id][$groupidnumber][$user->id]);
                        continue;
                    }

                    // Make sure the global grouping exists for this course.
                    $params = array('courseid' => $course->id, 'idnumber' => static::GLOBAL_GROUPING_IDNUMBER);
                    if (!$grouping = $DB->get_record('groupings', $params)) {
                        $data = new stdClass();
                        $data->courseid = $course->id;
                        $data->idnumber = static::GLOBAL_GROUPING_IDNUMBER;
                        $data->name = $this->config->groupingname;
                        if (empty($data->name)) {
                            $data->name = get_string('groupingnamedefault', 'tool_groupsdatabase');
                        }
                        $data->description = $this->config->groupingdesc;
                        if (empty($data->description)) {
                            $data->description = get_string('groupingdescdefault', 'tool_groupsdatabase');
                        }
                        $groupingid = groups_create_grouping($data);
                    } else if (!empty($grouping)) {
                        $groupingid = $grouping->id;
                    }

                    // Check if group exists in the course.
                    $sql = "SELECT g.id
                              FROM {groups} g
                        INNER JOIN {groupings} gr ON gr.courseid = g.courseid
                        INNER JOIN {groupings_groups} gg ON gg.groupingid = gr.id AND gg.groupid = g.id
                             WHERE g.idnumber = :groupidnumber
                               AND gr.idnumber = :groupingidnumber
                               AND gr.courseid = :courseid";
                    $params = array(
                        'groupidnumber' => $groupidnumber,
                        'groupingidnumber' => static::GLOBAL_GROUPING_IDNUMBER,
                        'courseid' => $course->id);
                    // Check whether group exists before adding memberships.
                    if (!$groupid = $DB->get_field_sql($sql, $params)) {
                        $trace->output("Creating new group: courseid($course->id) => groupidnumber($groupidnumber), " .
                            "groupname($groupname)");
                        // Set up new group data.
                        $data = new stdClass();
                        $data->name = $groupname;
                        $data->idnumber = $groupidnumber;
                        $data->courseid = $course->id;
                        try {
                            // Create the group. 
                            $groupid = groups_create_group($data);
                        } catch (moodle_exception $e) {
                            $message = $e->getMessage();
                            $trace->output("Could not create new group. Skipping row. Exception message: $message");
                            continue;
                        }

                        // Set the grouping.
                        groups_assign_grouping($groupingid, $groupid);
                    }

                    // Create group membership.
                    $trace->output("Adding group membership: courseid($course->id) => groupidnumber($groupidnumber) => " .
                        "userid($user->id)");
                    $result = groups_add_member($groupid, $user->id);
                    if (!$result) {
                        $trace->output("Failed to add: courseid($course->id) => groupidnumber($groupidnumber) => userid($user->id). User may be deleted or not enrolled in course.");
                    }
                }
            }
        }
        $extdb->Close();


        $this->coursementions = array_unique($this->coursementions);

        if (empty($removeaction) && !empty($this->groupmembers)) {
            // Unassign remaining memberships.
            $trace->output('Removing group memberships that are no longer in external database.');
            foreach ($this->groupmembers as $courseid => $group) {
                if ( ! in_array($courseid, $this->coursementions) ) {
                    // If a course was not present in the external data at all then do not remove
                    // anything. This is to preserve groups in archived courses.
                    continue;
                }
                foreach ($group as $groupidnumber => $users) {
                    foreach ($users as $userid) {
                        $params = array('courseid' => $courseid, 'idnumber' => $groupidnumber);
                        if ($group = $DB->get_record('groups', $params)) {
                            $trace->output("Unassigning: courseid($courseid) => groupidnumber($groupidnumber) => userid($userid)");
                            groups_remove_member($group->id, $userid);
                        } else {
                            $trace->output("Failed to unassign: courseid($courseid) => groupidnumber($groupidnumber) => ".
                                "userid($userid). Group was not found.");
                        }
                    }
                }
            }

            // Find and delete empty groups. Do not delete groups that are in courses that were not present in the external data.
            $trace->output('Removing empty groups.');
            $sql = "SELECT g.id, g.name, g.courseid
                      FROM {groups} g
                 LEFT JOIN {groups_members} gm ON gm.groupid = g.id
                INNER JOIN {groupings} gr ON gr.courseid = g.courseid
                INNER JOIN {groupings_groups} gg ON gg.groupingid = gr.id AND gg.groupid = g.id
                     WHERE gr.idnumber = :idnumber
                       AND gm.groupid IS NULL";

            $rs = $DB->get_recordset_sql($sql, array('idnumber' => static::GLOBAL_GROUPING_IDNUMBER));
            foreach ($rs as $row) {
                if ( ! in_array($row->courseid, $this->coursementions) ) {
                    // Course was not present in the external data
                    continue;
                }
                $trace->output("Deleting group: $row->id, $row->name");
                groups_delete_group($row->id);
            }
            $rs->close();
        }

        $trace->finished();

        return 0;
    }

    /**
     * Test plugin settings, print info to output.
     */
    public function test_settings() {
        global $CFG, $OUTPUT;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        raise_memory_limit(MEMORY_HUGE);

        $this->config = get_config('tool_groupsdatabase');

        $groupstable = $this->config->remotegroupstable;

        if (empty($groupstable)) {
            echo $OUTPUT->notification('External groups table not specified.', 'notifyproblem');
            return;
        }

        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        $olddebugdb = $this->config->debugdb;
        $this->config->debugdb = 1;
        error_reporting($CFG->debug);

        $adodb = $this->db_init();

        if (!$adodb or !$adodb->IsConnected()) {
            $this->config->debugdb = $olddebugdb;
            $CFG->debug = $olddebug;
            ini_set('display_errors', $olddisplay);
            error_reporting($CFG->debug);
            ob_end_flush();

            echo $OUTPUT->notification('Cannot connect the database.', 'notifyproblem');
            return;
        }

        if (!empty($groupstable)) {
            $rs = $adodb->Execute("SELECT *
                                     FROM $groupstable");
            if (!$rs) {
                echo $OUTPUT->notification('Can not read external groups table.', 'notifyproblem');

            } else if ($rs->EOF) {
                echo $OUTPUT->notification('External groups table is empty.', 'notifyproblem');
                $rs->Close();

            } else {
                $fieldsobj = $rs->FetchObj();
                $columns = array_keys((array)$fieldsobj);

                echo $OUTPUT->notification('External groups table contains following columns:<br />'.
                    implode(', ', $columns), 'notifysuccess');
                $rs->Close();
            }
        }

        $adodb->Close();

        $this->config->debugdb = $olddebugdb;
        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
        ob_end_flush();
    }

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    public function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->config->dbtype);
        if ($this->config->debugdb) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->config->dbhost, $this->config->dbuser, $this->config->dbpass,
                $this->config->dbname, true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->config->dbsetupsql) {
            $extdb->Execute($this->config->dbsetupsql);
        }
        return $extdb;
    }

    /**
     * Encode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_encode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    /**
     * Decode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_decode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    /**
     * Generate SQL required based on params.
     *
     * @param string $table - name of table
     * @param array $conditions - conditions for select.
     * @param array $fields - fields to return
     * @param boolean $distinct
     * @param string $sort
     * @return string
     */
    protected function db_get_sql($table, $conditions = array(), $fields = array(), $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";

        return $sql;
    }

    /**
     * Add slashes to text.
     *
     * @param string $text
     * @return string
     */
    protected function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->config->dbsybasequoting) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }
}



