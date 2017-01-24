<?php

class Protocol
{
    
    function __construct()
    {
        
    }
    
    function __destruct()
    {

    }
    
    function processCommand($id,$command){
        $cmd = $command;
        $data = "";
        $pos=strpos($command, ",");
        if ($pos)
        {
            $cmd = substr($command, 0, $pos);
            $data = substr($command, $pos+1);
        }
        $res ="";
        //echo ("Command:".$cmd." Data:".$data.PHP_EOL);
        switch ($cmd) {
            case "LK":
                $res = $this->commandLink($id,$data);
                break;
            case "UD":
                $res = $this->commandLocation($id,$data);
                break;
            case "AL": // alarm
            //todo short data
                $res = $this->commandLocation($id,$data);
                break;
            case "TK":
                // voice message
                $fp = fopen('/home/www/files/'.date('Y/m/d H:i:s').'.txt', 'w');
                fwrite($fp, $data);
                fclose($fp);
                break;
            case "TKQ":
            //todo
                $res = "TKQ";
                break;
            case "TKQ2":
            //todo
                $res = "TKQ2";
                break;
        }
        //echo $res.PHP_EOL;
        return $res;
    }
    
    function commandLink($id,$data){
        if ($data!="")
        {
            $parts = explode(",",$data);
            //steps,rolling times,the percentage of battery amount
            $steps = $parts[0];
            $rolling_times = $parts[1];
            $battery = $parts[2];
            $device = SQLSelectOne("SELECT * FROM gw_device WHERE DEVICE_ID='".$id."'");
            if ($device['DEVICE_ID']) {
                $device['BATTERY'] = $battery;
                SQLUpdate("gw_device", $device); // update
                // todo set link object
                if (isset($device['LINKED_OBJECT']))
                    setGlobal($device['LINKED_OBJECT'].".battery",$battery);
                
            }
        }
        return "LK";
    }
    
    function commandLocation($id,$data){
        //todo parse Location data
        $this->parseLocationData($id,$data);
        // Platform reply: Nodata
        return "";
    }
    
    function getBit($data, $num)
    {
        return ($data >> $num) & 1;
    }
    
    function parseLocationData($id,$data){
/* 0 Date	120414	 (day month year)2014 year 4 month 12 day
1 Time	101930	 (Time minute seconds)10 hour 19 minute 30 seconds
2 If position function or not	A	A:Position V:No position 
3 Latitude	22.564025	Definite in DD.DDDDDD format,The latitude is :22.564025.
4 Latitude identification	N	N represent North latitude ,S represents South latitude.
5 Longitude	113.242329	Definite in DDD.DDDDDD format ,The longitude is :113.242329.
6 Longitude	E	E represents east longitude ,W represents west longitude
7 Speed	5.21	5.21miles/hour.
8 Direction	152	The direction is in 152 degree.
9 Alititude	100	The unit is meter
10 Satellite	9	Represent the satellite number
11 GSM signal strength	100	Means the current GSM signal strength (0-100)
12 Battery	90	Means the current battery grade percentage.
13 Pedometer	1000	The steps is 1000
14 Rolling times	50	Rolling number is 50 times.
15 The terminal statement	00000000	Represents in Hexadecimal string,and the meaning isas following:The high 16bit is alarming,the low 16 bit is statement.
Bit(from 0)        Meaning(1 effective)
0                  Low-battery
1                  Out of fence 
2                  In fence
3                  Bracelet statement
16                 SOS alarming
17                 Low-battery alarming
18                 Out of fence alarming
19                 In fence alarming
20                 Watch-take-off alarming
16 Base station number	4	Report the station number,0 is no reporting
Connect to station ta	1	GSM time delay
MCC country code	460	460 represents china
MNC network number	02	02 represents china mobile
The area code to connect base station	10133	Area code
Serial number to connect base station	5173	Base station serial code
The signal strength	100	Signal strength
The nearby station1  area code	10133	Area code
The nearby station 1 serial number	5173	Station serial number
The nearby base station 1 signal strength	100	Signal strength
The nearby station2  area code	10133	Area code
The nearby station 2 serial number	5173	Station serial number
The nearby base station 2 signal strength	100	Signal strength
The nearby station3  area code	10133	Area code
The nearby station 3 serial number	5173	Station serial number
The nearby base station 3 signal strength	100	Signal strength
*/
        $parts = explode(",",$data);
        //print_r($parts);
        $dt = date_parse_from_format("dmy His", $parts[0]." ".$parts[1]);
        $dtime = DateTime::createFromFormat("dmy His", $parts[0]." ".$parts[1]);
        $timestamp = $dtime->getTimestamp();
        
        $provider = "gsm";
        $countGsmStation = $parts[16];
        $indWifiCount = 16 + 3 + 3*$countGsmStation + 1;
        $countWifiPoint = $parts[$indWifiCount];
        if ($countWifiPoint > 0)$provider = "wifi";
        if ($parts[2] == "A") $provider = "gps";
        
        $lat = $parts[3];
        if ($parts[4] == "S") $lat = -$lat;
        $lon = $parts[5];
        if ($parts[6] == "W") $lon = -$lon;
        $batt = $parts[12];
        
        $speed = 1.60934 * floatval($parts[7]); // 7 Speed	5.21	5.21miles/hour.
        $dir = $parts[8]; //8 Direction	152	The direction is in 152 degree.
        $alt = $parts[9]; //9 Alititude	100	The unit is meter
        
        $device = SQLSelectOne("SELECT * FROM gw_device WHERE DEVICE_ID='$id'");

        $statement = hexdec($parts[15]);
        $onhand = !$this->getBit($statement,3);
        $device["ONHAND"] = $onhand;
        $device["BATTERY"] = $batt;
        SQLUpdate("gw_device", $device);
        
        if (isset($device['LINKED_OBJECT']))
        {
            setGlobal($device['LINKED_OBJECT'].".onhand",$onhand);
            setGlobal($device['LINKED_OBJECT'].".battery",$batt);
            setGlobal($device['LINKED_OBJECT'].".sos_alarm",$this->getBit($statement,16));
        }

        
        $log = array();
        $log["DEVICE_ID"] = $device['ID'];
        $log["ADDED"] = date('Y/m/d H:i:s',$timestamp);
        $log["LAT"] = $lat;
        $log["LON"] = $lon;
        $log["ALT"] = $alt;
        $log["DIRECTION"] = $dir;
        $log["SPEED"] = $speed;
        $log["BATTLEVEL"] = $batt;
        $log["PROVIDER"] = $provider;
        //print_r($log);
        SQLInsert("gw_log", $log);
        
        //module gps tracker
        $url = "http://127.0.0.1/gps.php?";
        $url .= "latitude=".$lat;
        $url .= "&longitude=".$lon;
        $url .= "&deviceid=".$id;
        $url .= "&battlevel=".$batt;
        $url .= "&provider=".$provider;
        $url .= "&altitude=".$alt;
        $url .= "&bearing=".$dir;
        $url .= "&speed=".$speed;
            
        /*req.append("&accuracy=");
                req.append("&charging=1");
                req.append("&charging=0");
        */
        getUrl($url);
        
    }
}

?>