<?php

/**
 * acceptance.php - Accepts POST requests to create devices in the system
 * 
 * Note: This file has no authentication, permissions should be managed by HTTP
 * server. e.g. by using restriction on client IP adress
 * 
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @copyright (c) 2013, Super-Visions BVBA
 */

include("../../include/global.php");
include_once($config["base_path"] . '/lib/api_device.php');

$host_template_cache = array();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
	if(!empty($_POST['devices']) && is_array($_POST['devices'])){
		
		foreach($_POST['devices'] as $device){
			
			// check if device exists
			$foreign_id = $device['id'];
			$matching_host_id_sql = sprintf("SELECT id FROM host WHERE hostname LIKE '%s' LIMIT 1;", sanitize_search_string($device['hostname']) );
			$device['id'] = db_fetch_cell($matching_host_id_sql);
			
			// get host template id instead of name
			if(isset($host_template_cache[$device['name']])){
				$device['host_template_id'] = $host_template_cache[$device['name']];
			}else{
				$host_template_id_sql = sprintf("SELECT id FROM host_template WHERE name = '%s';", sanitize_search_string($device['name']) );
				$host_template_id = db_fetch_cell($host_template_id_sql);
				if(!empty($host_template_id)){
					$device['host_template_id'] = $host_template_id;
					$host_template_cache[$device['name']] = $host_template_id;
				}
			}
			unset($device['name']);
			
			// save device
			$host_id = api_device_save($device['id'], $device['host_template_id'], $device['description'], 
				$device['hostname'], $device['snmp_community'], $device['snmp_version'], $device['snmp_username'], 
				$device['snmp_password'], $device['snmp_port'], $device['snmp_timeout'], $device['disabled'], 
				$device['availability_method'], $device['ping_method'], $device['ping_port'], 
				$device['ping_timeout'], $device['ping_retries'], $device['notes'], $device['snmp_auth_protocol'], 
				$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'], 
				$device['max_oids'], $device['device_threads']);
			
			// done
			printf('%d: Host[%d] %s (%s) %s'.PHP_EOL, $foreign_id, $host_id, $device['description'], $device['hostname'], $host_id==$device['id']?'updated':'created');			
		}
	}
}

?>
