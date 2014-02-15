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
?>
