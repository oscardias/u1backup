<?php

require 'u1backup.php';

// Instantiate and execute
$u1obj = new u1Backup();

// Settings
$u1obj->setDatabase('localhost', 'root', '', 'dbname');
// or $u1obj->setDatabase('localhost', 'root', '', array('dbname1','dbname2'));

$u1obj->setPaths('/tmp/', '~/Ubuntu One/backups');
$u1obj->setUbuntuOne('email', 'pass');

// Execute
$u1obj->execute();
