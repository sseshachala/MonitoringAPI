<?php

if(empty($_GET['request']))
{
	echo json_encode(array('request_not_found' => 'No valid request'));
	exit(1);
}

$request = $_GET['request'];

switch($request)
{
	case 'hosts' : echo 'Hosts'; exit(); // Initial a Class hexx

}
