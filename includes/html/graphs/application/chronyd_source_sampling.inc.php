<?php

require 'includes/html/graphs/common.inc.php';

$colours = 'mixed';
$nototal = (($width < 224) ? 1 : 0);
$unit_text = 'Seconds';
$rrd_filename = Rrd::name($device['hostname'], ['app', 'chronyd', $app->app_id, $vars['source']]);
$array = [
    'adjusted_offset' => ['descr' => 'Adjusted'],
    'measured_offset' => ['descr' => 'Measured'],
    'offset' => ['descr' => 'Estimated'],
    'estimated_error' => ['descr' => 'Est. error'],
    'stddev' => ['descr' => 'Std dev'],
];

$rrd_list = [];
foreach ($array as $ds => $var) {
    $rrd_list[$i]['filename'] = $rrd_filename;
    $rrd_list[$i]['descr'] = $var['descr'];
    $rrd_list[$i]['ds'] = $ds;
    $rrd_list[$i]['colour'] = \App\Facades\LibrenmsConfig::get("graph_colours.$colours.$i");
    $i++;
}

require 'includes/html/graphs/generic_multi_line.inc.php';
