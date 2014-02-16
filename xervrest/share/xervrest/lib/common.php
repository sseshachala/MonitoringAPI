<?php

    function get_site()
    {
        $tmp_a = preg_split("/\//", $_SERVER['REQUEST_URI']);
        return $tmp_a[1];
    }

	function response_json($type, $response)
	{
		return json_encode( array( 'response' => $type, 'message' => $response ) );
	}

	function error_json($err_string)
	{
		return json_encode( array( 'response' => 'error', 'message' => $err_string ) );
	}
	
	function startsWith($haystack, $needle)
	{
	    return $needle === "" || strpos($haystack, $needle) === 0;
	}
	function endsWith($haystack, $needle)
	{
	    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}
	
	
	function extractGenConfig($buffer, & $allParams)
	{
		$array = explode('|', $buffer);
		for($i = 0 ; $i < count($array); $i++)
		{
			switch($i)
			{
				case 0 : $allParams['hostname'] = cleanuptags1($array[$i]); break;
				case 1 : $allParams['agent_type'] = $array[$i]; break;
				case 2 : $allParams['server'] = $array[$i]; break;
				case 3 : $allParams['system'] = $array[$i]; break;
				case 4 : $allParams['check'] = $array[$i]; break;
			}
		}
	}

	function extractIP($buffer, & $allParams)
	{
		$array = explode(':', $buffer);
		if(!empty($array[1]))
		{
			$allParams['ip'] = cleanuptags2	($array[1]);			
		}
	}
	
	function cleanuptags1($val)
	{
		$buffer = preg_replace("/[^a-zA-Z0-9]+/", "", str_replace("all_hosts", '', $val));
		return $buffer;
	}
	function cleanuptags2($val)
	{
		return str_replace(")", '',  str_replace("}", '', str_replace("'", '', str_replace('u', '', $val))));
		//return filter_var($val, FILTER_VALIDATE_IP);;
	}
	
?>
