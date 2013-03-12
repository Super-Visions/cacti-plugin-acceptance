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

/**
 * plugin_acceptance_install    - Initialize the plugin and setup all hooks
 */
function plugin_acceptance_install() {

	#api_plugin_register_hook('PLUGINNAME', 'HOOKNAME', 'CALLBACKFUNCTION', 'FILENAME');
	#api_plugin_register_realm('PLUGINNAME', 'FILENAMETORESTRICT', 'DISPLAYTEXT', 1);

    # setup all arrays needed for acceptance
    api_plugin_register_hook('acceptance', 'config_arrays', 'acceptance_config_arrays', 'setup.php');
    # setup all forms needed for acceptance
    api_plugin_register_hook('acceptance', 'config_settings', 'acceptance_config_settings', 'setup.php');

    # provide navigation texts
    #api_plugin_register_hook('acceptance', 'draw_navigation_text', 'acceptance_draw_navigation_text', 'setup.php');
	
	# show Accepance tab
	#api_plugin_register_hook('acceptance', 'top_header_tabs', 'acceptance_show_tab', 'setup.php');
	#api_plugin_register_hook('acceptance', 'top_graph_header_tabs', 'acceptance_show_tab', 'setup.php');

    # hook into the polling process
    api_plugin_register_hook('acceptance', 'poller_bottom', 'acceptance_poller_bottom', 'setup.php');
	# hook to find graphs after reloading data queries
    api_plugin_register_hook('acceptance', 'run_data_query', 'acceptance_run_data_query', 'setup.php');

    # register all permissions for this plugin
    api_plugin_register_realm('acceptance', 'report.php', 'Plugin -> Acceptance: Approve Devices', 1);
    api_plugin_register_realm('acceptance', 'config.php', 'Plugin -> Acceptance: Configure', 1);

}

/**
 * plugin_acceptance_uninstall    - Do any extra Uninstall stuff here
 */
function plugin_acceptance_uninstall() {
    // Do any extra Uninstall stuff here
}

/**
 * plugin_acceptance_check_config    - Here we will check to ensure everything is configured
 */
function plugin_acceptance_check_config() {
    // Here we will check to ensure everything is configured
    acceptance_check_upgrade();
    return true;
}

/**
 * plugin_acceptance_upgrade    - Here we will upgrade to the newest version
 */
function plugin_acceptance_upgrade() {
    // Here we will upgrade to the newest version
    acceptance_check_upgrade();
    return true;
}

/**
 * plugin_acceptance_version    - define version information
 */
function plugin_acceptance_version() {
    return acceptance_version();
}

/**
 * acceptance_check_upgrade        - perform version upgrade
 */
function acceptance_check_upgrade() {
}

/**
 * acceptance_check_dependencies    - check plugin dependencies
 */
function acceptance_check_dependencies() {
    return true;
}

/**
 * acceptance_version    - Version information (used by update plugin)
 */
function acceptance_version() {

    return array(
    	'name'		=> 'acceptance',
		'version'	=> '0.01',
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
	#$nav["acceptance_graph_rules.php:"]             = array("title" => "Graph Rules", "mapping" => "index.php:", "url" => "acceptance_graph_rules.php", "level" => "1");
    #$nav["acceptance_graph_rules.php:edit"]         = array("title" => "(Edit)", "mapping" => "index.php:,acceptance_graph_rules.php:", "url" => "", "level" => "2");
    #$nav["acceptance_graph_rules.php:actions"]         = array("title" => "Actions", "mapping" => "index.php:,acceptance_graph_rules.php:", "url" => "", "level" => "2");
    #$nav["acceptance_graph_rules.php:item_edit"]    = array("title" => "Graph Rule Items", "mapping" => "index.php:,acceptance_graph_rules.php:,acceptance_graph_rules.php:edit", "url" => "", "level" => "3");

    #$nav["acceptance_tree_rules.php:"]                 = array("title" => "Tree Rules", "mapping" => "index.php:", "url" => "acceptance_tree_rules.php", "level" => "1");
    #$nav["acceptance_tree_rules.php:edit"]             = array("title" => "(Edit)", "mapping" => "index.php:,acceptance_tree_rules.php:", "url" => "", "level" => "2");
    #$nav["acceptance_tree_rules.php:actions"]         = array("title" => "Actions", "mapping" => "index.php:,acceptance_tree_rules.php:", "url" => "", "level" => "2");
    #$nav["acceptance_tree_rules.php:item_edit"]        = array("title" => "Tree Rule Items", "mapping" => "index.php:,acceptance_tree_rules.php:,acceptance_tree_rules.php:edit", "url" => "", "level" => "3");

    return $nav;
}

/**
 * acceptance_config_arrays    - Setup arrays needed for this plugin
 */
function acceptance_config_arrays() {
    # globals changed
    global $menu, $acceptance_poller_frequencies;
	
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

    # menu titles
    #$menu["templates"]["items"]['plugins/acceptance/report.php'] = "Acceptance Report";
    #$menu["templates"]["items"]['plugins/acceptance/config.php'] = "Deployment rules";

}

/**
 * acceptance_config_settings    - configuration settings for this plugin
 */
function acceptance_config_settings() {
    global $tabs, $settings, $logfile_verbosity, $acceptance_poller_frequencies;

    /* check for an upgrade */
    plugin_acceptance_check_config();

    if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
        return;

    $temp = array(
        "acceptance_header" => array(
            "friendly_name" => "Acceptance",
            "method" => "spacer",
        ),
        "acceptance_log_verbosity" => array(
            "friendly_name" => "Logging Level for Acceptance",
            "description" => "What level of detail do you want sent to the log file.",
            "method" => "drop_array",
            "default" => POLLER_VERBOSITY_LOW,
            "array" => $logfile_verbosity,
        ),
        "acceptance_poller_interval" => array(
			"friendly_name" => "Poller Frequency",
			"description" => "Choose how often to reload data queries.",
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

/*
 * 
 */
function acceptance_poller_bottom() {
	global $config;

	include_once($config["library_path"] . "/database.php");
	
	if (read_config_option("acceptance_poller_interval") == "disabled")
		return;

	$t = read_config_option("acceptance_last_run");

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option("poller_interval");
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}

	if ($t != '' && (time() - $t < $poller_interval))
		return;
	
	$command_string = trim(read_config_option("path_php_binary"));

	// If its not set, just assume its in the path
	if (empty($command_string))
		$command_string = "php";
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/acceptance/poller.php';

	//exec_background($command_string, $extra_args);
	
	if (empty($t))
		$sql = "INSERT INTO settings VALUES ('acceptance_last_run','" . time() . "')";
	else
		$sql = "UPDATE settings SET value = '" . time() . "' WHERE name = 'acceptance_last_run'";
	
	$result = db_execute($sql); 
	
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
		cacti_log('Hook run_data_query started.',false,'ACCEPTANCE');
	
	$ds_ids = array();
	$graph_ids = array();
	
	
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
	if(ACCEPTANCE_DEBUG >= POLLER_VERBOSITY_LOW)
		cacti_log('Removed '.count($ds_ids).' Data sources and '.count($graph_ids).' graphs.',false,'ACCEPTANCE');
	
	return $data;
}

?>
