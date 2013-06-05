<?php

/**
 * report.php - display devices to be put on acceptance
 * 
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @copyright (c) 2013, Super-Visions BVBA
 */

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

include_once($config['base_path'] . '/plugins/acceptance/setup.php');


$per_page = 40;
$tree = (int) read_config_option('acceptance_tree');
$acceptance_actions = array(
	'accept' => 'Accept',
	'ignore' => 'Ignore',
	'delete' => 'Delete',
);
$sort_column = 'description';
$sort_direction = 'ASC';
$sort_options = array(
	'description' => array('Description', 'ASC'),
	'id' => array('ID', 'ASC'),
	'graphs' => array('Graphs', 'DESC'),
	'dss' => array('Data Sources', 'DESC'),
	'status' => array('Status', 'ASC'),
	'hostname' => array('Hostname', 'ASC'),
	'host_template' => array('Template', 'ASC'),
);
$script_url = $config['url_path'].'plugins/acceptance/acceptance_report.php';

// load saved settings
load_current_session_value('per_page','acceptance_per_page', $per_page);
load_current_session_value('sort_column','acceptance_sort_column', $sort_column);
load_current_session_value('sort_direction','acceptance_sort_direction', $sort_direction);

if($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'actions'){
	
	// perform action after verify
	if(!empty($_POST['selected_items']) && preg_match('#^([0-9]+,)*[0-9]+$#', $_POST['selected_items'])){
		$selected_items = $_POST['selected_items'];
		
		// redirect to overview after execution. Errors can be found in logfile.
		header('Location: '.$script_url);
		
		include_once($config['include_path'] . '/top_header.php');
		
		html_start_box('<b>' . $acceptance_actions[$_POST['drp_action']] . ' device</b>', '60%', $colors['header_panel'], '3', 'center', '');
		print '	<tr><td class="textArea" bgcolor="#' . $colors['form_alternate1']. '">'.PHP_EOL;
		
		switch ($_POST['drp_action']) {
			case 'accept':
				
				// find all device information
				$devices_sql = sprintf("SELECT host.id, host_template.name, description, 
	hostname, snmp_community, snmp_version, snmp_username, snmp_password, 
	snmp_port, snmp_timeout, disabled, availability_method, ping_method, 
	ping_port, ping_timeout, ping_retries, notes, snmp_auth_protocol, 
	snmp_priv_passphrase, snmp_priv_protocol, snmp_context, max_oids, device_threads 
FROM host 
LEFT JOIN host_template 
ON(host_template_id = host_template.id) 
WHERE host.id IN(%s);", $selected_items);
				$devices = db_fetch_assoc($devices_sql);
				
				// find username to add in notes
				$username_sql = sprintf("SELECT IFNULL(NULLIF(full_name,''),username) FROM user_auth WHERE id=%d;", $_SESSION["sess_user_id"]);
				$username = db_fetch_cell($username_sql);
				
				$note = 'Accepted by '.$username;
				
				foreach($devices as $device){
					
					// add notes
					if(empty($device['notes'])) $device['notes'] = $note;
					else $device['notes'] = $note.PHP_EOL.$device['notes'];

				}
				
			case 'ignore':
				
				// disable devices
				db_execute(sprintf("UPDATE host SET disabled='on' WHERE id IN(%s);", $selected_items));

				// update poller cache
				db_execute(sprintf('DELETE FROM poller_item WHERE host_id IN(%s);', $selected_items));
				db_execute(sprintf('DELETE FROM poller_reindex WHERE host_id IN(%s);', $selected_items));
				
				break;
			
			case 'delete':
				include_once($config["base_path"] . '/lib/api_data_source.php');
				include_once($config["base_path"] . '/lib/api_graph.php');
				include_once($config["base_path"] . '/lib/api_device.php');
				
				// delete data sources
				$device_ds_sql = sprintf('SELECT id FROM data_local WHERE host_id IN(%s);', $selected_items);
				$device_ds_ids = array();
				foreach(db_fetch_assoc($device_ds_sql) as $ds) $device_ds_ids[] = $ds['id'];
				api_data_source_remove_multi($device_ds_ids);
				
				// delete graphs
				$device_graph_sql = sprintf('SELECT id FROM graph_local WHERE host_id IN(%s);', $selected_items);
				$device_graph_ids = array();
				foreach(db_fetch_assoc($device_graph_sql) as $graph) $device_graph_ids[] = $graph['id'];
				api_graph_remove_multi($device_graph_ids);
				
				// delete the device
				api_device_remove_multi(explode(',', $selected_items));
				
				break;
		}
		
		print '	</td></tr>'.PHP_EOL;
				
		html_end_box();
		
		include_once($config['include_path'] . '/bottom_footer.php');
		
		exit;
	}
	
	include_once($config['include_path'] . '/top_header.php');
	
	print '<form name="acceptance_action" action="'.$script_url.'" method="post">';

	html_start_box('<b>' . $acceptance_actions[$_POST['drp_action']] . ' device</b>', '60%', $colors['header_panel'], '3', 'center', '');
	
	// load selected devices
	$checked_ids = array();
	foreach (array_keys($_POST) as $name) {
		if(preg_match('#chk_([0-9]+)#', $name, $match)){
			$checked_ids[] = $match[1];
		}
	}
	$selected_items = implode(',',$checked_ids);
	
	if(!empty($selected_items)){
		
		// get device description
		$device_description_sql = sprintf('SELECT description FROM host WHERE id IN(%s);', $selected_items);
		$device_description = db_fetch_assoc($device_description_sql);
		
		$device_list = '';
		foreach($device_description as $device){
			$device_list .= '				<li>'.htmlentities($device['description']).'</li>'.PHP_EOL;
		}
		
		// show verification actions
		switch ($_POST['drp_action']) {
			case 'accept':
				print '	<tr>
		<td class="textArea" bgcolor="#' . $colors['form_alternate1']. '">
			<p>For every selected device, the following rules will be applied.</p>
			<ul>
				<li>Disable device on current system and add it to the production system to be graphed.</li>
				<li>The device and its graphs will still be on the current system, but graphs will not be polled anymore.</li>
				<li>The device will not appear anymore on the acceptance report page.</li>
			</ul>
			<p>When you click Apply, the following devices will be accepted.</p>
			<p><ul>'.$device_list.'</ul></p>
		</td>
	</tr>'.PHP_EOL;
				break;
			
			case 'ignore':
				print '	<tr>
		<td class="textArea" bgcolor="#' . $colors['form_alternate1']. '">
			<p>For every selected device, the following rules will be applied.</p>
			<ul>
				<li>Disable device on current system, it will not be added on any other system.</li>
				<li>The device and its graphs will still be on the current system, but graphs will not be polled anymore.</li>
				<li>The device will not appear anymore on the acceptance report page until it is enabled again.</li>
			</ul>
			<p>When you click Apply, the following devices will be ignored.</p>
			<p><ul>'.$device_list.'</ul></p>
		</td>
	</tr>'.PHP_EOL;
				break;
			
			case 'delete':
				print '	<tr>
		<td class="textArea" bgcolor="#' . $colors['form_alternate1']. '">
			<p>For every selected device, the following rules will be applied.</p>
			<ul>
				<li>The device and its graphs will be completely deleted from the current system.</li>
				<li>It will not appear anymore on the acceptance report page.</li>
			</ul>
			<p>When you click Apply, the following devices will be deleted.</p>
			<p><ul>'.$device_list.'</ul></p>
			<p><b>Note:</b> It is still possible that the devices will be added again to the system because of the discovery plugin. 
			They will then also appear again on the acceptance report page.</p>
		</td>
	</tr>'.PHP_EOL;
				break;
			
			default:
				break;
		}
		
		$save_html = '<input type="button" value="Return" onClick="window.history.back()">&nbsp;<input type="submit" value="Apply" title="Apply requested action">';
	}else{
		print '<tr><td bgcolor="#' . $colors['form_alternate1']. '"><span class="textError">You must select at least one Rule.</span></td></tr>';
		$save_html = '<input type="button" value="Return" onClick="window.history.back()">';
	}

	print '	<tr>
		<td align="right" bgcolor="#eaeaea">
			<input type="hidden" name="action" value="actions">
			<input type="hidden" name="selected_items" value="'. $selected_items . '">
			<input type="hidden" name="drp_action" value="' . $_POST['drp_action'] . '">
			'.$save_html.'
		</td>
	</tr>';

	html_end_box();
	
	print '</form>';
	
}else{
	
	// load page and sort settings
	$page = (int) get_request_var_request('page', 1);
	$per_page = (int) get_request_var_request('per_page');
	if(isset($sort_options[get_request_var_request('sort_column')])) $sort_column = get_request_var_request('sort_column');
	if(in_array(get_request_var_request('sort_direction'), array('ASC','DESC'))) $sort_direction = get_request_var_request('sort_direction');
	
	// extra validation
	if($page < 1) $page = 1;
		
	// calculate total rows
	$total_rows_sql = sprintf("SELECT COUNT(*) 
FROM host 
JOIN graph_tree_items 
ON(host.id = graph_tree_items.host_id) 
WHERE 
	graph_tree_id = %d
	AND host.disabled <> 'on';", $tree);
	$total_rows = db_fetch_cell($total_rows_sql);
	
	// retrieve hosts for current page
	$host_sql = sprintf("SELECT host.id, description, hostname, status, host_template.name AS host_template, 
	(SELECT COUNT(*) FROM data_local WHERE host_id=host.id) AS dss, 
	(SELECT COUNT(*) FROM graph_local WHERE host_id=host.id) AS graphs 
FROM host 
LEFT JOIN host_template 
ON(host.host_template_id = host_template.id) 
JOIN graph_tree_items 
ON(host.id = graph_tree_items.host_id) 
WHERE 
	graph_tree_id = %d
	AND host.disabled <> 'on' 
ORDER BY %s %s 
LIMIT %d OFFSET %d;", $tree, $sort_column, $sort_direction, $per_page, ($page-1)*$per_page);
	$hosts = db_fetch_assoc($host_sql);
	
	include_once($config['include_path'] . '/top_graph_header.php');
	
	// filter box
	html_start_box('<strong>Filters</strong>', '100%', $colors['header'], '3', 'center', '');

?>
<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
	<td class="noprint">
	<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $script_url;?>">
		<input type="submit" value="Go" title="Set/Refresh Filters">
		<input type="submit" name="button_clear_x" value="Clear" title="Reset fields to defaults">
		<input type="submit" name="button_export_x" value="Export" title="Export to a file">		
	</form>
	</td>
</tr>
<?php

	html_end_box();

	/* print checkbox form for validation */
	print '<form name="chk" method="post" action="'.$script_url.'">';

	// show devices
	html_start_box('', '100%', $colors['header'], '3', 'center', '');

	/* generate page list */
	$url_page_select = get_page_list($page, ACCEPTANCE_MAX_DISPLAY_PAGES, $per_page, $total_rows, $script_url.'?');

	$nav = '<tr bgcolor="#' . $colors["header"] . '">
		<td colspan="11">
			<table width="100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td align="left" class="textHeaderDark">
						<strong>&lt;&lt; ';
	// previous page
	if ($page > 1) $nav .= '<a class="linkOverDark" href="'.$script_url.'?page=' . ($page-1) . '">';
	$nav .= 'Previous'; 
	if ($page > 1) $nav .= '</a>';

	$nav .= '</strong>
					</td>
					<td align="center" class="textHeaderDark">
						Showing Rows ' . (($per_page*($page-1))+1) .' to '. ((($total_rows < $per_page) || ($total_rows < ($per_page*$page))) ? $total_rows : ($per_page*$page)) .' of '. $total_rows .' ['. $url_page_select .']
					</td>
					<td align="right" class="textHeaderDark">
						<strong>'; 
	// next page
	if (($page * $per_page) < $total_rows) $nav .= '<a class="linkOverDark" href="'.$script_url.'?page=' . ($page+1) . '">';
	$nav .= 'Next'; 
	if (($page * $per_page) < $total_rows) $nav .= '</a>';

	$nav .= ' &gt;&gt;</strong>
					</td>
				</tr>
			</table>
		</td>
	</tr>';

	print $nav;


	// display column names
	html_header_sort_checkbox($sort_options, $sort_column, $sort_direction, false);

	$i = 0;
	if ($total_rows > 0) {
		foreach ($hosts as $host) {
			form_alternate_row_color($colors['alternate'], $colors['light'], $i, 'line' . $host['id']); $i++;
			// host description
			$description = '';
			if (api_user_realm_auth('host.php')){
				$description .= '<a href="'. htmlspecialchars($config['url_path'].'host.php?action=edit&id=' . $host['id']) . '">';
				$description .= '<img src="'.$config['url_path'].'plugins/thold/images/edit_object.png" border="0" alt="Edit Host" title="Edit Host">';
				$description .= '</a> ';
			}
			if(api_user_realm_auth('graph_view.php')){
				$description .= '<a href="'.htmlspecialchars($config['url_path'].'graph_view.php?action=preview&host_id='.$host['id'].'&graph_template_id=0&filter=&page=1').'">';
				$description .= '<img src="'.$config['url_path'].'plugins/thold/images/view_graphs.gif" border="0" alt="View Graphs" title="View Graphs" />';
				$description .= '</a> ';
			}
			$description .= htmlspecialchars($host['description']);
			form_selectable_cell($description, $host['id'], 250);

			form_selectable_cell($host['id'], $host['id']);
			if(api_user_realm_auth('graphs.php'))
				form_selectable_cell('<a href="'.htmlspecialchars($config['url_path'].'graphs.php?host_id='.$host['id'].'&filter=&template_id=-1&page=1').'">'.$host['graphs'].'</a>', $host['id']);
			else form_selectable_cell($host['graphs'], $host['id']);
			if(api_user_realm_auth('data_sources.php'))
				form_selectable_cell('<a href="'.htmlspecialchars($config['url_path'].'data_sources.php?host_id='.$host['id'].'&filter=&template_id=-1&method_id=-1&page=1').'">'.$host['dss'], $host['id']);
			else form_selectable_cell($host['dss'], $host['id']);
			form_selectable_cell(get_colored_device_status(false, $host['status']), $host['id']);
			form_selectable_cell(htmlspecialchars($host['hostname']), $host['id']);
			form_selectable_cell(htmlspecialchars($host['host_template']), $host['id']);
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print '<tr><td><em>No Hosts</em></td></tr>';
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($acceptance_actions);

	print '</form>';
	
}

include_once($config['include_path'] . '/bottom_footer.php');


?>
