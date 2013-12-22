# NamedManager

## Project Homepage

For more information including source code, issue tracker and documentation
visit the project homepage:

https://projects.jethrocarr.com/p/oss-namedmanager/


## Introduction

NamedManager is an AGPL web-based DNS management system designed to make the
adding, adjusting and removal of zones/records easy and reliable.

Rather than attempting to develop a new nameserver as in the case of many DNS
management interfaces, NamedManager supports the tried and tested Bind
nameserver, by generating Bind compatible configuration files whenever a change
needs to be applied.

This also ensures that an outage of the management server web interface or SQL
database will not result in any impact to DNS servers.


## Key Features

* Allows addition, adjusting and deletion DNS zones.
* Supports Bind 9 and pushes Bind compatible configuration and zone files to configured servers.
* Supports Amazon Route53
* Ability to import from Bind zonefile support.
* Includes a log tailer that runs on the name servers and sends back logs that are rendered in the web interface.
* SOAP API to allow other tools to hook into the interface.
* Written in PHP and uses a MySQL database backend.
* Supports IPv4 and IPv6 users of the management interface.
* Supports IPv4 and IPv6 forward and reverse records zones.
* Supports internationalized domain names.


## Application Structure

* docs/
	Documentation, authors file, copyright/license and other information.

* htdocs/
	Web-based Frontend

* bind/
	Scripts for integrating with Bind name servers.

* resources/
	Sample config files, spec files, packaging tools and other bits.

* sql/
	Database schemea install and upgrade components.


