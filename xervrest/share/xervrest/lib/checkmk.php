<?php

class CheckMkCfg
{
    public $cfg_file;

    public function CheckMkCfg($cfg_file)
    {
        $this->cfg_file = $cfg_file;
    }

    public function add_ps_check($host, $cname, $proc, $user=false, $warnmin=1, $okmin=1, $okmax=1, $warnmax=1)
    {
        $fh = fopen($this->cfg_file, 'w');
        $user = ($user) ? "\"$user\"" : 'ANY_USER';
        $cfg_s = sprintf('checks += [("%s", "ps", "%s", ("%s", %s, %s, %s, %s, %s)),]', $host, $cname, $proc, $user, $warnmin, $okmin, $okmax, $warnmax);

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
    
    public function add_contact_group($params)
    {
        $fh = fopen($this->cfg_file, 'w');
        
        if(!$fh)
        {
            throw Exception("Could not open file for writing: " . $this->cfg_file);
        }
        
        $cfg = "define contactgroup {\n";
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
        $this->cmk_cmd($site, ' -R');
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
