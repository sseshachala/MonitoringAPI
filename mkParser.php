<?php
function startsWith($haystack, $needle)
	{
	    return $needle === "" || strpos($haystack, $needle) === 0;
	}
	function endsWith($haystack, $needle)
	{
	    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}
	
function parseMK()
{
	$cfgFile = 'test.mk';
	$handle = @fopen($cfgFile, "r");
		$sb = '';
		if ($handle) {
			$allParams = array();
		    while (($buffer = fgets($handle, 4096)) !== false) 
		    {
		    	if(startsWith($buffer, 'all_hosts'))
				{
					extractGenConfig($buffer, $allParams);
				}
				if(startsWith($buffer, 'ipaddresses.update'))
				{
					extractIP($buffer, $allParams);
				}
				if(startsWith($buffer, 'checks'))
				{
					$allParams['checks'][] = $buffer;
				}
					
		       $sb .= $buffer ;
		    }
		    if (!feof($handle)) {
		        echo "Error: unexpected fgets() fail\n";
		    }
		    fclose($handle);
			//print_r($allParams);
		}
		return $allParams;
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

print_r(parseMK());
 