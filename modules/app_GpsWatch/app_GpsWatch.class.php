<?php
/**
* GpsWatch 
* @package project
* @author Eraser <eraser1981@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 13:12:17 [Dec 07, 2016])
*/
//
//
class app_GpsWatch extends module {
    
    protected $server;
/**
* app_SmartBabyWatch
*
* Module class constructor
*
* @access private
*/
function __construct() {
  $this->name="app_GpsWatch";
  $this->title="Gps Watch";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB'] = $this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 global $sendCommand;
 if ($sendCommand)
 {
    header("HTTP/1.0: 200 OK\n");
    header('Content-Type: text/html; charset=utf-8');
    global $id;
    global $cmd;
    global $param;
    if ($cmd == 'sos') $this->setSosPhone($id,$param);
    if ($cmd == 'msg') $this->sendMessage($id,$param);
    if ($cmd == 'flower') $this->setFlower($id,$param);
    if ($cmd == 'update') $this->setUpdate($id,$param);
    if ($cmd == 'location') $this->position($id);
    if ($cmd == 'find') $this->findWatch($id);
    if ($cmd == 'command') $this->addCommand($id,$param);
    if ($cmd == 'profile') $this->setProfile($id,$param);
    echo "Ok";
    exit;
 }
 $out['HOST']=$this->config['HOST'];
 if (!$out['HOST']) { $out['HOST']='0.0.0.0'; }
 $out['PORT']=$this->config['PORT'];
 if (!$out['PORT']) { $out['PORT']='2902'; }
 $out['ENABLE_PROXY']=$this->config['ENABLE_PROXY'];
 $out['HOST_PROXY']=$this->config['HOST_PROXY'];
 if (!$out['HOST_PROXY']) { $out['HOST_PROXY']='52.28.132.157'; }
 $out['PORT_PROXY']=$this->config['PORT_PROXY'];
 if (!$out['PORT_PROXY']) { $out['PORT_PROXY']='8001'; }
 $out['SCRIPTS']=SQLSelect("SELECT ID, TITLE FROM scripts ORDER BY TITLE");
 $out['SCRIPT_NEWVOICE_ID'] = $this->config['SCRIPT_NEWVOICE_ID'];    
 if ($this->view_mode=='update_settings') {
   global $host;
   $this->config['HOST']=$host;
   global $port;
   $this->config['PORT']=$port;
   global $enableProxy;
   $this->config['ENABLE_PROXY']=$enableProxy;
   global $hostProxy;
   $this->config['HOST_PROXY']=$hostProxy;
   global $portProxy;
   $this->config['PORT_PROXY']=$portProxy;
   global $script_newvoice_id;
   $this->config['SCRIPT_NEWVOICE_ID'] = $script_newvoice_id;
            
   $this->saveConfig();
   setGlobal('cycle_GpsWatch','restart');
   $this->redirect("?");
 }
 if($this->view_mode == 'device_edit') {
    $this->edit_device($out, $this->id);
 }
 if($this->view_mode == 'device_delete') {
    $this->delete_device($this->id);
    $this->redirect("?");
 }
 if($this->view_mode == '' || $this->view_mode == 'search_ms') {
    if($this->tab == 'device') {
        $this->gw_device($out);
    }  else {
        $this->gw_device($out);
    }
 }
}

function gw_device(&$out) {
    require(DIR_MODULES . $this->name . '/gw_device.inc.php');
}

function edit_device(&$out, $id) {
    require(DIR_MODULES . $this->name . '/device_edit.inc.php');
}
function delete_device($id) {
    $rec = SQLSelectOne("SELECT * FROM gw_device WHERE ID='$id'");
    // some action for related tables
    SQLExec("DELETE FROM gw_device WHERE ID='" . $rec['ID'] . "'");
    SQLExec("DELETE FROM gw_settings WHERE ID='" . $rec['ID'] . "'");
    SQLExec("DELETE FROM gw_traffic WHERE DEVICE_ID='" . $rec['ID'] . "'");
    SQLExec("DELETE FROM gw_cmd WHERE DEVICE_ID='" . $rec['ID'] . "'");
    SQLExec("DELETE FROM gw_log WHERE DEVICE_ID='" . $rec['ID'] . "'");
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
  $this->getConfig();
  require(DIR_MODULES.$this->name.'/usual.inc.php');
}


function update_settings($id,$property,$value)
{
    $rec = SQLSelectOne("SELECT * FROM gw_settings WHERE DEVICE_ID='$id'");
    $rec[$property]=$value;
    if ($rec['ID']) {
        SQLUpdate('gw_settings', $rec); // update
    } else {
        $rec['DEVICE_ID'] = $id;
        $rec['ID']=SQLInsert('gw_settings', $rec); // adding new record
        $id=$rec['ID'];
    }  
}

function addCommand($id,$data)
{
    $cmd = array();
    $cmd['DEVICE_ID']=$id;
    $cmd['DATA']=$data;
    $cmd['CREATED']=date('Y/m/d H:i:s');
    $cmd['ID'] = SQLInsert("gw_cmd", $cmd);
    return $cmd['ID'];
}

function sendMessage($id,$msg)
{
    echo $msg.PHP_EOL;
    $out = bin2hex(iconv('UTF-8', 'UTF-16BE', $msg));
    //echo $out.PHP_EOL;
    return $this->addCommand($id,"MESSAGE,".$out);
}

function sendVoice($id,$file)
{
    echo $file.PHP_EOL;
    $data = file_get_contents($file);
    $out = str_replace("\x7D", "\x7D\x01", $data);
    $out = str_replace("\x5B", "\x7D\x02", $out);
    $out = str_replace("\x5D", "\x7D\x03", $out);
    $out = str_replace("\x2C", "\x7D\x04", $out);
    $out = str_replace("\x2A", "\x7D\x05", $out);
    return $this->addCommand($id,"TK,".$out);
}

function setUpdate($id,$second)
{
    $this->update_settings($id,"UPDATE_INTERVAL",$second);
    return $this->addCommand($id,"UPLOAD,".$second);
}
function setSosPhone($id,$phones)
{
    $this->update_settings($id,"SOS",$phones);
    return $this->addCommand($id,"SOS,".$phones);
}
function setProfile($id,$profile)
{
    $this->update_settings($id,"PROFILE",$profile);
    return $this->addCommand($id,"PROFILE,".$profile);
}

function setFlower($id,$point)
{
    $this->update_settings($id,"FLOWER",$point);
    return $this->addCommand($id,"FLOWER,".$point);
}

function position($id)
{
    return $this->addCommand($id,"CR");
}

function findWatch($id)
{
    return $this->addCommand($id,"FIND");
}
/**
* SERVER
*
* 
*
* @access private
*/
function initServer() {
    SQLExec("UPDATE gw_device SET ONLINE='0', ONHAND='0'");
    $this->getConfig();
    $host=$this->config['HOST'];
    $port=$this->config['PORT'];
    include_once(DIR_MODULES . 'app_GpsWatch/server.php');
    $this->server = new GpsWatchServer($host, $port, $this->config['ENABLE_PROXY'], $this->config['HOST_PROXY'], $this->config['PORT_PROXY'],$this->config);

}
function cycle() {
    $this->server->run();
    //todo check send command
    
} 

/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }
 
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
function dbInstall($data) {
    $data = <<<EOD
 gw_device: ID int(10) unsigned NOT NULL auto_increment
 gw_device: DEVICE_ID varchar(10) NOT NULL
 gw_device: NAME varchar(255) NOT NULL DEFAULT ''
 gw_device: MEMBER_ID int(10) NOT NULL DEFAULT '1'
 gw_device: CREATED datetime
 gw_device: ONLINE int(3) unsigned NOT NULL DEFAULT '0' 
 gw_device: LAST_ONLINE datetime
 gw_device: LAST_IP text
 gw_device: BATTERY int(3) unsigned NOT NULL DEFAULT '0' 
 gw_device: ONHAND int(3) unsigned NOT NULL DEFAULT '0'
 gw_device: LINKED_OBJECT text
 
 gw_settings: ID int(10) unsigned NOT NULL auto_increment
 gw_settings: DEVICE_ID int(10) NOT NULL
 gw_settings: UPDATE_INTERVAL int(10)
 gw_settings: FLOWER int(10)
 gw_settings: SOS TEXT
 gw_settings: PROFILE int(3)
 
 gw_traffic: ID int(10) unsigned NOT NULL auto_increment
 gw_traffic: DEVICE_ID int(10) NOT NULL
 gw_traffic: DATE_TRAFFIC datetime
 gw_traffic: DOWNLOAD int(10) unsigned NOT NULL 
 gw_traffic: UPLOAD int(10) unsigned NOT NULL 
 
 gw_cmd: ID int(10) unsigned NOT NULL auto_increment
 gw_cmd: DEVICE_ID int(10) NOT NULL
 gw_cmd: DATA text
 gw_cmd: CREATED datetime
 gw_cmd: SENDED datetime
 
 gw_log: ID int(10) unsigned NOT NULL auto_increment
 gw_log: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 gw_log: ADDED datetime
 gw_log: LAT float DEFAULT '0' NOT NULL
 gw_log: LON float DEFAULT '0' NOT NULL
 gw_log: ALT float DEFAULT '0' NOT NULL
 gw_log: DIRECTION float DEFAULT '0' NOT NULL
 gw_log: PROVIDER varchar(30) NOT NULL DEFAULT ''
 gw_log: SPEED float DEFAULT '0' NOT NULL
 gw_log: BATTLEVEL int(3) NOT NULL DEFAULT '0'
 gw_log: CHARGING int(3) NOT NULL DEFAULT '0'
 gw_log: ACCURACY float DEFAULT '0' NOT NULL
EOD;
    parent::dbInstall($data);

}
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDA3LCAyMDE2IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
