<?php

$cdir = "./plugins/rrdcalendar/images";
$cdir = "/cacti/plugins/rrdcalendar/images";
$self = preg_replace("/.+\//","",__FILE__);

chdir('../..');
include("./include/auth.php");
include("./include/config.php");
include("./lib/rrd.php");
include_once("./lib/functions.php");

?>


<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - rrdcalendar</title>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
</head>

<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('init')">
<?php

$graph_data_array['print_source'] = 1;
$null_param = array();
ob_start();
@rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
$output = ob_get_clean();



$yearmon = isset_request_var('yearmon') ? get_request_var('yearmon') : date("Ym");

$yearmon_prev = substr(($yearmon-1),-2) == "00" ?  substr($yearmon,0,4)-1 . "12" : $yearmon-1 ;
$yearmon_next = substr(($yearmon+1),-2) == "13" ?  substr($yearmon,0,4)+1 . "01" : $yearmon+1;



print "<PRE>";
system("/usr/bin/perl /usr/share/cacti/plugins/rrdcalendar/rrdcalendar.pl " . get_request_var('local_graph_id') . " " . $yearmon . " ' $output'");
print "</PRE>";

$file = $cdir."/rrdcalimg".  get_request_var('local_graph_id') ."-".$yearmon.".png";


foreach ( explode("\n",$output) as $value){
  if( preg_match("/^--title=&#039;(.+)&#039; /",$value,$maches) ){
    print $maches[1] . "<BR>\n";
    print "<a href=./$self?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon_prev>". $yearmon_prev . "</a> ";
    print " < <b>" . $yearmon . "</b> > ";
    print "<a href=./$self?local_graph_id=". get_request_var('local_graph_id') . "&yearmon=$yearmon_next>". $yearmon_next . "</a> ";
  }
}

print "<img width=90% src=$file> <BR>\n";

#print $output;
#$o = `/usr/bin/perl /usr/share/cacti/plugins/rrdcalendar/rrdcalendar.pl '$output'`;
#print $o;

?>
</body>
</html>
