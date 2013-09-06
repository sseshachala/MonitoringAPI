<?php

    include_once('lib/common.php');
    include_once('lib/checkmk.php');

    class LQL
    {
        public $supported_verbs = Array('GET', 'COMMAND');
        public $query = Array();
        public $verb = Array();

        private $output_format;
        private $response_header;
        private $column_headers = True;

        function LQL($verb, $response_header = 'fixed16', $output_format = 'json')
        {
            if(!in_array($verb, $this->supported_verbs))
            {
                throw Exception("LQL verb ($verb) is not supported.");
            }

            $this->verb = $verb;
            $this->response_header = $response_header;
            $this->output_format = $output_format;
            array_push($this->query, $verb);
        }

        function command($command)
        {
            $t = date('U');
            $this->query[0] .= " [$t] $command";
        }

        function table($table)
        {
            $this->query[0] .= " $table";
        }

        function columns($columns)
        {
            array_push($this->query, "Columns: " . implode(' ', $columns));
        }

        function column_headers($toggle)
        {
            $this->column_headers = $toggle;
        }

        function filter($filter)
        {
            array_push($this->query, "Filter: $filter");
        }

        function _or($expr)
        {
            array_push($this->query, "Or: $expr");
        }

        function _and($expr)
        {
            array_push($this->query, "And: $$expr");
        }

        function negate()
        {
            array_push($this->query, "Negate:");
        }

        function stats($expr)
        {
            array_push($this->query, "Stats: $expr");
        }

        function as_string()
        {
            if($this->verb == 'GET')
            {
                if($this->column_headers == True)
                {
                    array_push($this->query, "ColumnHeaders: on");
                }

                array_push($this->query, "ResponseHeader: " . $this->response_header);
                array_push($this->query, "OutputFormat: " . $this->output_format);
                return implode("\n", $this->query) . "\n\n";
            }

            if($this->verb == 'COMMAND')
            {
                return $this->query[0] . "\n";
            }
        }

    }

    class XervRest
    {
        public $implemented = Array(
            'hosts' => Array('verb' => 'GET', 'params' => false),
            'services' => Array('verb' => 'GET', 'params' => false),
            'hostgroups' => Array('verb' => 'GET', 'params' => false),
            'servicegroups' => Array('verb' => 'GET', 'params' => false),
            'contactgroups' => Array('verb' => 'GET', 'params' => false),
            'servicesbygroup' => Array('verb' => 'GET', 'params' => false),
            'servicesbyhostgroup' => Array('verb' => 'GET', 'params' => false),
            'hostsbygroup' => Array('verb' => 'GET', 'params' => false),
            'contacts' => Array('verb' => 'GET', 'params' => false),
            'commands' => Array('verb' => 'GET', 'params' => false),
            'timeperiods' => Array('verb' => 'GET', 'params' => false),
            'downtimes' => Array('verb' => 'GET', 'params' => false),
            'comments' => Array('verb' => 'GET', 'params' => false),
            'log' => Array('verb' => 'GET', 'params' => false),
            'status' => Array('verb' => 'GET', 'params' => false),
            'columns' => Array('verb' => 'GET', 'params' => false),
            'statehist' => Array('verb' => 'GET', 'params' => false),

            'ack_host' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, 'message' => 1)),
            'ack_service' => Array('verb' => 'COMMAND', 'params' => Array( 'service' => 0, 'message' => 1)),
            'schedule_host_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),
            'schedule_host_services_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),
            'schedule_service_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, 'service' => 1)),
            'delete_comment' => Array('verb' => 'COMMAND', 'params' => Array( 'comment_id' => 0, )),
            'remove_host_acknowledgement' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),

            'getprocess' => Array('verb' => 'CHECKMK', 'params' => Array( 'host' => 0, 'proc' => 1 )),
            'add_proc_check' => Array('verb' => 'CHECKMK', 'params' => Array( 'host' => 0, 'cname' => 1, 'proc' => 2, 
                'user' => 3, 'warnmin' => 4, 'okmin' => 5, 'okmax' => 6, 'warnmax' => 7 )),
            'del_proc_check' => Array('verb' => 'CHECKMK', 'params' => Array( 'host' => 0, 'cname' => 1)),
            'host_proc_checks' => Array('verb' => 'CHECKMK', 'params' => Array( 'host' => 0, )),
            'enable_host_checks' => Array('verb' => 'CHECKMK', 'params' => Array( 'host' => 0, )),
        );

        private $livestatus_obj;
        private $dynamic_methods = Array('hosts');

        public function __call($method, $arguments)
        {
            if(array_key_exists($method, $this->implemented))
            {
                switch ($this->implemented[$method]['verb'])
                {
                    case 'GET':
                        array_unshift($arguments, $method);
                        return call_user_func_array(array($this, 'do_get'), $arguments);
                        break;
                }
            }

        }

        public function XervRest($livestatus_obj)
        {
            $this->livestatus_obj = $livestatus_obj;
        }

        public function handle_get_params($params)
        {
            $filters = Array();
            $limit = 0;
            $offset = 0;
            $columns = Array();

            foreach($params as $param => $value)
            {
                if(preg_match("/filter\d+/", $param))
                {
                    $o = preg_replace("/filter([0-9]+)/", '\1', $param);
                    $filters[$o] = $value;
                }
                elseif($param == "columns")
                {
                    $columns = explode(',',$value);
                }
                elseif($param == "limit")
                {
                    $limit = $value;
                }
                elseif($param == "offset")
                {
                    $offset = $value;
                }
            }
            return(Array($columns,$filters,$limit,$offset));
        }

        public function handle_command_params($method, $params)
        {
            $p = Array();
            foreach($params as $param => $value)
            {
                if(array_key_exists($param, $this->implemented[$method]['params']))
                {
                    $p[$this->implemented[$method]['params'][$param]] = $value;
                }
            }
            return($p);
        }

        public function handle_params($method, $params)
        {
            if(!$params)
            {
                return(Array(false,false,false,false));
            }

            if( $this->implemented[$method]['verb'] == 'GET' )
            {
                return $this->handle_get_params($params);
            }
            
            if( $this->implemented[$method]['verb'] == 'COMMAND' )
            {
                return $this->handle_command_params($method, $params);
            }

            if( $this->implemented[$method]['verb'] == 'CHECKMK' )
            {
                return $this->handle_command_params($method, $params);
            }
        }

        public function paginate($results, $limit, $offset=0)
        {
            $results_a = json_decode($results);
            $header = array_shift($results_a);

            if($offset > count($results_a) -1)
            {
                throw Exception("Offset ($offset) is larger than row count.");
            }

            if($limit < 1)
            {
                $limit = NULL;
            }

            $results_a = array_slice($results_a, $offset, $limit);
            array_unshift($results_a, $header);
            return str_replace('\/','/', json_encode($results_a));
        }

        public function do_get($table, $columns=false, $filters=false, $limit=false, $offset=false)
        {
            $query = new LQL('GET');
            $query->table($table);

            if($columns)
            {
                $query->columns($columns);
            }

            if($filters)
            {
                foreach($filters as $filter)
                {
                    $query->filter($filter);
                }
            }

            $results = $this->livestatus_obj->query($query->as_string());

            if($limit > 0 || $offset > 0)
            {
                $results = $this->paginate($results, $limit, $offset);
            }

            return($results);
        }

        public function do_command($command)
        {
            $query = new LQL('COMMAND');
            $query->command($command);
            $resp = $this->livestatus_obj->query($query->as_string(), true);
            $this->livestatus_obj->close();
            return "Request Submitted";
        }

        public function ack_host($host, $message)
        {
            $cmd = "ACKNOWLEDGE_HOST_PROBLEM;$host;0;0;0;nagiosadmin;$message";
            return $this->do_command($cmd);

        }

        public function ack_hosts($hosts, $message)
        {
            foreach ($hosts as $host)
            {
                $this->do_command("ACKNOWLEDGE_HOST_PROBLEM;$host;0;0;0;nagiosadmin;$message");
            }
        }

        public function ack_service($service, $message)
        {
            $cmd = "ACKNOWLEDGE_SVC_PROBLEM;$service;0;0;0;nagiosadmin;$message";
            return $this->do_command($cmd);
        }

        public function ack_services($services, $message)
        {
            foreach ($services as $service)
            {
                $this->do_command("ACKNOWLEDGE_SVC_PROBLEM;$service;0;0;0;nagiosadmin;$message");
            }
        }

        public function schedule_host_check($host)
        {
            $cmd = "SCHEDULE_FORCED_HOST_CHECK;$host;" . date('U');
            return $this->do_command($cmd);
        }

        public function schedule_host_services_check($host)
        {
            $cmd = "SCHEDULE_FORCED_HOST_SVC_CHECKS;$host;" . date('U');
            return $this->do_command($cmd);
        }

        public function schedule_service_check($host, $service)
        {
            $cmd = "SCHEDULE_FORCED_SVC_CHECK;$host;$service;" . date('U');
            return $this->do_command($cmd);
        }

        public function delete_comment($comment_id)
        {
            $cmd = "DEL_HOST_COMMENT;$comment_id";
            return $this->do_command($cmd);
        }

        public function remove_host_acknowledgement($host)
        {
            $cmd = "REMOVE_HOST_ACKNOWLEDGEMENT;$host";
            return $this->do_command($cmd);
        }

        public function _format_ps($data)
        {
            $tmp = Array();
            $i = 0;
            foreach($data as $n)
            {
                $m = Array();
                preg_match("/\((.*?),(\d+),(\d+),(.*?)\)\s+(.*?)$/", $n, $m);

                $tmp[$i] = Array( 'user' => $m[1], 'vsz' => $m[2], 'rss' => $m[3], 'pcpu' => $m[4], 'command' => $m[5] );
                $i++;
            }
            return $tmp;
        }

        public function getprocess($host, $proc=false)
        {
            $site = get_site();
            $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
            $cmk->execute($host);
            $ps = $cmk->section('ps');

            if($proc)
            {
                $ps = preg_grep("/$proc/i", $ps);
            }

            $ps = $this->_format_ps($ps);

            return str_replace('\/','/', json_encode($ps));
        }

        public function restart_site()
        {
            $site = get_site();
            $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
            $cmk->restart($site);
            return "Request Submitted";
        }

        public function add_proc_check($host, $cname, $proc, $user=false, $warnmin=1, $okmin=1, $okmax=1, $warnmax=1)
        {
            $site = get_site();

            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_ps_%s_%s.mk", $cfg_root, $host, $cname);

            $cfg = new CheckMkCfg($cfg_file);
            $cfg->add_ps_check($host, $cname, $proc, $user, $warnmin, $okmin, $okmax, $warnmax);

            return "Request Submitted";
        }

        public function del_proc_check($host, $cname)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_ps_%s_%s.mk", $cfg_root, $host, $cname);

            if(unlink($cfg_file) == FALSE)
            {
                throw Exception("Could not remove file $cfg_file");
            }

            $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
            $cmk->restart($site);

            return "Request Submitted";
        }

        public function host_proc_checks($host)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";

            $raw_files = glob("$cfg_root/xervrest_ps_$host*.mk");
            $files = Array();

            foreach($raw_files as $f)
            {
                $f = preg_replace("/.*xervrest_ps_(.*?)_(.*?)\.mk/", '$2', $f);
                array_push($files, $f);
            }

            return json_encode($files);
        }

        public function get_graphite_url()
        {
            $site = get_site();
            $graphios_cfg_file = "/omd/sites/$site/etc/graphios/graphios.ini";

            if(!file_exists($graphios_cfg_file))
            {
                return error_json("Graphios config file not found."); 
            }

            $graphios_cfg = parse_ini_file($graphios_cfg_file, true);

            if(!$graphios_cfg)
            {
                return error_json("Failed to parse graphios config.");
            }

            if(!$graphios_cfg['graphios']['carbon_server'])
            {
                return error_json("Graphios 'carbon_server' option not found.");
            }

            $username = 'xervmon';
            $password = 'xervmon';

            $url = "http://$username:$password" . '@' . $graphios_cfg['graphios']['carbon_server'];

            return str_replace('\/','/', json_encode( Array('graphite_url' => $url) ));
        }

        public function enable_host_checks($host)
        {
            $site = get_site();

            $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
            $cmk->cmk_cmd($site, " -I $host");
            $cmk->cmk_cmd($site, " -R");

            return response_json('request_submitted', 'The request has been executed.');
        }
    }
?>
