#!/usr/bin/php
<?php
/**
 * Automatically update certain Start Date field
 * based on the first "In Progress" status.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'redmine');
define('DB_USER', 'root');
define('DB_PASS', '');

if ($argc <> 2) {
	die("Usage: $argv[0] 'some status'\n");
}

$status_id = getStatusId($argv[1]);
updateStardDate($status_id);

function getStatusId($param) {
	$result = 0;

	if (is_numeric($param)) {
		$sql = "SELECT id FROM issue_statuses WHERE id = '$param'";
	}
	else {
		$param = mysql_real_escape_string($param);
		$sql = "SELECT id FROM issue_statuses WHERE name = '$param'";	
	}

	$conn = dbConnect();
	$sth = mysql_query($sql, $conn);
	if (!$sth) {
		die("Could not run SQL\n");
	}
	$data = mysql_fetch_assoc($sth);
	$result = $data['id'];

	return $result;
}

function updateStardDate($status_id) {

	$sql = "SELECT j.journalized_id as issue_id, j.created_on as status_date
			FROM journals j, journal_details jd 
			WHERE (jd.property = 'attr' AND jd.prop_key = 'status_id' AND jd.value = '$status_id') 
					AND j.id = jd.journal_id 
			ORDER BY journalized_id, created_on";
	$conn = dbConnect();
	$sth = mysql_query($sql, $conn);	
	if (!$sth) {
		die("Could not run SQL\n");
	}

	$prev_issue = null;
	while ($data = mysql_fetch_assoc($sth)) {
		if ($prev_issue == $data['issue_id']) {
			print "Skipping issue $prev_issue - already update\n";
			continue;
		}
		$sql = "UPDATE issues SET start_date = '" . $data['status_date'] . "' WHERE id = '" . $data['issue_id'] . "'";
		$sth2 = mysql_query($sql, $conn);
		if (!$sth2) {
			print "Failed to update issue " . $data['issue_id'] . " with date " . $data['status_date'] . "\n";
		}

		$prev_issue = $data['issue_id'];
	}
	
}

function dbConnect() {
	$result = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	if (!$result) {
		die("Failed to connect to the database\n");
	}
	if (!mysql_select_db(DB_NAME, $result)) {
		die("Failed to select database\n");
	}

	return $result;
}

function dbDisconnect($conn) {
	mysql_close($conn);
}
?>
