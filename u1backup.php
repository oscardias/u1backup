<?php

class u1Backup{
    // Database connection
    var $dbhost;
    var $dbuser;
    var $dbpass;
    var $dbname;
    
    // Ubuntu one
    var $email;
    var $pass;
    
    // Paths
    var $local;
    var $remote;
    
    public function setDatabase ($host, $user, $pass, $name)
    {
        $this->dbhost = $host;
        $this->dbuser = $user;
        $this->dbpass = $pass;
        $this->dbname = $name;
    }
    
    public function setUbuntuOne ($email, $pass)
    {
        $this->email = $email;
        $this->pass = $pass;
    }
    
    public function setPaths ($local, $remote)
    {
        $this->local = $local;
        $this->remote = $remote;
    }
    
    public function execute()
    {
        // Dump databases
        $this->dumpDatabase();
        
        // Sync to Ubuntu One
        $this->syncFiles();
    }
    
    public function dumpDatabase()
    {
        if(is_array($this->dbname)){
            // Multiple databases
            foreach ($this->dbname as $value) {
                $this->_dumpSingle($value);
            }
        } else {
            // One database
            $this->_dumpSingle($this->dbname);
        }
    }
    
    public function syncFiles()
    {
        $sso_notice = FALSE;
        
        if(!file_exists($this->local . 'u1backup')) {
            $data = $this->_authorize ();
            $sso_notice = TRUE;
        } else
            $data = file_get_contents($this->local . 'u1backup');
        
        $tokenA = json_decode($data, TRUE);
        
        // Set up the token for use in OAuth requests
        $conskey = $tokenA['consumer_key'];
        $conssec = $tokenA['consumer_secret'];
        $token = $tokenA['token'];
        $secret = $tokenA['token_secret'];

        $oauth = new OAuth($conskey, $conssec, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->enableDebug();
        $oauth->enableSSLChecks();
        $oauth->setToken($token,$secret);

        // Tell Ubuntu One about new token
        if($sso_notice) {
            $tell_u1_about_token_url = 'https://one.ubuntu.com/oauth/sso-finished-so-get-tokens/' . $this->email;
            $oauth->fetch($tell_u1_about_token_url);
        }

        // We want to urlencode the path (so that the space in "Ubuntu One", for example,
        // becomes %20), but not any slashes therein (so the slashes don't become %2F).
        // urlencode() encodes spaces as + so we need rawurlencode
        $encpath = rawurlencode($this->remote);
        $encpath = str_replace("%2F", "/", $encpath);

        // Send files
        if(is_array($this->dbname)){
            // Multiple databases
            foreach ($this->dbname as $value) {
                $this->_sendFile($oauth, $value, $encpath);
            }
        } else {
            // One database
            $this->_sendFile($oauth, $this->dbname, $encpath);
        }
    }
    
    private function _authorize()
    {
        $description = 'Ubuntu%20One%20@%20'.gethostname().'%20[u1Backup]';
        $url = 'https://login.ubuntu.com/api/1.0/authentications?ws.op=authenticate&token_name='.$description;
        
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERPWD, $this->email.':'.$this->pass);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $data = curl_exec($curl);
        curl_close($curl);
        
        file_put_contents($this->local . 'u1backup', $data);
        
        return $data;
    }
    
    private function _dumpSingle($dbname)
    {
        $backupfile = $this->local . $dbname . date("N") . '.sql';
        
        if($this->dbpass)
            system("mysqldump -h $this->dbhost -u $this->dbuser -p$this->dbpass $dbname > $backupfile");
        else
            system("mysqldump -h $this->dbhost -u $this->dbuser $dbname > $backupfile");
        
        // Compress
        $gzfile = $this->local . $dbname . date("N") . '.gz';
        $fp = gzopen ($gzfile, 'w9'); // w9 == highest compression
        gzwrite ($fp, file_get_contents($backupfile));
        gzclose($fp);
    }
    
    private function _sendFile($oauth, $dbname, $encpath)
    {
        $backupfile = $this->local . $dbname . date("N") . '.gz';
        $contents = file_get_contents($backupfile);

        $put_file_url = 'https://files.one.ubuntu.com/content' . $encpath . '/' . $dbname . date("N") . '.gz';
        $oauth->fetch($put_file_url,
                $contents,
                OAUTH_HTTP_METHOD_PUT,
                array('Content-Type'=>'application/json')
                );
    }
}