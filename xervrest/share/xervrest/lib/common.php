<?php

    function get_site()
    {
        $tmp_a = preg_split("/\//", $_SERVER['REQUEST_URI']);
        return $tmp_a[1];
    }

	function error_json($err_string)
	{
		return json_encode( array( 'response' => 'error', 'message' => $err_string ) );
	}

?>
