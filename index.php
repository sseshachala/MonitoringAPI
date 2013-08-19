<?php


error_reporting(-1);
require_once 'libraries/CheckMKLiveStatus.php';



$data = '';

$liveStatus = new CheckMKLiveStatus();
$result = $liveStatus ->connectSocket();

print_r($result);

$query = "GET hosts\n";
if($result)
{
	$data = $liveStatus -> queryLivestatus($query);
	print_r($data);
}

