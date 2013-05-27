<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 Super-Visions BVBA                                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 */

# define a debugging level specific to ACCEPTANCE
define('ACCEPTANCE_DEBUG', read_config_option("acceptance_log_verbosity"), true);

# define number of pages to display in lists
define('ACCEPTANCE_MAX_DISPLAY_PAGES', 21);

# non-gw-cacti compatibility
if(empty($database_idquote)) $database_idquote = '`';

/**
 * plugin_acceptance_install    - Initialize the plugin and setup all hooks
 */
function plugin_acceptance_install() {
	global $database_idquote;

	#api_plugin_register_hook('PLUGINNAME', 'HOOKNAME', 'CALLBACKFUNCTION', 'FILENAME');
	#api_plugin_register_realm('PLUGINNAME', 'FILENAMETORESTRICT', 'DISPLAYTEXT', 1);

    # setup all arrays needed for acceptance
    api_plugin_register_hook('acceptance', 'config_arrays', 'acceptance_config_arrays', 'setup.php');
    # setup all forms needed for acceptance
    api_plugin_register_hook('acceptance', 'config_settings', 'acceptance_config_settings', 'setup.php');

    # provide navigation texts
    api_plugin_register_hook('acceptance', 'draw_navigation_text', 'acceptance_draw_navigation_text', 'setup.php');
	
	# show Accepance tab
	api_plugin_register_hook('acceptance', 'top_header_tabs', 'acceptance_show_tab', 'setup.php');
	api_plugin_register_hook('acceptance', 'top_graph_header_tabs', 'acceptance_show_tab', 'setup.php');

    # hook into the polling process
    api_plugin_register_hook('acceptance', 'poller_bottom', 'acceptance_poller_bottom', 'setup.php');
	# hook to find graphs after reloading data queries
    api_plugin_register_hook('acceptance', 'run_data_query', 'acceptance_run_data_query', 'setup.php');

    # register all permissions for this plugin
    api_plugin_register_realm('acceptance', 'acceptance_report.php', 'Plugin -> Acceptance: Approve Devices', 1);
    api_plugin_register_realm('acceptance', 'acceptance_config.php', 'Plugin -> Acceptance: Configure', 1);
	
	# alter host_snmp_query table
	if(0 == db_fetch_cell("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'host_snmp_query' AND column_name = 'reindex_time';")){
		db_execute('ALTER TABLE host_snmp_query ADD reindex_time timestamp DEFAULT current_timestamp;');
		db_execute("INSERT INTO plugin_db_changes (plugin, ${database_idquote}table$database_idquote, ${database_idquote}column$database_idquote, method) VALUES ('acceptance', 'host_snmp_query', 'reindex_time', 'addcolumn');");
	}

}

/**
 * plugin_acceptance_version    - define version information
 */
function plugin_acceptance_version() {
	return acceptance_version();
}

/**
 * acceptance_version    - Version information (used by update plugin)
 */
function acceptance_version() {
    return array(
    	'name'		=> 'acceptance',
		'version'	=> '0.06',
		'longname'	=> 'Approve and deploy devices',
		'author'	=> 'Thomas Casteleyn',
		'homepage'	=> 'http://super-visions.com',
		'email'		=> 'thomas.casteleyn@super-visions.com',
		'url'		=> 'https://super-visions.com/redmine/projects/groundwork/wiki/Acceptance'
    );
}

/**
 * acceptance_draw_navigation_text    - Draw navigation texts
 * @param array $nav            - all current navigation texts
 * returns array                - updated navigation texts
 */
function acceptance_draw_navigation_text($nav) {
	// Displayed navigation text under the blue tabs of Cacti
	$nav["acceptance_report.php:"]	= array("title" => "Acceptance Report", "mapping" => "index.php:", "url" => "plugins/acceptance/acceptance_report.php", "level" => "1");
	$nav["acceptance_report.php:actions"]	= array("title" => "Confirm", "mapping" => "index.php:,acceptance_report.php:", "level" => "2");
	
    return $nav;
}

/**
 * acceptance_show_tab
 * @global array $config
 */
function acceptance_show_tab() {
	global $config;
	if (api_user_realm_auth('acceptance_report.php')) {
		$type = 'tab';
		if(isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) == 'acceptance_report.php') $type = 'red';

		print '<a href="' . $config['url_path'] . 'plugins/acceptance/acceptance_report.php"><img src="' . $config['url_path'] . 'plugins/acceptance/report_'.$type.'.gif" alt="thold" align="absmiddle" border="0"></a>';
	}
}

/**
 * acceptance_config_arrays    - Setup arrays needed for this plugin
 */
function acceptance_config_arrays() {
	
	global $acceptance_poller_frequencies;
	$acceptance_poller_frequencies = array(
		"disabled" => "Disabled",
		"60" => "Every 1 Hour",
		"120" => "Every 2 Hours",
		"240" => "Every 4 Hours",
		"360" => "Every 6 Hours",
		"480" => "Every 8 Hours",
		"720" => "Every 12 Hours",
		"1440" => "Every Day",
		"10080" => "Every Week",
		"20160" => "Every 2 Weeks",
		"40320" => "Every 4 Weeks"
		);
	
	global $trees;
	$trees =  array_rekey(db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name;'),'id','name');
	
	#global $menu;
	# menu titles
	#$menu["templates"]["items"]['plugins/acceptance/report.php'] = "Acceptance Report";
	#$menu["templates"]["items"]['plugins/acceptance/config.php'] = "Deployment rules";

}

/**
 * acceptance_config_settings    - configuration settings for this plugin
 */
function acceptance_config_settings() {
    global $tabs, $settings;
	global $logfile_verbosity;
	global $acceptance_poller_frequencies;
	global $trees;

    if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
        return;

    $temp = array(
        "acceptance_header" => array(
            "friendly_name" => "Acceptance",
            "method" => "spacer",
        ),
		"acceptance_tree" => array(
            "friendly_name" => "Acceptance Tree",
            "description" => "Select the tree with hosts to accept.",
            "method" => "drop_array",
            "default" => 1,
            "array" => $trees,
        ),
        "acceptance_log_verbosity" => array(
            "friendly_name" => "Logging Level for Acceptance",
            "description" => "What level of detail do you want sent to the log file.",
            "method" => "drop_array",
            "default" => POLLER_VERBOSITY_LOW,
            "array" => $logfile_verbosity,
        ),
        "acceptance_poller_interval" => array(
			"friendly_name" => "Poller Reindex Frequency",
			"description" => "Choose how often to reload data queries that are normally only updated when uptime changes.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $acceptance_poller_frequencies,
        ),
		"acceptance_remove_empty" => array(
			'friendly_name' => 'Remove empty DS',
			'description' => 'This option will remove data sources for indexes that don\'t exist anymore.',
			'method' => 'checkbox',
		),
		"acceptance_remove_duplicate" => array(
			'friendly_name' => 'Remove duplicate DS',
			'description' => 'This option will find and remove duplicated data sources.',
			'method' => 'checkbox',
		),
    );

    /* create a new Settings Tab, if not already in place */
    if (!isset($tabs["misc"])) {
        $tabs["misc"] = "Misc";
    }

    /* and merge own settings into it */
    if (isset($settings["misc"]))
        $settings["misc"] = array_merge($settings["misc"], $temp);
    else
        $settings["misc"] = $temp;
}

/**
 * 
 */
function acceptance_poller_bottom() {
	global $config, $database_type;

	include_once($config["library_path"] . "/database.php");
	
	$poller_interval = read_config_option("poller_interval");
	$acceptance_poller_interval = read_config_option("acceptance_poller_interval");
	$start_time = microtime(true);
	
	if($acceptance_poller_interval == "disabled")
		return;
	
	// find all data queries
	if ($database_type === "mysql") $data_queries_sql = sprintf("SELECT host_id, snmp_query_id 
FROM host_snmp_query 
JOIN host 
ON( host_id = host.id ) 
WHERE disabled <> 'on' 
AND reindex_method = %d 
AND reindex_time < NOW() - INTERVAL %d MINUTE 
ORDER BY reindex_time 
LIMIT 1;", DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME, $acceptance_poller_interval );
	else $data_queries_sql = sprintf("SELECT host_id, snmp_query_id 
FROM host_snmp_query 
JOIN host 
ON( host_id = host.id ) 
WHERE disabled <> 'on' 
AND reindex_method = %d 
AND reindex_time < NOW() - INTERVAL '%d minutes' 
ORDER BY reindex_time 
LIMIT 1;", DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME, $acceptance_poller_interval );
	
	// start poller_reindex for every data query
	$i = 0;
	$host_id = 0;
	while($data_query = db_fetch_row($data_queries_sql)){
		$i++;

		// some timeout if the same host is already being repolled by previous
		if($host_id == $data_query['host_id'])
			usleep(500000);
		else
			$host_id = $data_query["host_id"];

		if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_MEDIUM)
			cacti_log("Data query number " . $i . " starting. Host[".$data_query["host_id"]."] Query[".$data_query["snmp_query_id"]."]",false,'ACCEPTANCE');

		//update reindex_time to be sure its not get picked up also by another poller process in case of problems
		if ($database_type === "mysql") $update_reindex_time_sql = sprintf("UPDATE host_snmp_query 
SET reindex_time = NOW() - INTERVAL %d MINUTE + INTERVAL %d SECOND 
WHERE host_id=%d AND snmp_query_id=%d;",
			$acceptance_poller_interval, 2*$poller_interval,
			$data_query['host_id'], $data_query['snmp_query_id']
		);
		else $update_reindex_time_sql = sprintf("UPDATE host_snmp_query 
SET reindex_time = NOW() - INTERVAL '%d minutes' + INTERVAL '%d seconds' 
WHERE host_id=%d AND snmp_query_id=%d;",
			$acceptance_poller_interval, 2*$poller_interval,
			$data_query['host_id'], $data_query['snmp_query_id']
		);
		db_execute($update_reindex_time_sql);

		// do the actual reindex
		run_data_query($data_query['host_id'], $data_query['snmp_query_id']);
		
		// stop if using more time than the poller interval
		if(microtime(true) - $start_time > $poller_interval*.8) break;
	}
	
	if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_LOW && $i > 0)
		cacti_log('STATS: Reindexed ' . $i . ' data queries in '.(microtime(true) - $start_time).'s',false,'ACCEPTANCE');	
}

/**
 * 
 * @param array $data
 * @return array
 */
function acceptance_run_data_query($data){
	global $config;	
	
	include_once($config["base_path"] . '/lib/api_data_source.php');
	include_once($config["base_path"] . '/lib/api_graph.php');
	
	if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_DEBUG)
		cacti_log('DEBUG: Hook run_data_query started.',false,'ACCEPTANCE');
	
	$ds_ids = array();
	$graph_ids = array();
	
	// log message!
	if(empty($data)) return;
	
	// save last reindex time
	$update_reindex_time_sql = "UPDATE host_snmp_query 
SET reindex_time = current_timestamp 
WHERE host_id=".$data['host_id']." AND snmp_query_id=".$data['snmp_query_id'].";";
	db_execute($update_reindex_time_sql);
	
	if(read_config_option('acceptance_remove_duplicate') === 'on'){
		
		// find all duplicated data sources
		$ds_duplicate_sql = "SELECT COUNT(*) AS num, snmp_index, data_template_id 
FROM data_local 
WHERE host_id=".$data['host_id']." AND snmp_query_id=".$data['snmp_query_id']." 
GROUP BY snmp_index, data_template_id 
HAVING COUNT(*) > 1 
ORDER BY num DESC;";
		
		foreach(db_fetch_assoc($ds_duplicate_sql) as $ds){

			// find id of duplicates
			$duplicate_ds_id_sql = "SELECT data_local.id, name_cache 
FROM data_local 
JOIN data_template_data 
ON( local_data_id=data_local.id ) 
WHERE host_id=".$data['host_id']." AND snmp_query_id=".$data['snmp_query_id']." 
AND data_local.data_template_id=".$ds['data_template_id']." AND snmp_index='".$ds['snmp_index']."' 
ORDER BY id OFFSET 1;";

			foreach(db_fetch_assoc($duplicate_ds_id_sql) as $dds){
				$ds_ids[] = $dds['id'];

				if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_MEDIUM)
					cacti_log('Removing duplicate DS['.$dds['id'].'] ('.$dds['name_cache'].')',false,'ACCEPTANCE');
			}
		}
	}
	
	
	if(read_config_option('acceptance_remove_empty') === 'on'){
		
		// find all data sources linking to unexisting snmp indexes
		$ds_empty_sql = "SELECT data_local.id, name_cache 
FROM data_local 
LEFT JOIN host_snmp_cache 
USING(host_id, snmp_query_id, snmp_index) 
JOIN data_template_data 
ON( local_data_id=data_local.id ) 
WHERE host_id=".$data['host_id']." AND snmp_query_id=".$data['snmp_query_id']." 
GROUP BY data_local.id, name_cache 
HAVING MAX(oid) IS NULL;";
		
		foreach(db_fetch_assoc($ds_empty_sql) as $ds){
			$ds_ids[] = $ds['id'];

			if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_MEDIUM)
				cacti_log('Removing empty DS['.$ds['id'].'] ('.$ds['name_cache'].')',false,'ACCEPTANCE');
		}
	}
	
	
	// actually delete data sources
	api_data_source_remove_multi($ds_ids);
	
	
	// cleanup graphs without data source
	$empty_graphs_sql = "SELECT local_graph_id AS id, title_cache 
FROM graph_templates_item 
LEFT JOIN data_template_rrd 
ON( task_item_id = data_template_rrd.id ) 
JOIN graph_templates_graph 
USING(local_graph_id) 
GROUP BY local_graph_id, title_cache 
HAVING MAX(local_data_id) IS NULL;";
	
	foreach(db_fetch_assoc($empty_graphs_sql) as $local_graph){
		$graph_ids[] = $local_graph['id'];
		
		if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_MEDIUM)
			cacti_log('Removing empty Graph['.$local_graph['id'].'] ('.$local_graph['title_cache'].')',false,'ACCEPTANCE');
	}
	
	// delete graphs without data source
	api_graph_remove_multi($graph_ids);
	
	// report statistics
	if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_LOW && (count($ds_ids) > 0 or count($graph_ids) > 0 ))
		cacti_log('STATS: Removed '.count($ds_ids).' Data sources and '.count($graph_ids).' graphs.',false,'ACCEPTANCE');
	
	return $data;
}

?>
