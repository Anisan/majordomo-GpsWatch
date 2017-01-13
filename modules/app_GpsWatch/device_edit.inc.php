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
        global $user_id;
        $rec['USER_ID']=$user_id;
    }
    global $name;
    $rec['NAME']=$name;
        
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
  
?>
