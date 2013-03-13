<?php

/**
 * poller.php - rerun data queries for all active hosts
 * 
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @copyright (c) 2013, Super-Visions BVBA
 */

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

ini_set("max_execution_time", "0");

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once($config["base_path"] . "/lib/snmp.php");
include_once($config["base_path"] . "/lib/data_query.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

/* utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

$debug		= FALSE;
$timeout	= 0;
$orderby	= 'host';

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "--order":
		$orderby = $value;
		break;
	case "-t":
	case "--timeout":
		$timeout = (int) $value;
		break;
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

// determine sorting order
if($orderby == 'random'){
	$orderfield = 'RANDOM()';
}elseif($orderby == 'host'){
	$orderfield = 'host_id';
}elseif($orderby == 'query'){
	$orderfield = 'snmp_query_id';
}else{
	print "ERROR: You must specify either 'random', 'host' or 'query' to proceed.\n";
	display_help();
	exit;
}

// find all data queries
$data_queries_sql = "SELECT host_id, snmp_query_id 
FROM host_snmp_query 
JOIN host 
ON( host_id = host.id ) 
WHERE disabled <> 'on' 
ORDER BY $orderfield;";

$data_queries = db_fetch_assoc($data_queries_sql);


/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Reindexing can take quite some time\n";
debug("There are '" . sizeof($data_queries) . "' data queries to run");

$i = 1;
if (sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) print ".";
		debug("Data query number '" . $i . "' host: '".$data_query["host_id"]."' SNMP Query Id: '".$data_query["snmp_query_id"]."' starting");
		run_data_query($data_query["host_id"], $data_query["snmp_query_id"]);
		debug("Data query number '" . $i . "' host: '".$data_query["host_id"]."' SNMP Query Id: '".$data_query["snmp_query_id"]."' ending");
		$i++;
		// timeout
		if($t > 0) usleep($t*1000);
	}
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Acceptance Reindex Poller 0.1, Copyright 2013 - Super-Visions BVBA\n\n";
	print "usage: poller.php --order=[host|query|random] [-t=[timeout]]\n";
	print "                           [-d] [--debug] [-h] [--help] [-v] [--version]\n\n";
	print "--order=order            - Choose which order to process the queries\n";
	print "-t=timeout               - Time (ms) to wait between data queries\n";
	print "-d --debug               - Display verbose output during execution\n";
	print "-v --version             - Display this help message\n";
	print "-h --help                - Display this help message\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}

?>
