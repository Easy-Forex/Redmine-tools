#!/usr/bin/php
<?php
/**
 * Automatically create time entries, based on the changesets (commits)
 */

/**
 * This will be prepended to the comment of the time entry
 */
define('AUTO_PREFIX', 'AUTO: ');

/**
 * How much time to use - default is 15 minutes
 */
define('AUTO_HOURS', 0.25);

// We'll keep the last processed changeset id in here
// For safety reason, we'll never run without a last known issue.
// Create with something like:
// mysql -u root redmine --skip-column-names -e 'select max(changeset_id) from changesets_issues' > time.last
$cache_file = __DIR__ . DIRECTORY_SEPARATOR . 'time.last';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'redmine';

// Use this activity for time entries.
// Make sure it exists in Redmine, or we will fail miserably.
$activity = 'Development';

$dbh = mysql_connect($db_host, $db_user, $db_pass);
if (!$dbh) {
	die("Failed to connect to the database [$db_host] as user [$db_user]");
}
if (!mysql_select_db($db_name, $dbh)) {
	die("Failed to select database [$db_name]");
}

$cache_commit = get_last_cache_commit($cache_file);
$db_commit = get_last_db_commit($dbh);
if ($cache_commit >= $db_commit) {
	//echo "Nothing to do...\n";
	exit();
}

insert_time_entries($dbh, $cache_commit, $activity, $cache_file);

/**
 * Get last processed commit id from cache file
 * 
 * If the file is not there, or we can't read it, or the commit id
 * is not numeric, we'll die with a message.
 * 
 * @param string $cache_file Path to the cache file
 * @return numeric
 */
function get_last_cache_commit($cache_file) {
	$result = 0;
	
	if (!file_exists($cache_file) || !is_readable($cache_file)) {
		die("Cache file [$cache_file] is not readable");
	}
	
	$result = trim(file_get_contents($cache_file));
	if (empty($result) || !is_numeric($result)) {
		die("Failed to find numeric commit id in [$cache_file]");
	}
	
	return $result;
}

/**
 * Get last inserted commit from the database
 * 
 * If we aren't connected to the database, or there is no commits found,
 * we'll die with a message.
 * 
 * @param resource $dbh Database connection resource
 * @return numeric
 */
function get_last_db_commit($dbh) {
	$result = 0;

	$sql = 'SELECT MAX(changeset_id) AS max FROM changesets_issues';
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL [$sql] : " . mysql_error());
	}
	$data = mysql_fetch_assoc($sth);
	if (empty($data['max'])) {
		die("Failed to find any changesets");
	}
	$result = $data['max'];

	return $result;
}

/**
 * Insert time entries for all unprocessed commits
 * 
 * @param resource $dbh Database connection resource
 * @param numeric $since_commit Last processed commit ID
 * @param string $activity Which activity to use for time entries
 * @param string $cache_file Path to the cache file
 * @return numeric Number of entries inserted
 */
function insert_time_entries($dbh, $since_commit, $activity, $cache_file) {
	$result = 0;

	$commits = get_commits($dbh, $since_commit);
	if (empty($commits)) {
		//print "No commits to process\n";
		exit();
	}

	$activity_id = get_activity_id($dbh, $activity);

	foreach ($commits as $commit) {
		$commit_info = get_commit_info($dbh, $commit['changeset_id']);
		$issue_info = get_issue_info($dbh, $commit['issue_id']);

		$sql = 'INSERT INTO time_entries SET ';
		$sql .= 'project_id = ' . $issue_info['project_id'] . ', ';
		$sql .= 'user_id = ' . $commit_info['user_id'] . ', ';
		$sql .= 'issue_id = ' . $issue_info['id'] . ', ';
		$sql .= 'hours = ' . AUTO_HOURS . ', ';
		// Use the first line of the commit message
		$sql .= 'comments = "' . AUTO_PREFIX . mysql_real_escape_string(strtok($commit_info['comments'], "\n"), $dbh) . '", ';
		$sql .= 'activity_id = ' . $activity_id . ', ';
		$sql .= 'spent_on = "' . $commit_info['commit_date'] . '", ';
		$sql .= 'tyear = ' . date('Y', strtotime($commit_info['commit_date'])) . ', ';
		$sql .= 'tmonth = ' . date('m', strtotime($commit_info['commit_date'])) . ', ';
		$sql .= 'tweek = ' . date('W', strtotime($commit_info['commit_date'])) . ', ';
		$sql .= 'created_on = NOW(), ';
		$sql .= 'updated_on = NOW()';
		//print($sql);

		$sth = mysql_query($sql, $dbh);
		if (!$sth) {
            echo "Commit Info:\n";
            foreach($commit_info as $key => $value){
                echo "$key => $value\n";
            }   
            echo "\nIssue Info:\n:";
            foreach($issue_info as $key => $value){
                echo "$key => $value\n";
            }   
            echo "\n";

			die("Failed to run SQL [$sql] : " . mysql_error());
		}
		file_put_contents($cache_file, $commit['changeset_id']);
		$result++;
	}

	return $result;
}

/**
 * Find a list of unprocessed commits
 * 
 * @param resouce $dbh Database connection resource
 * @param numeric $since_commit Last processed commit id
 * @return array
 */
function get_commits($dbh, $since_commit) {
	$result = array();

	$sql = "SELECT changeset_id, issue_id FROM changesets_issues WHERE changeset_id > $since_commit ORDER BY changeset_id, issue_id";
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL [$sql] : " . mysql_error());
	}
	while ($data = mysql_fetch_assoc($sth)) {
		$result[] = $data;
	}

	return $result;
}

/**
 * Get activity ID
 * 
 * Given a string with activity description, find the ID of the active
 * record for this type of enumeration
 * 
 * @param resource $dbh Database connection resource
 * @param string $activity Activity name
 * @return numeric
 */
function get_activity_id($dbh, $activity) {
	$result = null;

	$sql = "SELECT id FROM enumerations WHERE name = '$activity' AND type = 'TimeEntryActivity' AND active = 1 LIMIT 1";
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL [$sql] : " . mysql_error());
	}
	$data = mysql_fetch_assoc($sth);
	if (empty($data['id'])) {
		die("Failed to find activity id for [$activity]");
	}
	$result = $data['id'];
	
	return $result;
}

/**
 * Fetch commit record
 * 
 * @param resource $dbh Database connection id
 * @param numeric $id Commit id
 * @return array
 */
function get_commit_info($dbh, $id) {
	$result = array();

	$sql = "SELECT * FROM changesets WHERE id = $id";
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL [$sql] : " . mysql_error());
	}
	$result = mysql_fetch_assoc($sth);

	return $result;
}

/**
 * Fetch issue record
 * 
 * @param resource $dbh Database connection id
 * @param numeric $id Issue id
 * @return array
 */
function get_issue_info($dbh, $id) {
	$result = array();

	$sql = "SELECT * FROM issues WHERE id = $id";
	$sth = mysql_query($sql, $dbh);
	if (!$sth) {
		die("Failed to run SQL [$sql] : " . mysql_error());
	}
	$result = mysql_fetch_assoc($sth);

	return $result;
}

?>
