<?php
require_once("../backend/modules/Backend.php");
$backend = Backend::create("Crosswiki tool performance", "Simulates a tool connecting to every wiki DB with cached connections. This is a common scenario for crosswiki tools, so the performance is important.")->header();
echo "<pre>";


# get DB details
$config = parse_ini_file("/data/project/pathoschild-contrib/replica.my.cnf");
$username = $config["user"];
$password = $config["password"];


# define a few helpers to make code more readable
function connect($slice, $db) {
	global $username, $password;
	return new PDO("mysql:host=$slice;dbname=${db}_p", $username, $password);
}

function executeQuery($connection, $sql) {
	$query = $connection->prepare($sql);
	$query->execute();
	return $query;
}

function fetchQuery($connection, $sql) {
	return executeQuery($connection, $sql)->fetchAll(PDO::FETCH_ASSOC);
}

function start($name) {
	global $backend;
	$backend->profiler->start($name);
}

function stop($name) {
	global $backend;
	$backend->profiler->stop($name);
}


# fetch wiki dbname/slice pairs from DB
start("fetch wikis");
$connection = connect("metawiki.labsdb", "metawiki");
$wikis = fetchQuery($connection, "SELECT dbname, slice FROM meta_p.wiki WHERE url IS NOT NULL");
stop("fetch wikis");

# connect once to each slice
start("connect to each slice");
$sliceCache = array();
foreach($wikis as $wiki) {
	$slice = $wiki["slice"];
	$dbname = $wiki["dbname"];
	if(!isset($sliceCache[$slice])) {
		echo "Connected slice: $slice\n";
		$sliceCache[$slice] = connect($slice, $dbname);
	}
}
stop("connect to each slice");


# connect to each wiki
echo "Switching to each of the " . count($wikis) . " wikis...";
start("switch to each wiki DB");
foreach($wikis as $wiki) {
	$slice = $wiki["slice"];
	$dbName = $wiki["dbname"];
	executeQuery($sliceCache[$slice], "use `{$dbName}`");
}
stop("switch to each wiki DB");


echo "done!";
$backend->footer();
?>
