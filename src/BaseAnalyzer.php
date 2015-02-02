<?php

class BaseAnalyzer
{
    protected $time;
    protected $timeout = 40;
    
    
    function __construct()
    {
        $this->init();
        $this->time = new DateTime();
    }
    
    public function checkTime()
    {
        $time = new DateTime('-'.$this->timeout.' seconds');
        if ($time > $this->time) {
            $this->writeln('TIMEOUT');
            die();
        }
    }

    function writeln($msg)
    {
        echo $msg . "\n";
    }
    
    function getRootDir()
    {
        return '../..';
    }

    function getDomainPrefix()
    {
        return 'http://www.example.com';
    }

    function connect()
    {
        $host = 'localhost';
        $username = 'root';
        $password = 'root';
        $database = 'mydatabase';
        mysql_connect($host, $username, $password);
        mysql_select_db($database);
        mysql_query('SET CHARACTER SET utf8');
    }

    function query($sql)
    {
        $result = mysql_query($sql);

        if (!$result) {
            $this->writeln(mysql_error());
        }

        return $result;
    }
    
    function getAsArray($result)
    {
        $records = array();
        while ($record = mysql_fetch_assoc($result)) {
            $records[] = $record;
        }
        return $records;
    }

    function hasPrefix($str)
    {
        return (bool)$this->getPrefixFor($str);
    }
    
    function getPrefixFor($str)
    {
        $prefix = $this->getDomainPrefix();
        
        if (substr($str, 0, strlen($prefix)) == $prefix) {
            return $prefix;
        }
            
        $prefix = str_replace('www.', '', $prefix);
        if (substr($str, 0, strlen($prefix)) == $prefix) {
            return $prefix;
        }
        
        return false;
    }

    function init()
    {
        $this->connect();
    }
    
    function searchFile($dir, $filename, &$pathes )
    {
        $subdirs = array();
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..' ) {
                    continue;
                }
                if (is_dir($dir.'/'.$file)) {
                    $subdirs[] = $file;
                }
                
                if ($file==$filename) {
                    $pathes[] = $dir;
                }
            }
            closedir($dh);
        }

        foreach($subdirs as $subdir) {
            $this->searchFile($dir.'/'.$subdir, $filename, $pathes );
        }
    }

}
