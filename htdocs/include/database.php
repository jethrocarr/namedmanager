<?php
/*
	include/database.php

	Establishes connection to the MySQL database.
*/



// login to the database
$link = mysql_connect($config["db_host"], $config["db_user"], $config["db_pass"]);
if (!$link)
	die("Unable to connect to DB:" . mysql_error());

// select the database
$db_selected = mysql_select_db($config["db_name"], $link);
if (!$db_selected)
	die("Unable to connect to DB:" . mysql_error());




/*
	Bootstrap Framework

	We couldn't use the Amberphplib framework to connect to the database, however
	now that we have connected, we can force set the default values and it will
	use the connection we have established as the default for all queries.
*/

$GLOBALS["cache"]["database_default_link"]	= $link;
$GLOBALS["cache"]["database_default_type"]	= "mysql";



?>
