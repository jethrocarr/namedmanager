<?php
/*
	user/login-process.php
	
	Logs the user in
*/


// includes
require("../include/config.php");
require("../include/amberphplib/main.php");

// erase any data - gets rid of stale errors and user sessions.

if ($_SESSION["user"]["debug"] == "on")
{
	$_SESSION["error"] = array();
	$_SESSION["user"] = array();


	$_SESSION["user"]["debug"] = "on";

	log_debug("start", "");
	log_debug("start", "ERASED LOGGING AS PART OF USER SESSION CLEANUP");
	log_debug("start", "");
}
else
{
	$_SESSION["error"] = array();
	$_SESSION["user"] = array();
}

if (user_online())
{
	// user is already logged in!
	$_SESSION["error"]["message"][] = "You are already logged in!";
	$_SESSION["error"]["username_namedmanager"] = "error";
	$_SESSION["error"]["password_namedmanager"] = "error";

}
else
{
	// check & convert input
	$instance	= NULL;
	$username	= security_form_input("/^[A-Za-z0-9.]*$/", "username_namedmanager", 1, "Please enter a username.");
	$password	= security_form_input("/^\S*$/", "password_namedmanager", 1, "Please enter a password.");


	if ($_SESSION["error"]["message"])
	{
		// errors occured
		header("Location: ../index.php?page=user/login.php");
		exit(0);
	}




	// call the user functions to authenticate the user and handle blacklisting
	$result = user_login($instance, $username, $password);

	if ($result == 1)
	{
		// login succeded

		// if user has been redirected to login from a previous page, lets take them to that page.
		if ($_SESSION["login"]["previouspage"])
		{	
			header("Location: ../index.php?" . $_SESSION["login"]["previouspage"] . "");
			$_SESSION["login"] = array();
			exit(0);
		}
		else
		{
			// no page? take them to home.
			header("Location: ../index.php?page=home.php");
			exit(0);
		}
	}
	else
	{
		// login failed
		$_SESSION["error"]["form"]["login"] = "failed";

		// if no errors were set for other reasons (eg: the user forgetting to input any password at all)
		// then display the incorrect username/password error.
		if (!$_SESSION["error"]["message"])
		{
			$_SESSION["error"]["message"][] = "That username and/or password is invalid!";
			$_SESSION["error"]["username_namedmanager-error"] = 1;
			$_SESSION["error"]["password_namedmanager-error"] = 1;
		}

		if ($result == -3)
		{
			$_SESSION["error"]["instance_namedmanager-error"] = 1;
		}
		
		// errors occured
		header("Location: ../index.php?page=user/login.php");
		exit(0);

	} // end of errors.
	
} // end of "is user logged in?"



?>
