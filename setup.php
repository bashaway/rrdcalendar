<?php

#define('REALTIME_REALM_ID', '181');

function plugin_init_rrdcalendar() {
	global $plugin_hooks;

	// This is where you hook into the plugin archetecture
	$plugin_hooks['graph_buttons']['rrdcalendar']            = 'rrdcalendar_graph_buttons';
	$plugin_hooks['graph_buttons_thumbnails']['rrdcalendar'] = 'rrdcalendar_graph_buttons';
}

function plugin_rrdcalendar_install() {
	api_plugin_register_hook('rrdcalendar', 'graph_buttons',            'rrdcalendar_graph_buttons',   "setup.php");
	api_plugin_register_hook('rrdcalendar', 'graph_buttons_thumbnails', 'rrdcalendar_graph_buttons',   "setup.php");

	api_plugin_register_realm('rrdcalendar', 'rrdcalendar.php', 'Plugin -> Realtime', 1);

	rrdcalendar_setup_table_new ();
}

function plugin_rrdcalendar_uninstall () {
	/* Do any extra Uninstall stuff here */
}

function plugin_rrdcalendar_check_config () {
	/* Here we will check to ensure everything is configured */
	rrdcalendar_check_upgrade();
	return true;
}

function plugin_rrdcalendar_upgrade () {
	/* Here we will upgrade to the newest version */
	rrdcalendar_check_upgrade();
	return false;
}

function plugin_rrdcalendar_version () {
	return rrdcalendar_version();
}

function rrdcalendar_check_upgrade () {
}

function rrdcalendar_database_upgrade () {
}

function rrdcalendar_check_dependencies() {
	global $plugins, $config;
	return true;
}

function rrdcalendar_setup_table_new () {
}

function rrdcalendar_version () {
        global $config;
        $info = parse_ini_file($config['base_path'] . '/plugins/rrdcalendar/INFO', true);
        return $info['info'];
}


function rrdcalendar_config_settings () {
	global $tabs, $settings, $rrdcalendar_refresh, $rrdcalendar_window, $rrdcalendar_sizes;

	/* check for an upgrade */
	plugin_rrdcalendar_check_config();

}

function rrdcalendar_config_arrays () {
	global $user_auth_realm_filenames, $rrdcalendar_refresh, $rrdcalendar_window;
}

function rrdcalendar_graph_buttons($args) {
	global $config;

	$local_graph_id = $args[1]['local_graph_id'];

	if (api_user_realm_auth("rrdcalendar.php")) {
		echo "<a href='#' ";
                echo "onclick=\"window.open('".$config['url_path']."plugins/rrdcalendar/rrdcalendar.php?local_graph_id=".$local_graph_id."', 'popup_".$local_graph_id;
                echo "', 'toolbar=no,menubar=no,location=no,scrollbars=no,status=no,titlebar=no,width=800,height=1100,resizable=yes')\">";
                echo "<img src='".$config['url_path']."plugins/rrdcalendar/rrdcalendar.gif' border='0' alt='rrdcalendar' title='calendar' style='padding: 3px;'>";
                echo "</a><br/>";
	}

	rrdcalendar_setup_table();
}

function rrdcalendar_setup_table() {
	global $config, $database_default;
}

?>
