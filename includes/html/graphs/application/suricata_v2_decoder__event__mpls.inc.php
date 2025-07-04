<?php

$name = 'suricata';
$unit_text = 'MPLS pkt/s';
$colours = 'psychedelic';
$descr_len = 20;

if (isset($vars['sinstance'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__bad_label_implicit_null']);
    $decoder__event__mpls__bad_label_reserved_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__bad_label_reserved']);
    $decoder__event__mpls__bad_label_router_alert_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__bad_label_router_alert']);
    $decoder__event__mpls__header_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__header_too_small']);
    $decoder__event__mpls__pkt_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__pkt_too_small']);
    $decoder__event__mpls__unknown_payload_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__mpls__unknown_payload_type']);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__bad_label_implicit_null']);
    $decoder__event__mpls__bad_label_reserved_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__bad_label_reserved']);
    $decoder__event__mpls__bad_label_router_alert_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__bad_label_router_alert']);
    $decoder__event__mpls__header_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__header_too_small']);
    $decoder__event__mpls__pkt_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__pkt_too_small']);
    $decoder__event__mpls__unknown_payload_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__mpls__unknown_payload_type']);
}

$rrd_list = [
    [
        'filename' => $rrd_filename,
        'descr' => 'Bad Lbl Impl Null',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__mpls__bad_label_reserved_rrd_filename,
        'descr' => 'Bad Label Res',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__mpls__bad_label_router_alert_rrd_filename,
        'descr' => 'Bad Label Rtr Alrt',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__mpls__header_too_small_rrd_filename,
        'descr' => 'Hdr Too Small',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__mpls__pkt_too_small_rrd_filename,
        'descr' => 'Pkt Too Small',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__mpls__unknown_payload_type_rrd_filename,
        'descr' => 'Unknown Payload Type',
        'ds' => 'data',
    ],
];

require 'includes/html/graphs/generic_multi_line.inc.php';
