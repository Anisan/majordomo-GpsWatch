<?php

if ($this->owner->name=='panel') {
    $out['CONTROLPANEL']=1;
}

$table_name='gw_device';
$rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

if ($this->mode=='update') {
    $ok=1;
    if ($this->tab=='') {
        if (!$rec['ID'])
        {
            global $device_id;
            $rec['DEVICE_ID']=$device_id;
        }
        global $name;
        $rec['NAME']=$name;
        global $linked_object;
        $rec['LINKED_OBJECT']=$linked_object;
        
        //UPDATING RECORD
        if ($ok) {
            if ($rec['ID']) {
                SQLUpdate($table_name, $rec); // update
            } else {
                $new_rec=1;
                $rec['ID']=SQLInsert($table_name, $rec); // adding new record
                $id=$rec['ID'];
            }
            $out['OK']=1;
        } else {
            $out['ERR']=1;
        }
    }
    $ok=1;
}



outHash($rec, $out);

require(DIR_MODULES . $this->name . '/device_settings.inc.php');

?>