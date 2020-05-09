<?php

function plugin_rrdcalendar_install() {
  api_plugin_register_hook('rrdcalendar', 'config_arrays',            'rrdcalendar_config_arrays',    'setup.php');
  api_plugin_register_hook('rrdcalendar', 'config_form',              'rrdcalendar_config_form',      'setup.php');
  api_plugin_register_hook('rrdcalendar', 'config_settings',          'rrdcalendar_config_settings',  'setup.php');
  api_plugin_register_hook('rrdcalendar', 'graph_buttons',            'rrdcalendar_graph_buttons',    "setup.php");
  api_plugin_register_hook('rrdcalendar', 'graph_buttons_thumbnails', 'rrdcalendar_graph_buttons',    "setup.php");

  api_plugin_register_realm('rrdcalendar', 'rrdcalendar.php', 'Plugin -> RRDcalendar', 1);

}

function plugin_rrdcalendar_uninstall () {
}

function plugin_rrdcalendar_check_config () {
  rrdcalendar_check_upgrade();
  return true;
}

function plugin_rrdcalendar_upgrade () {
  rrdcalendar_check_upgrade();
  return false;
}

function plugin_rrdcalendar_version () {
  global $config;
  $info = parse_ini_file($config['base_path'] . '/plugins/rrdcalendar/INFO', true);
  return $info['info'];
}



function rrdcalendar_check_upgrade () {
  return true;
}

function rrdcalendar_config_form ($force = false) {
  global $settings_user,$tabs_graphs , $rrdcalendar_start_wd, $rrdcalendar_fontsize;

  /* check for an upgrade */
  plugin_rrdcalendar_check_config();

  if ($force === false && isset($_SERVER['PHP_SELF']) &&
    basename($_SERVER['PHP_SELF']) != 'settings.php' &&
    basename($_SERVER['PHP_SELF']) != 'auth_profile.php')
    return;

  $tabs_graphs['rrdcalendar'] = __('RRDcalendar', 'rrdcalendar');

  $tempUser = array(
    'rrdcalendar_legend' => array(
      'friendly_name' => __('Display Legend', 'rrdcalendar'),
      'description' => __('Check this to display legend.', 'rrdcalendar'),
      'method' => 'checkbox',
      'default' => 'on'
    ),

    'rrdcalendar_start_wd' => array(
      'friendly_name' => __('Start Day of Week', 'rrdcalendar'),
      'description' => __('Select which start to day of the week.', 'rrdcalendar'),
      'method' => 'drop_array',
      'default' => '1',
      'array' => $rrdcalendar_start_wd
    ),

    'rrdcalendar_fontsize' => array(
      'friendly_name' => __('Fontsize', 'rrdcalendar'),
      'description' => __('Select graph scale by fontsize.', 'rrdcalendar'),
      'method' => 'drop_array',
      'default' => '8',
      'array' => $rrdcalendar_fontsize
    ),


    'rrdcalendar_custom_graph_title' => array(
      'friendly_name' => __('Custom Title', 'rrdcalendar'),
      'description' => __('Add Original Strings for Specified Graph Title.', 'rrdcalendar'),
      'method' => 'textbox',
      'max_length' => 255,
      'default' => '%s',
    )

  );

  if (isset($settings_user['rrdcalendar'])) {
    $settings_user['rrdcalendar'] = array_merge($settings_user['rrdcalendar'], $tempUser);
  }else {
    $settings_user['rrdcalendar'] = $tempUser;
  }

}

function rrdcalendar_config_settings ($force = false) {
  global $config , $tabs, $settings;

  /* check for an upgrade */
  plugin_rrdcalendar_check_config();

  if ($force === false && isset($_SERVER['PHP_SELF']) &&
    basename($_SERVER['PHP_SELF']) != 'settings.php' &&
    basename($_SERVER['PHP_SELF']) != 'auth_profile.php')
    return;

  $tabs['rrdcalendar'] = __('RRDcalendar', 'rrdcalendar');

  $tempGlobal = array(
    'rrdcalendar_path_setting' => array(
      'friendly_name' => __('Path Options', 'rrdcalendar'),
      'method' => 'spacer',
    ),

    'rrdcalendar_path_rrdtool' => array(
      'friendly_name' => __('RRDTool command path', 'rrdcalendar'),
      'description' => __('input RRDTool command path ', 'rrdcalendar'),
      'method' => 'filepath',
      'filetype' => 'binary',
      'default' => '/usr/bin/rrdtool',
      'max_length' => 64,
    ),

    'rrdcalendar_path_convert' => array(
      'friendly_name' => __('convert command path', 'rrdcalendar'),
      'description' => __('input convert (ImageMagick) command path ', 'rrdcalendar'),
      'method' => 'filepath',
      'filetype' => 'binary',
      'default' => '/usr/bin/convert',
      'max_length' => 64,
    ),

    'rrdcalendar_path_cache' => array(
      'friendly_name' => __('writable image directory ', 'rrdcalendar'),
      'description' => __('generate image cache for this directory', 'rrdcalendar'),
      'method' => 'dirpath',
      'default' => $config['base_path'] .  '/plugins/rrdcalendar/cache',
      'max_length' => 64,
    )

  );

  if (isset($settings['rrdcalendar'])) {
    $settings['rrdcalendar'] = array_merge($settings['rrdcalendar'], $tempGlobal);
  }else {
    $settings['rrdcalendar'] = $tempGlobal;
  }

}

function rrdcalendar_config_arrays () {
  global $user_auth_realm_filenames, $rrdcalendar_start_wd, $rrdcalendar_fontsize;

  $rrdcalendar_start_wd = array(
    0 => __('Sunday ', 0, 'rrdcalendar'),
    1 => __('Mondary', 1, 'rrdcalendar')
  );

  $rrdcalendar_fontsize = array(
     6 => __('Small  (%d pt)',  6, 'rrdcalendar'),
     8 => __('Medium (%d pt)',  8, 'rrdcalendar'),
    10 => __('Large  (%d pt)', 10, 'rrdcalendar')
  );

  return true;
}

function rrdcalendar_graph_buttons($args) {
  global $config;

  $local_graph_id = $args[1]['local_graph_id'];

  if (api_user_realm_auth("rrdcalendar.php")) {
    echo "<a href='#' ";
    echo "onclick=\"window.open('".$config['url_path']."plugins/rrdcalendar/rrdcalendar.php?local_graph_id=".$local_graph_id."', 'popup_".$local_graph_id;
    echo "', 'toolbar=no,menubar=no,location=no,scrollbars=no,status=no,titlebar=no,width=800,height=1100,resizable=yes')\">";
    echo "<img src='".$config['url_path']."plugins/rrdcalendar/rrdcalendar.png' border='0' alt='rrdcalendar' title='calendar' style='padding: 3px;'>";
    echo "</a><br/>";
  }

  rrdcalendar_setup_table();
}

function rrdcalendar_setup_table() {
  global $config, $database_default;
}

// Old Plugin Archtecture ??
function plugin_init_rrdcalendar() {
  global $plugin_hooks;

  // This is where you hook into the plugin archetecture
  $plugin_hooks['graph_buttons']['rrdcalendar']            = 'rrdcalendar_graph_buttons';
  $plugin_hooks['graph_buttons_thumbnails']['rrdcalendar'] = 'rrdcalendar_graph_buttons';
}


?>
