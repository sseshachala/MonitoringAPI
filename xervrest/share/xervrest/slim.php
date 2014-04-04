<?php
	include_once( 'lib/Slim/Slim.php' );
	include_once( 'lib/common.php' );
	include_once( 'lib/livestatus.php' );
	include_once( 'api.php' );

    function get_live_object()
    {
        $site = get_site();
        $sock_file = "/omd/sites/$site/tmp/run/live";
        $live = new LiveStatus($sock_file);
        
        if(!file_exists($sock_file))
        {
            throw Exception("Socket file ($sock_file) not found.");
        }
        
        if(!$live)
        {
            throw Exception("There was an error connecting to the socket.");
        }
        
        return $live;
    }
    
    \Slim\Slim::registerAutoloader();
    
	$app = new \Slim\Slim( Array('log.enabled' => true, 'log.level' => \Slim\Log::DEBUG) );

    $app->get('/', function() use ($app) {
        $app->redirect('index.html');
    });

    $app->get('/index.html', function() use ($app) {
        $app->render('index.html', Array());
    });
    
    $app->get('/add_contact', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->add_contact($params);
    });
    
    $app->get('/del_contact', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->del_contact($params);
    });
    
    $app->get('/add_contact_group', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->add_contact_group($params);
    });

    $app->get('/del_contact_group', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->del_contact_group($params);
    });
    
    $app->get('/add_check', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->add_check($params);
    });
    $app->get('/add_proc_check', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->add_proc_check($params);
    });

    $app->get('/del_proc_check', function() use ($app) {
		$app->contentType('application/json');
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        print $rest->del_proc_check($params);
    });
    
    $app->get('/enable_host_checks', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        if(!array_key_exists('host', $params))
        {
            exit(error_json("Missing parameter: host"));
        }
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->enable_host_checks($params['host']);
    });
    
    $app->get('/host_proc_checks', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->host_proc_checks($params);
    });

    $app->get('/getprocess', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->getprocess($params);
    });
	
	$app->get('/getPatchUpdates', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->getPatchUpdates($params);
    });
	
	$app->get('/portScanner', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->portScanner($params);
    });
	
	$app->get('/uName', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->uName($params);
    });

    $app->get('/add_check_template', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->add_check_template($params);
    });

    $app->get('/del_check_template', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->del_check_template($params);
    });

    $app->get('/get_check_templates', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        print $rest->get_check_templates($params);
    });

    $app->post('/install_agent', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        $site = get_site();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        if(array_key_exists('keyfile', $_FILES))
        {
            if($_FILES["keyfile"]["error"] > 0)
            {
                exit(error_json("SSH key file upload error: " . $_FILES["keyfile"]["error"]));
            }
        }
        
        $params['_files'] = $_FILES;
        
        $rest = new XervRest($live);
        print $rest->installer($params);
    });
    
    $app->post('/install_agent_report', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        $site = get_site();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        if(array_key_exists('keyfile', $_FILES))
        {
            if($_FILES["keyfile"]["error"] > 0)
            {
                exit(error_json("SSH key file upload error: " . $_FILES["keyfile"]["error"]));
            }
        }
        
        $params['action'] = 'report';
        $params['_files'] = $_FILES;
        $rest = new XervRest($live);
        print $rest->installer($params);
    });
    
    $app->get('/ack_host', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        #print_r($params);
        print $rest->ack_host($params);
    });
    
    $app->get('/ack_service', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->ack_service($params);
    });
    
    $app->get('/schedule_host_check', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->schedule_host_check($params);
    });

    $app->get('/schedule_host_services_check', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->schedule_host_services_check($params);
    });
    
    $app->get('/schedule_service_check', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->schedule_service_check($params);
    });
    
    $app->get('/delete_comment', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->delete_comment($params);
    });
    
    $app->get('/remove_host_acknowledgement', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->remove_host_acknowledgement($params);
    });
    
    $app->get('/host_proc_checks', function() use ($app) {
		$app->contentType('application/json');
        $params = $app->request->params();
        
        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->host_proc_checks($params);
    });

    $app->get('/add_hosts', function() use ($app) {
        $app->contentType('application/json');
        $params = $app->request->params();

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->add_hosts($params);
    });

    $app->get('/del_hosts', function() use ($app) {
        $app->contentType('application/json');
        $params = $app->request->params();

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->del_hosts($params);
    });
	
	 $app->get('/configureApache', function() use ($app) {
        $app->contentType('application/json');
        $params = $app->request->params();

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->configureApache($params);
    });
	
	$app->get('/getHostConfig', function() use ($app) {
        $app->contentType('application/json');
        $params = $app->request->params();

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->getHostConfig($params);
    });
	
	$app->get('/deleteHostConfig', function() use ($app) {
        $app->contentType('application/json');
        $params = $app->request->params();

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }

        $rest = new XervRest($live);
        print $rest->deleteHostConfig($params);
    });
    //getConfiguredApache
	$app->get('/:method', function ($method) use ($app) {

		$app->contentType('application/json');

        try {
            $live = get_live_object();
        } catch(Exception $e) {
            exit(error_json($e->getMessage()));
        }
        
        $rest = new XervRest($live);
        $params = $app->request->params();
        $rest_call = Array($rest,$method);
        print call_user_func($rest_call, $params);
	});

	$app->run();
?>
