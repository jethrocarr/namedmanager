<?php
/*
	inc_phone_home.php

	This class provides functions for reading stats from the system and submitting
	then back to Amberstats in a "phone-home" feature.

	The stats returned are a small selection of general OS/platform/server details
	and a randomly generated unique ID, used to track the upgrade patterns of users
	over their lifespan of using the software.

	The following is a full list and example of the types of details collected:

	Field                 Example
	-----                 -----
	Application Name      Amberdms Billing System
	Application Version   1.3.0
	Server App            Apache/2.0.52 (CentOS)
	Language Version      5.1.6
	Subscription Type     opensource
	Subscription ID       qwertyuiop234567890


	Typicially this class is called at login but only actually executes once a day.
*/


class phone_home
{
	var $url	= "https://www.amberdms.com/api/opensource/amberdms_phone_home.php";
	var $url_ssl	= true;	// set to true to enforce validation of SSL certificate

	var $stats;	// used to hold stats about the system for submission upstream


	/*
		check_enabled

		Check that phone home is enabled.

		Returns
		0	Disabled
		1	Enabled
	*/
	function check_enabled()
	{
		if ($GLOBALS["config"]["PHONE_HOME"] == "enabled" || $GLOBALS["config"]["PHONE_HOME"] == "1")
		{
			// enabled
			return 1;
		}

		return 0;

	} // end of check_enabled



	/*
		check_phone_home_timer

		Check if it's time to phone home, we only want to do this once every 12 hours at the most, otherwise
		it's just adding unnessary load and delays on the application if we check too often.

		Returns
		0	No need to run phone home functions
		1	Time to call in
	*/
	function check_phone_home_timer()
	{
		$time_update		= $GLOBALS["config"]["PHONE_HOME_TIMER"];
		$time_current		= mktime();

		if (($time_update + 43200) < $time_current)
		{
			// time to phone home (been more than 12 hours since last attempt)
			return 1;
		}

		return 0;

	} // end of check_phone_home_timer





	/*
		stats_generate

		Generates a list of different stats and stores in $this->stats;
	*/
	function stats_generate()
	{
		log_write("debug", "phone_home", "Executing stats_generate()");

		/*
			Application Details
		*/

		$this->stats["app_name"] 	= $GLOBALS["config"]["app_name"];
		$this->stats["app_version"]	= $GLOBALS["config"]["app_version"];

		/*
			Server Details
		*/

		$this->stats["server_app"]	= $_SERVER["SERVER_SOFTWARE"];
		$this->stats["server_platform"]	= phpversion();


		/*
			Configuration Information
		*/

		// account information
		$this->stats["subscription_type"]	= $GLOBALS["config"]["SUBSCRIPTION_SUPPORT"];
		$this->stats["subscription_id"]		= $GLOBALS["config"]["SUBSCRIPTION_ID"];

		// check account ID
		if (!$this->stats["subscription_id"])
		{
			// if no subscription id exists, then this must be running the open source version
			// we should create an ID so we can track requests from this server, we do this by
			// aggregating the server name and the instance name (if any) however we then hash
			// for privacy reasons.

			$this->stats["subscription_id"] = @md5($_SERVER["SERVER_NAME"] . $_SESSION["user"]["instance"]["id"] . rand(0,1000000));

			$sql_obj		= New sql_query;
			$sql_obj->string	= "UPDATE config SET value='". $this->stats["subscription_id"] ."' WHERE name='SUBSCRIPTION_ID' LIMIT 1";
			$sql_obj->execute();

			log_write("debug", "phone_home", "Generated new subscription ID of ". $this->stats["subscription_id"] ."");
		}


		return 1;
		
	} // end of stats_generate



	/*
		stats_submit()

		Upload the stats to the Amberstats servers for user base size & tracking purposes.

		Returns
		0	Failure - possible HTTP/S transport issue such as firewalled DMZ server?
		1	Success
	*/
	function stats_submit()
	{
		log_write("debug", "phone_home", "Executing stats_submit()");


		/*
			Update Timer
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "UPDATE config SET value='". mktime() ."' WHERE name='PHONE_HOME_TIMER' LIMIT 1";
		$sql_obj->execute();



		/*
			Submit Data
		*/

		foreach($this->stats as $key=>$value)
		{
			$fields_string .= $key.'='.urlencode($value).'&';
		}

		rtrim($fields_string,'&');

		// open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL,$this->url);
		curl_setopt($ch,CURLOPT_POST,count($this->stats));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_TIMEOUT,2);				// wait a max of 2 seconds
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->url_ssl); 	// ssl validation options

		if ($this->url_ssl == false)
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // ssl validation options
		}

		// execute post
		if (!curl_exec($ch))
		{	
			log_write("debug", "process", "Unable to phone home to Amberstats server, Curl error whilst POSTing data: ". curl_error($ch));
			
			curl_close($ch);
			return 0;
		}

		// check HTTP return code
		switch ($http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE))
		{
			case "500":
				log_write("debug", "process", "Unexpected fault on the Amberstats server, phone home not recorded");
			break;

			case "400":
				log_write("debug", "process", "Invalid data provided to phone home interface, information refused.");
			break;

			case "200":
				log_write("debug", "process", "Server stats successfully returned to Amberstats. Thank you for submitting.");
			break;

			default:
				log_write("debug", "process", "Unexpected error, HTTP code $http_return_code returned!");
			break;
		}
		
		curl_close($ch);
		return 1;

	} // end of stats_submit


} // end of phone_home class

?>
