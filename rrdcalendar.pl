#!/usr/bin/perl

$debug_print = 1;
$debug_print = 0;
$debug_buf="";

$cmd_convert = "/usr/bin/convert ";
$tmpdir = "/usr/share/cacti/plugins/rrdcalendar/images";

$cmd_rrdtool = "/usr/bin/rrdtool ";
$cmd_graph_png   = $cmd_rrdtool . ' graph - '." \\\n";;
$cmd_graph_info  = $cmd_rrdtool . ' graphv '." \\\n";;

$local_graph_id = $ARGV[0];
$yearmon = $ARGV[1];
$file_output = sprintf("%s/rrdcalimg-%d-%d.png",$tmpdir,$local_graph_id,$yearmon);

# monday start : $mon_start = 1;
# sunday start : $mon_start = 0;
$mon_start = $ARGV[2];

# $limits = sprintf("%s,%s,%s,%s", $upper_limit_type ,$upper_limit ,$lower_limit_type ,$lower_limit);
$limits =  $ARGV[3];

$fontsize = $ARGV[4];

$graph_opt = $ARGV[5];
$graph_opt =~ s/<PRE>//;
$graph_opt =~ s/<\/PRE>.*//;
$graph_opt =~ s/&#039;/'/g;
$graph_opt =~ s/&quot;/"/g;

if($debug_print){ $debug_buf .= $graph_opt."\n"; }


# ---------------------------------------------------------------- #
# Timespan calculate
# ---------------------------------------------------------------- #

$year = substr($yearmon,0,4);
$mon  = substr($yearmon,4,2);

for($week=1,$day = 1;  ($unixtime,$wday) = check_date($year,$mon,$day) ;$day++){
  if($wday == $mon_start && $day != 1){
    $week++;
  }
  push(@{"week$week"},$day);
  push(@unixtimes,$unixtime) ;
}
$weeks = $week;
$days = $day-1;


$start_all = $unixtimes[0];
$end_all = $unixtimes[-1] + 24*60*60;



# ---------------------------------------------------------------- #
# Get Graph Option 
# ---------------------------------------------------------------- #

# Create legend png file
$cmd_graph_opts = "";


my($upper_limit_type ,$upper_limit ,$lower_limit_type ,$lower_limit) = split(/,/,$limits);

if($upper_limit_type eq "fixed"){
  $cmd_graph_opts .= "--upper-limit=$upper_limit \\\n";
}
if($lower_limit_type eq "fixed"){
  $cmd_graph_opts .= "--lower-limit=$lower_limit \\\n";
}

foreach (split(/\n/,$graph_opt)) {
  if($_ =~ /rrdtool\ graph|^--start=|^--end=|^--width=|^--height=|^--title=|^--watermark |^--color |^--font |^--x-grid |^--alt-autoscale-max |^--upper-limit=|^--lower-limit=/){next;}

  if($_ !~ /\\$/){ $_ .= "\\" ;}
  $cmd_graph_opts .= $_ . "\n";

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
$cmd_graph_opts .= '--no-legend '." \\\n";


# ---------------------------------------------------------------- #
# Get Graph Information
# ---------------------------------------------------------------- #
%graph_info = split(/\ =\ |\n/,`$cmd_graph_info $cmd_graph_opts`);
foreach ( sort keys %graph_info ){
  ${$_} = ${graph_info{$_}};
}

$image_height .= $height_append;
$graph_right = $image_width-$graph_width-$graph_left;
$legend_height = $image_height - $graph_top - $graph_height - $fontsize*2;


# ---------------------------------------------------------------- #
# Generate Graph (month)
# ---------------------------------------------------------------- #
$tmpfile_month = $file_output."_month";
$cmd = sprintf("%s %s > %s",$cmd_graph_png,$cmd_graph_opts,$tmpfile_month);
system($cmd);
push(@tmpfiles,$tmpfile_month);

# ---------------------------------------------------------------- #
# Generate graph axis (Y)
# ---------------------------------------------------------------- #
$tmpfile_y_axis = $file_output."_y_axis";
$crop_width  = $graph_left;
$crop_height = $image_height - $legend_height;
$crop_left   = 0;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis);
system($cmd);
push(@tmpfiles,$tmpfile_y_axis);


# ---------------------------------------------------------------- #
# Generate graph axis (Y-2nd)
# ---------------------------------------------------------------- #
$tmpfile_y_axis_2nd = $file_output."_y_axis_2nd";
$crop_width  = $graph_right;
$crop_height = $image_height - $legend_height;
$crop_left   = $image_width-$graph_right;
$crop_top    = 0;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_y_axis_2nd);
system($cmd);
push(@tmpfiles,$tmpfile_y_axis_2nd);


# ---------------------------------------------------------------- #
# Generate graph legend
# ---------------------------------------------------------------- #
$tmpfile_legend = $file_output."_legend";
$crop_width  = $graph_right+$graph_width_day*7 + $graph_left;
$crop_height = $legend_height;
$crop_left   = 0;
$crop_top    = $image_height-$legend_height;
$cmd = sprintf("%s %s -crop %dx%d+%d+%d %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile_legend);
system($cmd);
push(@tmpfiles,$tmpfile_legend);


# ---------------------------------------------------------------- #
# Extract spec range (= week) from month graph.
# ---------------------------------------------------------------- #
foreach $week (1..$weeks){
  $graph_width_week =  $graph_width_day * (${"week$week"}[-1] - ${"week$week"}[0] + 1);
  $graph_left_week  =  $graph_left + $graph_width_day * (${"week$week"}[0] - 1);
  $tmpfile = $file_output."_$week.png";

  $crop_width  = $graph_width_week;
  $crop_height = $image_height-$legend_height;
  $crop_left   = $graph_left_week;
  $crop_top    = 0;
  $cmd = sprintf("%s   \\(   %s -crop %dx%d+%d+%d  \\)   %s",$cmd_convert,$tmpfile_month,$crop_width,$crop_height,$crop_left,$crop_top,$tmpfile);
  system($cmd);

  $cmd = "$cmd_convert +append $tmpfile_y_axis $tmpfile $tmpfile_y_axis_2nd $tmpfile";
  system($cmd);

  # First week graph shifts to left.
  if($week ==1){
    $cmd = sprintf("%s -size %dx%d canvas:white %s -gravity east -composite %s ",$cmd_convert , $graph_left+$graph_width_day*7+$graph_right,$image_height-$legend_height,$tmpfile,$tmpfile);
    system($cmd);
  }

  push(@files,$tmpfile);
  
}


# concat multiple graphs.
$cmd = "$cmd_convert -append ";
foreach(@files){
  $cmd .= $_ ." ";
}
$cmd .= "$tmpfile_legend $file_output";
system($cmd);

# delete temprary graphs
push(@files,@tmpfiles);
foreach(@files){ system("rm -f $_"); }



if($upper_limit_type ne "fixed"){
  $upper_limit = $graph_info{'value_max'};
}
if($lower_limit_type ne "fixed"){
  $lower_limit = $graph_info{'value_min'};
}


print "$lower_limit:$upper_limit";


if($debug_print){
  open(FILE," > $tmpdir/out.log");
  print FILE $debug_buf;
  close(FILE);
}

exit;

# ---------------------------------------------------------------- #
# Functions
# ---------------------------------------------------------------- #

sub check_date{
  my $year = shift;
  my $mon  = shift;
  my $day  = shift;
  my $unix = `date -d "$year-$mon-$day 00:00:00" +%s 2> /dev/null`;
  chomp($unix);

  if($unix eq ""){
    return ;
  }else{
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($unix);
  return ($unix,$wday);
  }
}
