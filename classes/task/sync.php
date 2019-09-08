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
 * Groups database tool - sync.
 *
 * @package   tool_groupsdatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_groupsdatabase\task;
defined('MOODLE_INTERNAL') || die();
/**
 * Task class
 *
 * @package   tool_groupsdatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync extends \core\task\scheduled_task {
    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('sync', 'tool_groupsdatabase');
    }
    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        if (empty($CFG->showcrondebugging)) {
            $trace = new \null_progress_trace();
        } else {
            $trace = new \text_progress_trace();
        }
        $groupsdatabase = new \tool_groupsdatabase_sync();
        return $groupsdatabase->sync($trace);
    }
}
