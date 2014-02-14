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
        public $dynamic_methods = Array(
            'hosts' => true,
            'services' => true,
            'hostgroups' => true,
            'servicegroups' => true,
            'contactgroups' => true,
            'servicesbygroup' => true,
            'servicesbyhostgroup' => true,
            'hostsbygroup' => true,
            'contacts' => true,
            'commands' => true,
            'timeperiods' => true,
            'downtimes' => true,
            'comments' => true,
            'log' => true,
            'status' => true,
            'columns' => true,
            'statehist' => true,
        );

        private $livestatus_obj;

        private function _param_defaults($params, $defaults)
        {
            foreach($defaults as $key => $val)
            {
                if(!array_key_exists($key, $params))
                {
                    $params[$key] = $val; 
                }
            }
            
            return $params;
        }
        
        private function _check_params($params, $mandatory)
        {
            $missing = Array();
            foreach($mandatory as $key)
            {
                if(!array_key_exists($key, $params))
                {
                    array_push($missing, $key);
                }
            }
            return $missing;
        }
        
        public function __call($method, $arguments)
        {
            array_unshift($arguments, $method);
            if(array_key_exists($method, $this->dynamic_methods))
            {
                return call_user_func_array(array($this, 'do_get'), $arguments);
            }
            
            return error_json("$method is not supported.");
            
        }

        public function XervRest($livestatus_obj)
        {
            $this->livestatus_obj = $livestatus_obj;
        }

        public function paginate($results, $limit, $offset=0)
        {
            $results_a = json_decode($results);
            $header = array_shift($results_a);

            if($offset > count($results_a) -1)
            {
                return error_json("Offset ($offset) is larger than row count.");
            }

            if($limit < 1)
            {
                $limit = NULL;
            }

            $results_a = array_slice($results_a, $offset, $limit);
            array_unshift($results_a, $header);
            return str_replace('\/','/', json_encode($results_a));
        }

        public function do_get($table, $params)
        {
            $columns = array_key_exists('columns', $params) ? $params['columns'] : false;
            $limit = array_key_exists('limit', $params) ? $params['limit'] : false;
            $offset = array_key_exists('offset', $params) ? $params['offset'] : false;
            
            try {
                $query = new LQL('GET');
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            $query->table($table);

            if($columns)
            {
                $columns = explode(',', $params['columns']);
                $query->columns($columns);
            }

            foreach($params as $key => $val)
            {
                if(preg_match('/filter\d+/', $key))
                {
                    $regex = "^(.*?)(=|~|=~|~~|<|>|<=|>=|!=|!~|!=~|!~~)(.*?)$";
                    $val = preg_replace("/$regex/", '$1 $2 $3', $val);
                    $query->filter($val);
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
            try {
                $query = new LQL('GET');
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            $query->command($command);
            $resp = $this->livestatus_obj->query($query->as_string(), true);
            $this->livestatus_obj->close();
            return response_json('success', 'The request has been executed.');
        }

        public function ack_host($params)
        {
            $missing_params = $this->_check_params($params, Array('host', 'message'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            $message = $params['message'];
            
            $cmd = "ACKNOWLEDGE_HOST_PROBLEM;$host;0;0;0;nagiosadmin;$message";
            return $this->do_command($cmd);
        }

        public function ack_hosts($params)
        {
            $missing_params = $this->_check_params($params, Array('hosts', 'message'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $params['hosts'] = preg_replace('/,$/', '', $params['hosts']);
            $hosts = explode(',', $params['hosts']);
            $message = $params['message'];
            
            foreach ($hosts as $host)
            {
                $this->do_command("ACKNOWLEDGE_HOST_PROBLEM;$host;0;0;0;nagiosadmin;$message");
            }
        }

        public function ack_service($params)
        {
            $missing_params = $this->_check_params($params, Array('service', 'message'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $service = $params['service'];
            $message = $params['message'];
            $cmd = "ACKNOWLEDGE_SVC_PROBLEM;$service;0;0;0;nagiosadmin;$message";
            return $this->do_command($cmd);
        }

        public function ack_services($params)
        {
            $missing_params = $this->_check_params($params, Array('services', 'message'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $params['services'] = preg_replace('/,$/', '', $params['services']);
            $services = explode(',', $params['services']);
            $message = $params['message'];
            
            foreach ($services as $service)
            {
                $this->do_command("ACKNOWLEDGE_SVC_PROBLEM;$service;0;0;0;nagiosadmin;$message");
            }
        }

        public function schedule_host_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            $cmd = "SCHEDULE_FORCED_HOST_CHECK;$host;" . date('U');
            return $this->do_command($cmd);
        }

        public function schedule_host_services_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            $cmd = "SCHEDULE_FORCED_HOST_SVC_CHECKS;$host;" . date('U');
            return $this->do_command($cmd);
        }

        public function schedule_service_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host', 'service'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            $service = $params['service'];
            $cmd = "SCHEDULE_FORCED_SVC_CHECK;$host;$service;" . date('U');
            return $this->do_command($cmd);
        }

        public function delete_comment($params)
        {
            $missing_params = $this->_check_params($params, Array('comment_id'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $comment_id = $params['comment_id'];
            $cmd = "DEL_HOST_COMMENT;$comment_id";
            return $this->do_command($cmd);
        }

        public function remove_host_acknowledgement($params)
        {
            $missing_params = $this->_check_params($params, Array('host'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
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

        public function getprocess($params)
        {
            $missing_params = $this->_check_params($params, Array('host'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            $site = get_site();
            
            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->execute($host);
                $ps = $cmk->section('ps');
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            if(array_key_exists('proc', $params))
            {
                $proc = $params['proc'];
                $ps = preg_grep("/$proc/i", $ps);
            }

            $ps = $this->_format_ps($ps);

            return str_replace('\/','/', json_encode($ps));
        }

        public function restart_site()
        {
            $site = get_site();
            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $res = $cmk->restart($site);
            } catch(Exception $e) {
                return error_json( $e->getMessage() . " Output: $res");
            }

            return response_json('success', sprintf('The request has been executed. Command output: %s', $res));
        }
        
        private function _get_contact_group_members($contactgroup_name)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_contactgroup.%s.%s.mk", $cfg_root, $site, $contactgroup_name);
            $data = file($cfg_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if(!$data)
            {
                exit(error_json("Could not open and read file $cfg_file"));
            }
            
            foreach($data as $line)
            {
                if(preg_match("/contactgroup_members/", $line))
                {
                    return array_map(function($i){ return str_replace('"', '', $i); }, 
                                        explode(',', preg_replace("/contactgroup_members.*? = \[\s+(.*?)\s+\].*/", '$1', $line)));
                }
            }
            
            return Array();
        }
        
        private function _is_in_contact_group($contact)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $raw_files = glob("$cfg_root/xervrest_contactgroup.*.mk");
            
            foreach($raw_files as $file)
            {
                $contactgroup_name = preg_replace("/.*xervrest_contactgroup.*?\..*?\.(.*?)\.mk/", '$1', $file);
                $members = $this->_get_contact_group_members($contactgroup_name);
                
                foreach($members as $member)
                {
                    if($member == $contact)
                    {
                        return $contactgroup_name;
                    }
                }
            }
            return false;
        }
        
        public function add_contact($params)
        {
            $params = $this->_param_defaults($params, Array('timeperiod' => $params['host_notification_period'] , 'host_events' => $params['host_notification_options'], 'service_events' => $params['service_notification_options'],  'notification_period' => $params['host_notification_period'], 'alias' => $params['contact_name'], 'pager' => false, 'only_services' => false, 'parameters' => false ));
            $mandatory_params = Array('contact_name', 'contactgroups', 'host_notification_commands', 
                'host_notification_options', 'notification_period', 'service_notification_commands', 'service_notification_options', 
                'host_events', 'only_services', 'parameters', 'service_events', 'timeperiod');
            $missing_params = $this->_check_params($params, $mandatory_params);
            
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            if(preg_match("/\./", $params['contact_name']))
            {
                return error_json("Contact name may not contain a dot/period: . ");
            }
            
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_contact.%s.%s.mk", $cfg_root, $site, $params['contact_name']);

            try {
                $cfg = new CheckMkCfg($cfg_file);
                $cfg->add_flexible_contact($params);
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }
        
        public function del_contact($params)
        {
            $missing_params = $this->_check_params($params, Array('contact_name'));
            
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_contact.%s.%s.mk", $cfg_root, $site, $params['contact_name']);
            
            if(!file_exists($cfg_file))
            {
                return error_json("Cannot Delete. Contact does not exist.");
            }

            $blocking_contact_group = $this->_is_in_contact_group($params['contact_name']);
            if($blocking_contact_group)
            {
                return error_json("Cannot Delete. Contact is still referenced in contact group: " . $blocking_contact_group);
            }
            
            try 
            {
                if(unlink($cfg_file) == FALSE)
                {
                    return error_json("Could not remove file $cfg_file");
                }

                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->cmk_cmd($site, " -R");
            } 
            catch(Exception $exc)
            {
                return error_json( $exc->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }
        
        public function add_contact_group($params)
        {
        
            $missing_params = $this->_check_params($params, Array('contactgroup_name'));
            
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }

            $params = $this->_param_defaults($params, Array('alias' => $params['contactgroup_name']));
            
            if(preg_match("/\./", $params['contactgroup_name']))
            {
                return error_json("Contact group name may not contain a dot/period: . ");
            }
            
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_contactgroup.%s.%s.mk", $cfg_root, $site, $params['contactgroup_name']);

            try {
                $cfg = new CheckMkCfg($cfg_file);
                $cfg->add_contact_group($params);
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }
        
        public function del_contact_group($params)
        {
            $missing_params = $this->_check_params($params, Array('contactgroup_name'));
            
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_contactgroup.%s.%s.mk", $cfg_root, $site, $params['contactgroup_name']);

            try 
            {
                if(unlink($cfg_file) == FALSE)
                {
                    return error_json("Could not remove file $cfg_file");
                }

                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->cmk_cmd($site, " -R");
            } 
            catch(Exception $exc)
            {
                return error_json( $exc->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }

        public function add_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host', 'check'));
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest.%s.%s.mk", $cfg_root, $params['host'], $params['check']);
                 

            $defaults = Array('cname' => null, 'params_str' => null);
            $params = $this->_param_defaults($params, $defaults);

            try {
                $cfg = new CheckMkCfg($cfg_file);
                $cfg->add_check($params['check'], $params['host'], $params['cname'], $params_str=$params['params_str']);
            } catch(Exception $e) {
throw $e;
                return error_json( $e->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }
        public function add_proc_check($params)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_ps.%s.%s.mk", $cfg_root, $params['host'], $params['cname']);

            $defaults = Array('user' => false, 'warnmin' => 1, 'okmin' => 1, 'okmax' => 1, 'warnmax' => 1);
            $params = $this->_param_defaults($params, $defaults);

            if(preg_match("/\./", $params['cname']))
            {
                return error_json("Check cname may not contain a dot/period: . ");
            }


            try {
                $cfg = new CheckMkCfg($cfg_file);
                $cfg->add_ps_check($params['host'], $params['cname'], $params['proc'], $params['user'], 
                                    $params['warnmin'], $params['okmin'], $params['okmax'], $params['warnmax']);
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }

        public function del_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host', 'check'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
        
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest.%s.%s.mk", $cfg_root, $params['host'], $params['check']);

            try 
            {
                if(unlink($cfg_file) == FALSE)
                {
                    return error_json("Could not remove file $cfg_file");
                }

                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->cmk_cmd($site, " -R");
            } 
            catch(Exception $exc)
            {
                return error_json( $exc->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }

        public function del_proc_check($params)
        {
            $missing_params = $this->_check_params($params, Array('host', 'cname'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
        
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_ps.%s.%s.mk", $cfg_root, $params['host'], $params['cname']);

            try 
            {
                if(unlink($cfg_file) == FALSE)
                {
                    return error_json("Could not remove file $cfg_file");
                }

                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->cmk_cmd($site, " -R");
            } 
            catch(Exception $exc)
            {
                return error_json( $exc->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }
        
        private function _get_check_data($file)
        {
            $fh = fopen($file, 'r');
            $data = '';
            while(!feof($fh))
            {
                $data .= fread($fh, 1024);
            }
            fclose($fh);
            
            $data = preg_replace("/^.*?\(.*?\(\"(.*?)\)\).*/", '$1', $data);
            $arr = explode(',', $data);
            
            return Array('proc' => str_replace('"', '', $arr[0]), 'user' => $arr[1], 
                         'warnmin' => $arr[2], 'okmin' => $arr[3], 'okmax' => $arr[4], 'warnmax' => $arr[5]);
        }

        public function host_proc_checks($params)
        {
            $site = get_site();
            $missing_params = $this->_check_params($params, Array('host'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $host = $params['host'];
            
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $raw_files = glob("$cfg_root/xervrest_ps.$host*.mk");
            $check_data = Array();
            
            foreach($raw_files as $file)
            {
                $cname = preg_replace("/.*xervrest_ps\.(.*?)\.(.*?)\.mk/", '$2', $file);
                $check_data[$cname] = $this->_get_check_data($file);
            }

            return str_replace('\/','/', json_encode($check_data));
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
        
        public function graph_name_map()
        {
            $map = Array(
                'CPU load' => Array('load1', 'load5', 'load15'),
                'CPU utilization' => Array('user', 'wait'),
                'Disk IO SUMMARY' => Array('read', 'write'),
                'Interface 2' => Array('in', 'inucast', 'innucast', 'indisc', 'inerr', 'out', 'outucast', 'outdisc', 'outerr', 'outqlen'),
                'Kernel Context Switches' => Array('ctxt'),
                'Kernel Major Page Faults' => Array('pgmajfault'),
                'Kernel Process Creations' => Array('processes'),
                'Memory used' => Array('ramused', 'swapused', 'memused'),
                'Number of threads' => Array('threads'),
                'Postfix Queue' => Array('length'),
                'TCP Connections' => Array('ESTABLISHED', 'SYN_SENT', 'SYN_RECV', 'LAST_ACK', 'CLOSE_WAIT', 'TIME_WAIT', 'CLOSED', 'CLOSING', 'FIN_WAIT1', 'FIN_WAIT2', 'BOUND'),
                'Uptime' => Array('uptime'),
                'fs_/' => Array('/' ,'growth', 'trend'),
                'fs_/boot' => Array('/boot' ,'growth', 'trend'),
                'Check_MK' => Array('execution_time'),
                'apache' => Array('IdleWorkers', 'BusyWorkers', 'OpenSlots', 'TotalSlots', 'Total_Accesses', 'Total_kBytes', 'CPULoad', 'ReqPerSec', 'BytesPerReq', 'BytesPerSec'),
            );
            return str_replace('\/','/', json_encode($map));
        }
        
        public function enable_host_checks($host)
        {            
            $site = get_site();

            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $cmk->cmk_cmd($site, " -I $host");
                $cmk->cmk_cmd($site, " -R");
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', 'The request has been executed.');
        }

        public function del_check_template($params)
        {
            $site = get_site();
            $json_file = "/omd/sites/$site/etc/xervrest/check_templates.json";
            $json_string = file_get_contents($json_file);
            $missing_params = $this->_check_params($params, Array('name'));

            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }

            $name = $params['name'];

            if($json_string === false)
            {
                return error_json("Could not open and read $json_file");
            }

            if($json_string !== '')
            {
                $template = json_decode($json_string, true);
                if(array_key_exists($name, $template))
                {
                    unset($template[$name]);
                }
            }

            $json_string = json_encode($template);

            if($json_string === false)
            {
                return error_json("Error generating JSON.");
            }

            if(file_put_contents($json_file, $json_string) < 1)
            {
                return error_json("Could not write JSON to $json_file");
            }

            return response_json('success', 'The request has been executed.');
        }

        public function get_check_templates($params)
        {
            $site = get_site();
            $json_file = "/omd/sites/$site/etc/xervrest/check_templates.json";
            $json_string = file_get_contents($json_file);

            if($json_string === false)
            {
                return error_json("Could not open and read $json_file");
            }

            if($json_string !== '')
            {
                return $json_string;
            }
            else
            {
                return error_json("Could not extract JSON string from $json_file");
            }
        }

        public function add_check_template($params)
        {
            $site = get_site();
            $json_file = "/omd/sites/$site/etc/xervrest/check_templates.json";
            $json_string = file_get_contents($json_file);
            $template = Array();
            $missing_params = $this->_check_params($params, Array('name'));

            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }

            if($json_string === false)
            {
                return error_json("Could not open and read $json_file");
            }

            if($json_string !== '')
            {
                $template = json_decode($json_string, true);
            }

            $checks = Array();

            foreach($params as $param => $value)
            {
                if(preg_match("/check\d+/", $param))
                {
                    array_push($checks, $value);
                }
            }

            if(count($checks) < 1)
            {
                return error_json("No checks found.");
            }

            $template[$params['name']] = $checks;
            $json_string = json_encode($template);
            
            if($json_string === false)
            {
                return error_json("Error generating JSON.");
            }

            if(file_put_contents($json_file, $json_string) < 1)
            {
                return error_json("Could not write JSON to $json_file");
            }
            
            return response_json('success', 'The request has been executed.');
        }
        
        public function installer($params)
        {
            $site = get_site();
            $installer = "/omd/sites/$site/bin/install_omd_agent.py";
            $config_file = "/omd/sites/$site/etc/xervrest/installer_config.json";
            
            $missing_params = $this->_check_params($params, Array('host', 'username'));
            if(count($missing_params) > 0)
            {
                return error_json("Missing parameters: " . implode(' ', $missing_params));
            }
            
            $cmd = sprintf("/usr/bin/python %s --config %s --host %s --username %s", $installer, $config_file, $params['host'], $params['username']);
            
            if(array_key_exists('action', $params))
            {
                $cmd .= sprintf(" --action %s", $params['action']);
            }
            
            if(array_key_exists('port', $params))
            {
                $cmd .= sprintf(" --port %s", $params['port']);
            }
            
            if(array_key_exists('username', $params))
            {
                $cmd .= sprintf(" --username %s", $params['username']);
            }

            if(array_key_exists('password', $params))
            {
                $cmd .= sprintf(" --password %s", $params['password']);
            }

            if(array_key_exists('keyfile', $params['_files']))
            {
                $_tmp = sprintf("/omd/sites/$site/tmp/xervrest.%s.%s.%s.key", $site, $params['host'], $params['username']);
                move_uploaded_file($_FILES["keyfile"]["tmp_name"], $_tmp);
                $cmd .= sprintf(" --key %s", $_tmp);
            }
            
            $output = shell_exec($cmd);
            return $output;
        }

        public function add_hosts($params)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_host_", $cfg_root);

            try {
                $cfg = new CheckMkCfg($cfg_file);
                $all_hosts = $cfg->add_hosts($params);
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            $inv_retval = '';
            $res_retval = '';

            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $inv_retval = $cmk->cmk_cmd($site, " -I $all_hosts 2>&1");
                $res_retval = $cmk->cmk_cmd($site, " -R");
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', sprintf('The request has been executed. Inventory command output: %s Restart command output: %s', $inv_retval, $res_retval ));
        }

        public function del_hosts($params)
        {
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $host_count = 0;
            $del_count = 0;
            $warnings = Array();

            foreach($params as $param => $value)
            {
                if(preg_match('/^ip_(\d+)/', $param, $m))
                {
                    $host_count++;
                    $cfg_file = sprintf("%s/xervrest_host_%s.mk", $cfg_root, $value);
                    if(file_exists($cfg_file))
                    {
                        if(unlink($cfg_file) == FALSE)
                        {
                            array_push($warnings, "unlink($cfg_file) returned FALSE");
                        }
                        else
                        {
                            $del_count++;
                        }
                    }
                    else
                    {
                        array_push($warnings, "Could not find config file for " . $m[1]);
                    }
                }
            }
        
            $retval = '';

            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $retval = $cmk->cmk_cmd($site, " -R");
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            if($host_count != $del_count)
            {
                array_push($warnings, sprintf("%s/%s hosts deleted",$del_count,$host_count));
            }

            if(count($warnings) > 0)
            {
                return error_json("WARNINGS: " . implode("\n", $warnings));
            }

            return response_json('success', 'The request has been executed. Results: ' . $retval);
        }

		 public function configureApache($params)
        {
        	if(empty($params['check']) || empty($params['checkName']) || empty($params['host']) )
			{
				return error_json('check, checkName and host are mandatory');
			}
            $site = get_site();
            $cfg_root = "/omd/sites/$site/etc/check_mk/conf.d";
            $cfg_file = sprintf("%s/xervrest_%s_host_%s.mk", $cfg_root, $params['check'], $params['checkName'], $params['host']);

            try {
                $cfg = new CheckMkCfg($cfg_file);
                $all_hosts = $cfg->configureApache($params);
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            $inv_retval = '';
            $res_retval = '';

            try {
                $cmk = new CheckMk(Array( 'defaults_path' => "/omd/sites/$site/etc/check_mk/defaults"));
                $inv_retval = $cmk->cmk_cmd($site, " -I $all_hosts 2>&1");
                $res_retval = $cmk->cmk_cmd($site, " -R");
            } catch(Exception $e) {
                return error_json( $e->getMessage() );
            }

            return response_json('success', sprintf('The request has been executed. Inventory command output: %s Restart command output: %s', $inv_retval, $res_retval ));
        }
    }
?>
