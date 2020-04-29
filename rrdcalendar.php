<?php

$cdir = "./plugins/rrdcalendar/images";
$cdir = "/cacti/plugins/rrdcalendar/images";
$self = preg_replace("/.+\//","",__FILE__);

$debug_buf ="";

$cmd_convert = "/usr/bin/convert ";
$tmpdir = "/usr/share/cacti/plugins/rrdcalendar/images";

$cmd_rrdtool = "/usr/bin/rrdtool ";
$cmd_graph_png   = $cmd_rrdtool . ' graph - '." \\\n";;
$cmd_graph_info  = $cmd_rrdtool . ' graphv '." \\\n";;



chdir('../..');
include("./include/auth.php");
include("./include/config.php");
include("./lib/rrd.php");
include_once("./lib/functions.php");

ob_start();

# --------------------------------------- #
# Get ORIGINAL RRDTool command 
# --------------------------------------- #
$graph_data_array['print_source'] = 1;
$null_param = array();
@rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
$cmd_rrdtool = ob_get_clean();

$graph_opt = "";
foreach ( explode("\n",$cmd_rrdtool) as $value ){
  if($value == ""){continue;}
  $value = preg_replace("/<PRE>/","",$value);
  $value = preg_replace("/&#039;/","'", $value);
  $value = preg_replace("/&quot;/","\"", $value);
  $value = preg_replace("/<\/PRE>.+/","",$value);
  $graph_opt .= $value ."\n";
}



# --------------------------------------- #
# Get Request Variables
# --------------------------------------- #
$yearmon = isset_request_var('yearmon') ? get_request_var('yearmon') : date("Ym");
$yearmon_prev = substr(($yearmon-1),-2) == "00" ?  substr($yearmon,0,4)-1 . "12" : $yearmon-1 ;
$yearmon_next = substr(($yearmon+1),-2) == "13" ?  substr($yearmon,0,4)+1 . "01" : $yearmon+1;

$mon_start = isset_request_var('mon_start') ? get_request_var('mon_start') : read_config_option('rrdcalendar_start_wd') ;
$fontsize  = isset_request_var('fontsize') ? get_request_var('fontsize') : read_config_option('rrdcalendar_fontsize') ;

$graph_title = sprintf("%s %s",read_config_option('rrdcalendar_custom_graph_title'),get_graph_title(get_request_var('local_graph_id')));

$orig_upper_limit_type = "auto";
$orig_lower_limit_type = "auto";
$orig_upper_limit = "";
$orig_lower_limit = "";
foreach ( explode("\n",$graph_opt) as $value){
  if( preg_match("/^--upper-limit=(')*(\w+)(')*/",$value,$matches)){
    $orig_upper_limit = $matches[2];
    $orig_upper_limit_type = "fixed";
  }
  if( preg_match("/^--lower-limit=(')*(\w+)(')*/",$value,$matches)){
    $orig_lower_limit = $matches[2];
    $orig_lower_limit_type = "fixed";
  }
}

$upper_limit_type = isset_request_var('upper_limit_type') ? get_request_var('upper_limit_type') : $orig_upper_limit_type;
$upper_limit = isset_request_var('upper_limit') ? get_request_var('upper_limit') : $orig_upper_limit;

$lower_limit_type = isset_request_var('lower_limit_type') ? get_request_var('lower_limit_type') : $orig_lower_limit_type;
$lower_limit = isset_request_var('lower_limit') ? get_request_var('lower_limit') : $orig_lower_limit;

$limits = sprintf("%s,%s,%s,%s", $upper_limit_type ,$upper_limit ,$lower_limit_type ,$lower_limit);

$file_output_system = sprintf("%s/rrdcalimg-%d-%d.png",$tmpdir, get_request_var('local_graph_id'), $yearmon);
$file_output = $cdir."/rrdcalimg-".  get_request_var('local_graph_id') ."-".$yearmon.".png";



# --------------------------------------- #
# Generate Graph by script
# --------------------------------------- #
#$cmd_graph = "/usr/bin/perl /usr/share/cacti/plugins/rrdcalendar/rrdcalendar.pl " . get_request_var('local_graph_id') . " $yearmon  $mon_start '$limits' $fontsize  '$cmd_rrdtool' ";
#system($cmd_graph);
#$result = ob_get_clean();



# ---------------------------------------------------------------- #
# Timespan calculate
# ---------------------------------------------------------------- #

$year = substr($yearmon,0,4);
$mon  = substr($yearmon,4,2);
$days = date("t",mktime(0,0,0,$mon,1,$year));

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



$cmd_graph_opts = "";
if($upper_limit_type == "fixed"){
  $cmd_graph_opts .= "--upper-limit=$upper_limit \\\n";
}
if($lower_limit_type == "fixed"){
  $cmd_graph_opts .= "--lower-limit=$lower_limit \\\n";
}

foreach (explode("\n",$graph_opt) as $value) {
  if( preg_match( "/^$|rrdtool\ graph|^--start=|^--end=|^--width=|^--height=|^--title=|^--watermark |^--color |^--font |^--x-grid |^--alt-autoscale-max |^--upper-limit=|^--lower-limit=/",$value,$matches)){continue;}

  if(!preg_match("/\\\\$/",$value)){
    $value .= "\\" ;
  }
  $cmd_graph_opts .= $value . "\n";
}


$height_append = $fontsize*1.9;
$graph_height  = $fontsize*12;
$graph_width_day = $graph_height;

$cmd_graph_opts .= '--start='.$start_all." \\\n";
$cmd_graph_opts .= '--end='.$end_all." \\\n";
$cmd_graph_opts .= '--width='. $graph_width_day*$days ." \\\n";
$cmd_graph_opts .= '--height='.$graph_height ." \\\n";

$cmd_graph_opts .= '--font AXIS:'.$fontsize.': '."\\\n";
$cmd_graph_opts .= '--font LEGEND:'.$fontsize.': '."\\\n";
$cmd_graph_opts .= '--font UNIT:'.$fontsize.': '."\\\n";

$cmd_graph_opts .= '--color MGRID#000000 '."\\\n";
$cmd_graph_opts .= '--color BACK#FFFFFF '."\\\n";
$cmd_graph_opts .= '--color CANVAS#FFFFFF '."\\\n";
$cmd_graph_opts .= '--color SHADEA#FFFFFF '."\\\n";
$cmd_graph_opts .= '--color SHADEB#FFFFFF '."\\\n";
$cmd_graph_opts .= '--color FONT#000000 '."\\\n";
$cmd_graph_opts .= '--color AXIS#000000 '."\\\n";
$cmd_graph_opts .= '--color ARROW#FFFFFF '."\\\n";

$cmd_graph_opts .= '--disable-rrdtool-tag '."\\\n";
$cmd_graph_opts .= '--x-grid HOUR:6:DAY:1:DAY:1:86400:%m\/%d\(%a\) '."\\\n";
$cmd_graph_opts .= '--step 300'." \\\n";


# ---------------------------------------------------------------- #
# Get Graph Information
# ---------------------------------------------------------------- #

foreach ( explode("\n",`$cmd_graph_info $cmd_graph_opts`) as $value){
  if(preg_match("/^$/",$value)){continue;}
  list($key,$value) = explode(" = ",$value);
  ${$key} = $value;
}

$image_height .= $height_append;
$graph_right = $image_width-$graph_width-$graph_left;
$legend_height = $image_height - $graph_top - $graph_height - $fontsize*2;


# ---------------------------------------------------------------- #
# Generate Graph (month)
# ---------------------------------------------------------------- #
$tmpfile_month = $file_output_system."_month";
$cmd = sprintf("%s %s > %s",$cmd_graph_png,$cmd_graph_opts,$tmpfile_month);
$dummy = `$cmd`;
$tmpfiles[] = $tmpfile_month;



# ---------------------------------------------------------------- #
# Generate graph axis (Y)
# ---------------------------------------------------------------- #
$tmpfile_y_axis = $file_output_system."_y_axis";
$crop_width  = $graph_left;
$crop_height = $image_height - $legend_height;
$crop_left   = 0;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis);
$dummy = `$cmd`;
$tmpfiles[] = $tmpfile_y_axis;


# ---------------------------------------------------------------- #
# Generate graph axis (Y-2nd)
# ---------------------------------------------------------------- #
$tmpfile_y_axis_2nd = $file_output_system."_y_axis_2nd";
$crop_width  = $graph_right;
$crop_height = $image_height - $legend_height;
$crop_left   = $image_width-$graph_right;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis_2nd);
$dummy = `$cmd`;
$tmpfiles[] = $tmpfile_y_axis_2nd;


# ---------------------------------------------------------------- #
# Generate graph legend
# ---------------------------------------------------------------- #
$tmpfile_legend = $file_output_system."_legend";
$crop_width  = $graph_right+$graph_width_day*7 + $graph_left;
$crop_height = $legend_height;
$crop_left   = 0;
$crop_top    = $image_height-$legend_height;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_legend);
$dummy = `$cmd`;
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
  $cmd = sprintf("%s    %s -crop %dx%d+%d+%d   %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile);
  $dummy = `$cmd`;


  $cmd = "$cmd_convert +append $tmpfile_y_axis $tmpfile $tmpfile_y_axis_2nd $tmpfile";
  $dummy = `$cmd`;

  # First week graph shifts to left.
  if($week ==1){
    $cmd = sprintf("%s -size %dx%d canvas:white %s -gravity east -composite %s ",$cmd_convert , $graph_left+$graph_width_day*7+$graph_right,$image_height-$legend_height,$tmpfile,$tmpfile);
    $dummy = `$cmd`;
  }

  $files[] = $tmpfile;
  
}

# concat multiple graphs.
$cmd = "$cmd_convert -append ";
foreach($files as $file){
  $cmd .= $file ." ";
}
$cmd .= "$tmpfile_legend $file_output_system";
$dummy = `$cmd`;

# delete temprary graphs
$delfiles = array_merge($files,$tmpfiles);
foreach($delfiles as $file){ system("rm -f $file"); }


if($lower_limit_type == "auto"){
  $lower_limit = $value_min;
}
if($upper_limit_type == "auto"){
  $upper_limit = $value_max;
}


ob_end_clean();

?>


<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - rrdcalendar</title>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
</head>

<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('init')">
<?php
#print "OUTPUT from php script<BR>\n";
#print "<PRE>CHECK UPPER LIMIT : $upper_limit_type $upper_limit </PRE><BR>\n";
#print "<PRE>CHECK LOWER LIMIT : $lower_limit_type $lower_limit </PRE><BR>\n";
#print "<PRE>RRDtool CMD\n$cmd_rrdtool"."\nEND</PRE>\n";
print "<PRE> $debug_buf </PRE>\n";
#print "<PRE> $result </PRE>\n";
#print "<PRE> $limits </PRE>\n";

#print get_graph_title(get_request_var('local_graph_id')) . "<BR>\n";
#print "<a href=./$self?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon_prev>". $yearmon_prev . "</a> ";
#print " < <b>" . $yearmon . "</b> > ";
#print "<a href=./$self?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon_next>". $yearmon_next . "</a> ";
#print "<BR>\n";


?>

<center>

<form id="limits" action="./<?php print $self;?>" method="get">

<table border=1>
<tr>
  <th>
  <input type="button" value="PREV" onclick="document.getElementById('limits').yearmon.value = <?php print $yearmon_prev; ?> ; document.getElementById('limits').submit(); "><BR>
  <?php print $yearmon_prev; ?>
  </th>

  <th>
  <b> <?php print $graph_title; #get_graph_title(get_request_var('local_graph_id')); ?> </b><BR>
  <?php print $yearmon; ?>
  </th>

  <th>
  <input type="button" value="NEXT" onclick="document.getElementById('limits').yearmon.value = <?php print $yearmon_next; ?> ; document.getElementById('limits').submit(); "><BR>
  <?php print $yearmon_next; ?>
  </th>
</tr>
<tr>
  <td colspan="3">
    <img src=<?php print $file_output;?>>
  </td>
</tr>
</table>

<table border=0>
<tr>
  <td> UpperLimit : </td>
  <td> <input type="text" id="upper_limit" name="upper_limit" value="<?php print $upper_limit;?>" size="15">  </td>
  <td> <input type="radio"  name="upper_limit_type" value="fixed" <?php if($upper_limit_type == "fixed"){print "checked";}?> >fixed
       <input type="radio"  name="upper_limit_type" value="auto"  <?php if($upper_limit_type == "auto"){print "checked";}?> >auto
  </td>
</tr>

<tr>
  <td> LowerLimit : </td>
  <td> <input type="text" id="lower_limit" name="lower_limit" value="<?php print $lower_limit;?>" size="15"> </td>
  <td> <input type="radio" id="lower_limit_type" name="lower_limit_type" value="fixed" <?php if($lower_limit_type == "fixed"){print "checked";}?> >fixed
       <input type="radio" id="lower_limit_type" name="lower_limit_type" value="auto"  <?php if($lower_limit_type == "auto"){print "checked";}?> >auto
  </td>
</tr>

<tr>
  <td align=center>
  Start at
  <select name="mon_start">
    <option value="1" <?php if($mon_start == 1){print "selected";} ?>>Mon</option>
    <option value="0" <?php if($mon_start == 0){print "selected";} ?>>Sun</option>
  </select>
  <BR>
  Graph Size
  <select name="fontsize">
    <option value="6" <?php if($fontsize == 6){print "selected";} ?>>Small</option>
    <option value="8" <?php if($fontsize == 8){print "selected";} ?>>Medium</option>
    <option value="10" <?php if($fontsize == 10){print "selected";} ?>>Large</option>
  </select>
  </td>
  <td align=center>
  <input type="submit" value="refresh">
  <input type="button" value="x1/2" onclick="document.getElementById('limits').upper_limit_type[0].checked = true; document.getElementById('upper_limit').value =  2 *  parseFloat(document.getElementById('upper_limit').value) ; document.getElementById('limits').submit(); ">
  <input type="button" value="x2"   onclick="document.getElementById('limits').upper_limit_type[0].checked = true; document.getElementById('upper_limit').value =  0.5   *  parseFloat(document.getElementById('upper_limit').value) ; document.getElementById('limits').submit(); ">
  </td>
  <td align=center>
  <input type="button" value="DEFAULT"  onclick="location.href='./<?php print "$self?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon";?>'">
  </td>
</tr>
</table>

  <input type="hidden" name="local_graph_id" value="<?php print get_request_var('local_graph_id');?>">
  <input type="hidden" name="yearmon" value="<?php print $yearmon;?>">


</form>
</center>

</body>
</html>


