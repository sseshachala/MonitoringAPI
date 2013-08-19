<?php
require_once 'API.php';
class AbstractAPI implements API
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
	
	public function connect($params)
	{
		
	}
	public function getHosts()
	{
		
	}
	
	public function getHost($params)
	{
		
	}
	
	public function systemStatusJson()
	{
		
	}
	
	public function getServiceGroups()
	{
	
	}
}