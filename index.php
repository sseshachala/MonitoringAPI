<?php

if(empty($_GET['request']))
{
	echo json_encode(array('request_not_found' => 'No valid request'));
	exit(1);
}

$request = $_GET['request'];
error_reporting(-1);
require_once 'libraries/server/core/classes/GlobalBackendmklivestatus.php';
require_once 'libraries/server/core/classes/GlobalBackendmklivestatus.php';

$data = '';
switch($request)
{
	
	case 'hosts' : $backend = new GlobalBackendmklivestatus('', 'mysite'); 
					print_r($backend);
				exit(); // Initial a Class hexx

}
