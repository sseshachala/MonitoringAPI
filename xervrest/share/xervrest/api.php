<?php

    class LQL
    {
        public $supported_verbs = Array('GET', 'COMMAND');
        public $query = Array();
        public $verb = Array();

        private $output_format;
        private $response_header;

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
            'ack_service' => Array('verb' => 'COMMAND', 'params' => Array( 'ack_service' => 0, 'message' => 1)),
            'schedule_host_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),
            'schedule_host_services_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),
            'schedule_service_check' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, 'service' => 1)),
            'delete_comment' => Array('verb' => 'COMMAND', 'params' => Array( 'comment_id' => 0, )),
            'remove_host_acknowledgement' => Array('verb' => 'COMMAND', 'params' => Array( 'host' => 0, )),
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
                        return $this->do_get($method, $arguments[0], $arguments[1]);
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
            }
            return(Array($columns,$filters));
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
                return(Array());
            }

            if( $this->implemented[$method]['verb'] == 'GET' )
            {
                return $this->handle_get_params($params);
            }
            
            if( $this->implemented[$method]['verb'] == 'COMMAND' )
            {
                return $this->handle_command_params($method, $params);
            }
        }

        public function do_get($table, $columns=false, $filters=false)
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

            return $this->livestatus_obj->query($query->as_string());
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
    }
?>
