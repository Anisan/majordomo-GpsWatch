<?php
$table_name='gw_settings';
$rec=SQLSelectOne("SELECT * FROM $table_name WHERE DEVICE_ID='$id'");

$phonebook = array();
for ($i = 1; $i <= 10; $i++) {
    $phone = array();
    $phone["ID"] = $i;
    $phone["NAME"] = "Name ".$i;
    $phone["PHONE"] = "Phone ".$i;
    $phonebook [] = $phone;
}
$out["PHONEBOOK"] = $phonebook;

$logCount=SQLSelectOne("SELECT count(*) as TOTAL FROM gw_log WHERE DEVICE_ID='$id'");
$out["LOGCOUNT"] = $logCount["TOTAL"];

$sos = explode(",",$rec['SOS']);
foreach($sos as $i =>$key) {
    $out["SOS".($i+1)] = $key;
}

unset ($rec['ID']);
unset ($rec['DEVICE_ID']);
outHash($rec, $out);

?>