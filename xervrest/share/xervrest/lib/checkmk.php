<?php

class CheckMkCfg
{
    public $cfg_file;

    public function CheckMkCfg($cfg_file)
    {
        $this->cfg_file = $cfg_file;
    }

    public function add_check($check, $host, $citem=null, $params=null, $params_str=null)
    {
        $fh = fopen($this->cfg_file, 'w');
	if ($citem == null) {
		$citem_str = 'None';
	} else {
		$citem_str = sprintf('"%s"', citem);
	}
	if ($params_str == null) {
		if ($params == null){
			$params_str = 'None';
		} else {
			$params_str = '(';
			$params_str += implode(", ", $params);
			$params_str += ')';

		}
	}
        $cfg_s = sprintf('checks += [(["%s"], "%s", %s, %s),]', $host, $check, $citem_str, $params_str);

        if(!$fh)
        {
            throw Exception("Could not open file for writing: " . $this->cfg_file);
        }

        if(fwrite($fh, $cfg_s) == FALSE)
        {
            throw Exception("Could not write to file: " . $this->cfg_file);
        }

        fclose($fh);
    }
    public function add_ps_check($host, $cname, $proc, $user=false, $warnmin=1, $okmin=1, $okmax=1, $warnmax=1) {
	    $user = ($user) ? "\"$user\"" : 'ANY_USER';
	    $this->add_check("ps", $host, $cname, $params=[$proc, $user, $warnmin, $okmin, $okmax, $warnmax]);
    }
    
    public function add_contact($params)
    {
        $fh = fopen($this->cfg_file, 'w');
        
        if(!$fh)
        {
            throw Exception("Could not open file for writing: " . $this->cfg_file);
        }
        
        $cfg = "define contact {\n";
        foreach($params as $key => $val)
        {
            $cfg .= "\t$key\t\t$val\n";
        }
        $cfg .= "}\n";
        
        if(fwrite($fh, $cfg) == FALSE)
        {
            throw Exception("Could not write to file: " . $this->cfg_file);
        }
        
        fclose($fh);
    }

    public function add_flexible_contact($params)
    {
        $_tmp = Array('contactgroups', 'only_services', 'host_events', 'service_events', 'parameters');
        foreach($_tmp as $key)
        {
            if(array_key_exists($key, $params))
            {
                if($params[$key] == false)
                {
                    continue;
                }
                
                $params[$key] = implode(',', array_map(function($i){ return "'$i'"; }, explode(',', $params[$key])));
            }
        }

        $cfg_tmpl = "contacts.update({'%s': {
                   'alias': u'%s',
                   'contactgroups': [%s],
                   'email': '%s',
                   'pager': '%s',
                   'host_notification_commands': '%s',
                   'host_notification_options': '%s',
                   'notification_period': '%s',
                   'notifications_enabled': True,
                   'service_notification_commands': '%s',
                   'service_notification_options': '%s',
                   'notification_method': ('flexible',
                                   [{'disabled': False,
                                     'escalation': (2, 999999),
                                     'host_events': [%s],
                                     'only_services': [%s],
                                     'parameters': [%s],
                                     'plugin': None,
                                     'service_events': [%s],
                                     'timeperiod': '%s'}]) }})";

        $cfg = sprintf($cfg_tmpl, 
                            $params['contact_name'], 
                            $params['alias'], 
                            $params['contactgroups'], 
                            $params['email'],
                            $params['pager'],
                            $params['host_notification_commands'],
                            $params['host_notification_options'],
                            $params['notification_period'],
                            $params['service_notification_commands'],
                            $params['service_notification_options'],
                            $params['host_events'],
                            $params['only_services'],
                            $params['parameters'],
                            $params['service_events'],
                            $params['timeperiod']
                );

        $fh = fopen($this->cfg_file, 'w');

        if(!$fh)
        {
            throw Exception("Could not open file for writing: " . $this->cfg_file);
        }

        if(fwrite($fh, $cfg) == FALSE)
        {
            throw Exception("Could not write to file: " . $this->cfg_file);
        }

        fclose($fh);
    }
    
    public function add_contact_group($params)
    {
        $fh = fopen($this->cfg_file, 'w');
        
        if(!$fh)
        {
            throw Exception("Could not open file for writing: " . $this->cfg_file);
        }

        $params['hosts'] = preg_replace('/,$/', '', $params['hosts']);
        
        $hosts = '';
        if(preg_match('/ALL_HOSTS/', $params['hosts']))
        {
            $hosts = 'ALL_HOSTS';
        }
        else
        {
            $hosts = '[' . implode(',', array_map(function($i){return "\"$i\"";}, explode(',', $params['hosts']))) . ']';
        }

        $members = implode(',', array_map(function($i){ return "\"$i\""; }, explode(',', $params['members'])));
        
        $cfg = "define_contactgroups = True\n";
        $cfg .= sprintf("contactgroup_members[\"%s\"] = [ %s ]\n", $params['contactgroup_name'], $members);
        $cfg .= sprintf("host_contactgroups.extend( [ ( \"%s\", %s ), ])\n", $params['contactgroup_name'], $hosts);

        if(fwrite($fh, $cfg) == FALSE)
        {
            throw Exception("Could not write to file: " . $this->cfg_file);
        }
        
        fclose($fh);
    }
}

class CheckMk
{
	public $checkmk_path = '/omd/versions/default/share/check_mk/modules/check_mk.py';
	public $python_path = '/usr/bin/python';
    public $defaults_path;
    public $default_opts = '-n -d';
    public $content = Array();

	public function CheckMk($opts)
	{
        $supported_opts = Array('checkmk_path', 'python_path', 'defaults_path', 'default_opts');
        foreach($supported_opts as $_opt)
        {
            if(array_key_exists($_opt, $opts))
            {
                $this->$_opt = $opts[$_opt];
            }
        }
    }

    public function execute($host)
    {
        $cmd = $this->python_path . ' ' . $this->checkmk_path . ' ';
        $cmd .= '--defaults ' . $this->defaults_path . ' ';
        $cmd .= $this->default_opts . ' ' . $host;
        
        $fptr = popen($cmd, 'r');

        if($fptr)
        {
            while(($line = fgets($fptr, 2048)) != false)
            {
                $line = rtrim($line);
                array_push($this->content, $line);
            }
            pclose($fptr);
        }
        else
        {
            throw Exception("Could not execute check_mk.");
        }
	}

    public function cmk_cmd($site, $cmd)
    {
        $cmd = "/omd/sites/$site/bin/check_mk $cmd";
        $fptr = popen($cmd, 'r');
        if(!$fptr)
        {
            throw Exception("Could not execute command $cmd");
        }

        $output = '';

        while(!feof($fptr))
        {
            $output .= fread($fptr, 1024);
        }

        fclose($fptr);

        return $output;
    }

    public function restart($site)
    {
        return $this->cmk_cmd($site, ' -R');
    }

    public function auto_inventory($site, $host=false)
    {
        $cmd = "/omd/sites/$site/bin/check_mk -I";
        if($host)
        {
            $cmd .= " $host";
        }

        $fptr = popen($cmd, 'r');
        if(!$fptr)
        {
            throw Exception("Could not execute check_mk.");
        }

        pclose($fptr);
    }

    public function inventory($host, $check=false)
    {
        $cmd = $this->python_path . ' ' . $this->checkmk_path . ' ';
        $cmd .= '--defaults ' . $this->defaults_path . ' ';
        $cmd .= ($check) ? "--checks=$check " : '';
        $cmd .= "-I $host";

        $fptr = popen($cmd, 'r');
        if(!$fptr)
        {
            throw Exception("Could not execute check_mk.");
        }
        pclose($fptr);
    }

    public function section($section)
    {
        $marker = false;
        $section_data = Array();

        foreach($this->content as $line)
        {
            if(preg_match("/<<<$section>>>/", $line))
            {
                $marker = true;
                continue;
            }

            if($marker == true && preg_match("/<<<.*?/", $line))
            {
                $marker = false;
                break;
            }

            if($marker)
            {
                array_push($section_data,$line);
            }
        }

        return $section_data;
    }
}

?>
