<?php

class SocketServer {

    protected $socket;
    protected $clients = [];
    protected $changed;

    function __construct($host = 'localhost', $port = 4000) 
    {
        set_time_limit(0);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        socket_bind($socket, 0,$port);
        socket_listen($socket);
        $this->socket = $socket;
        
    }
    
    function __destruct() 
    {
        foreach ($this->clients as $client)
        {
            socket_close($client);
        }
        socket_close($this->socket);
    }

    function run()
    {
        while(true)
        {
            $this->waitForChange();
            $this->checkNewClients();
            $this->checkMessageRecieved();
            $this->checkDisconnect();
            
        }
    }
    
    function checkDisconnect()
    {
        foreach ($this->changed as $changed_socket)
        {
            
            $buf = socket_read($changed_socket, 2048, PHP_NORMAL_READ);
           
            if($buf!==false)
            {
                continue;
            }
            
            
            //remove client from client array
            $found_socket = array_search($changed_socket, $this->clients);
            socket_getpeername($changed_socket, $ip);
            unset($this->clients[$found_socket]);
            $response = "Client ".$ip." has disconnected";
            $this->sendMessage($response);
        }
    }
    
    
    function checkMessageRecieved()
    {
        foreach ($this->changed as $key=>$socket)
        {
            $buffer = null;
            while (socket_recv($socket, $buffer, 2048, 0)>=1)
            {
                $this->sendMessage(trim($buffer).PHP_EOL);
                unset($this->changed[$key]);
                break;
            }
            
        }
    }
    
    function waitForChange()
    {
        $this->changed = array_merge([$this->socket],  $this->clients);
        $null = null;
        socket_select($this->changed,$null,$null,null);
    }
    
    function checkNewClients()
    {
        if(!in_array($this->socket, $this->changed))
        {
            return;
        }
        
        $socket_new = socket_accept($this->socket);
        $first_line = socket_read($socket_new,2048);
        $this->sendMessage('a new client has connected '.PHP_EOL);
        $this->sendMessage('new client says '.trim($first_line).PHP_EOL);
        $this->clients[] = $socket_new;
        unset($this->changed[0]);
    }
    
    function sendMessage($msg)
    {
        foreach ($this->clients as $client)
        {
            @socket_write($client,$msg,  strlen($msg));
        }
        return true;
    }
}

(new SocketServer())->run();

?>