<?php

class GpsWatchServer
{
    protected $socket;
    protected $clients;
    protected $changed;
    protected $protocol;
    protected $proxy;
    protected $proxyport;
    
    function __construct($host = 'localhost', $port = 2902, $proxy = '52.28.132.157',$proxyport = 8001)
    {
        $this->clients = [];
        
        $this->proxy = $proxy;
        $this->proxyport = $proxyport;
        
        set_time_limit(0);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
        }
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        //socket_set_nonblock($socket);
        //bind socket to specified host
        if (socket_bind($socket, $host, $port) === false) {
            echo "Не удалось выполнить socket_bind(): причина: " . socket_strerror(socket_last_error($socket)) . "\n";
        }
        //listen to port
        if (socket_listen($socket) === false) {
            echo "Не удалось выполнить socket_listen(): причина: " . socket_strerror(socket_last_error($socket)) . "\n";
        }
        $this->socket = $socket;
        
        include_once(DIR_MODULES . 'app_GpsWatch/Protocol.php');
        $this->protocol = new Protocol();
        
    }
    
    function __destruct()
    {
        foreach($this->clients as $client) {
            socket_close($client);
        }
        socket_close($this->socket);
    }
    
    function runCycle()
    {
        while(true) {
            $this->run();
            //echo "cycle\n";
        }
    }
    
    function run()
    {
        if ($this->waitForChange()>0)
        {
            $this->checkNewClients();
            $this->checkMessageRecieved();
            $this->checkDisconnect();
        }
    }
    
    function checkDisconnect()
    {
        foreach ($this->changed as $changed_socket) {
            $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
            if ($buf !== false) { // check disconnected client
                continue;
            }
            // remove client for $clients array
            $key_socket = $this->findBySocket($changed_socket);
            $id = $this->clients[$key_socket]["id"];
            $response = 'Client #'.$id ." ". $this->clients[$key_socket]["ip"] . ' has disconnected\n';
            // change online status
            $device = SQLSelectOne("SELECT * FROM gw_device WHERE DEVICE_ID='".$id."'");
            if ($device['DEVICE_ID']) {
                $device['ONLINE']=0;
                SQLUpdate("gw_device", $device); // update
            }
            unset($this->clients[$key_socket]);
            echo ($response);
        }
    }
    
    function checkMessageRecieved()
    {
        foreach ($this->changed as $key => $socket) {
            $buffer = null;
            socket_getpeername($socket, $ip);
            while(socket_recv($socket, $buffer, 1024, 0) >= 1) {
                $this->processingMessage($socket, $buffer);
                unset($this->changed[$key]);
                break;
            }
        }
    }
    
    function createMessage($id,$msg)
    {
        $len = strlen($msg);
        $lenhex = str_pad(dechex($len), 4, "0", STR_PAD_LEFT);
        return "[SG*".$id."*".$lenhex."*".$msg."]";
    }
    
    function waitForChange()
    {
        //reset changed
        $this->changed = array_merge([$this->socket], array_column($this->clients, 'socket'),array_column($this->clients, 'proxy'));
        //variable call time pass by reference req of socket_select
        $null = null;
        //this next part is blocking so that we dont run away with cpu
        return socket_select($this->changed, $null, $null, 5);
    }
    
    function checkNewClients()
    {
        if (!in_array($this->socket, $this->changed)) {
            return; //no new clients
        }
        $socket_new = socket_accept($this->socket); //accept new socket
        $first_line = socket_read($socket_new, 1024);
        socket_getpeername($socket_new, $ip);
        echo ("Client " . $ip . " has connected\n");
        //echo ("New client says: " . trim($first_line) . PHP_EOL);
        $proxy = $this->createSocket();
        //@socket_write($proxy,$first_line,strlen($first_line));
        $this->clients[] = array( "socket" => $socket_new, "ip" => $ip, "id" => "", "proxy"=>$proxy);
        $this->processingMessage($socket_new, $first_line);
        
        unset($this->changed[0]);
    }
    
    function createSocket()
    {
        /* Получаем порт сервиса WWW. */
        $service_port = $this->proxyport;

        /* Получаем  IP адрес целевого хоста. */
        $address = gethostbyname($this->proxy);

        /* Создаём  TCP/IP сокет. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
        } else {
            echo "OK.\n";
        }

        echo "Пытаемся соединиться с '$address' на порту '$service_port'...";
        $result = socket_connect($socket, $address, $service_port);
        if ($result === false) {
            echo "Не удалось выполнить socket_connect().\nПричина: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
        } else {
            echo "OK.\n";
        }
        return $socket;
    }
    
    function processingMessage($socket, $buffer)
    {
        socket_getpeername($socket, $ip);
        $isWatch = TRUE;
        $key_socket = $this->findByIp($ip);
        if (is_null($key_socket)) $isWatch = FALSE;
                
        echo "Get message from ".$ip;
        if (!$isWatch)
        {
            echo " server".PHP_EOL;
            $key_socket = $this->findByProxy($socket);
            echo ("Server #". $this->clients[$key_socket]["id"] ." ".  $ip . " recv ". trim($buffer) . PHP_EOL);
            $this->sendMessage($this->clients[$key_socket]["socket"] , $buffer);
            return;
        }
        echo " watch".PHP_EOL;
            
        echo ("Client #". $this->clients[$key_socket]["id"] ." ".  $ip . " recv ". trim($buffer) . PHP_EOL);
        @socket_write($this->clients[$key_socket]["proxy"],$buffer,strlen($buffer)); 
        
        $re = '/\[(.+)\*(\d+)\*(\w+)\*(.+)\]/';
        $str = trim($buffer);
                
        if (preg_match_all($re, $str, $matches,PREG_SET_ORDER))
        {
            // Print the entire match result [SG*8800000015*0002*LK][SG*8800000015*0002*LK]
            echo ("Count command: ". count($matches).PHP_EOL);
                    //print_r($matches);
                    foreach ($matches as $match)
                    {
                        $id = $match[2];
                        $len = hexdec($match[3]);
                        $cmd = $match[4];
                        $this->clients[$key_socket]["id"] = $id;
                        echo ("ID:".$id." Lenght:".$len." Command:".$cmd);
                        // record device
                        $device = SQLSelectOne("SELECT * FROM gw_device WHERE DEVICE_ID='$id'");
                        if ($device['DEVICE_ID']) {
                            // update IP
                            $device['ONLINE']=1;
                            $device['LAST_ONLINE']=date('Y/m/d H:i:s');;
                            $device['LAST_IP']=$ip;
                            SQLUpdate("gw_device", $device); // update
                        } else {
                            $device['DEVICE_ID']=$id;
                            $device['NAME']=$id;
                            $device['CREATED']=date('Y/m/d H:i:s');;
                            $device['ONLINE']=1;
                            $device['LAST_ONLINE']=date('Y/m/d H:i:s');;
                            $device['LAST_IP']=$ip;
                            $device['ID'] = SQLInsert("gw_device", $device); // adding new record
                        }
                        $this->clients[$key_socket]["device_id"] = $device['ID'];
                        $traffic = SQLSelectOne("select * from gw_traffic where date(DATE_TRAFFIC)=date(now()) and DEVICE_ID=".$device['ID']);
                        if ($traffic['DEVICE_ID']) {
                            $traffic['DOWNLOAD']+=strlen($str);
                            SQLUpdate("gw_traffic", $traffic); // update
                        } else {
                            $traffic['DEVICE_ID']=$device['ID'];
                            $traffic['DATE_TRAFFIC']=date('Y/m/d H:i:s');;
                            $traffic['DOWNLOAD']=strlen($str);
                            $traffic['UPLOAD']=0;
                            SQLInsert("gw_traffic", $traffic);
                        }
                        //todo check len command
                        if (strlen($cmd) == $len)
                        {
                            echo (" OK".PHP_EOL);
                            //todo proc command
                            $res = $this->protocol->processCommand($id,$cmd);
                            if ($res!=""){
                                $msg = $this->createMessage($id,$res);
                                echo ("Client #". $this->clients[$key_socket]["id"] ." ".  $ip . " sent ". $msg . PHP_EOL);
                                //$this->sendMessage($socket , $msg . PHP_EOL);
                            }
                        }
                        else
                            echo (" ERROR - Wrong lenght (". strlen($cmd) .")".PHP_EOL);
                    }
                }
    }
    
    function findBySocket($socket)
    {
        foreach ($this->clients as $key => $childarray)
        {
            if ($childarray["socket"] == $socket)
            {
                return $key;
            }
        }
        return NULL;
    }
    function findByIp($ip)
    {
        foreach ($this->clients as $key => $childarray)
        {
            if ($childarray["ip"] == $ip)
            {
                return $key;
            }
        }
        return NULL;
    }
    function findByProxy($socket)
    {
        foreach ($this->clients as $key => $childarray)
        {
            if ($childarray["proxy"] == $socket)
            {
                return $key;
            }
        }
        return NULL;
    }
    function sendMessageById($id, $msg)
    {
        $client = $this->clients[$this->id_clients[$id]];
        $this->sendMessage($client,$msg);
        return TRUE;
    }
    
    
    function sendMessage($client, $msg)
    {
        $key_socket = $this->findBySocket($client);
        $id = $this->clients[$key_socket]["device_id"];
        $traffic = SQLSelectOne("select * from gw_traffic where date(DATE_TRAFFIC)=date(now()) and DEVICE_ID='$id'");
        if ($traffic['DEVICE_ID']) {
            $traffic['UPLOAD']+=strlen($msg);
            SQLUpdate("gw_traffic", $traffic); // update
        }
        @socket_write($client,$msg,strlen($msg));
        return TRUE;
    }
}

?>