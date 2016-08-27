<?php
/*
	include/database.php

	Establishes connection to the MySQL database.
*/



// login to the database
$link = mysqli_connect($config["db_host"], $config["db_user"], $config["db_pass"]);
if (!$link)
	die("Unable to connect to DB:" . mysqli_error());

// select the database
$db_selected = mysqli_select_db($link, $config["db_name"]);
if (!$db_selected)
	die("Unable to connect to DB:" . mysqli_error());


// Disable SQL modes for this session to ensure backwards compat with
// newer MySQL version (> 5.6) using STRICT modes.

//mysql_query("SET SESSION sql_mode='';", $link);



/*
	Bootstrap Framework

	We couldn't use the Amberphplib framework to connect to the database, however
	now that we have connected, we can force set the default values and it will
	use the connection we have established as the default for all queries.
*/

$GLOBALS["cache"]["database_default_link"]	= $link;
$GLOBALS["cache"]["database_default_type"]	= "mysql";



?>
