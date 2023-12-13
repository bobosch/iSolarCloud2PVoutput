<?php
$cmd = $cwd . "GoSungrow api get queryMutiPointDataList '" . json_encode($sg_opt) . "'";
debug($cmd);
ob_start();
passthru($cmd);
$ret = ob_get_clean();

// Check data
$out = json_decode($ret, true);
debug($out);
$sg_data = $out['data'];
/*
array(1) {
  ["2023-02-02T12:00:00Z"]=>
  array(3) {
    ["timestamp"]=>
    string(14) "20230202120000"
    ["ps_key"]=>
    string(13) "5132189_1_1_1"
    ["points"]=>
    array(7) {
      ["p1"]=>
      int(2400)
      ["p14"]=>
      int(574)
      ["p4"]=>
      float(34.6)
      ["p5"]=>
      float(423.4)
      ["p6"]=>
      float(0.3)
      ["p7"]=>
      float(466.5)
      ["p8"]=>
      float(0.9)
    }
  }
}
*/
if(!is_array($sg_data)) {
	echo 'No valid data from GoSungrow!' . PHP_EOL;
	var_dump($ret);
	die;
}

$data = array();
foreach($sg_data as $ts => $sg_status) {
	$data[$sg_status['timestamp']] = $sg_status['points'];
}
?>