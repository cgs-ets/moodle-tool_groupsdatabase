# Groups external database sync tool for Moodle
[![Build Status](https://travis-ci.org/cgs-ets/moodle-tool_groupsdatabase.svg?branch=master)](https://travis-ci.org/cgs-ets/moodle-tool_groupsdatabase)

This plugin syncs course groups using an external database table.

Author
--------
Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>


Features
--------
* Can be triggered via CLI and/or scheduled task.
* Syncronises course groups
* Syncronises users in cohorts
* Does not affect groups that are created/managed separately to this plugin.


Installation
------------

1. Download the plugin or install it by using git to clone it into your source:

   ```sh
   git clone git@github.com:cgs-ets/moodle-tool_groupsdatabase.git admin/tool/groupsdatabase
   ```

2. Then run the Moodle upgrade

External database requirements
------------------------------
Only a single table/view is required in the external database which contains a record for every course => group => user combination. If the table is large it is a good idea to make sure appropriate indexes have been created. The table/view must have the following minimum fields: 

* A unique course identifier to match 
  * the "idnumber" field in Moodle's course table (varchar 100), which is manually specified as the "Course ID number" when editing a course's settings
  * the "shortname" field in Moodle's course table (varchar 255), which is manually specified as the "Course short name" when editing a course's settings
  * the "id" field in Moodle's course table (int 10), which is based on course creation order
* A unique group identifier
* A name for the group
* A unique user identifier to match one of the following fields.
  * the "idnumber" field in Moodle's user table (varchar 255), which is manually specified as the "ID number" when editing a user's profile
  * the "username" field in Moodle's user table (varchar 100), which is manually specified as the "Username" when editing a user's profile
  * the "email" field in Moodle's user table (varchar 100), which is manually specified as the "Email address" when editing a user's profile
  * the "id" field in Moodle's user table (int 10), which is based on user creation order

Setting up the groups sync (How to)
-----------------------------------
In Moodle, go to Site administration > Plugins > Admin tools > Groups external database > Settings.

* In the top panel, select the database type (make sure you have the necessary configuration in PHP for that type) and then supply the information to connect to the database.
* localcoursefield - in Moodle the name of the field in the course that uniquely identifies the course (e.g., idnumber).
* localuserfield - in Moodle the name of the field in the user profile that uniquely identified the user (e.g., username).
* remotegroupstable - the name of the remote table/view.
* remotecoursefield - the name of the column in the external database table that uniquiely identifies the course.
* remotegroupcodefield - the name of the column in the external database table that uniquiely identifies the group.
* remotegroupnamefield - the name of the column in the external database table that contains the group name.
* remoteuserfield - the name of the column in the external database table that uniquiely identifies the user.
* removeaction - Select whether to remove or keep groups/group memberships that are removed from the external database table.

The edit the cron schedule, go to Site administration > Server > Scheduled tasks and edit the task named "Sync groups with external database".

Support
-------

If you have issues please log them in github here:
https://github.com/cgs-ets/moodle-tool_groupsdatabase/issues

