<?php
//
// include/errors.php
//
// contains a mixture of error handling functions, data transport functions, and notification services.
//
//
// FUNCTIONS:
// error_render_message()
//	displays the error and/or notification message in the uniform way
//
// error_render_input(value)
//	if the value is "error", it will return a colour statment.
//	if the value is something else, it will return a value statment
//
// error_render_checkbox(value)
//	same as the above, but suited for a checkbox.
//
// error_render_table(value)
//	renders a table cell to the error colour, if the value is of an error. Used to highlight objects such as check boxes.
//
// error_render_nologin()
//	if user needs to login to view this page, set an error message.
//
// error_render_noperms()
//	if user doesn't have the right perms to view this page, let em' know.
//


/*
	Is this obsolete?
	
function error_render_message()
{
	if ($_SESSION["error"]["message"])
	{
		?>
		<table width="100%" cellpadding="3" cellspacing="0" bgcolor="#ffeda4" style="border: 1px solid #ffcc00">
		<tr>
			<td width="100%">
				<p><b>The following errors have been returned:</b><br><br>
				<?php print $_SESSION["error"]["message"]; ?></p>
			</td>
		</tr>
		</table>

		<?php
	}

	if ($_SESSION["notification"]["message"])
	{
		?>
		<table width="100%" cellpadding="3" cellspacing="0" bgcolor="#c7e8ed" style="border: 1px solid #83b9c1">
		<tr>
			<td width="100%">
				<p><b>Notification:</b><br><br>
				<?php print $_SESSION["notification"]["message"]; ?></p>
			</td>
		</tr>
		</table>

		<?php
	}


	return 1;
}
*/


function error_render_nologin()
{
	// check if the user is yet to login, or if they have been logged out due to inactivity.
	if (!empty($_SESSION["user"]["timeout"]))
	{
		// user has been logged out due to time.
		$_SESSION["error"]["message"] = array("You have been logged out due to inactivity. You need to login again to continue from where you were. Please <a href=\"index.php?page=user/login.php\">click here</a> to login.");
	}
	else
	{
	
		$_SESSION["error"]["message"] = array("You must be logged in to view this page! Please <a href=\"index.php?page=user/login.php\">click here</a> to login.");
	
		// save query string (so a login can take the user to the correct page)
		$_SESSION["login"]["previouspage"] = $_SERVER["QUERY_STRING"];

	}

	// end the page
	$_SESSION["error"]["pagestate"] = 0;
	

	return 1;
}


function error_render_noperms()
{
	// display a different message if the user isn't online
	if (user_online())
	{
		$_SESSION["error"]["message"] = array("Sorry, your login permissions do not permit you to access this content.");

		// end the page
		$_SESSION["error"]["pagestate"] = 0;
	}
	else
	{
		error_render_nologin();
	}

	return 1;
}


function error_render_input($value)
{
	// check if error reporting is occuring
	if ($_SESSION["error"][$value ."-error"])
	{
		print "  style=\"background-color: #ffeda4;\"";
	}
	
	print " value=\"". $_SESSION["error"][$value] . "\"";
	
	return 1;
}


function error_render_checkbox($value)
{
	// we can't colour the checkbox text, so this function simply checks the box or not.
	if ($_SESSION["error"]["$value"] == "on")
	{
			print " checked";
	}
	
	return 1;
}


function error_render_table($value)
{
	// check if error reporting is occuring
	if ($_SESSION["error"]["$value-error"])
	{
		print " bgcolor=\"#ffeda4\"";
	}
	
	return 1;
}


/*
	error_check

	Determines if any error messages currently exist in the session data.

	Returns
	0	No error messages
	1	Errors exist
*/
function error_check()
{
	log_debug("inc_errors", "Executing error_check()");

	if (isset($_SESSION["error"]["message"]))
	{
		return 1;
	}

	return 0;
}	


/*
	error_clear

	Erases any error messages
*/
function error_clear()
{
	log_debug("inc_errors", "Executing error_clear()");

	$_SESSION["error"] = array();

	return 1;
}	



/*
	error_flag_field

	Marks a form field as having an error.
*/
function error_flag_field($fieldname)
{
	log_debug("inc_errors", "Executing error_flag_field($fieldname)");

	$_SESSION["error"][$fieldname ."-error"] = 1;

	return 1;
}





?>
