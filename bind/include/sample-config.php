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
	Log file to find messages from Named. Note that:

	* File should be in syslog format
	* Named Manager uses tail -f to read it, this can break with logrotate - make sure that either "copytruncate" mode is used, or tail processes are killed
*/

$config["log_file"]		= "/var/log/messages";



/*
	Lock File

	Used to prevent clashes when multiple instances are accidently run.
*/

$config["lock_file"]		= "/var/lock/namedmanager_lock";





/*
	Bind Configuration Files

	Theses files define what files that NamedManager will write to. By design, NamedManager does
	not write directly into the master named configuration file, but instead into a seporate file
	that gets included - which allows custom configuration and zones to be easily added without
	worries of them being over written by NamedManager.


*/

$config["bind"]["version"]		= "9";					// version of bind (currently only 9 is supported, although others may work)
$config["bind"]["reload"]		= "/usr/sbin/rndc reload";		// command to reload bind config & zonefiles
$config["bind"]["config"]		= "/etc/named.namedmanager.conf";	// configuration file to write bind config too
$config["bind"]["zonefiledir"]		= "/var/named/";			// directory to write zonefiles too
										// note: if using chroot bind, will often be /var/named/chroot/var/named/
$config["bind"]["verify_zone"]		= "/usr/sbin/named-checkzone";		// Used to verify each generated zonefile as OK
$config["bind"]["verify_config"]	= "/usr/sbin/named-checkconf";		// Used to verify generated NamedManager configuration



/*
	Unusual Compatibility Options
*/

// Include a full path to the zonefiles in Bind - useful if Bind lacks a
// directory configuration or you really, really to store you zonefiles
// in a different location
//
// $config["bind"]["zonefullpath"]		= "on";	


// force debugging on for all users + scripts
// (note: debugging can be enabled on a per-user basis by an admin via the web interface)
//$_SESSION["user"]["debug"] = "on";


?>
