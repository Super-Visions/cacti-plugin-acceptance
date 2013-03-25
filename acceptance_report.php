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

include_once($config['include_path'] . '/top_graph_header.php');


$page = 1;
$per_page = 40;
$tree = (int) read_config_option('acceptance_tree');
$acceptance_actions = array(
	'accept' => 'Accept',
	'ignore' => 'Ignore',
	'delete' => 'Delete',
);

$host_sql = sprintf("SELECT host.id, description, hostname, status, host_template.name AS host_template, 
	(SELECT COUNT(*) FROM data_local WHERE host_id=host.id) AS dss, 
	(SELECT COUNT(*) FROM graph_local WHERE host_id=host.id) AS graphs 
FROM host 
JOIN host_template 
ON(host.host_template_id = host_template.id) 
JOIN graph_tree_items 
ON(host.id = graph_tree_items.host_id) 
WHERE 
	graph_tree_id = %d
	AND host.disabled <> 'on'", $tree);
$hosts = db_fetch_assoc($host_sql);
$total_rows = count($hosts);


// filter box
html_start_box('<strong>Filters</strong>', '100%', $colors['header'], '3', 'center', '');

?>
<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
	<td class="noprint">
	<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $config['url_path'];?>plugins/acceptance/acceptance_report.php">
		<input type="submit" value="Go" title="Set/Refresh Filters">
		<input type="submit" name="button_clear_x" value="Clear" title="Reset fields to defaults">
		<input type="submit" name="button_export_x" value="Export" title="Export to a file">		
	</form>
	</td>
</tr>
<?php

html_end_box();

/* print checkbox form for validation */
print '<form name="chk" method="post" action="acceptance_report.php">';

// show devices
html_start_box('', '100%', $colors['header'], '3', 'center', '');

/* generate page list */
$url_page_select = get_page_list($page, ACCEPTANCE_MAX_DISPLAY_PAGES, $per_page, $total_rows, "acceptance_report.php?");

$nav = '<tr bgcolor="#' . $colors["header"] . '">
		<td colspan="11">
			<table width="100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td align="left" class="textHeaderDark">
						<strong>&lt;&lt; ';
// previous page
if ($page > 1) $nav .= '<a class="linkOverDark" href="acceptance_report.php?page=' . ($page-1) . '">';
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
if (($page * $per_page) < $total_rows) $nav .= '<a class="linkOverDark" href="acceptance_report.php?page=' . ($page+1) . '">';
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
$display_text = array(
	'description' => array('Description', 'ASC'),
	'id' => array('ID', 'ASC'),
	'graphs' => array('Graphs', 'ASC'),
	'dds' => array('Data Sources', 'ASC'),
	'status' => array('Status', 'ASC'),
	'hostname' => array('Hostname', 'ASC'),
	'host_template' => array('Template', 'ASC'),
);
html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

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
		if(api_user_realm_auth('graph.php'))
			form_selectable_cell('<a href="'.htmlspecialchars($config['url_path'].'graphs.php?host_id='.$host['id'].'&filter=&template_id=-1&page=1').'">'.$host['graphs'].'</a>', $host['id']);
		else form_selectable_cell($host['graphs'], $host['id']);
		if(api_user_realm_auth('graph.php'))
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

include_once($config['include_path'] . '/bottom_footer.php');


?>
