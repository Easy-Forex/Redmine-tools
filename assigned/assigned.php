#!/usr/bin/php
<?php
/**
 * Change New tickets to Assigned if there is
 * an Assignee, and the ticket is of certain
 * trackers.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'redmine');
define('DB_USER', 'root');
define('DB_PASS', '');

define('STATUS_NEW', 'New');
define('STATUS_ASSIGNED', 'Assigned');
define('TRACKERS', 'Bug, Feature, Support, Taks, User Story');

$status_new_id = dbGetSingleField('id', "SELECT id FROM issue_statuses WHERE name = '" . mysql_real_escape_string(STATUS_NEW) . "'");
$status_assigned_id = dbGetSingleField('id', "SELECT id FROM issue_statuses WHERE name = '" . mysql_real_escape_string(STATUS_ASSIGNED) . "'");

$trackers = explode(',', TRACKERS);
$tracker_ids = array();
if (!empty($trackers)) {
	foreach ($trackers as $tracker) {
		$tracker_id = dbGetSingleField('id', "SELECT id FROM trackers WHERE name = '" . mysql_real_escape_string(trim($tracker)) . "'");
		if (!empty($tracker_id)) {
			$tracker_ids[] = $tracker_id;
		}
	}
}

# Now we have all we need.  Let's rock-n-roll!
if (!empty($status_new_id) && !empty($status_assigned_id) && !empty($tracker_ids)) {
	$trackers_list = '(' . implode(', ', $tracker_ids) . ')';
	$sql = "UPDATE issues 
			SET status_id = $status_assigned_id 
			WHERE  status_id = $status_new_id
				AND assigned_to_id IS NOT NULL
				AND tracker_id IN $trackers_list";
	$dbh = dbConnect();
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL : " . mysql_error());
	}
}


function dbConnect() {
	$dbh = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	if (!$dbh) {
		die("Failed to connect to the database: " . mysql_error());
	}
	if (!mysql_select_db(DB_NAME, $dbh)) {
		die("Failed to select the database: " . mysql_error());
	}

	return $dbh;
}

function dbClose($conn) {
	mysql_close($conn);
}

function dbGetSingleField($field, $sql) {
	$result = null;

	$dbh = dbConnect();
	$sth = mysql_query($sql);
	if (!$sth) {
		die("Failed to run SQL: " . mysql_error());
	}
	$data = mysql_fetch_assoc($sth);
	if (!empty($data[$field])) {
		$result = $data[$field];
	}

	return $result;
}

?>
