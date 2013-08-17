<?php
/*
	misc.php
	
	Various one-off functions
*/



/*
	CONFIGURATION FUNCTIONS 

	Configuration functions perform queries against the config DB with the structure of:
	
	CREATE TABLE `config` (
	  `name` varchar(255) NOT NULL default '',
	  `value` varchar(255) NOT NULL default '',
	  PRIMARY KEY  (`name`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
*/


/*
	config_generate_uniqueid()

	This function will generate a unique ID by looking up the current value of the supplied
	name from the config database, and will then work out an avaliable value.

	Once a suitable value has been determined, the code will return it and then update the 
	value in the config table.

	This function is ideal for when you need a field to be auto-incremented, but still providing
	the user the ability to over-write it with their own value.

	Values
	config_name	Name of the configuration field to fetch the value from
	check_sql	(optional) SQL query to check for current usage of this ID. Note that the VALUE keyword will
			be replaced by the code ID.
				eg: "SELECT id FROM mytable WHERE codevalue='VALUE'

	Returns
	#	unique ID to be used.
*/
function config_generate_uniqueid($config_name, $check_sql)
{
	log_debug("inc_misc", "Executing config_generate_uniqueid($config_name)");
	
	$config_name = strtoupper($config_name);
	
	$returnvalue = 0;
	$uniqueid = 0;
	

	// fetch the starting ID from the config DB
	$uniqueid	= sql_get_singlevalue("SELECT value FROM config WHERE name='$config_name'");

	if (!$uniqueid)
		die("Unable to fetch $config_name value from config database");
	
	// first set the uniqueid prefix to an empty string, in case the following tests fail	
	$uniqueid_prefix = '';
	
	if (!is_numeric($uniqueid))
	{
		preg_match("/^(\S*?)([0-9]*)$/", $uniqueid, $matches);

		$uniqueid_prefix	= $matches[1];
		$uniqueid		= (int)$matches[2];
	}
	

	if ($check_sql)
	{
		// we will use the supplied SQL query to make sure this value is not currently used
		while ($returnvalue == 0)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= str_replace("VALUE", $uniqueid_prefix.$uniqueid, $check_sql);
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				// the ID has already been used, try incrementing
				$uniqueid++;
			}
			else
			{
				// found an avaliable ID
				$returnvalue = $uniqueid;
			}
		}
		$returnvalue = $uniqueid_prefix.$returnvalue;
	}
	else
	{
		// conducting no DB checks.
		$returnvalue = $uniqueid_prefix.$uniqueid;
	}
	

	// update the DB with the new value + 1
	$uniqueid++;
				
	$sql_obj		= New sql_query;
	$sql_obj->string	= "UPDATE config SET value='{$uniqueid_prefix}{$uniqueid}' WHERE name='$config_name'";
	$sql_obj->execute();


	return $returnvalue;
}


/* ARRAY FUNCTIONS */


/*;
	array_insert_after
	
	Inserts an array into another array after a certain key
	http://stackoverflow.com/questions/1783089/array-splice-for-associative-arrays/1783125#1783125

	Values
	input		array
	key		key you want to splice 
	array		Filename or path

	Returns
	array		results
*/
function array_insert_after($input, $key, $segment)
{
	log_debug("inc_misc", "Executing array_insert_after($input, $key, $segment)");


	// TODO: what exactly does this comment mean? we are inserting at 0?
	// Insert at offset 2
	$offset = 0;
	
	foreach($input as $array_key => $values)
	{
		$offset++;
		if($key == $array_key) 
		{
			break;
		}
	} 
	log_debug("misc", "Inserting data into array after $key, offset $offset");
	
	
	$output = array_slice($input, 0, $offset, true) +
	$segment +
	array_slice($input, $offset, NULL, true);
	
	
	return $output;
}


/*
	derive_installation_url

	Returns the URL of the root of the Amberphplib-based application, typically needed
	for features such as RSS feeds.

	Returns
	string		Application root URL path
*/

function derive_installation_url()
{
	if($_SERVER['HTTPS'])
	{
		$protocol = "https://";
	}
	else
	{
		$protocol = "http://";
	} 
	
	$server_name	= $_SERVER['SERVER_NAME'];
	$script_dirname	= dirname($_SERVER['SCRIPT_NAME']);

	return $protocol.$server_name.$script_dirname."/"; 

}




/* FORMATTING/DISPLAY FUNCTIONS */


/*
	format_file_extension

	Returns only the extension portion of the supplied filename/filepath.

	Values
	filename	Filename or path

	Returns
	string		file extension (lowercase)
*/
function format_file_extension($filename)
{
	log_debug("misc", "Executing format_file_extension($filename)");

	return strtolower(substr(strrchr($filename,"."),1));
}


/*
	format_file_noextension

	Returns everything but the file extension

	Values
	filename	Filename or path

	Returns
	string		file name without extension
*/
function format_file_noextension($filename)
{
	log_debug("misc", "Executing format_file_noextension($filename)");

	// note: we can't use strstr to search before needle, as it's PHP 5.3.0+ only :'(

	$extension = strtolower(substr(strrchr($filename,"."),1));
	return str_replace(".$extension", "", $filename);
}



/*
	format_file_name

	Returns the filename & extension of the supplied filepath - effectively strips
	off the directory path.

	Values
	filepath	File path

	Returns
	string		filename
*/
function format_file_name($filepath)
{
	log_debug("misc", "Executing format_file_name($filepath)");

	return substr(strrchr($filepath,"/"),1);
}


/*
	format_file_contenttype

	Returns the MIME content type of the supplied field extension.

	Use with format_file_extension if you want to strip the extension information
	from a filename.

	Values
	file_extension	Extension of filename (without the .)

	Returns
	string		Ctype
*/

function format_file_contenttype($file_extension)
{
	log_debug("misc". "Executing format_file_contenttype($file_extension)");

	$ctype = NULL;

	switch ($file_extension)
	{
		case "pdf": $ctype="application/pdf"; break;
		case "exe": $ctype="application/octet-stream"; break;
		case "zip": $ctype="application/zip"; break;
		case "doc": $ctype="application/msword"; break;
		case "xls": $ctype="application/vnd.ms-excel"; break;
		case "ppt": $ctype="application/vnd.ms-powerpoint"; break;
		case "gif": $ctype="image/gif"; break;
		case "png": $ctype="image/png"; break;
		case "jpeg":
		case "jpg": $ctype="image/jpg"; break;
		case "csv": $ctype="text/csv"; break;
		default: $ctype="application/force-download";
	}

	return $ctype;
}



/*
	format_text_display($text)

	Formats a block of text from a database into a form suitable for display as HTML.

	Returns the processed text.
*/
function format_text_display($text)
{
	log_debug("misc", "Executing format_text_display(TEXT)");
	
	// replace unrenderable html tags of > and <
	$text = str_replace(">", "&gt;", $text);
	$text = str_replace("<", "&lt;", $text);
	
	// fix newlines last
	$text = str_replace("\n", "<br>", $text);

	return $text;
}


/*
	format_text_textarea($text)

	Formats a block of text from a database into a form suitable for display inside textarea forms.

	Returns the processed text.
*/
function format_text_textarea($text)
{
	log_debug("misc", "Executing format_text_textarea(TEXT)");
	
	// replace unrenderable html tags of > and <
	$text = str_replace(">", "&gt;", $text);
	$text = str_replace("<", "&lt;", $text);
	
	return $text;
}



/*
	format_size_human($bytes)

	Returns a human readable size.
*/
function format_size_human($bytes)
{
	log_debug("misc", "Executing format_size_human($bytes)");

	if(!$bytes)
	{
		// unknown - most likely the program hasn't called one one of the fetch_information_by_* functions first.
		log_debug("misc", "Error: Unable to determine file size - no value provided");	
		return "unknown size";
	}
	else
	{
		$file_size_types = array(" Bytes", " KB", " MB", " GB", " TB");
		return round($bytes/pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $file_size_types[$i];
	}
}

/*
	format_size_bytes($string)

	Converts a human readable size string to bytes.
*/
function format_size_bytes($string)
{
	log_debug("misc", "Executing format_size_bytes($string)");

	if(!$string)
	{
		// unknown - most likely the program hasn't called one one of the fetch_information_by_* functions first.
		log_debug("misc", "Error: Unable to determine file size - no value provided");	
		return "unknown size";
	}
	else
	{
		$string	= strtolower($string);
		$string = preg_replace("/\s*/", "", $string);			// strip spaces
		$string = preg_replace("/,/", "", $string);			// strip formatting
		$string = preg_match("/^([0-9]*)([a-z]*)$/", $string, $values);

		if ($values[2])
		{
			switch ($values[2])
			{
		        	case "g":
				case "gb":
					$bytes = (($values[1] * 1024) * 1024) * 1024;
				break;

				case "m":
				case "mb":
					$bytes = ($values[1] * 1024) * 1024;
				break;

				case "k":
				case "kb":
					$bytes = $values[1] * 1024;
				break;

				case "b":
				case "bytes":
				default:
					$bytes = int($values[1]);
				break;
			}
		}
		else
		{
			// assume value must be in bytes already.
			$bytes = int($values[1]);
		}

		return $bytes;
	}
}




/*
	format_msgbox($type, $text)

	Creates a coloured message box, based on the type.

	Supported types:
	important
	info
	bubble
*/
function format_msgbox($type, $text)
{
	log_debug("misc", "Executing format_msgbox($type, text)");

	print "<table width=\"100%\" class=\"table_highlight_$type\">";
	print "<tr>";
		print "<td>";
		print "$text";
		print "</td>";
	print "</tr>";
	print "</table>";
}

/*
	format_linkbox($type, $hyperlink, $text)

	Creates a coloured message box configured to take the user
	to the specified link upon being clicked

	Supported types:
	important
	info
*/
function format_linkbox($type, $hyperlink, $text)
{
	log_debug("misc", "Executing format_linkbox($type, $hyperlink, text)");

	print "<table width=\"100%\" class=\"table_linkbox_$type\" onclick=\"location.href='$hyperlink'\">";
	print "<tr>";
		print "<td>";
		print "$text";
		print "</td>";
	print "</tr>";
	print "</table>";
}



/*
	format_money($amount)

	Formats the provided floating integer and adds the default currency and applies
	rounding to it to make a number suitable for display.

	Set nocurrency to 1 to disable addition of the currency symbol.

	Set round to desired number of SF values, default is 2
*/
function format_money($amount, $nocurrency = NULL, $round = 2)
{
	log_debug("misc", "Executing format_money($amount)");
	
	//get separators
	$thousands	= $GLOBALS["config"]["CURRENCY_DEFAULT_THOUSANDS_SEPARATOR"];
	$decimal	= $GLOBALS["config"]["CURRENCY_DEFAULT_DECIMAL_SEPARATOR"];

	// formatting for readability
	$amount 	= number_format($amount, $round, $decimal, $thousands);


	if ($nocurrency)
	{
		return $amount;
	}
	else
	{
		// add currency & return
		$position = $GLOBALS["config"]["CURRENCY_DEFAULT_SYMBOL_POSITION"];

		if ($position == "after")
		{
			$result = "$amount ". $GLOBALS["config"]["CURRENCY_DEFAULT_SYMBOL"];
		}
		else
		{
			$result = $GLOBALS["config"]["CURRENCY_DEFAULT_SYMBOL"] ."$amount";
		}

		return $result;
	}
}


/*
	format_arraytocommastring($array)

	returns a provided array as a comma seporated string - very useful for creating value
	lists to be used in a SQL query.

	Fields
	array		Array of data.
	encase		(optional) surrond each value with the char - eg set to " to have a list like "optionA", "optionB", created.

	Returns
	string		Formatted string
*/
function format_arraytocommastring($array, $encase = NULL)
{
	log_write("debug", "misc", "Executing format_arraytocommastring(Array, $encase)");

	$returnstring = "";

	$array_num = count($array);

	for ($i=0; $i < $array_num; $i++)
	{
		$returnstring .= $encase . $array[$i] . $encase;

		if ($i != ($array_num - 1))
		{
			$returnstring .= ", ";
		}
	}

	return $returnstring;
}



/* TIME FUNCTION */


/*
	time_date_to_timestamp($date)

	returns a timestamp calculated from the provided YYYY-MM-DD date
*/
function time_date_to_timestamp($date)
{
	log_debug("misc", "Executing time_date_to_timestamp($date)");


	if ($date == "0000-00-00")
	{
		// feeding 0000-00-00 to mktime would cause an incorrect timedstamp to be generated
		return 0;
	}
	else
	{
		$date_a = explode("-", $date);

		return mktime(0, 0, 0, $date_a[1], $date_a[2] , $date_a[0]);
	}
}


/*
	time_bind_to_seconds($bindtime)

	Converts bind formatted time strings into seconds.

	Values
	bindtime	Format of:
			#S == seconds
			#M == minutes	60 seconds
			#H == Hours	3600 seconds
			#D == Days	86400 seconds
			#W == Weeks	604800 seconds

	Returns
	int		number of seconds
*/

function time_bind_to_seconds($bindtime)
{
	log_write("debug", "misc", "Executing time_bind_to_seconds($bindtime)");

	$bindtime = strtoupper($bindtime);

	// this works by multiplying by the appropate amount and converting
	// to an integer to strip the alpha characters.
	//
	switch (substr($bindtime, -1))
	{
		case "M":
			$bindtime = intval($bindtime) * 60;
		break;

		case "H":
			$bindtime = intval($bindtime) * 3600;
		break;

		case "D":
			$bindtime = intval($bindtime) * 86400;
		break;

		case "W":
			$bindtime = intval($bindtime) * 604800;
		break;

		case "S":
		default:
			intval($bindtime);
		break;
	}

	return $bindtime;

} // end of time_bind_to_seconds




/*
	time_format_hourmins($seconds)
	
	returns the number of hours, and the number of minutes in the form of H:MM
*/
function time_format_hourmins($seconds)
{
	log_debug("misc", "Executing time_format_hourmins($seconds)");
	
 	$minutes	= $seconds / 60;
	$hours		= sprintf("%d",$minutes / 60);

	$excess_minutes = sprintf("%02d", $minutes - ($hours * 60));


	// excess minutes must never be negative
	if ($excess_minutes < 0)
	{
		$excess_minutes = $excess_minutes * -1;
	}

	return "$hours:$excess_minutes";
}


/*
	time_format_humandate

	Provides a date formated in the user's perferred way. If no date is provided, will return the current date.

	Values
	date		Format YYYY-MM-DD OR unix timestamp (optional)
	time		(optional) TRUE/FALSE to inclue time

	Returns
	string		Date in human-readable format.
*/
function time_format_humandate($date = NULL, $time = FALSE)
{
	log_debug("misc", "Executing time_format_humandate($date)");

	if ($date)
	{
		if (preg_match("/^[0-9]*$/", $date))
		{
			// already a timestamp, yay!
			$timestamp = $date;
		}
		else
		{
			// convert date to timestamp so we can work with it
			$timestamp = time_date_to_timestamp($date);
		}
	}
	else
	{
		// no date supplied - generate current timestamp
		$timestamp = time();
	}


	if (isset($_SESSION["user"]["dateformat"]))
	{
		// fetch from user preferences
		$format = $_SESSION["user"]["dateformat"];
	}
	else
	{
		// user hasn't chosen a default time format yet - use the system
		// default
		$format = $GLOBALS["config"]["DATEFORMAT"];
	}


	// convert to human readable format
	$string = "";

	switch ($format)
	{
		case "mm-dd-yyyy":
			$string = date("m-d-Y", $timestamp);
		break;

		case "dd-mm-yyyy":
			$string = date("d-m-Y", $timestamp);
		break;

		case "dd-Mmm-yyyy":
			return date("d-M-Y", $timestamp);
		break;
		
		case "yyyy-mm-dd":
		default:
			$string = date("Y-m-d", $timestamp);
		break;
	}
	
	if ($time)
	{
		return $string ." ". date("H:i");
	}
	else
	{
		return $string;
	}
}



/*
	time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)

	returns the start date of the week in format YYYY-MM-DD
	
*/
function time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)
{
	log_debug("misc", "Executing time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)");
	
	// work out the start date of the current week
	$date_curr_weekofyear	= date("W");
	$date_curr_year		= date("Y");
	$date_curr_start	= mktime(0, 0, 0, date("m"), ((date("d") - date("w")) + 1) , $date_curr_year);

	// work out the difference in the number of weeks desired
	$date_selected_weekdiff	= ($date_curr_year - $date_selected_year) * 52;
	$date_selected_weekdiff += ($date_curr_weekofyear - $date_selected_weekofyear);

	// work out the difference in seconds (1 week == 604800 seconds)
	$date_selected_seconddiff = $date_selected_weekdiff * 604800;

	// timestamp of the first day in the week.
	$date_selected_start = $date_curr_start - $date_selected_seconddiff;

	return date("Y-m-d", $date_selected_start);
}


/*
	time_calculate_daysofweek($date_selected_start_ts)

	Passing YYYY-MM-DD of the first day of the week will
	return an array containing date of each day in YYYY-MM-DD format
*/
function time_calculate_daysofweek($date_selected_start)
{
	log_debug("misc", "Executing time_calculate_daysofweek($date_selected_start)");

	$days = array();

	// get the start day, month + year
	$dates = explode("-", $date_selected_start);
	
	// get the value for all the days
	for ($i=0; $i < 7; $i++)
	{
		$days[$i] = date("Y-m-d", mktime(0,0,0,$dates[1], ($dates[2] + $i), $dates[0]));
	}

	return $days;
}


/*
	time_calculate_daynum($date)

	Calculates what day the supplied date is in. If not date is supplied, then
	returns the current day.
*/
function time_calculate_daynum($date = NULL)
{
	log_debug("misc", "Executing time_calculate_daynum($date)");

	if (!$date)
	{
		return date("d");
	}
	else
	{
		preg_match("/-([0-9]*)$/", $date, $matches);

		return $matches[1];
	}
}



/*
	time_calculate_weeknum($date)

	Calculates what week the supplied date is in. If not date is supplied, then
	returns the current week.
*/
function time_calculate_weeknum($date = NULL)
{
	log_debug("misc", "Executing time_calculate_weeknum($date)");

	if (!$date)
	{
		$date = date("Y-m-d");
	}


	/*
		Use the SQL database to get the week number based on ISO 8601
		selection criteria.

		Note that we intentionally use SQL instead of the php date("W") function, since
		in testing the date("W") function has been found to beinconsistant on different systems.

		TODO: Investigate further what is wrong with PHP date("W")
	*/
	return sql_get_singlevalue("SELECT WEEK('$date',1) as value");
}


/*
	time_calculate_monthnum($date)

	Calculates what month the supplied date is in. If not date is supplied, then
	returns the current month.
*/
function time_calculate_monthnum($date = NULL)
{
	log_debug("misc", "Executing time_calculate_monthnum($date)");

	if (!$date)
	{
		return date("m");
	}
	else
	{
		preg_match("/^[0-9]*-([0-9]*)-/", $date, $matches);

		return $matches[1];
	}
}



/*
	time_calculate_yearnum($date)

	Calculates what year the supplied date is in. If not date is supplied, then
	returns the current year.
*/
function time_calculate_yearnum($date = NULL)
{
	log_debug("misc", "Executing time_calculate_yearnum($date)");

	if (!$date)
	{
		return date("Y");
	}
	else
	{
		preg_match("/^([0-9]*)-/", $date, $matches);

		return $matches[1];
	}
}



/*
	time_calculate_monthday_first($date)

	Calculates what the first date of the month is, for the provided date. If
	no date is provided, returns for the current month.
*/
function time_calculate_monthdate_first($date = NULL)
{
	log_debug("misc", "Executing time_calculate_monthday_first($date)");

	if (!$date)
	{
		$date = date("Y-m-d");
	}

	$date = preg_replace("/-[0-9]*$/", "-01", $date);

	return $date;
}
	

/*
	time_calculate_monthday_last($date)

	Calculates what the final date of the month is, for the provided date. If
	no date is provided, returns for the current month.
*/
function time_calculate_monthdate_last($date = NULL)
{
	log_debug("misc", "Executing time_calculate_monthday_last($date)");

	if (!$date)
	{
		$timestamp	= time();
		$date		= date("Y-m-d", $timestamp);
	}
	else
	{
		$timestamp = time_date_to_timestamp($date);
	}
	
	// fetch the final day of the month
	$lastday = date("t", $timestamp);
	
	// replace the day with the final day
	$date = preg_replace("/-[0-9]*$/", "-$lastday", $date);

	// done
	return $date;
}
	




/* HELP FUNCTIONS */

/*
	helplink( id )
	returns an html string, including a help icon, with a hyperlink to the help page specified by id.
*/

function helplink($id)
{
	return "<a href=\"javascript:url_new_window_minimal('help/viewer.php?id=$id');\" title=\"Click here for a popup help box\"><img src=\"images/icons/help.gif\" alt=\"?\" border=\"0\"></a>";
}




/* LOGGING FUNCTIONS */


/*
	log_error_render()

	Displays any error logs
*/
function log_error_render()
{
        if ($_SESSION["error"]["message"])
        {
		print "<table class=\"error_table\">";
                print "<tr><td class=\"error_td\">";
                print "<p><b>Error:</b><br><br>";

		foreach ($_SESSION["error"]["message"] as $errormsg)
		{
			print "$errormsg<br>";
		}
		
		print "</p>";
                print "</td></tr>";
		print "</table>";
	}
}


/*
	log_notification_render()

	Displays any notification messages, provided that there are no error messages as well
*/
function log_notification_render()
{
        if (isset($_SESSION["notification"]["message"]) && !isset($_SESSION["error"]["message"]))
        {
		print "<table class=\"notification_table\">";
                print "<tr><td class=\"notification_td\">";
                print "<p><b>Notification:</b><br><br>";
		
		foreach ($_SESSION["notification"]["message"] as $notificationmsg)
		{
			print "$notificationmsg<br>";
		}

		print "</p>";
                print "</td></tr>";
		print "</table>";
        }
}




/*
	log_debug_render()

	Displays the debugging log - suitable for both CLI and web UI display - could do
	with some level of more modular split.
*/
function log_debug_render()
{
	log_debug("inc_misc", "Executing log_debug_render()");


	if (!empty($_SESSION["mode"]))
	{
		if ($_SESSION["mode"] == "cli")
		{
			/*
				CLI Interface

				Limited to a statistical display only.
			*/

			// get first time entry
			$time_first = (float)$_SESSION["user"]["log_debug"][0]["time_sec"] + (float)$_SESSION["user"]["log_debug"][0]["time_usec"];

			// count SQL queries
			$num_sql_queries	= 0;
			$num_cache_hits		= 0;
		
			// run through the log to get stats
			foreach ($_SESSION["user"]["log_debug"] as $log_record)
			{
				// get last time entry
				$time_last = (float)$log_record["time_sec"] + (float)$log_record["time_usec"];

				// last memmor
				$memory_last = $log_record["memory"];

				// choose formatting
				switch ($log_record["type"])
				{
					case "sql":
						$num_sql_queries++;
					break;

					case "cache":
						$num_cache_hits++;
					break;

					default:
						// nothing todo
					break;
				}
			}
			
			// report completion time
			$time_diff = ($time_last - $time_first);

			// display
			log_write("debug", "stats", "----");
			log_write("debug", "stats", "Application execution time:\t". $time_diff  ." seconds");
			log_write("debug", "stats", "Total Memory Consumption:\t". number_format($memory_last) ." bytes.");
			log_write("debug", "stats", "SQL Queries Executed:\t". number_format($num_sql_queries) ." queries.");
			log_write("debug", "stats", "Total Cache Hits:\t\t". number_format($num_cache_hits) ." cache lookups.");
			log_write("debug", "stats", "----");

		} // end if CLI

	} // end if CLI
	else
	{
		/*
			Web Interface
		*/


		print "<p><b>Debug Output:</b></p>";
		print "<p><i>Please be aware that debugging will cause some impact on performance and should be turned off in production.</i></p>";
		
		
		// table header
		print "<table class=\"table_content\" width=\"100%\" cellspacing=\"0\">";
		
		print "<tr class=\"header\">";
			print "<td nowrap><b>Time</b></td>";
			print "<td nowrap><b>Memory</b></td>";
			print "<td nowrap><b>Type</b></td>";
			print "<td nowrap><b>Category</b></td>";
			print "<td><b>Message/Content</b></td>";
		print "</tr>";

		// get first time entry
		$time_first = (float)$_SESSION["user"]["log_debug"][0]["time_sec"] + (float)$_SESSION["user"]["log_debug"][0]["time_usec"];

		// count SQL queries
		$num_sql_queries	= 0;
		$num_cache_hits		= 0;

		// content
		foreach ($_SESSION["user"]["log_debug"] as $log_record)
		{
			// get last time entry
			$time_last = (float)$log_record["time_sec"] + (float)$log_record["time_usec"];


			// choose formatting
			switch ($log_record["type"])
			{
				case "error":
					print "<tr bgcolor=\"#ff5a00\">";
				break;

				case "warning":
					print "<tr bgcolor=\"#ffeb68\">";
				break;

				case "sql":
					print "<tr bgcolor=\"#7bbfff\">";
					$num_sql_queries++;
				break;

				case "cache":
					print "<tr bgcolor=\"#ddf9ff\">";
					$num_cache_hits++;
				break;

				default:
					print "<tr>";
				break;
			}
			
			// display
			print "<td nowrap>". $time_last  ."</td>";
			print "<td nowrap>". format_size_human($log_record["memory"]) ."</td>";
			print "<td nowrap>". $log_record["type"] ."</td>";
			print "<td nowrap>". $log_record["category"] ."</td>";
			print "<td>". $log_record["content"] ."</td>";
			print "</tr>";


		}

		print "</table>";


		// report completion time
		$time_diff = ($time_last - $time_first);

		print "<p>Completed in $time_diff seconds.</p>";

		// report number of SQL queries
		print "<p>Executed $num_sql_queries of SQL queries.</p>";
		print "<p>Executed $num_cache_hits cache lookups.</p>";

	} // end if web UI
}


/*
	FILESYSTEM FUNCTIONS
*/


/*
	file_generate_name

	Generates a unique name based on the base name provided and touches it to reserve it.
	
	File permissions are 660, limiting access to webserver user for security reasons.

	Fields
	basename		Base of the filename
	extension		Extension for the file (if any)

	Returns
	string			Name for an avaliable file
*/
function file_generate_name($basename, $extension = NULL)
{
	log_debug("inc_misc", "Executing file_generate_name($basename, $extension)");
	

	if ($extension)
	{
		$extension = ".$extension";
	}

	// calculate a temporary filename
	$uniqueid = 0;
	while (!isset($complete) || ($complete == ""))
	{
		$filename = $basename ."_". time() ."_$uniqueid" . $extension;

		if (file_exists($filename))
		{
			// the filename has already been used, try incrementing
			$uniqueid++;
		}
		else
		{
			// found an avaliable ID
			touch($filename);
			chmod($filename, 0660);		// note: what happens on windows?
			return $filename;
		}
	}
}



/*
	file_generate_tmpfile

	Generates a tempory file and returns the full path & filename - files do
	not automatically get deleted, unless the temp dir is subject to an external
	process such as tmpwatch.

	Returns
	string		tmp filename & path
*/
function file_generate_tmpfile()
{
	log_debug("inc_misc", "Executing file_generate_tmpfile()");

	$path_tmpdir = sql_get_singlevalue("SELECT value FROM config WHERE name='PATH_TMPDIR'");

	return file_generate_name("$path_tmpdir/temporary_file");
}



/*
	HTTP/HEADER FUNCTIONS
*/


/*
	http_header_lookup

	Returns the full HTTP header string for the specified return code

	Fields
	num		number of the HTTP code to return

	Returns
	string		HTTP header string
*/

function http_header_lookup($num)
{
	log_debug("inc_misc", "Executing http_header_lookup($num)");

	$return_codes = array (
		100 => "HTTP/1.1 100 Continue",
		101 => "HTTP/1.1 101 Switching Protocols",
		200 => "HTTP/1.1 200 OK",
		201 => "HTTP/1.1 201 Created",
		202 => "HTTP/1.1 202 Accepted",
		203 => "HTTP/1.1 203 Non-Authoritative Information",
		204 => "HTTP/1.1 204 No Content",
		205 => "HTTP/1.1 205 Reset Content",
		206 => "HTTP/1.1 206 Partial Content",
		300 => "HTTP/1.1 300 Multiple Choices",
		301 => "HTTP/1.1 301 Moved Permanently",
		302 => "HTTP/1.1 302 Found",
		303 => "HTTP/1.1 303 See Other",
		304 => "HTTP/1.1 304 Not Modified",
		305 => "HTTP/1.1 305 Use Proxy",
		307 => "HTTP/1.1 307 Temporary Redirect",
		400 => "HTTP/1.1 400 Bad Request",
		401 => "HTTP/1.1 401 Unauthorized",
		402 => "HTTP/1.1 402 Payment Required",
		403 => "HTTP/1.1 403 Forbidden",
		404 => "HTTP/1.1 404 Not Found",
		405 => "HTTP/1.1 405 Method Not Allowed",
		406 => "HTTP/1.1 406 Not Acceptable",
		407 => "HTTP/1.1 407 Proxy Authentication Required",
		408 => "HTTP/1.1 408 Request Time-out",
		409 => "HTTP/1.1 409 Conflict",
		410 => "HTTP/1.1 410 Gone",
		411 => "HTTP/1.1 411 Length Required",
		412 => "HTTP/1.1 412 Precondition Failed",
		413 => "HTTP/1.1 413 Request Entity Too Large",
		414 => "HTTP/1.1 414 Request-URI Too Large",
		415 => "HTTP/1.1 415 Unsupported Media Type",
		416 => "HTTP/1.1 416 Requested range not satisfiable",
		417 => "HTTP/1.1 417 Expectation Failed",
		500 => "HTTP/1.1 500 Internal Server Error",
		501 => "HTTP/1.1 501 Not Implemented",
		502 => "HTTP/1.1 502 Bad Gateway",
		503 => "HTTP/1.1 503 Service Unavailable",
		504 => "HTTP/1.1 504 Gateway Time-out"       
	);

	return $return_codes[$num];
}




/*
	dir_generate_name

	Generates a unique directory based on the base name provided and creates it.
	
	Dir permissions are 770, limiting access to webserver user for security reasons.

	Fields
	basename		Base of the directory name,

	Returns
	string			Name of the directory
*/
function dir_generate_name($basename)
{
	log_debug("inc_misc", "Executing dir_generate_name($basename)");
	

	// calculate a temporary directory name
	$uniqueid = 0;
	while (!isset($complete) || $complete == "")
	{
		$dirname = $basename ."_". time() ."_$uniqueid";

		if (file_exists($dirname))
		{
			// the dirname has already been used, try incrementing
			$uniqueid++;
		}
		else
		{
			// found an avaliable ID
			mkdir($dirname);
			chmod($dirname, 0770);		// note: what happens on windows?
			return $dirname;
		}
	}
}


/*
	dir_generate_tmpdir

	Generates a tempory directory and returns the full path - directories do
	not automatically get deleted, unless the temp dir is subject to an external
	process such as tmpwatch.

	Returns
	string		directory path
*/
function dir_generate_tmpdir()
{
	log_debug("inc_misc", "Executing dir_generate_tmpfile()");

	$path_tmpdir = sql_get_singlevalue("SELECT value FROM config WHERE name='PATH_TMPDIR'");

	return dir_generate_name("$path_tmpdir/temporary_dir");
}


/*
	dir_list_contents

	Returns an array listing all files (recursively) in the selected directory.

	Values
	directory	(optional) defaults to current dir

	Returns
	0		failure
	array		list of directories

*/
function dir_list_contents($directory='.')
{
	log_debug("inc_misc", "Executing dir_list_contents($directory)");

	 $files = array();

	  if (is_dir($directory))
	  {
		$fh = opendir($directory);

		// loop through files
		while (($file = readdir($fh)) !== false)
		{
			if ($file != "." && $file != "..")
			{
				$filepath = $directory . '/' . $file;

				array_push($files, $filepath);
				
				if ( is_dir($filepath) )
				{
					$files = array_merge($files, dir_list_contents($filepath));
				}
			}
		}

		closedir($fh);
	}
	else
	{
		log_write("error", "inc_misc", "Invalid/non-existant directory supplied");
		return 0;
	}

	return $files;
}




/*
	IPv4 Networking Functions
*/



/*
	ip_type_detect

	Returns the type of IP address for the specified value (v4 or v6). This function assumes a
	single address is being provided only (no ranges/subnets).

	Returns
	0		Failure/Error
	4		IPv4
	6		IPv6
*/
function ip_type_detect($address)
{
	log_debug("inc_misc", "Executing ip_type_detect($address)");
     
     	return strpos($address, ":") === false ? 4 : 6;
}


/*
	ipv4_subnet_members

	Returns an array of all IP addresses in the provided subnet

	TODO/IMPORTANT: This function has been found to sometimes do unexpected weirdness with
			versions of PHP older than 5.3.0, this should be investigated in more detail
			and a version check or version-specific workaround to be implemented.

	Fields
	address_with_cidr	IP and subnet in CIDR notation (eg: 192.168.0.0/24)
	include_network		(optional, default == FALSE) Return the network and broadcast addresses too.

	Returns
	0		Failure
	array		Array of all IPs belonging to subnet
*/

function ipv4_subnet_members($address_with_cidr, $include_network = FALSE)
{
	log_write("debug", "inc_misc", "Executing ipv4_subnet_members($address_with_cidr, $include_network)");

	$address = explode('/', $address_with_cidr);			// eg: 192.168.0.0/24


	// calculate subnet mask
	$bin = NULL;

	for ($i = 1; $i <= 32; $i++)
	{
		$bin .= $address[1] >= $i ? '1' : '0';
	}

	// calculate key values
	$long_netmask	= bindec($bin);					// eg: 255.255.255.0
	$long_network	= ip2long($address[0]);				// eg: 192.168.0.0
	$long_broadcast	= ($long_network | ~($long_netmask));		// eg: 192.168.0.255


	// run through the range and generate all possible IPs
	// do not include mask/network IPs
	$return = array();

	if ($include_network)
	{
		// include network ranges
		for ($i = $long_network; $i <= $long_broadcast; $i++)
		{
			$return[] = long2ip($i);
		}
	}
	else
	{
		// include network ranges
		for ($i = ($long_network + 1); $i < $long_broadcast; $i++)
		{
			$return[] = long2ip($i);
		}
	}

	return $return;

} // end of ipv4_subnet_members


/*
	ipv4_split_to_class_c

	Takes the provided network & CIDR notation (less than /24) and divides it
	into multiple /24s, returning those in CIDR notation in an array.

	Fields
	address_with_cidr

	Returns
	0	Failure
	array	Array of /24 CIDR notation networks without notation
*/

function ipv4_split_to_class_c($address_with_cidr)
{
	log_write("debug", "inc_misc", "Executing ipv4_split_to_class_c($address_with_cidr)");

	// source range
	$matches	= split("/", $address_with_cidr);

	$src_addr	= $matches[0];
	$src_cidr	= $matches[1];


	// calculate subnet mask
	$bin = NULL;

	for ($i = 1; $i <= 32; $i++)
	{
		$bin .= $src_cidr >= $i ? '1' : '0';
	}

	// calculate key values
	$long_netmask	= bindec($bin);					// eg: 255.255.255.0
	$long_network	= ip2long($src_addr);				// eg: 192.168.0.0
	$long_broadcast	= ($long_network | ~($long_netmask));		// eg: 192.168.0.255
	$long_classc	= ip2long("0.0.1.0");				// used for addition calculations

	$long_broadcast	= ip2long(long2ip($long_broadcast));		// fixes ugly PHP math issues -without this
									// the broadcast long is totally incorrect.


	/*
		Debugging

	print "addr: $address_with_cidr <br>";
	print "netmask: $long_netmask ". long2ip($long_netmask) ."<br>";
	print "network: $long_network ". long2ip($long_network) ."<br>";
	print "broadcast: $long_broadcast ". long2ip($long_broadcast) ."<br>";
	print "classc: $long_classc ". long2ip($long_classc) ."<br>";

	*/


	// get network addresses for /24s
	$curr	= $long_network;
	$return	= array();

	while ($curr < $long_broadcast)
	{
		$return[]	= long2ip($curr);
		$curr		= $curr + $long_classc;
	}

	return $return;

} // end of ipv4_split_to_class_c


/*
	ipv4_convert_arpa

	Converts the provided IPv4 address into the arpa format typically
	used for reverse DNS.

	Fields
	ipaddress (IPv4)

	Returns
	0		Invalid IP address
	string		apra format eg 0.168.192.in-addr.arpa
*/

function ipv4_convert_arpa( $ipaddress )
{
	log_write("debug", "inc_misc", "Executing ipv4_convert_arpa( $ipaddress )");

	$tmp_network = explode(".", $ipaddress);

	$result = $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";

	return $result;

} // end of ipv4_convert_arpa


/*
	ipv6_convert_arpa

	Converts the provided IPv6 address into the arpa format typically
	used for reverse DNS. Supports both CIDR and non-CIDR format addresses

	Fields
	ipaddress (IPv6)

	Returns
	0	Invalid IP Address / Other Error
	string	arpa format eg 1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa.
*/

function ipv6_convert_arpa( $ipaddress )
{
	log_write("debug", "inc_misc", "Executing ipv6_convert_arpa( $ipaddress)");

	if (preg_match("/^([0-9a-f:]*)\/([0-9]*)$/i", $ipaddress, $matches))
	{
		$cidr		= $matches[2];
		$addr		= inet_pton($matches[1]);
		$unpack		= unpack('H*hex', $addr);
		$hex		= $unpack['hex'];
		$hex_array	= str_split($hex);
		
		for ($i=0; $i < ($cidr / 4); $i++)
		{
			$hex_array2[$i] = $hex_array[$i];
		}

		$result		= implode('.', array_reverse($hex_array2)) . '.ip6.arpa';
	}
	else
	{
		$addr	= inet_pton($ipaddress);
		$unpack	= unpack('H*hex', $addr);
		$hex	= $unpack['hex'];
		$result	= implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
	}

	return $result;

} // end of ipv6_convert_arpa


/*
	ipv6_convert_fromarpa

	Takes the provided apra/PTR format IPv6 address and turns it into
	a regular IPv6 address.

	Fields
	ipaddress_arpa (IPv6 arpa record, eg 1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa.)

	Returns
	0	Invalid IP Address / Other Error
	string	IPv6 address, eg 2001:db8::1 or 2001:db8::/32
*/


function ipv6_convert_fromarpa ($arpa)
{
	// credit to http://stackoverflow.com/questions/6619682/convert-ipv6-to-nibble-format-for-ptr-records
	log_write("debug", "inc_misc", "Executing ipv6_convert_fromarpa($arpa)");

	$mainptr	= substr($arpa, 0, strlen($arpa)-9);
	$pieces		= array_reverse(explode(".",$mainptr));  
	$pieces2	= $pieces;

	if (count($pieces) < 32)
	{
		// network? append the zeros to make the conversion work
		$missing = 32 - count($pieces);

		for ($i=0; $i < $missing; $i++)
		{
			$pieces2[] = '0';
		}
	}

        $hex		= implode("",$pieces2);
	$ipbin		= pack('H*', $hex);
	$ipv6addr	= inet_ntop($ipbin);

	// Is it a network address and requires a subnet mask? If so, we can tell by
	// the length of the record and calculate the range from that
	//
	// TODO / Warning: This logic always assumes you have an arpa address that is dividable
	// by 4 (eg /48, /52, /56, etc). It's possible to have weirder subnets (eg /49) but
	// there's little/no good way to detect if this is the case without already knowing
	// the subnet mask.
	//
	// Of course if I'm wrong and you can fix this, please send me a patch and I'll add
	// a comment proclaiming your coding glory. :-)
	//
	// We only really use this function for making it easier to import IPv6 domains
	// from zonefiles anyway....
	//
	if (count($pieces) < 32)
	{
		$ipv6cidr = count($pieces) * 4;
		$ipv6addr = $ipv6addr .'/'. $ipv6cidr;
	}

	return $ipv6addr;
}


/*
	SORTING FUNCTIONS

	These functions exist to help with special sorting circumstances
*/

function sort_natural_ipaddress($a, $b)
{
	return strnatcmp($a["ipaddress"], $b["ipaddress"]);
}





/*
	DEBUGGING/PROGRAMMER ASSIST FUNCTIONS

	The following functions are intended for developers working with the Amberphplib codebase
	or wanting to run debug proceedures.
*/


/*
	break_array

	Displays the provided array and then terminates the application with die() if set.

	Fields
	array
	die		1 == kill, 0 == continue

*/

function break_array($array, $die = 0)
{
	print "<pre>";
	print_r($array);
	print "</pre>";

	if ($die)
	{
		die("Forced execute of break_array()");
	}

} // end of break_array




?>
