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
 * Plugin settings and presets.
 *
 * @package   tool_groupsdatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


if ($hassiteconfig) {

    // Add a new category under tools.
    $ADMIN->add('tools',
        new admin_category('tool_groupsdatabase', get_string('pluginname', 'tool_groupsdatabase')));

    $settings = new admin_settingpage('tool_groupsdatabase_settings', new lang_string('settings', 'tool_groupsdatabase'),
        'moodle/site:config', false);

    // Add the settings page.
    $ADMIN->add('tool_groupsdatabase', $settings);

    // Add the test settings page.
    $ADMIN->add('tool_groupsdatabase',
            new admin_externalpage('tool_groupsdatabase_test', get_string('testsettings', 'tool_groupsdatabase'),
                $CFG->wwwroot . '/' . $CFG->admin . '/tool/groupsdatabase/test_settings.php'));

    // General settings.
    $settings->add(new admin_setting_heading('tool_groupsdatabase_settings', '',
        get_string('settings_desc', 'tool_groupsdatabase')));

    $settings->add(new admin_setting_heading('tool_groupsdatabase_exdbheader',
        get_string('settingsheaderdb', 'tool_groupsdatabase'), ''));

    $options = array('', "pdo", "pdo_mssql", "pdo_sqlsrv", "access", "ado_access", "ado", "ado_mssql", "borland_ibase",
        "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql",
        "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64",
        "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('tool_groupsdatabase/dbtype',
        get_string('dbtype', 'tool_groupsdatabase'),
        get_string('dbtype_desc', 'tool_groupsdatabase'), '', $options));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/dbhost',
        get_string('dbhost', 'tool_groupsdatabase'),
        get_string('dbhost_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/dbuser',
        get_string('dbuser', 'tool_groupsdatabase'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('tool_groupsdatabase/dbpass',
        get_string('dbpass', 'tool_groupsdatabase'), '', ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/dbname',
        get_string('dbname', 'tool_groupsdatabase'),
        get_string('dbname_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/dbencoding',
        get_string('dbencoding', 'tool_groupsdatabase'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/dbsetupsql',
        get_string('dbsetupsql', 'tool_groupsdatabase'),
        get_string('dbsetupsql_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_groupsdatabase/dbsybasequoting',
        get_string('dbsybasequoting', 'tool_groupsdatabase'),
        get_string('dbsybasequoting_desc', 'tool_groupsdatabase'), 0));

    $settings->add(new admin_setting_configcheckbox('tool_groupsdatabase/debugdb',
        get_string('debugdb', 'tool_groupsdatabase'),
        get_string('debugdb_desc', 'tool_groupsdatabase'), 0));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/minrecords',
        get_string('minrecords', 'tool_groupsdatabase'),
        get_string('minrecords_desc', 'tool_groupsdatabase'), 1));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/groupingname',
        get_string('groupingname', 'tool_groupsdatabase'),
        get_string('groupingname_desc', 'tool_groupsdatabase'), get_string('groupingnamedefault', 'tool_groupsdatabase')));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/groupingdesc',
        get_string('groupingdesc', 'tool_groupsdatabase'),
        '', get_string('groupingdescdefault', 'tool_groupsdatabase')));

    // Local fields.
    $settings->add(new admin_setting_heading('tool_groupsdatabase_localheader',
        get_string('settingsheaderlocal', 'tool_groupsdatabase'), ''));

    $options = array('id' => 'id', 'idnumber' => 'idnumber', 'shortname' => 'shortname');
    $settings->add(new admin_setting_configselect('tool_groupsdatabase/localcoursefield',
        get_string('localcoursefield', 'tool_groupsdatabase'), '', 'idnumber', $options));


    $options = array('id' => 'id', 'idnumber' => 'idnumber', 'email' => 'email', 'username' => 'username');
    $settings->add(new admin_setting_configselect('tool_groupsdatabase/localuserfield',
        get_string('localuserfield', 'tool_groupsdatabase'), '', 'username', $options));

    // Remote fields.
    $settings->add(new admin_setting_heading('tool_groupsdatabase_remoteheader',
        get_string('settingsheaderremote', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/remotegroupstable',
        get_string('remotegroupstable', 'tool_groupsdatabase'),
        get_string('remotegroupstable_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/remotecoursefield',
        get_string('remotecoursefield', 'tool_groupsdatabase'),
        get_string('remotecoursefield_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/remotegroupcodefield',
        get_string('remotegroupcodefield', 'tool_groupsdatabase'),
        get_string('remotegroupcodefield_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/remotegroupnamefield',
        get_string('remotegroupnamefield', 'tool_groupsdatabase'),
        get_string('remotegroupnamefield_desc', 'tool_groupsdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_groupsdatabase/remoteuserfield',
        get_string('remoteuserfield', 'tool_groupsdatabase'),
        get_string('remoteuserfield_desc', 'tool_groupsdatabase'), ''));

    $options = array(0  => get_string('removegroups', 'tool_groupsdatabase'),
                     1  => get_string('keepgroups', 'tool_groupsdatabase'));
    $settings->add(new admin_setting_configselect('tool_groupsdatabase/removeaction',
        get_string('removedaction', 'tool_groupsdatabase'),
        get_string('removedaction_desc', 'tool_groupsdatabase'), 0, $options));

}
