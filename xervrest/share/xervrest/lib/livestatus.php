<?php

class LiveStatus
{

	public $socket;
	public $socket_file;
	public $site;
	public $output_format = 'json';

	private function _connect()
	{
		$unix_socket = 'unix://' . $this->socket_file;
		$socket = stream_socket_client($unix_socket, $sock_errno, $sock_errstr);
		
		if(!$socket)
		{
			exit(error_json("Could not connect to socket $unix_socket. $sock_errstr error=$sock_errno")); 
		}
		
		return $socket;
	}

	public function LiveStatus($socket_file)
	{
		$this->socket_file = $socket_file;
		$this->socket = $this->_connect();
	}

	public function query($query)
	{
		fwrite($this->socket, $query);
		$response = explode("\n",stream_get_contents($this->socket));

		$resp_header = $response[0]; 
		$results = implode(' ', array_slice($response, 1));

		if($response)
		{
			$status_code = substr($resp_header,0,3);
			if($status_code == "200")
			{
				return $results;
			}
			else
			{
				throw new Exception("LiveStatus error. Status Code: $status_code Error String: $results");
			}
			
		}
	}

	public function close()
	{
		fclose($this->socket);
	}

}

?>
