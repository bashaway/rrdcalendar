<?php

include_once("../../include/auth.php");
include_once("../../lib/rrd.php");
include_once("./rrdcalendar_functions.php");


# ---------------------------------- #
# API MODE MODIFY
# ---------------------------------- #
$mode      = isset_request_var('mode') ? get_request_var('mode') : "" ;
$hostname  = isset_request_var('hostname') ? get_request_var('hostname') : "" ;
$graphtype = isset_request_var('graphtype') ? get_request_var('graphtype') : "" ;

if($mode == 'api' && $hostname != "" && $graphtype != "" ){


$info = db_fetch_assoc("
select graph_local.id as local_graph_id ,host.description as hostname,graph_templates.name as graph_title from host_graph
 inner join host on host.id = host_graph.host_id
 inner join graph_local on graph_local.graph_template_id = host_graph.graph_template_id and graph_local.host_id = host.id
 inner join graph_templates on graph_templates.id = host_graph.graph_template_id
 where host.description = \"$hostname\"
 and graph_templates.name like \"%$graphtype%\"
")[0];


$link = "/cacti/graph.php?local_graph_id=${info['local_graph_id']}";


?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - rrdcalendar</title>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
        <meta http-equiv="refresh" content="0;URL=<?php print $link; ?>">

</head>

<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('init')">

<center>
<h3>nagios to cacti graph</h3>
<?php print "<a href=${link}> ${info['hostname']} ${info['graph_title']} </a>"; ?>
</center>

</body>
</html>


<?php

exit;
}


# ---------------------------------- #
# Request vars 
# ---------------------------------- #
$yearmon = isset_request_var('yearmon') ? get_request_var('yearmon') : date("Ym");
$year = substr($yearmon,0,4);
$mon  = substr($yearmon,4,2);
$yearmon_prev = date("Ym",mktime(0,0,0,$mon-1,1,$year));
$yearmon_next = date("Ym",mktime(0,0,0,$mon+1,1,$year));

$graph_title = sprintf("%s %s",read_user_setting('rrdcalendar_custom_graph_title'),get_graph_title(get_request_var('local_graph_id')));
$graph_title = sprintf(read_user_setting('rrdcalendar_custom_graph_title'),get_graph_title(get_request_var('local_graph_id')));
$file_output = "/cacti/plugins/rrdcalendar/cache/rrdcalimg-".  get_request_var('local_graph_id') ."-".$yearmon.".png";

$mon_start = isset_request_var('mon_start') ? get_request_var('mon_start') : read_user_setting('rrdcalendar_start_wd') ;
$fontsize  = isset_request_var('fontsize') ? get_request_var('fontsize') : read_user_setting('rrdcalendar_fontsize') ;




if(!(is_writable( read_config_option('rrdcalendar_path_cache')) && is_executable( read_config_option('rrdcalendar_path_convert')))){
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - rrdcalendar</title>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
</head>

<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('init')">

<center>
<h3>require packege(ImageMagick)</h3>
<h3> or </h3>
<h3>cache directory(~plugins/rrdcalendar/cache) permission</h3>
<h3>check failed.</h3>
<BR>
$ sudo dnf -y install ImageMagick<BR>
$ sudo chown apache.apache ./cache<BR>
<BR>
see document.<BR>
<a href=https://github.com/bashaway/rrdcalendar#installation>https://github.com/bashaway/rrdcalendar#installation</a><BR>
or<BR>
<a href=https://github.com/bashaway/rrdcalendar#configuration>https://github.com/bashaway/rrdcalendar#configuration</a><BR>

</center>

</body>
</html>


<?php

exit;
}


# ---------------------------------- #
# Generate calendar graph
# ---------------------------------- #
$limits = rrdcalendar();
foreach ( $limits as $key => $value ){
  ${$key} = $value;
}


?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - rrdcalendar</title>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
</head>

<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('init')">

<center>

<form id="limits" action="./<?php print get_current_page();?>" method="get">

<table border=1>
<tr>
  <th>
  <input type="button" value="PREV" onclick="document.getElementById('limits').yearmon.value = <?php print $yearmon_prev; ?> ; document.getElementById('limits').submit(); "><BR>
  <?php print $yearmon_prev; ?>
  </th>

  <th>
  <b> <?php print $graph_title; ?> </b><BR>
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
  <input type="button" value="DEFAULT"  onclick="location.href='./<?php print get_current_page()."?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon";?>'">
  </td>
</tr>
</table>

  <input type="hidden" name="local_graph_id" value="<?php print get_request_var('local_graph_id');?>">
  <input type="hidden" name="yearmon" value="<?php print $yearmon;?>">

</form>
</center>

</body>
</html>

