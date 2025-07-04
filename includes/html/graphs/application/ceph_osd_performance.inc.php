<?php

$ds_in = 'apply_ms';
$ds_out = 'commit_ms';

$in_text = 'Apply';
$out_text = 'Commit';

$rrd_filename = Rrd::name($device['hostname'], ['app', 'ceph', $app->app_id, 'osd', $vars['osd']]);

$colour_area_in = 'FF3300';
$colour_line_in = 'FF0000';
$colour_area_out = 'FF6633';
$colour_line_out = 'CC3300';

$colour_area_in_max = 'FF6633';
$colour_area_out_max = 'FF9966';

$unit_text = 'Miliseconds';

require 'includes/html/graphs/generic_duplex.inc.php';
