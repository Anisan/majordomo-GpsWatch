<?php

function sizeFilter( $bytes )
{
    $label = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $label ) -1 ); $bytes /= 1024, $i++ );
    return( round( $bytes, 2 ) . " " . $label[$i] );
}

global $session;

global $did;
if ($did!='') {
    $qry.=" AND device_ID LIKE '%".DBSafe($did)."%'";
    $out['device_ID']=$did;
}

global $name;
if ($name!='') {
    $qry.=" AND NAME LIKE '%".DBSafe($name)."%'";
    $out['NAME']=$name;
}

// FIELDS ORDER
global $sortby_device;
if (!$sortby_device) {
    $sortby_device=$session->data['gw_device_sort'];
} else {
    if ($session->data['gw_device_sort']==$sortby_device) {
        if (Is_Integer(strpos($sortby_device, ' DESC'))) {
            $sortby_device=str_replace(' DESC', '', $sortby_device);
        } else {
            $sortby_device=$sortby_device." DESC";
        }
    }
    $session->data['gw_device_sort']=$sortby_device;
}
if (!$sortby_device) $sortby_device="NAME";
$out['sortby']=$sortby_device;

// SEARCH RESULTS
$res=SQLSelect("SELECT *,gw_device.ID as DEV_ID,gw_device.DEVICE_ID as WATCH_ID FROM gw_device left join (select * from gw_traffic where date_traffic>CURDATE()) tr on tr.DEVICE_ID=gw_device.ID ORDER BY ".$sortby_device);
if ($res[0]['DEV_ID']) {
    paging($res, 20, $out); // search result paging
    colorizeArray($res);
    $total=count($res);
    for($i=0;$i<$total;$i++) {
        // some action for every record if required
        if (!is_null($res[$i]["DOWNLOAD"]))
            $res[$i]["DOWNLOAD"] = sizeFilter($res[$i]["DOWNLOAD"]);
        if (!is_null($res[$i]["UPLOAD"]))
            $res[$i]["UPLOAD"] = sizeFilter($res[$i]["UPLOAD"]);
    }
    $out['RESULT']=$res;
}
?>