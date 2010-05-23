#!/usr/bin/perl -w
#
# resources/schema_update.pl
#
# This script checks the current version of the database for the NamedManager application
# and applies any upgrades.
#
# It reads in the MySQL authentication data from the configuration file to provide
# interaction-less upgrades.
# 
#

use strict;
use DBI;
use Getopt::Long;



## DEFAULT SETTINGS ##

my $debug = 0;
my $help;

my $opt_cfgfile			= "/etc/namedmanager/config.php";

my $opt_schema;
my $opt_version_schema;


# get CLI options
GetOptions ("cfgfile=s"		=> \$opt_cfgfile,	"c=s"	=> \$opt_cfgfile,
	    "schemadir=s"	=> \$opt_schema,	"s=s"	=> \$opt_schema,
	    "verbose"		=> \$debug,		"v"	=> \$debug,
	    "help"		=> \$help,		"h"	=> \$help);

if ($help)
{
	print "NamedManager Schema Management Utility\n";
	print "\n";
	print "Usage: schema_manage.pl --schemadir=<schemadir>\n";
	print "\n";
	print "-c, --cfgfile=FILENAME            NamedManager configuration file with MySQL settings.\n";
	print "\n";
	print "-s, --schemadir=LOCATION          Location of the schema SQL files..\n";
	print "\n";
	print "-v, --verbose\n";
	print "\n";
	print "-h, --help\n";
	print "\n";

	exit 0;
}



# check options
if (!$opt_cfgfile)
{
	die("Error: No config file specified.\n");
}

if (!$opt_schema)
{
	die("Error: No schmea directory provided.\n");
}


## GET CONFIG ##
my ($db_host, $db_name, $db_user, $db_pass);

open(CFG, $opt_cfgfile) || die("Error: Unable to open config file $opt_cfgfile");

while (my $line = <CFG>)
{
	chomp($line);
	
	if ($line =~ /^\$config\["db_host"\]\s*=\s*"(\S*)";/)
	{
		$db_host = $1;
	}

	if ($line =~ /^\$config\["db_name"\]\s*=\s*"(\S*)";/)
	{
		$db_name = $1;
	}
	
	if ($line =~ /^\$config\["db_user"\]\s*=\s*"(\S*)";/)
	{
		$db_user = $1;
	}

	if ($line =~ /^\$config\["db_pass"\]\s*=\s*"(\S*)";/)
	{
		$db_pass = $1;
	}
}

close(CFG);


# check that DB config was gathered.
if (!$db_host || !$db_name || !$db_user)
{
	die("Error: Unable to gather required information from config file $opt_cfgfile");
}



## PROGRAM ##

# connect to MySQL DB.
my $mysql_handle = DBI->connect("dbi:mysql:database=$db_name;host=$db_host;user=$db_user;password=$db_pass") || die("Error: Unable to connect to MySQL database: $DBI::errstr\n");
my ($mysql_string, $mysql_result, $mysql_data);



# 1. Fetch schema version
print "Fetching current version from the database...\n" if $debug;

$mysql_string = "SELECT value FROM `config` WHERE name='SCHEMA_VERSION'";
$mysql_result = $mysql_handle->prepare($mysql_string) || die("Error: SQL query ($mysql_string) failed: $DBI::errstr\n");
$mysql_result->execute;

while ($mysql_data = $mysql_result->fetchrow_hashref())
{
	$opt_version_schema = $mysql_data->{value};
}
$mysql_result->finish;



## 2. Upgrade Schema

print "Existing schema version is: $opt_version_schema\n" if $debug;

# get a list of upgrade files
my @data	= glob("$opt_schema/version_*_upgrade.sql");
my $count	= scalar @data;

if ($count == 0)
{
	print "No data needs to be imported.\n" if $debug;
}
else
{
	print "Applying any upgrade SQL files that match...\n" if $debug;
	
	my $latestversion;
	foreach my $sqlfile (@data)
	{
		if ($sqlfile =~ /_([0-9]{4}[0-9]{2}[0-9]{2}[0-9]*)_/)
		{
			$latestversion = $1;
			
			if ($latestversion > $opt_version_schema)
			{
				# need to import this schema upgrade file
				print "Importing file $sqlfile\n" if $debug;
				import_sql($sqlfile, $mysql_handle);
			}
			else
			{
				print "Schema version $latestversion has already been applied, skipping...\n" if $debug;
			}
		}
		else
		{
			print "Warning: Incorrectly named SQL upgrade file: $sqlfile\n";
		}
	}
}

# disconnect from MySQL
$mysql_handle->disconnect;

print "Schema installation complete!\n" if $debug;
exit 0;


## FUNCTIONS


#
# import_sql ( filename, MySQL handle )
#
# Imports the specified SQL file into MySQL
#
sub import_sql
{
	my $sqlfile		= shift;
	my $mysql_handle	= shift;

	open(SQL, "$sqlfile") or die("Error: Unable to open $sqlfile\n");
				
	my @statements = split(/;\n/,join('',<SQL>));
	foreach my $sqlline ( @statements )
	{
		# remove crap lines
		if ($sqlline =~ /^\s*$/)
		{
			next;
		}
		
		if ($sqlline =~ /^#/)
		{
			next;
		}


		# line is good - process it.
		if ($sqlline)
		{
			$mysql_handle->do($sqlline);
		}
	}
				    
	close(SQL);
}


