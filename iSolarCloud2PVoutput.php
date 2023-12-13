<?php
// ***** Configuration *****
$cwd = dirname(__FILE__) . '/';
$tz = date_default_timezone_get();

$interval = 5 * 60; // 5 minutes / 300 seconds
$get_date = false;

// Load configuration
$content = file_get_contents($cwd . 'config.json');
if ($content) {
	$config = json_decode($content, true);
} else {
	$config = array();
}
// default configuration
if (!isset($config['SG_key'])) $config['SG_key'] = '5132189_1_1_1';
if (!isset($config['SG_ID'])) $config['SG_ID'] = '5132189';
if (!isset($config['PVO_key'])) $config['PVO_key'] = '0000000000000000000000000000000000000000';
if (!isset($config['PVO_ID'])) $config['PVO_ID'] = '00000';
if (!isset($config['start_date'])) $config['start_date'] = '202301010000';

$sg_point_names = array(
	'p1',  // kWh Daily Yield
	'p2',  // kWh Total Yield
	'p3',  // h   Total On-grid Running Time
	'p4',  // ℃  Internal Air Temperature
	'p5',  // V   MPPT1 Voltage
	'p6',  // A   MPPT1 Current
	'p7',  // V   MPPT2 Voltage
	'p8',  // A   MPPT2 Current
	'p14', // kW  Total DC Power
);

// PVoutput.org allows only 30 status updates at once
$end_ts = strtotime($config['start_date']) + 29 * $interval;
if ($end_ts > time()) $end_ts = floor(time() / $interval) * $interval; // align to interval
$end_date = date('YmdHi', $end_ts);

// ***** Get date from iSolarCloud *****
$sg_opt = new stdClass();
$sg_opt->ps_key = implode(',', array_fill(0, count($sg_point_names), $config['SG_key']));
$sg_opt->points = implode(',', $sg_point_names);
$sg_opt->minute_interval = sprintf("%02d", $interval / 60);
$sg_opt->start_time_stamp = $config['start_date'] . '00';
$sg_opt->end_time_stamp = $end_date . '00';
$sg_opt->ps_id = $config['SG_ID'];

require('sg.php');

// ***** Prepare data *****
$pvo_array = array();
foreach($data as $ts => $values) {
	foreach($sg_point_names as $sg_point_name) {
		if(!isset($values[$sg_point_name])) $values[$sg_point_name] = 0;
	}
	// Valid when Total On-grid Running Time available
	if($values['p3']) {
		$get_date = substr($ts, 0, 12);
		// Send to pvoutput.org when string voltage or current or total DC power available
		if($values['p5'] || $values['p6'] || $values['p7'] || $values['p8'] || $values['p14']) {
			$pvo_status = array(
				substr($ts, 0, 8),
				substr($ts, 8, 2) . ':' . substr($ts, 10, 2),
				$values['p1'],
				$values['p14'],
				'',
				'',
				$values['p4'],
				'',
				$values['p5'],
				$values['p6'],
				$values['p7'],
				$values['p8'],
			);
			$pvo_array[] = implode(',', $pvo_status);
		}
	}
}
debug($pvo_array);

// ***** Send data to PVoutput.org *****
// https://pvoutput.org/help/api_specification.html
if($pvo_array) {
	$ch = curl_init('https://pvoutput.org/service/r2/addbatchstatus.jsp');
	curl_setopt( $ch, CURLOPT_POST, true);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, 'data=' . implode(';', $pvo_array));
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt( $ch, CURLOPT_HTTPHEADER, ['X-Pvoutput-Apikey: ' . $config['PVO_key'], 'X-Pvoutput-SystemId: ' . $config['PVO_ID']]);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
	// curl_setopt( $ch, CURLOPT_VERBOSE, true);
	$response = curl_exec( $ch );
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if($http_code != 200) {
		echo 'Error uploading data to pvoutput.org!' . PHP_EOL;
		var_dump($response);
		die;
	}
}

// ***** Save configuration *****
if($get_date) {
	$config['start_date'] = $get_date;
	file_put_contents($cwd . 'config.json', json_encode($config));
}

function debug($data) {
//	var_dump($data);
}
?>