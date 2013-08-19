<?php
require_once 'AbstractAPI.php';
require_once 'LiveException.php';
class CheckMKLiveStatus extends AbstractAPI
{
	protected $conf = Array(
			// The socket type can be 'unix' for connecting with the unix socket
			// to connect to a tcp socket.
			'socketType'       => 'unix',
			// When using a unix socket the path to the socket needs to be set
			'socketPath'       => '/opt/omd/sites/mysite/tmp/run/live',
			// When using a tcp socket the address and port needs to be set
			'socketAddress'    => '',
			'socketPort'       => '',
			// Modify the default allowed query type match regex
			'queryTypes'       => '(GET|LOGROTATE|COMMAND)',
			// Modify the matchig regex for the allowed tables
			'queryTables'      => '([a-z]+)',
	);
	
	
	private $live = null;
	
	private function getSocketConnection()
	{
		$result = false;
		
		switch($this->conf['socketType'])
		{
			case 'unix' : $result = socket_connect($this->live, $this->conf['socketPath']); break;
			case 'tcp' :  $result = socket_connect($this->live, $this->conf['socketAddress'], $this->conf['socketPort']); break;
		}
		
		return $result;
	}
	
	private function initSocket()
	{
		switch($this->conf['socketType'])
		{
			case 'unix' : $this->live = socket_create(AF_UNIX, SOCK_STREAM, 0); break;
			case 'tcp' :  $this->live = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);break;
		}

	}
	
	public function connectSocket() {
		//global $conf, $LIVE;
		// Create socket connection
		$this->initSocket();
		
		if($this->live == false) 
		{
			throw new LiveException('Could not create livestatus connection');
    	}
    	$result = $this->getSocketConnection();
	   
	
		if(!$result) 
		{
            throw new LiveException("error.livestatus_socket_error", socket_strerror(socket_last_error($this->live)));
        }
	
		// Maybe set some socket options
		if($this->conf['socketType'] === 'tcp') {
			// Disable Nagle's Alogrithm - Nagle's Algorithm is b
			if(defined('TCP_NODELAY')) 
			{
				socket_set_option($this->live, SOL_TCP, TCP_NODELAY, 1);
			} else 
			{
				// See http://bugs.php.net/bug.php?id=46360
				socket_set_option($this->live, SOL_TCP, 1, 1);
			}
		}
		return $result;
	}
	
	public function queryLivestatus($query) 
	{
		if($this->live === NULL) 
		{
			$this->connectSocket();
		}
		
		echo 'Writing to socket..';
		@socket_write($this->live, $query."\nOutputFormat: json\n\n");
		
		$buf = '';
		if (false !== ($bytes = socket_recv($this->live, $buf, 2048, MSG_WAITALL))) 
		{
			echo "Read $bytes bytes from socket_recv(). Closing socket...";
		} else 
		{
			throw new LiveException("error.livestatus_socket_error", socket_strerror(socket_last_error($this->live)));
		}
		
		
			// Decode the json response
		$obj = json_decode(utf8_encode(array('total_bytes' => $bytes, 'data' => $buf)));
		socket_close($this->live);
		$this->live = NULL;
		return $obj;
	}
	
	
	
	public function getHosts()
	{
		
	}
	
	
}