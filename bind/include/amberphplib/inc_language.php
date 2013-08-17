<?php
/*
	language.php

	Provides translation functions for the Amberphplib framework and applications
	using it.
	

	Translation is provided by a table in the DB called "language" which stores label
	to translation records.

	The languages supported are defined in the languages_available table, and the user
	managment pages allow selection of a language on a per-user basis as well as the
	default system-wide language.


	The performance of the language functions is significantly increased by enabling the
	language db preload feature, as this means all the language records are loaded at
	application stat requiring only a single SQL query (but possibly more memory).
*/



/*
	lang_trans
	
	Performs a transalation of the string into the user's perferred language

	Values
	label		string - label of the translation

	Returns
	string		Translation upon success or input string upon failure
*/
function lang_trans($label)
{
	log_write("debug", "inc_language", "Executing lang_trans($label)");

	//
	// see if the value is already cached
	if (isset($GLOBALS["cache"]["lang"][$label]))
	{
		// return cached label
		//if user can edit translations, surround in {{}}
		if(isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"] == "show_all_translatable_fields"))
		{
			return "{{".$GLOBALS["cache"]["lang"][$label]."}} (".$label.")";
		}
		else
		{
			return $GLOBALS["cache"]["lang"][$label];
		}
	}
	else
	{
		if ($GLOBALS["cache"]["lang_mode"] == "preload")
		{
			/*
				All transations have been loaded, therefore requested translation
				does not exist.

				In this case, just return the label as the translation and also add it to
				the cache to prevent extra lookups.
			*/

			$GLOBALS["cache"]["lang"][ $label ] = $label;
			//if translation mode is turned on, surround non-translated field with [[]]
			if(isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"]=="show_all_translatable_fields" || $_SESSION["user"]["translation"]=="show_only_non-translated_fields"))
			{
				return "[[".$label."]]";
			}
			else
			{
				return $label;
			}
		}
		else
		{
			// not cached - need to do a DB lookup.

			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT translation FROM `language` WHERE language='". $_SESSION["user"]["lang"] ."' AND label='$label' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();

				// add to cache
				$GLOBALS["cache"]["lang"][ $label ] = $sql_obj->data[0]["translation"];


				// done
				//if user can edit translations, surround in {{}}
				if($_SESSION["user"]["translation"]=="show_all_translatable_fields")
				{
					return "{{".$sql_obj->data[0]["translation"]."}} (".$label.")";
				}
				else
				{
					return $sql_obj->data[0]["translation"];
				}
			}
			else
			{
				// no value was returned for the particular label we looked up, it means
				// that no translation exists.
				//
				// In this case, just return the label as the translation and also add it to
				// the cache to prevent extra lookups.

				$GLOBALS["cache"]["lang"][ $label ] = $label;
				
				//if translation mode is turned on, surround non-translated field with [[]]
				if($_SESSION["user"]["translation"]=="show_all_translatable_fields" || $_SESSION["user"]["translation"]=="show_only_non-translated_fields")
				{
					return "[[".$label."]]";
				}
				else
				{
					return $label;
				}
			}

			unset($sql_obj);
		}
			
	} // end if lookup required


	// unexpected failure, return the label
	return $label;

} // end of lang_translate






/*
	language_translate($language, $label_array)

	Legacy support function. Deprecated for future removal
	
*/
function language_translate($language, $label_array)
{
	log_write("warning", "language", "Executing language_translate($language, label_array) -- DEPRECATED function");
	
	if (!$language || !$label_array)
		print "Warning: Invalid input received for function language_translate<br>";
	

	// store labels to fetch from DB in here
	$label_fetch_array = array();

	// run through the labels - see what ones we have cached, and what ones we need to query
	foreach ($label_array as $label)
	{
		if (isset($GLOBALS["cache"]["lang"][$label]))
		{
			//if user can edit translations, surround in {{}}
			if ( isset($_SESSION["user"]["translation"]) && ( $_SESSION["user"]["translation"]=="show_all_translatable_fields"))
			{			
				$result[$label] = "{{".$GLOBALS["cache"]["lang"][$label]."}} (".$label.")";
			}
			else
			{
				$result[$label] = $GLOBALS["cache"]["lang"][$label];
			}
		}
		else
		{
			if ($GLOBALS["cache"]["lang_mode"] == "preload")
			{
				// All translations have been loaded, therefore requested translation
				// does not exist.
				///
				// In this case, just return the label as the translation and also add it to
				// the cache to prevent extra lookups.

				$GLOBALS["cache"]["lang"][ $label ] = $label;
				
				//if translation mode is turned on, surround non-translated field with [[]]
				if ( isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"]=="show_all_translatable_fields" || $_SESSION["user"]["translation"]=="show_only_non-translated_fields"))
				{
					$result[$label] = "[[".$label."]]";
				}
				else
				{
					$result[$label] = $label;
				}
			}
			else
			{
				$label_fetch_array[] = $label;
			}
		}
	}


	if ($label_fetch_array)
	{
		// there are some new labels for us to translate
		// we get the information from the database and then save it to the cache
		// to prevent future lookups.

		// prepare the SQL
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT label, translation FROM `language` WHERE language='". $language ."' AND (";

		// add all the labels to the SQL query.
		$count = 0;
		foreach ($label_fetch_array as $label)
		{
			$count++;
				
			if ($count < count($label_fetch_array))
			{
				$sql_obj->string .= "label='$label' OR ";
			}
			else
			{
				$sql_obj->string .= "label='$label'";
			}
		}

		$sql_obj->string .= ")";


		// query
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();
			foreach ($sql_obj->data as $data)
			{
				//if user can edit translations, surround in {{}}
				if ($_SESSION["user"]["translation"]=="show_all_translatable_fields")
				{
					$result[ $data["label"] ]		= "{{".$data["translation"]."}} (".$label.")";
				}
				else
				{
					$result[ $data["label"] ]		= $data["translation"];
				}
				$GLOBALS["cache"]["lang"][ $data["label"] ]	= $data["translation"];
			}
		}
		
	} // end if lookup required


	// if no value was returned for the particular label we looked up, it means
	// that no translation exists.
	//
	// In this case, just return the label as the translation and also add it to
	// the cache to prevent extra lookups.
	foreach ($label_array as $label)
	{
		if (!$result[$label])
		{
			//if translation mode is turned on, surround non-translated field with [[]]
			if( isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"]=="show_all_translatable_fields" || $_SESSION["user"]["translation"]=="show_only_non-translated_fields"))
			{
				$result[$label]			= "[[".$label."]]";
			}
			else
			{
				$result[$label]			= $label;
			}
			$GLOBALS["cache"]["lang"][$label]	= $label;
		}
	}


	// return the results
	return $result;
	
} // end of language_translate function


/*
	language_translate_string($language, $string)

	This function performs the same actions as the language_translate
	function, except for a single value rather than an array of values.

	For a single value lookup, this function is more efficent.
*/
function language_translate_string($language, $label)
{
	log_write("warning", "language", "Executing language_translate_string($language, $label) -- DEPRECATED function");


	// see if the value is already cached
	if (isset($GLOBALS["cache"]["lang"][$label]))
	{
		// return cached label
		//if user can edit translations, surround in {{}}
		if (isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"]=="show_all_translatable_fields"))
		{
			return "{{".$GLOBALS["cache"]["lang"][$label]."}} (".$label.")";
		}
		else
		{
			return $GLOBALS["cache"]["lang"][$label];
		}
	}
	else
	{
		if ($GLOBALS["cache"]["lang_mode"] == "preload")
		{
			// All transations have been loaded, therefore requested translation
			// does not exist.
			///
			// In this case, just return the label as the translation and also add it to
			// the cache to prevent extra lookups.

			$GLOBALS["cache"]["lang"][ $label ] = $label;
			
			//if translation mode is turned on, surround non-translated field with [[]]
			if(isset($_SESSION["user"]["translation"]) && ($_SESSION["user"]["translation"] == "show_all_translatable_fields" || $_SESSION["user"]["translation"] == "show_only_non-translated_fields"))
			{
				return "[[".$label."]]";
			}
			else
			{
				return $label;
			}
		}
		else
		{
			// not cached - need to do a DB lookup.

			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT label, translation FROM `language` WHERE language='". $language ."' AND label='$label' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();

				// add to cache
				$GLOBALS["cache"]["lang"][ $label ] = $sql_obj->data[0]["translation"];


				// done
				//if user can edit translations, surround in {{}}
				if ($_SESSION["user"]["translation"]=="show_all_translatable_fields")
				{
					return "{{".$sql_obj->data[0]["translation"]."}} (".$label.")";
				}
				else
				{
					return $sql_obj->data[0]["translation"];
				}
			}
			else
			{
				// no value was returned for the particular label we looked up, it means
				// that no translation exists.
				//
				// In this case, just return the label as the translation and also add it to
				// the cache to prevent extra lookups.

				$GLOBALS["cache"]["lang"][ $label ] = $label;
				
				//if translation mode is turned on, surround non-translated field with [[]]
				if($_SESSION["user"]["translation"]=="show_all_translatable_fields" || $_SESSION["user"]["translation"]=="show_only_non-translated_fields")
				{
					return "[[".$label."]]";
				}
				else
				{
					return $label;
				}
			}

			unset($sql_obj);
		}
			
	} // end if lookup required


	// unexpected failure, return the label
	return $label;
}



?>
