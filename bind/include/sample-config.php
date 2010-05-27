<?php
/*
	Sample Configuration File

	Copy this file to config-settings.php

	This file should be read-only to the user whom the bind configuration scripts are running as.
*/



/*
	API Configuration
*/
$config["api_url"]		= "http://example.com/namedexample";			// Application Install Location
$config["api_server_name"]	= "dnsmaster.example.com";				// Name of the DNS server (important: part of the authentication process)
$config["api_auth_key"]		= "ultrahighsecretkey";					// API authentication key


/*
	Log Pipe File
$config["log_pipe"]		= "/var/run/phpfreeradius_log";
$config["log_owner"]		= "radiusd";

*/



/*
	Bind Configuration Files

	Theses files define what files that NamedManager will write to. By design, NamedManager does
	not write directly into the master named configuration file, but instead into a seporate file
	that gets included - which allows custom configuration and zones to be easily added without
	worries of them being over written by NamedManager.


*/

$config["bind"]["version"]		= "9";					// version of bind (currently only 9 is supported, although others may work)
$config["bind"]["reload"]		= "rndc reload";			// command to reload bind config & zonefiles
$config["bind"]["config"]		= "/etc/named.namedmanager.conf"	// configuration file to write bind config too
$config["bind"]["zonefiledir"]		= "/var/named/";			// directory to write zonefiles too
										// note: if using chroot bind, will often be /var/named/chroot/var/named/


// force debugging on for all users + scripts
// (note: debugging can be enabled on a per-user basis by an admin via the web interface)
//$_SESSION["user"]["debug"] = "on";


?>
