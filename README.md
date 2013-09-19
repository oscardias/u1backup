u1backup
========

MySQL Backup script that syncs with Ubuntu One

How to
------

The class code is defined in u1backup.php and a simple usage example is at backup.php.
You can (should) create a cron job to execute your version of backup.php in a regular basis.

What it does
------------

The class made available here will dump your MySQL databases (one or more) and save them into a folder
(defined by you - test if the script can write to this folder).

Next, it will connect to Ubuntu One using the credentials you provide.
If it's the first time running the script, it will create a file named u1backup with the token
information from Ubuntu One.

When you execute it a second time, the token info will be already available and the script
won't authorize itself twice (unless you delete the file u1backup).

Finally, the files will be uploaded to Ubuntu One using OAuth PUT.

What you need
-------------

Besides setting things up in the script, you need PHP's OAuth.
After you have it installed, remember that this script will run in CLI,
which meand you need to enable OAuth in php.ini for CLI (it's a different file than Apache's).
