<?php
include_once("../../include/auth.php");
include_once("../../lib/rrd.php");

# require functions : 
#  rrdtool_function_graph
#  read_config_option
#  get_request_var
#  isset_request_var


function mkcalgraph(){

$debug_buf ="";
$path_convert = read_config_option('rrdcalendar_path_convert');
$path_rrdtool = read_config_option('rrdcalendar_path_rrdtool');
$path_images  = read_config_option('rrdcalendar_path_images');


# --------------------------------------- #
# Get ORIGINAL RRDTool command options
# --------------------------------------- #
ob_start();
$graph_data_array['print_source'] = 1;
$null_param = array();
@rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
$raw_rrdtool_opts = ob_get_clean();
ob_end_clean();

$orig_graph_opts = "";
foreach ( explode("\n",$raw_rrdtool_opts) as $value ){
  if($value == ""){continue;}
  $value = preg_replace("/<PRE>/","",$value);
  $value = preg_replace("/&#039;/","'", $value);
  $value = preg_replace("/&quot;/","\"", $value);
  $value = preg_replace("/<\/PRE>.+/","",$value);
  $value = preg_replace("/\\\\$/","",$value);
  $orig_graph_opts .= $value ."\n";
}


# --------------------------------------- #
# Get Request Variables
# --------------------------------------- #
$yearmon = isset_request_var('yearmon') ? get_request_var('yearmon') : date("Ym");
$year = substr($yearmon,0,4);
$mon  = substr($yearmon,4,2);
$days = date("t",mktime(0,0,0,$mon,1,$year));

$mon_start = isset_request_var('mon_start') ? get_request_var('mon_start') : read_config_option('rrdcalendar_start_wd') ;
$fontsize  = isset_request_var('fontsize') ? get_request_var('fontsize') : read_config_option('rrdcalendar_fontsize') ;


# Filename for filesystem and web-img
$file_output_system = sprintf("%s/rrdcalimg-%d-%d.png",$path_images, get_request_var('local_graph_id'), $yearmon);


# ---------------------------------------------------------------- #
# Timespan , CalendarArray
# ---------------------------------------------------------------- #

$week = 1;
for($day=1;$day <= $days;$day++){
  list($unixtime,$wday) = explode(",",date("U,w",mktime(0,0,0,$mon,$day,$year)));
  if($wday == $mon_start && $day != 1){
    $week++;
  }

  ${"week$week"}[] = $day;
  $unixtimes[] = $unixtime;

}

$weeks = $week;
$days = $day-1;

$start_all = $unixtimes[0];
$end_all = end($unixtimes) + 24*60*60;


# ---------------------------------------------------------------- #
# Create New Graph options
# ---------------------------------------------------------------- #
$new_graph_opts = "";
foreach (explode("\n",$orig_graph_opts) as $value) {
  if( preg_match( "/^$|rrdtool\ graph|^--start=|^--end=|^--width=|^--height=|^--title=|^--watermark |^--color |^--font |^--x-grid |^--alt-autoscale-max |^--upper-limit=|^--lower-limit=/",$value,$matches)){continue;}

  if(!preg_match("/\\\\$/",$value)){
    $value .= "\\" ;
  }
  $new_graph_opts .= $value . "\n";
}


# ---------------------------------------------------------------- #
# Upper / Lower Limit 
# ---------------------------------------------------------------- #
if(isset_request_var('upper_limit_type')){
  $upper_limit_type = get_request_var('upper_limit_type');
  if($upper_limit_type == "fixed"){
    $upper_limit = get_request_var('upper_limit');
    $new_graph_opts .= "--upper-limit=$upper_limit \\\n";
  }else{
    $upper_limit = "";
  }
}else{
  if( preg_match("/--upper-limit=(')*(\w+)(')*/",$orig_graph_opts,$matches)){
    $upper_limit_type = "fixed";
    $upper_limit = $matches[2];
    $new_graph_opts .= "--upper-limit=$upper_limit \\\n";
  }else{
    $upper_limit_type = "auto";
    $upper_limit = "";
  }
}

if(isset_request_var('lower_limit_type')){
  $lower_limit_type = get_request_var('lower_limit_type');
  if($lower_limit_type == "fixed"){
    $lower_limit = get_request_var('lower_limit');
    $new_graph_opts .= "--lower-limit=$lower_limit \\\n";
  }else{
    $lower_limit = "";
  }
}else{
  if( preg_match("/--lower-limit=(')*(\w+)(')*/",$orig_graph_opts,$matches)){
    $lower_limit_type = "fixed";
    $lower_limit = $matches[2];
    $new_graph_opts .= "--lower-limit=$lower_limit \\\n";
  }else{
    $lower_limit_type = "auto";
    $lower_limit = "";
  }
}

# ---------------------------------------------------------------- #
# Other Options
# ---------------------------------------------------------------- #
# Timespan
$new_graph_opts .= '--start='.$start_all." \\\n";
$new_graph_opts .= '--end='.$end_all." \\\n";

# Graph size and Step
$height_append = $fontsize*1.9;
$graph_height  = $fontsize*12;
$graph_width_day = $graph_height;
$new_graph_opts .= '--width='. $graph_width_day*$days ." \\\n";
$new_graph_opts .= '--height='.$graph_height ." \\\n";
$new_graph_opts .= '--step 300'." \\\n";
$new_graph_opts .= '--font AXIS:'.$fontsize.': '."\\\n";
$new_graph_opts .= '--font LEGEND:'.$fontsize.': '."\\\n";
$new_graph_opts .= '--font UNIT:'.$fontsize.': '."\\\n";

# Const Options
$new_graph_opts .= '--color MGRID#000000 '."\\\n";
$new_graph_opts .= '--color BACK#FFFFFF '."\\\n";
$new_graph_opts .= '--color CANVAS#FFFFFF '."\\\n";
$new_graph_opts .= '--color SHADEA#FFFFFF '."\\\n";
$new_graph_opts .= '--color SHADEB#FFFFFF '."\\\n";
$new_graph_opts .= '--color FONT#000000 '."\\\n";
$new_graph_opts .= '--color AXIS#000000 '."\\\n";
$new_graph_opts .= '--color ARROW#FFFFFF '."\\\n";

$new_graph_opts .= '--disable-rrdtool-tag '."\\\n";
$new_graph_opts .= '--x-grid HOUR:6:DAY:1:DAY:1:86400:%m\/%d\(%a\) '."\\\n";



# ---------------------------------------------------------------- #
# Get Graph Information
# ---------------------------------------------------------------- #
foreach ( explode("\n", rtrim(`$path_rrdtool graphv $new_graph_opts`,"\n")) as $value){
  list($key,$value) = explode(" = ",$value);
  ${$key} = $value;
}

# adjust variables
$image_height .= $height_append;
$graph_right = $image_width-$graph_width-$graph_left;
$legend_height = $image_height - $graph_top - $graph_height - $fontsize*2;

# Overwrite upper/lower limit for auto scale
if($lower_limit_type == "auto"){ $lower_limit = $value_min; }
if($upper_limit_type == "auto"){ $upper_limit = $value_max; }


# ---------------------------------------------------------------- #
# Generate Graph (month)
# ---------------------------------------------------------------- #
$tmpfile_month = $file_output_system."_month";
$cmd = sprintf("%s graph - %s > %s",$path_rrdtool,$new_graph_opts,$tmpfile_month);
system($cmd);
$tmpfiles[] = $tmpfile_month;


# ---------------------------------------------------------------- #
# Generate graph axis (Y)
# ---------------------------------------------------------------- #
$tmpfile_y_axis = $file_output_system."_y_axis";
$crop_width  = $graph_left;
$crop_height = $image_height - $legend_height;
$crop_left   = 0;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$path_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis);
system($cmd);
$tmpfiles[] = $tmpfile_y_axis;


# ---------------------------------------------------------------- #
# Generate graph axis (Y-2nd)
# ---------------------------------------------------------------- #
$tmpfile_y_axis_2nd = $file_output_system."_y_axis_2nd";
$crop_width  = $graph_right;
$crop_height = $image_height - $legend_height;
$crop_left   = $image_width-$graph_right;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$path_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis_2nd);
system($cmd);
$tmpfiles[] = $tmpfile_y_axis_2nd;


# ---------------------------------------------------------------- #
# Generate graph legend
# ---------------------------------------------------------------- #
$tmpfile_legend = $file_output_system."_legend";
$crop_width  = $graph_right+$graph_width_day*7 + $graph_left;
$crop_height = $legend_height;
$crop_left   = 0;
$crop_top    = $image_height-$legend_height;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$path_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_legend);
system($cmd);
$tmpfiles[] = $tmpfile_legend;


# ---------------------------------------------------------------- #
# Extract spec range (= week) from month graph.
# ---------------------------------------------------------------- #
for($week = 1; $week <= $weeks ; $week++){
  $graph_width_week =  $graph_width_day * (end(${"week$week"}) - ${"week$week"}[0] + 1);
  $graph_left_week  =  $graph_left + $graph_width_day * (${"week$week"}[0] - 1);
  $tmpfile = $file_output_system."_$week.png";

  $crop_width  = $graph_width_week;
  $crop_height = $image_height-$legend_height;
  $crop_left   = $graph_left_week;
  $crop_top    = 0;
  $cmd = sprintf("%s    %s -crop %dx%d+%d+%d   %s",$path_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile);
  system($cmd);

  $cmd = "$path_convert +append $tmpfile_y_axis $tmpfile $tmpfile_y_axis_2nd $tmpfile";
  system($cmd);

  # First week graph shifts to left.
  if($week ==1){
    $cmd = sprintf("%s -size %dx%d canvas:white %s -gravity east -composite %s ",$path_convert , $graph_left+$graph_width_day*7+$graph_right,$image_height-$legend_height,$tmpfile,$tmpfile);
    system($cmd);
  }

  $files[] = $tmpfile;
  
}

# ---------------------------------------------------------------- #
# Calendar Graph Generate
# ---------------------------------------------------------------- #
# append graphs 
$cmd = "$path_convert -append ";
foreach($files as $file){
  $cmd .= $file ." ";
}
$cmd .= "$tmpfile_legend $file_output_system";
system($cmd);

# delete temprary graphs
$delfiles = array_merge($files,$tmpfiles);
foreach($delfiles as $file){ system("rm -f $file"); }

$limits{'upper_limit'} = $upper_limit;
$limits{'upper_limit_type'} = $upper_limit_type;
$limits{'lower_limit'} = $lower_limit;
$limits{'lower_limit_type'} = $lower_limit_type;

return ($limits);

}

?>
