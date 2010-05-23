<?php
//
// user/logout-process.php
//
// process user logout.
//

include_once("../include/config.php");
include_once("../include/amberphplib/main.php");


if (user_logout())
{
	$_SESSION["notification"]["message"][] = "You have been successfully logged out.";
	header("Location: ../index.php?page=home.php");
	exit(0);
}
else
{
	$_SESSION["error"]["message"][] = "You could not be logged out, as you are not logged in!";
	header("Location: ../index.php?page=home.php");
	exit(0);

}

?>
