<?php

	function error_json($err_string)
	{
		return json_encode( array( 'response' => 'error', 'message' => $err_string ) );
	}

?>
