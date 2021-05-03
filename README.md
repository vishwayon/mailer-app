# Mailer-App

coreERP-Mailer Service Application 

This is the mailer service for coreERP that can be installed on the application server via systemd.


### Dependencies

* composer update  ## or tar -zxvf vendor.tar.gz
* PHP 7.0


Installation
------------
After cloning this git repository, open a ssh terminal to the server and type the following ::

    $ sudo nano /etc/systemd/system/coreERP-mailer.service

Copy the following code into the file with changes to /path/to/coreERP (ensure that this reflects the proper path that you used for download of source)

::

	[Unit]
	Description=CoreERP auto mailer service

	[Service]
	ExecStart=/usr/bin/php /path-to/mailer-app/SendMail.php '/path-to/cwfconfig.php'
	User=root
	Restart=always
	RestartSec=30


	[Install]
	WantedBy=multi-user.target


Remember to change the /path-to/mailer-app and /path-to/cwfconfig.php to reflect the proper path of 
application install on the local drive. Save and close (ctrl+o), (ctrl+x).

This script would ensure that every time your server is restarted, the script would automatically start. 

To ensure that it starts automatically with every reboot ::

    $ sudo systemctl enable coreERP-mailer
    $ sudo systemctl start coreERP-mailer

To verify the status, type ::

    $ sudo service coreERP-mailer status

If you run into any problems, look into the log file situated in /var/log/syslog

