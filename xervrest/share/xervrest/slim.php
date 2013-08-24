<?php
	include_once( 'lib/Slim/Slim.php' );
	include_once( 'lib/common.php' );
	include_once( 'lib/livestatus.php' );
	include_once( 'api.php' );

	\Slim\Slim::registerAutoloader();

	$app = new \Slim\Slim( Array('log.enabled' => true, 'log.level' => \Slim\Log::DEBUG) );

	$app->get('/:method', function ($method) use ($app) {

		$app->contentType('application/json');

		$tmp_a = preg_split("/\//", $_SERVER['REQUEST_URI']);
		$site = $tmp_a[1];

		$sock_file = "/omd/sites/$site/tmp/run/live";

		if(!file_exists($sock_file))
		{
			$app->log->error("Unix socket ($sock_file) does not exist.");
			exit(error_json("Socket file ($sock_file) not found."));
		}

        $live = new LiveStatus($sock_file);

        if(!$live)
        {
			exit(error_json("There was an error connecting to the socket."));
        }

        $rest = new XervRest($live);
        $params = $app->request->params();
        if($params)
        {
            $params = $rest->handle_params($method, $params);
        }

        $rest_call = Array($rest,$method);
        print call_user_func_array($rest_call, $params);
	});

	$app->run();
?>
