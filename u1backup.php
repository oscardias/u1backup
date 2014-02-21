<?php
/*
u1backup v1.1.2
Copyright (C) 2013  Oscar de Souza Dias

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

class u1Backup{
    // Database connection
    var $dbhost;
    var $dbuser;
    var $dbpass;
    var $dbname;
    
    // Folder (or folders)
    var $folder;
    
    // Ubuntu one
    var $email;
    var $pass;
    
    // Paths
    var $local;
    var $remote;
    
    // Compressed files
    var $zip_files;
    
    /*
     * Set database details
     */
    public function setDatabase ($host, $user, $pass, $name)
    {
        $this->dbhost = $host;
        $this->dbuser = $user;
        $this->dbpass = $pass;
        $this->dbname = $name;
    }
    
    /*
     * Set folder to be backuped
     */
    public function setFolder ($folder)
    {
        $this->folder = $folder;
    }
    
    /*
     * Set Ubuntu One credentials
     */
    public function setUbuntuOne ($email, $pass)
    {
        $this->email = $email;
        $this->pass = $pass;
    }
    
    /*
     * Set local and Ubuntu One's path
     */
    public function setWorkFolders ($local, $remote)
    {
        $this->local = $local;
        $this->remote = $remote;
    }
    
    /*
     * Execution
     */
    public function execute()
    {
        // Prepare array for zip files
        $this->zip_files = array();
        
        // Dump databases
        $this->dumpDatabase();
        
        // Compress folder
        $this->compressFolder();
        
        // Sync to Ubuntu One
        $this->syncFiles();
    }
    
    /*
     * MySQL Database dump
     */
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
    
    /*
     * Compress directories
     */
    public function compressFolder()
    {
        if(is_array($this->folder)) {
            // Multiple folders
            foreach ($this->folder as $folder) {
                $this->_compressFolder($folder);
            }
        } else {
            // One folder
            $this->_compressFolder($this->folder);
        }
    }

    /*
     * Sync files to Ubuntu One
     */
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
        $token = $tokenA['token_key'];
        $secret = $tokenA['token_secret'];

        $oauth = new OAuth($conskey, $conssec, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->enableDebug();
        $oauth->enableSSLChecks();
        $oauth->setToken($token, $secret);

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
        foreach ($this->zip_files as $file) {
            $this->_sendFile($oauth, $file, $encpath);
        }
    }
    
    /*
     * Athorize app with Ubuntu One
     */
    private function _authorize()
    {
        $fields = array(
            'email' => $this->email,
            'password' => $this->pass,
            'token_name' => 'Ubuntu One @ '.gethostname().' [u1Backup]'
        );

        // Url-ify the data for the POST
        $fields_string = json_encode($fields);

        $url = 'https://login.ubuntu.com/api/v2/tokens/oauth';
        
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST"); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($fields_string)
        ));
        $data = curl_exec($curl);
        curl_close($curl);
        
        file_put_contents($this->local . 'u1backup', $data);
        
        return $data;
    }
    
    /*
     * Dump specific database
     */
    private function _dumpSingle($dbname)
    {
        $filename = $dbname . date("N") . '.sql';
        $backupfile = $this->local . $filename;
        
        if($this->dbpass)
            system("mysqldump -h $this->dbhost -u $this->dbuser -p$this->dbpass $dbname > $backupfile");
        else
            system("mysqldump -h $this->dbhost -u $this->dbuser $dbname > $backupfile");
        
        // Compress
        $zip = new ZipArchive();
        
        $zipFilename = $this->local . $dbname . date("N") . '.zip';
        
        if ($zip->open($zipFilename, ZIPARCHIVE::CREATE) !== TRUE) {
            die ("Could not open target file!");
        }
        
        $zip->addFile($backupfile, $filename) or die ("Could not add file: $filename");
        
        $zip->close();
        
        // Add to zip files array
        $this->zip_files[] = $zipFilename;
    }
    
    /*
     * Compress single folder
     */
    private function _compressFolder($folder)
    {
        // Zip object
        $zip = new ZipArchive();
        
        // Use folder path for file name
        $filename = $this->local . str_replace('\\', '_', str_replace('/', '_', $folder)) . date("N") . '.zip';

        // Open target
        if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE) {
            die ("Could not open target file!");
        }

        // Initialize an iterator with the folder
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));

        // Iterate over the directory and add each file found to the archive
        foreach ($iterator as $key => $value) {
                $zip->addFile(realpath($key), $key) or die ("Could not add file: $key");
        }

        // Close and save archive
        $zip->close();
            
        // Add to zip files array
        $this->zip_files[] = $filename;
    }
    
    /*
     * Send single file to Ubuntu One
     */
    private function _sendFile($oauth, $file, $encpath)
    {
        $contents = file_get_contents($file);

        $put_file_url = 'https://files.one.ubuntu.com/content' . $encpath . '/' . basename( $file );
        $oauth->fetch($put_file_url,
                $contents,
                OAUTH_HTTP_METHOD_PUT,
                array('Content-Type'=>'application/json')
                );
    }
}