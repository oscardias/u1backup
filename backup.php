<?php
/*
u1backup v1.1.1
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

require 'u1backup.php';

// Instantiate and execute
$u1obj = new u1Backup();

// Settings
// Define database connection
$u1obj->setDatabase('localhost', 'root', '', 'dbname');
// or $u1obj->setDatabase('localhost', 'root', '', array('dbname1','dbname2')); // Multiple databases

// Define folders for backup
$u1obj->setFolder('/var/www');
// or $u1obj->setFolder(array('/var/www/site1', '/var/www/site2')); // Multiple folders

// Local path for files (must have permission) and Ubuntu One path
$u1obj->setWorkFolders('/tmp/', '/~/Ubuntu One/backups');

// Your credentials for Ubuntu One
$u1obj->setUbuntuOne('email', 'pass');

// Execute
$u1obj->execute();
