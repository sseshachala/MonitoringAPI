<?php

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
