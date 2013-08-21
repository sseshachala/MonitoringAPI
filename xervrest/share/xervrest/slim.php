<?php
	include_once( 'lib/Slim/Slim.php' );
	include_once( 'lib/common.php' );
	include_once( 'lib/livestatus.php' );
	include_once( 'api_spec.php' );

	\Slim\Slim::registerAutoloader();

	$app = new \Slim\Slim( Array('log.enabled' => true, 'log.level' => \Slim\Log::DEBUG) );

	$app->get('/:method', function ($method) use ($app) {
		global $implemented;

		$app->contentType('application/json');

                if(!array_key_exists($method, $implemented['GET']))
                {
			$app->log->error("Method $method is not implemented in api_spec.php");
			$app->response->setStatus(404);
                }

		$tmp_a = preg_split("/\//", $_SERVER['REQUEST_URI']);
		$site = $tmp_a[1];

		$sock_file = "/omd/sites/$site/tmp/run/live";

		if(!file_exists($sock_file))
		{
			$app->log->error("Unix socket ($sock_file) does not exist.");
			exit(error_json("Socket file ($sock_file) not found."));
		}

		$query = "GET $method";

		$columns = $app->request->params('columns');
		if($columns)
		{
			$columns = implode(' ', explode(',', $columns));
			$query .= "\nColumns: $columns";
		}

		$query .= "\nResponseHeader: fixed16\nOutputFormat: json\n\n";

		$app->log->debug("API Query to be sent: $query");

		$live = new LiveStatus($sock_file);
		$results = $live->query($query);
		$live->close();

		print $results;

	});

	$app->run();
?>
