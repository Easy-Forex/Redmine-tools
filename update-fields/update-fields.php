#!/usr/bin/php
<?php
/**
 * Automatically update certain fields based on 
 * some conditions.
 * 
 * This obviously can be a rather large piece of
 * software.  The initial scenario is to update
 * the issue's start date when the status changes
 * to "In Progress".
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'redmine');
define('DB_USER', 'root');
define('DB_PASS', '');

if ($argc <> 3) {
	die("Usage: $argv[0] condition_field=condition_value update_field=update_value\n");
}
$sql = buildSQL($argv);
print "$sql\n";

function buildSQL($params) {
	$result = '';
	
	list($condition_field, $condition_value) = explode('=', $params[1], 2);
	list($update_field, $update_value) = explode('=', $params[2], 2);

	$result = "UPDATE issues SET `$update_field` = '$update_value' WHERE `$condition_field` = '$condition_value' AND `$update_field` IS NULL";

	return $result;
}

function runSQL($sql) {
	$dbh = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	if (!$dbh) {
		die("Failed to connect to the database\n");
	}
	if (!mysql_select_db(DB_NAME, $db)) {
		die("Failed to select database\n");
	}

	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL: $sql\n" . mysql_error());
	}
	mysql_close($dbh);
}
?>
