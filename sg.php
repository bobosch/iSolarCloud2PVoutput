<?php
//die;
$cmd = 'python3 ' . $cwd . "sg.py '" . json_encode($sg_opt) . "'";
debug($cmd);
ob_start();
passthru($cmd);
$ret = ob_get_clean();

// Check data
$out = json_decode($ret, true);
debug($out);
/*
array(4) {
  ["req_serial_num"]=>
  string(32) "2023121193e144fe9d5cdae36f127a39"
  ["result_code"]=>
  string(1) "1"
  ["result_msg"]=>
  string(7) "success"
  ["result_data"]=>
  array(1) {
    ["5132189_1_1_1"]=>
    array(4) {
      ["p1"]=>
      array(30) {
        [20231130031500]=>
        string(3) "0.0"
        [20231130032000]=>
        string(3) "0.0"
*/
$sg_data = $out['result_data'];
if(!is_array($sg_data)) {
	echo 'No valid data from sg.py!' . PHP_EOL;
	var_dump($ret);
	die;
}

$data = array();
foreach($sg_data[$config['SG_key']] as $point_name => $values) {
	foreach($values as $timestamp => $value) {
		$data[$timestamp][$point_name] = floatval($value);
	}
}
?>