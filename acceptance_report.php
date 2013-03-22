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


// debug values
$hosts = array(
	array(
		'id'			=> 1,
		'description'	=> 'localhost',
		'hostname'		=> '127.0.0.1',
		'graphs'		=> 25,
		'dss'			=> 45,
		'disabled'		=> 'off',
		'status'		=> 3,
		'host_template'	=> 'Local Linux Machine',
	),
	array(
		'id'			=> 5,
		'description'	=> 'localhost2',
		'hostname'		=> '127.0.0.2',
		'graphs'		=> 35,
		'dss'			=> 45,
		'disabled'		=> 'off',
		'status'		=> 2,
		'host_template'	=> 'Local Linux Machine',
	),
);

$page = 1;
$per_page = 40;
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
	'nosort1' => array('Graphs', 'ASC'),
	'nosort2' => array('Data Sources', 'ASC'),
	'status' => array('Status', 'ASC'),
	'hostname' => array('Hostname', 'ASC'),
	'host_template' => array('Template', 'ASC'),
);
html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

$i = 0;
if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		form_alternate_row_color($colors['alternate'], $colors['light'], $i, 'line' . $host['id']); $i++;
		if (api_user_realm_auth('host.php'))
			form_selectable_cell('<a class="linkEditMain" href="'. htmlspecialchars($config['url_path'].'host.php?action=edit&id=' . $host['id']) . '">' . htmlspecialchars($host['description']) . '</a>', $host['id'], 250);
		else form_selectable_cell(htmlspecialchars($host['description']), $host['id'], 250);
		form_selectable_cell($host['id'], $host['id']);
		if(api_user_realm_auth('graph.php'))
			form_selectable_cell('<a href="'.htmlspecialchars($config['url_path'].'graphs.php?host_id='.$host['id'].'&filter=&template_id=-1&page=1').'">'.$host['graphs'].'</a>', $host['id']);
		else form_selectable_cell($host['graphs'], $host['id']);
		if(api_user_realm_auth('graph.php'))
			form_selectable_cell('<a href="'.htmlspecialchars($config['url_path'].'data_sources.php?host_id='.$host['id'].'&filter=&template_id=-1&method_id=-1&page=1').'">'.$host['dss'], $host['id']);
		else form_selectable_cell($host['dss'], $host['id']);
		form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on'), $host['status']), $host['id']);
		form_selectable_cell(htmlspecialchars($host['hostname']), $host['id']);
		form_selectable_cell(htmlspecialchars($host['host_template']), $host['id']);
		form_checkbox_cell(htmlspecialchars($host['hostname']), $host['id']);
		form_end_row();
	}

	/* put the nav bar on the bottom as well */
	print $nav;
}else{
	print '<tr><td><em>No Hosts</em></td></tr>';
}

html_end_box();


include_once($config['include_path'] . '/bottom_footer.php');


?>
