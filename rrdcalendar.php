<?php

$cdir = "./plugins/rrdcalendar/images";
$cdir = "/cacti/plugins/rrdcalendar/images";
$self = preg_replace("/.+\//","",__FILE__);

chdir('../..');
include("./include/auth.php");
include("./include/config.php");
include("./lib/rrd.php");
include_once("./lib/functions.php");

ob_start();

$graph_data_array['print_source'] = 1;
$null_param = array();
@rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
$cmd_rrdtool = ob_get_clean();


$yearmon = isset_request_var('yearmon') ? get_request_var('yearmon') : date("Ym");
$yearmon_prev = substr(($yearmon-1),-2) == "00" ?  substr($yearmon,0,4)-1 . "12" : $yearmon-1 ;
$yearmon_next = substr(($yearmon+1),-2) == "13" ?  substr($yearmon,0,4)+1 . "01" : $yearmon+1;

$mon_start = 1;

$orig_upper_limit_type = "auto";
$orig_lower_limit_type = "auto";
$orig_upper_limit = "";
$orig_lower_limit = "";
foreach ( explode("\n",$cmd_rrdtool) as $value){
  


  if( preg_match("/^--upper-limit=(&#039;)*(\w+)(&#039;)*/",$value,$matches)){
    $orig_upper_limit = $matches[2];
    $orig_upper_limit_type = "fixed";
  }
  if( preg_match("/^--lower-limit=(&#039;)*(\w+)(&#039;)*/",$value,$matches)){
    $orig_lower_limit = $matches[2];
    $orig_lower_limit_type = "fixed";
  }
}

$upper_limit_type = isset_request_var('upper_limit_type') ? get_request_var('upper_limit_type') : $orig_upper_limit_type;
$upper_limit = isset_request_var('upper_limit') ? get_request_var('upper_limit') : $orig_upper_limit;

$lower_limit_type = isset_request_var('lower_limit_type') ? get_request_var('lower_limit_type') : $orig_lower_limit_type;
$lower_limit = isset_request_var('lower_limit') ? get_request_var('lower_limit') : $orig_lower_limit;

$limits = sprintf("%s,%s,%s,%s", $upper_limit_type ,$upper_limit ,$lower_limit_type ,$lower_limit);

$cmd_graph = "/usr/bin/perl /usr/share/cacti/plugins/rrdcalendar/rrdcalendar.pl " . get_request_var('local_graph_id') . " $yearmon  $mon_start '$limits'  '$cmd_rrdtool' ";

system($cmd_graph);
$result = ob_get_clean();

list($calced_lower_limit,$calced_upper_limit) = explode(":",$result);
if($lower_limit_type == "auto"){
  $lower_limit = $calced_lower_limit;
}
if($upper_limit_type == "auto"){
  $upper_limit = $calced_upper_limit;
}



$file = $cdir."/rrdcalimg-".  get_request_var('local_graph_id') ."-".$yearmon.".png";

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
#print "<PRE>$cmd_rrdtool</PRE>\n";
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
  <b> <?php print get_graph_title(get_request_var('local_graph_id')); ?> </b><BR>
  <?php print $yearmon; ?>
  </th>

  <th>
  <input type="button" value="NEXT" onclick="document.getElementById('limits').yearmon.value = <?php print $yearmon_next; ?> ; document.getElementById('limits').submit(); "><BR>
  <?php print $yearmon_next; ?>
  </th>
</tr>
<tr>
  <td colspan="3">
    <img src=<?php print $file;?>>
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
