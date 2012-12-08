#!/usr/bin/perl -w
#
# resources/autoinstall.pl
#
# (C) Copyright 2009 Amberdms Ltd <jethro.carr@amberdms.com>
# This utility is licensed under the GNU AGPL Version 3.0
#
# This script is run manually by the user to setup the MySQL database
# for the NamedManager application.
#
# * Create the MySQL database
# * Create user in MySQL
# * Write settings to config file.
# * Import the inital database.
#
#

use strict;
use DBI;

## SETTINGS ##

# default settings 
# (only need to change these if you are doing development work)
my $db_user		= "root";		# name of user to be used to create data
my $db_name		= "namedmanager";	# name of the DB to create
my $db_host		= "localhost";		# MySQL server

my $db_bs_user		= "namedmanager";	# name of the aoconf user to create
my $db_bs_password	= random_password(10);	# random password to generate

# location of config.php file
my $opt_cfgfile = "/etc/namedmanager/config.php";

# tmp file to use for SQL query generation
my $opt_tmpfile = "/tmp/namedmanager_mysqlquery";

# location of install schema
my $opt_schemadata;
if ($0 =~ /^([\S\s]*)\/resources\/autoinstall.pl$/)
{
	$opt_schemadata = "$1/sql/";
}
else
{
	$opt_schemadata = "../sql/";
}


## CHECKS ##

# make sure config file is installed
if (! -e $opt_cfgfile)
{
	die("Error: $opt_cfgfile does not exist\n");
}



## PROGRAM ##

system("touch $opt_tmpfile");
system("chown root:root $opt_tmpfile");
system("chmod 600 $opt_tmpfile");


print "autoinstall.pl\n";
print "\n";
print "This script setups the NamedManager database components:\n";
print " * NamedManager MySQL user\n";
print " * NamedManager database\n";
print " * NamedManager configuration files\n";
print "\n";
print "THIS SCRIPT ONLY NEEDS TO BE RUN FOR THE VERY FIRST INSTALL OF NAMEDMANAGER.\n";
print "DO NOT RUN FOR ANY OTHER REASON\n";
print "\n";

print "Please enter MySQL $db_user password (if any): ";
my $db_pass = get_question('^[\S\s]*$');


# connect to mysql
# note: not connecting to DB is deliberate, since at this stage the database does not exist.
my $mysql_handle = DBI->connect("dbi:mysql:host=$db_host;user=$db_user;password=$db_pass") || die("Error: Unable to connect to MySQL database: $DBI::errstr\n");


## 1. IMPORT SCHEMA

# import the latest database
print "Searching $opt_schemadata for latest install schema...\n";

# determine the latest install file
my @data	= glob("$opt_schemadata/version_*_install.sql");
my $count	= scalar @data;

if ($count == 0)
{
	die("Error: No schema found in $opt_schemadata!\n");
}
else
{
	$count = $count - 1;
	
	print $data[$count] ." is the latest file and will be used for the install.\n";
	# import schema
	print "Importing file $data[$count]\n";
	import_sql($data[$count], $mysql_handle);
}




## 2. SETUP MYSQL USER

# create queries in tmp file
open (MYSQL, ">$opt_tmpfile") || die("Unable to create tmp file mysqltmp\n");

# create MySQL user
print "Creating user...\n";

if ($db_host eq "localhost")
{
	print MYSQL "GRANT USAGE ON * . * TO '$db_bs_user'\@'localhost' IDENTIFIED BY '$db_bs_password' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 ;\n";
	print MYSQL "GRANT SELECT , INSERT , UPDATE , DELETE , CREATE , DROP , INDEX , ALTER , CREATE TEMPORARY TABLES, LOCK TABLES ON `$db_name` . * TO '$db_bs_user'\@'localhost';\n";
}
else
{
	print MYSQL "GRANT USAGE ON * . * TO '$db_bs_user'\@'%' IDENTIFIED BY '$db_bs_password' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 ;\n";
	print MYSQL "GRANT SELECT , INSERT , UPDATE , DELETE , CREATE , DROP , INDEX , ALTER , CREATE TEMPORARY TABLES, LOCK TABLES ON `$db_name` . * TO '$db_bs_user'\@'%';\n";
}

close(MYSQL);

# run queries
import_sql($opt_tmpfile, $mysql_handle);

# remove tmp file.
system("rm -f $opt_tmpfile");



## 3. WRITE CONFIGURATION FILE

# update configuration file
print "Updating configuration file...\n";

open (CFG, $opt_cfgfile) || die("Unable to open config file to update settings.");
open (CFGTMP, ">$opt_cfgfile.new") || die("Unable to create tmp config file.");

while (my $line = <CFG>)
{
	chomp($line);

	# update config file with mysql user settings
	if ($line =~ /^\$config\["db_host"\]\s*=\s*"(\S*)";/)
	{
		$line =~ s/"$1"/"$db_host"/;
	}
	
	if ($line =~ /^\$config\["db_name"\]\s*=\s*"(\S*)";/)
	{
		$line =~ s/"$1"/"$db_name"/;
	}
		
	if ($line =~ /^\$config\["db_user"\]\s*=\s*"(\S*)";/)
	{
		$line =~ s/"$1"/"$db_bs_user"/;
	}
	
	if ($line =~ /^\$config\["db_pass"\]\s*=\s*"(\S*)";/)
	{
		$line =~ s/"$1"/"$db_bs_password"/;
	}

	print CFGTMP "$line\n";
}
close(CFG);
close(CFGTMP);

# we use cat instead of mv to retain permissions
system("cat $opt_cfgfile.new > $opt_cfgfile");
system("rm -f $opt_cfgfile.new");



$mysql_handle->disconnect;

print "DB installation complete!\n";
print "\n";
print "You can now login with the default username/password of setup/setup123 at http://localhost/namedmanager\n";
print "\n";



# complete! :-)
exit 0;


### FUNCTIONS ###

# get_question
#
# Gets user input from CLI and does verification
#
sub get_question {
        my $regex = shift;
        my $complete = 0;


        while (!$complete)
        {
                my $input = <STDIN>;
                chomp($input);

                if ($input =~ /$regex/)
                {
                        return $input;
                }
                else
                {
                        print "Invalid input! Please re-enter.\n";
			print "Retry: ";
                }
        }

}

# random_password
#
# generates a random password of any desired length
#
sub random_password
{
	my $password;
	my $_rand;

	my $password_length = $_[0];

	if (!$password_length) {
		$password_length = 10;
	}

	my @chars = split(" ",
		"a b c d e f g h i j k l m n o
		p q r s t u v w x y z
		0 1 2 3 4 5 6 7 8 9");

	srand;

	for (my $i=0; $i <= $password_length ;$i++)
	{
		$_rand = int(rand (scalar @chars));
		$password .= $chars[$_rand];
	}
	return $password;
}


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

