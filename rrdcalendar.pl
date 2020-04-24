#!/usr/bin/perl

$cmd_convert = "/usr/bin/convert ";
$cmd_rrdtool = "/usr/bin/rrdtool ";
$tmpdir = "/usr/share/cacti/plugins/rrdcalendar/images";

$local_graph_id = $ARGV[0];
$date = $ARGV[1];
$graph_opt = $ARGV[2];
$graph_opt =~ s/&#039;/'/g;

#print $graph_opt."\n";

# ---------------------------------------------------------------- #
# Time Span Calc
# ---------------------------------------------------------------- #

$year = substr($date,0,4);
$mon  = substr($date,4,2);

# monday start : $mon_start = 1;
# sunday start : $mon_start = 0;

$mon_start = 1;

if($mon_start){
  print " The week starts on Monday. \n";
}else{
  print " The week starts on Sunday. \n";
}


for($current_week=0,$day = 1;  ($unixtime,$wday) = check_date($year,$mon,$day) ;$day++){
  if($wday == $mon_start && $day != 1){
    $current_week++;
  }
  push(@{$times{$current_week}},$unixtime) ;
}
$weeks = $current_week;

foreach $week ( sort ( keys(%times) ) ){
  $start{$week} = ${$times{$week}}[0] ;
  $end{$week}   = ${$times{$week}}[-1] - 1 + 24*60*60;
}

foreach $week (0 .. $weeks){
  #print $start{$week} ." - ". $end{$week} ."\n";
}




$start_all = $start{"0"};
$end_all = $end{$weeks};

foreach $line (split(/\n/,$graph_opt)) {
  if($line =~ /^DEF:(\w+)='(.+?)':'(.+?)':(\w+)/){

    $rra   = $2;
    $rrds{'index'}{$1} = $1;
    $rrds{'index_to_rra'}{$1} = $2;
    $rrds{'index_to_type'}{$1} = $3;
    $rrds{'type_to_index'}{$3} = $1;
    $rrds{'index_to_func'}{$1} = $4;
    $rrds_funcs{$4} = 1;
    $rrds_types{$3} = 1;

    #print "$1 $2 $3 $4 \n";

  }

}


# resolution =  1w = 7*24*60*60 = 86400
# resolution = 24h =   24*60*60 = 86400
# resolution =  1h =      60*60 =  3600
$resolution = 1 * 24 * 60 * 60 ;
$resolution = 600 ;

foreach $func ( keys %rrds_funcs ){
  foreach $type ( keys %rrds_types ){
    $maxs{$type}{$func} = 0;
  }
}

foreach $func ( keys %rrds_funcs ){
  #print $func . "\n";
  
  $cmd = "$cmd_rrdtool fetch $rra $func --start $start_all --end $end_all --resolution $resolution \n";

  @lines = split(/\n/,`$cmd`);
  @types = split(/ +/,shift(@lines));

  foreach $line (@lines){
    if( ($line =~ /:/) && ($line !~ /nan/)){
      @counters = split(/ +/,$line);
      foreach $i (1 .. $#counters){

        if($maxs{$types[$i]}{$func} < $counters[$i] ){ $maxs{$types[$i]}{$func} = $counters[$i]; }
        #print $types[$i] . " : " . $counters[$i] . "\n";

      }
    }
  }
}


foreach $func ( keys %rrds_funcs ){
  foreach $type ( keys %rrds_types ){
    #print "$type : $func : ".$maxs{$type}{$func}."\n";
  }
}




# index to maximum 
foreach $index ( keys %{$rrds{'index'}} ){
  $rrds{'max'}{$index} = $maxs{$rrds{'index_to_type'}{$index}}{$rrds{'index_to_func'}{$index}};
  #print "$index  : " . $rrds{'max'}{$index} . "\n";
}


# check immidiate value 
#

#print "-- AREA or LINE  -- \n";
foreach (split(/\n/,$graph_opt)) {
  if($_ =~ /^(LINE1|AREA):(\w+)#/){
    #print "$_ \n";
    $cdefs{"index"}{$2} = $2;
    $cdefs{"formula"}{$2} = $2;
  }
}

#print "-- CDEF -- \n";
foreach $line (split(/\n/,$graph_opt)) {
  if($line =~ /^CDEF:(\w+)='(.+)'/){
    #print "$1 : $2 \n";
    $cdefs{"index"}{$1} = $1;
    $cdefs{"formula"}{$1} = $2;
  }
}

%operator = ( '+' => \&plus, '-' => \&minus, '*' => \&multiply, '/' => \&division,);

$max_final = 0;
foreach $index ( keys  ( %{$cdefs{"index"}} )){
    #print "$index : ".$cdefs{"formula"}{$index} ."\n";
    $formula = "";
    foreach(split(/,/,$cdefs{"formula"}{$index})){
      if($_ =~ /[a-zA-Z]+/){
        $formula .= $rrds{'max'}{$_}.",";
      }else{
        $formula .= $_.",";
      }
    }
    chop($formula);
    @stack = ();
    eval{
      $result = calc_rpn($formula);
    };
    if( !$@ ){ 
      #print "$formula   = $result \n";
      if($max_final < $result ){$max_final = $result; }
    }
    
}

#print "UPPER LIMIT : $max_final \n";

# ---------------------------------------------------------------- #
# Graph Option 
# ---------------------------------------------------------------- #

$opts = "";
$defs = "";

$opts .= "--upper-limit=$max_final \\\n";
foreach (split(/\n/,$graph_opt)) {
  $_ =~ s/<\/PRE>.*/\\/g;

  if($_ =~ /^--title/){
    $title = (split(/=/,$_))[-1];
    $title = (split(/'/,$title))[1];
    $title =~ s/ //g;
  } 

  if($_ =~ /^--start=|^--end=|^--width=|^--height=|^--title=|^--watermark |^--color |^--font |^--x-grid /){next;}


  if($_ =~ /^--/){
    $opts .= $_ . "\n";
  }elsif($_ =~ /DEF/){
    $defs .= $_ . "\n";
  }elsif($_ =~ /^LINE|^AREA/){
    $line .= $_ . "\n";
    $_ =~ s/'.+'/''/g;
    $line_nostr .= $_ . "\n";
  }


}




# LIMIT

# CPU graph
# --upper-limit='100' \
# --lower-limit='0' \

# RTT
# --alt-autoscale-max \
# --lower-limit='0' \

# Interface Error 
# --alt-autoscale-max \
# --lower-limit='0' \

# Interface Traffic
# --alt-autoscale-max \
# --lower-limit='0' \


# Memory Usage
# --alt-autoscale-max \
# --lower-limit='0' \


foreach $week (0 .. $weeks){

  $time = $end{$week} - $start{$week} +1 ;

  $timespan = 600;

  $width_week = 7*24*60*60 / $timespan;
  $width_day  = 1*24*60*60 / $timespan;
  $width = $time / $timespan;

  $height= $width_day;


  $cmd  = $cmd_rrdtool . ' graph - '." \\\n";;
  $cmd .= '--start='.$start{$week}." \\\n";
  $cmd .= '--end='.$end{$week}." \\\n";
  $cmd .= '--width='.$width." \\\n";
  $cmd .= '--height='.$height ." \\\n";

  $cmd .= '--color MGRID#000000 '."\\\n";
  $cmd .= '--color BACK#FFFFFF '."\\\n";
  $cmd .= '--color CANVAS#FFFFFF '."\\\n";
  $cmd .= '--color SHADEA#FFFFFF '."\\\n";
  $cmd .= '--color SHADEB#FFFFFF '."\\\n";
  $cmd .= '--font TITLE:9: '."\\\n";
  $cmd .= '--font AXIS:8: '."\\\n";
  $cmd .= '--font LEGEND:8: '."\\\n";
  $cmd .= '--font UNIT:8: '."\\\n";
  $cmd .= '--x-grid HOUR:6:DAY:1:DAY:1:86400:%m\/%d\(%a\) '."\\\n";

  # Upper/Lower Limit standardization


  $cmd .= $opts;
  $cmd .= $defs;
  if($week == $weeks){
    $cmd .= $line;
  }else{
    $cmd .= $line_nostr;
  }

  $file = $tmpdir."/rrdcal_tmp-".$local_graph_id."-".$week.'.png';
  $cmd .= " > $file ";
  push(@files,$file);
  $result = `$cmd`;
  #print $cmd . "\n\n\n\n\n\n\n\n";

  if($week == 0){
    $tmpcmd = sprintf("%s -size %dx%d canvas:white %s -composite -roll +%d+0 %s ",$cmd_convert , $width_week+100,$width_day+40,$file , $width_week-$width,$file);
    $check = `$tmpcmd`;
  }

}

$cmd = "$cmd_convert -append ";
foreach(@files){
  $cmd .= $_ ." ";
}
$cmd .= " $tmpdir"."/rrdcalimg" . $local_graph_id . "-" . $date . ".png";

$check = `$cmd`;

foreach(@files){
  system("rm -f $_");
}

exit;

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



sub plus {
  my $n2 = pop @stack;
  my $n1 = pop @stack;
  push @stack, $n1 + $n2;
}

sub minus {
  my $n2 = pop @stack;
  my $n1 = pop @stack;
  push @stack, $n1 - $n2;
}

sub multiply {
  my $n2 = pop @stack;
  my $n1 = pop @stack;
  push @stack, $n1 * $n2;
}

sub division {
  my $n2 = pop @stack;
  my $n1 = pop @stack;
  push @stack, $n1 / $n2;
}

sub calc_rpn {
  my $string = shift;
  foreach $item ( split(/,/, $string) ) {
    my $op = $operator{$item};
    if( $op ){
      die "stack underflow: $item [@stack]\n" if @stack < 2;
      &$op;
    } else {
      push @stack, $item;
    }
  }
  die "expression error: [@stack]\n" if @stack != 1;
  pop @stack;
}
