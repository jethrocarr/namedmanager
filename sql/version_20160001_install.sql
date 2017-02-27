--
-- NAMEDMANAGER APPLICATION
--
-- Inital database install SQL.
--

CREATE DATABASE `namedmanager` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `namedmanager`;


--
-- Table structure for table `config`
--

CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`name`, `value`) VALUES('APP_MYSQL_DUMP', '/usr/bin/mysqldump');
INSERT INTO `config` (`name`, `value`) VALUES('APP_PDFLATEX', '/usr/bin/pdflatex');
INSERT INTO `config` (`name`, `value`) VALUES('AUTH_METHOD', 'ldaponly');
INSERT INTO `config` (`name`, `value`) VALUES('BLACKLIST_ENABLE', 'enabled');
INSERT INTO `config` (`name`, `value`) VALUES('BLACKLIST_LIMIT', '10');
INSERT INTO `config` (`name`, `value`) VALUES('DATA_STORAGE_LOCATION', 'use_database');
INSERT INTO `config` (`name`, `value`) VALUES('DATA_STORAGE_METHOD', 'database');
INSERT INTO `config` (`name`, `value`) VALUES('DATEFORMAT', 'yyyy-mm-dd');
INSERT INTO `config` (`name`, `value`) VALUES('DEFAULT_TTL_MX', '120');
INSERT INTO `config` (`name`, `value`) VALUES('DEFAULT_TTL_NS', '86400');
INSERT INTO `config` (`name`, `value`) VALUES('DEFAULT_TTL_OTHER', '120');
INSERT INTO `config` (`name`, `value`) VALUES('DEFAULT_TTL_SOA', '86400');
INSERT INTO `config` (`name`, `value`) VALUES('LANGUAGE_DEFAULT', 'en_us');
INSERT INTO `config` (`name`, `value`) VALUES('LANGUAGE_LOAD', 'preload');
INSERT INTO `config` (`name`, `value`) VALUES('PATH_TMPDIR', '/tmp');
INSERT INTO `config` (`name`, `value`) VALUES('PHONE_HOME', 'enabled');
INSERT INTO `config` (`name`, `value`) VALUES('PHONE_HOME_TIMER', '1274585928');
INSERT INTO `config` (`name`, `value`) VALUES('SCHEMA_VERSION', '20100520');
INSERT INTO `config` (`name`, `value`) VALUES('SUBSCRIPTION_ID', '5f4d732e933c8ac621d99c0e2a15a536');
INSERT INTO `config` (`name`, `value`) VALUES('SUBSCRIPTION_SUPPORT', 'opensource');
INSERT INTO `config` (`name`, `value`) VALUES('SYNC_STATUS_CONFIG', '');
INSERT INTO `config` (`name`, `value`) VALUES('TIMEZONE_DEFAULT', 'SYSTEM');
INSERT INTO `config` (`name`, `value`) VALUES('UPLOAD_MAXBYTES', '5242880');
INSERT INTO `config` (`name`, `value`) VALUES('ZONE_DB_HOST', 'localhost');
INSERT INTO `config` (`name`, `value`) VALUES('ZONE_DB_NAME', 'powerdns_dev1');
INSERT INTO `config` (`name`, `value`) VALUES('ZONE_DB_PASSWORD', 'sdr05ynw4tuj');
INSERT INTO `config` (`name`, `value`) VALUES('ZONE_DB_TYPE', 'zone_internal');
INSERT INTO `config` (`name`, `value`) VALUES('ZONE_DB_USERNAME', 'root');

-- --------------------------------------------------------

--
-- Table structure for table `dns_domains`
--

CREATE TABLE IF NOT EXISTS `dns_domains` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `domain_name` varchar(255) NOT NULL,
  `domain_description` text NOT NULL,
  `soa_hostmaster` varchar(255) NOT NULL,
  `soa_serial` bigint(20) unsigned NOT NULL,
  `soa_refresh` int(10) unsigned NOT NULL,
  `soa_retry` int(10) unsigned NOT NULL,
  `soa_expire` int(10) unsigned NOT NULL,
  `soa_default_ttl` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `dns_records`
--

CREATE TABLE IF NOT EXISTS `dns_records` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `id_domain` int(11) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(6) NOT NULL,
  `content` varchar(255) NOT NULL,
  `ttl` int(11) NOT NULL,
  `prio` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `dns_record_types`
--

CREATE TABLE IF NOT EXISTS `dns_record_types` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `type` varchar(6) NOT NULL,
  `user_selectable` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;

--
-- Dumping data for table `dns_record_types`
--

INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(1, 'SOA', 0);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(2, 'NS', 0);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(3, 'MX', 0);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(4, 'A', 1);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(5, 'AAAA', 1);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(6, 'PTR', 1);
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`) VALUES(7, 'CNAME', 1);

-- --------------------------------------------------------

--
-- Table structure for table `file_uploads`
--

CREATE TABLE IF NOT EXISTS `file_uploads` (
  `id` int(11) NOT NULL auto_increment,
  `customid` int(11) NOT NULL default '0',
  `type` varchar(20) NOT NULL,
  `timestamp` bigint(20) unsigned NOT NULL default '0',
  `file_name` varchar(255) NOT NULL,
  `file_size` varchar(255) NOT NULL,
  `file_location` char(2) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `file_uploads`
--


-- --------------------------------------------------------

--
-- Table structure for table `file_upload_data`
--

CREATE TABLE IF NOT EXISTS `file_upload_data` (
  `id` int(11) NOT NULL auto_increment,
  `fileid` int(11) NOT NULL default '0',
  `data` blob NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table for use as database-backed file storage system' AUTO_INCREMENT=1 ;

--
-- Dumping data for table `file_upload_data`
--


-- --------------------------------------------------------

--
-- Table structure for table `journal`
--

CREATE TABLE IF NOT EXISTS `journal` (
  `id` int(11) NOT NULL auto_increment,
  `locked` tinyint(1) NOT NULL default '0',
  `journalname` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `userid` int(11) NOT NULL default '0',
  `customid` int(11) NOT NULL default '0',
  `timestamp` bigint(20) unsigned NOT NULL default '0',
  `content` text NOT NULL,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `journalname` (`journalname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `journal`
--


-- --------------------------------------------------------

--
-- Table structure for table `language`
--

CREATE TABLE IF NOT EXISTS `language` (
  `id` int(11) NOT NULL auto_increment,
  `language` varchar(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `translation` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `language` (`language`),
  KEY `label` (`label`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=314 ;


-- --------------------------------------------------------

--
-- Table structure for table `language_avaliable`
--

CREATE TABLE IF NOT EXISTS `language_avaliable` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(5) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `language_avaliable`
--

INSERT INTO `language_avaliable` (`id`, `name`) VALUES(1, 'en_us');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL auto_increment,
  `id_server` int(11) NOT NULL,
  `id_domain` int(11) NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  `log_type` char(10) NOT NULL,
  `log_contents` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `logs`
--


-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(11) NOT NULL auto_increment,
  `priority` int(11) NOT NULL default '0',
  `parent` varchar(50) NOT NULL,
  `topic` varchar(50) NOT NULL,
  `link` varchar(50) NOT NULL,
  `permid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=185 ;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(170, 100, 'top', 'menu_overview', 'home.php', 0);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(171, 200, 'top', 'menu_logs', 'logs/logs.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(172, 300, 'top', 'menu_domains', 'domains/domains.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(173, 301, 'menu_domains', 'menu_domains_view', 'domains/domains.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(174, 302, 'menu_domains', 'menu_domains_add', 'domains/add.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(175, 310, 'menu_domains_view', '', 'domains/view.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(176, 310, 'menu_domains_view', '', 'domains/records.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(177, 310, 'menu_domains_view', '', 'domains/delete.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(178, 500, 'top', 'menu_servers', 'servers/servers.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(179, 501, 'menu_servers', 'menu_servers_view', 'servers/servers.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(180, 502, 'menu_servers', 'menu_servers_add', 'servers/add.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(181, 510, 'menu_servers_view', '', 'servers/view.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(182, 510, 'menu_servers_view', '', 'servers/logs.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(183, 510, 'menu_servers_view', '', 'servers/delete.php', 2);
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`) VALUES(184, 900, 'top', 'menu_configuration', 'admin/config.php', 0);

-- --------------------------------------------------------

--
-- Table structure for table `name_servers`
--

CREATE TABLE IF NOT EXISTS `name_servers` (
  `id` int(11) NOT NULL auto_increment,
  `server_primary` tinyint(1) NOT NULL,
  `server_name` varchar(255) character set latin1 NOT NULL,
  `server_description` text character set latin1 NOT NULL,
  `server_type` varchar(20) NOT NULL,
  `api_auth_key` varchar(255) character set latin1 NOT NULL,
  `api_sync_config` bigint(20) NOT NULL,
  `api_sync_log` bigint(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;



-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL auto_increment,
  `value` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Stores all the possible permissions' AUTO_INCREMENT=4 ;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `value`, `description`) VALUES(1, 'disabled', 'Enabling the disabled permission will prevent the user from being able to login.');
INSERT INTO `permissions` (`id`, `value`, `description`) VALUES(2, 'namedadmins', 'Full management over domains, records and name servers.');
INSERT INTO `permissions` (`id`, `value`, `description`) VALUES(3, 'admin', 'Allows configuration of user accounts and application settings.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL auto_increment,
  `username` varchar(255) NOT NULL,
  `realname` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_salt` varchar(20) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `time` bigint(20) NOT NULL default '0',
  `ipaddress` varchar(15) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `ipaddress` (`ipaddress`),
  KEY `time` (`time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='User authentication system.' AUTO_INCREMENT=2 ;


-- --------------------------------------------------------

--
-- Table structure for table `users_blacklist`
--

CREATE TABLE IF NOT EXISTS `users_blacklist` (
  `id` int(11) NOT NULL auto_increment,
  `ipaddress` varchar(15) NOT NULL,
  `failedcount` int(11) NOT NULL default '0',
  `time` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Prevents automated login attacks.' AUTO_INCREMENT=2 ;


-- --------------------------------------------------------

--
-- Table structure for table `users_options`
--

CREATE TABLE IF NOT EXISTS `users_options` (
  `id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL default '0',
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=181 ;


-- --------------------------------------------------------

--
-- Table structure for table `users_permissions`
--

CREATE TABLE IF NOT EXISTS `users_permissions` (
  `id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL default '0',
  `permid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Stores user permissions.' AUTO_INCREMENT=2 ;


-- --------------------------------------------------------

--
-- Table structure for table `users_sessions`
--

CREATE TABLE IF NOT EXISTS `users_sessions` (
  `id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL,
  `authkey` varchar(40) NOT NULL,
  `ipaddress` varchar(15) NOT NULL,
  `time` bigint(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;



--
-- 1.0.0 alpha 1 to 1.0.0 alpha 2 upgrade
--

INSERT INTO `config` (`name`, `value`) VALUES ('DEFAULT_HOSTMASTER', '');

ALTER TABLE `logs` ADD `username` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `id_domain` ;

TRUNCATE TABLE `language`;

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(292, 'en_us', 'username_namedmanager', 'Username');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(293, 'en_us', 'password_namedmanager', 'Password');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(294, 'en_us', 'powerdns_mysql', 'PowerDNS-compliant MySQL Database (unstable, alpha feature)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(295, 'en_us', 'domain_records_ns_help', 'The following is a list of all the nameservers that this domain is managed by.\r\n\r\nThese are auto-populated with the domains configured in the DB, however you can add your own records if you wish to sub-delegate the domain (for example, setting internal.example.com to be handled by another name server)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(296, 'en_us', 'domain_records_mx_help', 'Configure all the mailservers for the system here, remember that all mail will be delivered to the server with the lowest priority by default.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(297, 'en_us', 'domain_records_custom_help', 'Configure all remaining records here - select the type from the dropdown and enter the suitable values');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(298, 'en_us', 'zone_internal', 'Use internal application SQL database');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(299, 'en_us', 'server_primary_option_help', 'Make this server the primary one used for DNS SOA records.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(300, 'en_us', 'menu_configuration', 'Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(301, 'en_us', 'menu_servers', 'Name Servers');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(302, 'en_us', 'menu_servers_view', 'View Name Servers');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(303, 'en_us', 'menu_servers_add', 'Add Name Server');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(304, 'en_us', 'menu_domains', 'Domains/Zones');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(305, 'en_us', 'menu_domains_view', 'View Domains');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(306, 'en_us', 'menu_domains_add', 'Add Domain');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(307, 'en_us', 'menu_overview', 'Overview');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(308, 'en_us', 'menu_logs', 'Changelog');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(309, 'en_us', 'tbl_lnk_details', 'details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(310, 'en_us', 'tbl_lnk_records', 'domain records');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(311, 'en_us', 'tbl_lnk_delete', 'delete');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(312, 'en_us', 'tbl_lnk_delete', 'delete');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(313, 'en_us', 'tbl_lnk_logs', 'logs');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(314, 'en_us', 'domain_name', 'Domain Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(315, 'en_us', 'domain_serial', 'Domain Serial');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(316, 'en_us', 'domain_description', 'Description');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(317, 'en_us', 'domain_details', 'Domain Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(318, 'en_us', 'domain_soa', 'Start of Authority Record');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(319, 'en_us', 'soa_hostmaster', 'Email Administrator Address');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(320, 'en_us', 'soa_serial', 'Domain Serial');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(321, 'en_us', 'soa_refresh', 'Refresh Timer');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(322, 'en_us', 'soa_retry', 'Refresh Retry Timeout');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(323, 'en_us', 'soa_expire', 'Expiry Timer');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(324, 'en_us', 'soa_default_ttl', 'Default Record TTL');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(325, 'en_us', 'submit', 'Save Changes');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(326, 'en_us', 'domain_records_ns', 'Nameserver Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(327, 'en_us', 'domain_records_mx', 'Mailserver Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(328, 'en_us', 'domain_records_custom', 'Host Records Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(329, 'en_us', 'record_type', 'Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(330, 'en_us', 'record_ttl', 'TTL');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(331, 'en_us', 'record_name', 'Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(332, 'en_us', 'record_content', 'Content');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(333, 'en_us', 'record_prio', 'Priority');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(334, 'en_us', 'server_primary', 'Primary Nameserver');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(335, 'en_us', 'server_name', 'Name Server FQDN');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(336, 'en_us', 'server_description', 'Description');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(337, 'en_us', 'server_type', 'Server Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(338, 'en_us', 'sync_status', 'Sync Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(339, 'en_us', 'server_details', 'Server Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(340, 'en_us', 'api_auth_key', 'API Authentication Key');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(341, 'en_us', 'server_status', 'Server Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(342, 'en_us', 'sync_status_config', 'Configuration Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(343, 'en_us', 'help_api_auth_key', 'Authentication key to enable bind configuration generation script to talk back to NamedManager.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(344, 'en_us', 'sync_status_log', 'Logging Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(345, 'en_us', 'api', 'API (supports Bind)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(346, 'en_us', 'config_zone_defaults', 'Zone Configuration Defaults');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(347, 'en_us', 'config_zone_database', 'Zone Database Defaults');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(348, 'en_us', 'config_dateandtime', 'Date and Time Configuration');



--
-- 1.0.0 alpha 2 to 1.0.0 alpha 3 upgrade
--
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(349, 'en_us', 'timestamp', 'Timestamp');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(350, 'en_us', 'username', 'Username');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(351, 'en_us', 'log_type', 'Log Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(352, 'en_us', 'log_contents', 'Log Contents');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(353, 'en_us', 'filter_searchbox', 'Search');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(354, 'en_us', 'filter_num_logs_rows', 'Maximum Log Lines');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(355, 'en_us', 'filter_id_server_name', 'Name Server');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(356, 'en_us', 'filter_id_domain', 'Domain Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(357, 'en_us', 'help_ipv4_autofill', 'Automatically create PTR records for all the IPs in the domain, with the specified domain name suffix.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(358, 'en_us', 'ipv4_help', 'Note');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(359, 'en_us', 'help_ipv4_help', 'This interface allows you to setup a reverse DNS record for a /24 network range, by specifying the network address and optionally a domain for the PTR records if you want all the IP-DNS mapping created automatically.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(360, 'en_us', 'domain_standard', 'Standard Domain');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(361, 'en_us', 'domain_reverse_ipv4', 'Reverse Domain (IPv4)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(362, 'en_us', 'domain_type', 'Domain Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(363, 'en_us', 'ipv4_network', 'IPv4 Network Address');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(364, 'en_us', 'ipv4_autofill', 'Autofill IPs');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(365, 'en_us', 'ipv4_autofill_domain', 'Autofill IPs with domain');




--
-- 1.0.0 alpha 3 to 1.0.0 alpha 4 upgrade
--
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (366, 'en_us', 'reverse_ptr', 'Reverse PTR');


ALTER TABLE `dns_record_types` ADD `is_standard` BOOL NOT NULL AFTER `user_selectable`;

UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =4;
UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =5;
UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =7;

INSERT INTO `config` (`name`, `value`) VALUES ('ADMIN_API_KEY', '');



--
-- 1.0.0 alpha 4 to 1.0.0 alpha 5 upgrade
--

INSERT INTO  `menu` (`id` ,`priority` ,`parent` ,`topic` ,`link` ,`permid`) VALUES (NULL ,  '320',  'menu_domains',  'menu_domains_import',  'domains/import.php',  '2');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'file_bind_8', 'Bind 8/9 Compatible Zonefile');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_domains_import', 'Import Domain');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'records_not_imported', 'Warning: No records were imported into the application!');

INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SRV', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SPF', '1', '1');

UPDATE dns_records SET name='@' WHERE type='MX' AND name='';


--
-- 1.0.0 alpha 5 to 1.0.0 beta 1 upgrade
--

INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'TXT', '1', '1');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_import_guide', 'All the records that have been processed by NamedManager from the uploaded zonefile are displayed below. Double-check that everything appears correctly - there may be some records that need adjusting, or some that are no longer required (eg old NS records).\n\nYou can check/uncheck the import button to include/exclude records from the import process if they are no longer desired.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_delete', 'Delete Domain');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'delete_confirm', 'Confirm Deletion');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_api', 'API Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_miscellaneous', 'Miscellaneous Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_records', 'Domain Records');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_type', 'Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_ttl', 'TTL');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_prio', 'Priority');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_name', 'Name/Origin');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_content', 'Content/Record');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_import', 'Import Record?');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_origin', 'Name/Origin');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'unmatched_import', 'Unmatched Records');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_notice_unmatched_rows', 'Not all records were imported successfully, please review the unmatched lines below - if they are desired, you can adjust the format in the file before upload or create the domain and then add these missed records manually');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_notice_no_unmatched_rows', 'All records in the zone file have been identified and imported into the array above. :-)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'upload', 'Upload');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_upload_type', 'Import Source');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_upload_file', 'Zone File');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_admin_api_key', 'Key used to authenticate Nameserver-located scripts.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'unmatched_import_help', 'Zonefile import is not always perfect, especially when importing from human-written text zone files. If there are any records that couldn\'t be matched, they will appear below for manual handling.');


--
-- 1.0.0 beta 1 to 1.0.0 upgrade
--

INSERT INTO config (name, value) VALUES ('LOG_UPDATE_INTERVAL', 5);

ALTER TABLE  `name_servers` ADD  `server_record` BOOLEAN NOT NULL AFTER  `server_primary`;
UPDATE `name_servers` SET server_record='1';

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(NULL, 'en_us', 'server_record', 'Use as NS Record');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(NULL, 'en_us', 'server_record_option_help', 'Adds this name server to all domains as a public NS record.');


-- 1.0.0 to 1.1.0 upgrade
--

INSERT INTO `config` (`name`, `value`) VALUES ('PAGINATION_DOMAIN_RECORDS', '25');

INSERT INTO  `language` (`id` , `language` , `label` , `translation`) VALUES (NULL ,  'en_us',  'ipv4_autofill_forward',  'Create Forward Record');
INSERT INTO  `language` (`id` , `language` , `label` , `translation`) VALUES (NULL ,  'en_us',  'help_ipv4_autofill_forward',  'Automatically creates forward records for each IP in the specified domain.');



--
-- 1.1.0 to 1.2.0 upgrade
--

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'sync_status_zones', 'Zonefile Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'ipv4_autofill_reverse_from_forward', 'Create Records From Existing');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_ipv4_autofill_reverse_from_forward', 'Automatically find any existing A records for the IP range being added and set the reverse records to them where possible.');


--
-- 1.2.0 to 1.3.0 upgrade
--

INSERT INTO `config` (`name`, `value`) VALUES('FEATURE_LOGS_API', '1');
INSERT INTO `config` (`name`, `value`) VALUES('FEATURE_LOGS_AUDIT', '1');
INSERT INTO `config` (`name`, `value`) VALUES('FEATURE_LOGS_ENABLE', '1');
INSERT INTO `config` (`name`, `value`) VALUES('LOG_RETENTION_CHECKTIME', '0');
INSERT INTO `config` (`name`, `value`) VALUES('LOG_RETENTION_PERIOD', '0');

ALTER TABLE `menu` ADD `config` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;
UPDATE `menu` SET config='FEATURE_LOGS_ENABLE' WHERE topic='menu_logs' LIMIT 1;



--
-- 1.3.0 to 1.4.0 upgrade
--

CREATE TABLE `name_servers_groups` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`group_name` VARCHAR( 255 ) CHARACTER SET ucs2 COLLATE ucs2_general_ci NOT NULL ,
`group_description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `dns_domains_groups` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`id_domain` INT UNSIGNED NOT NULL ,
`id_group` INT UNSIGNED NOT NULL
) ENGINE = InnoDB;


ALTER TABLE `name_servers` ADD `id_group` INT NOT NULL DEFAULT '1' AFTER `id` ;

INSERT INTO `name_servers_groups` (`id`, `group_name`, `group_description`) VALUES ('1', 'default', 'Default Nameserver Group');
INSERT INTO dns_domains_groups (id_domain, id_group) SELECT id, '1' FROM dns_domains;

INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 503, 'menu_servers', 'menu_servers_groups', 'servers/groups.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 520, 'menu_servers_groups', 'menu_servers_groups_view', 'servers/groups.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups', 'menu_servers_groups_add', 'servers/group-add.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups_view', '', 'servers/group-view.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups_view', '', 'servers/group-delete.php', 2, '');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_domain_group_selection', 'Select the group or groups that this domain belongs to - groups allow domains to be located on specific sets of name servers which is useful for segregation purposes (eg internal vs external name servers).');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_logging', 'Logging Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_groups', 'Domain Server Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'server_group', 'Server Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups', 'Manage Server Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups_view', 'View Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups_add', 'Add Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_name', 'Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_description', 'Description');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_members', 'Server Group Members');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_details', 'Server Group Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_member_servers', 'Member Name Servers');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_member_domains', 'Member Domains');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_delete', 'Delete Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'server_domains', 'Server Domain Settings');

ALTER TABLE `dns_records` ADD INDEX ( `id_domain` );


--
-- Added local Admin user default when running with SQL mode authentication
--
INSERT INTO `users` (`id`, `username`, `realname`, `password`, `password_salt`, `contact_email`, `time`, `ipaddress`) VALUES (1, 'setup', 'Setup Account', '14c2a5c3681b95582c3e01fc19f49853d9cdbb31', 'hctw8lbz3uhxl6sj8ixr', 'support@amberdms.com', 0, '');
INSERT INTO `users_permissions` (`id`, `userid`, `permid`) VALUES (1, 1, 2);
INSERT INTO `users_permissions` (`id`, `userid`, `permid`) VALUES (2, 1, 3);

INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 920, 'top', 'menu_admin_users', 'user/users.php', 3, 'AUTH_METHOD=sql');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 921, 'menu_admin_users', '', 'user/user-view.php', 3, 'AUTH_METHOD=sql');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 921, 'menu_admin_users', '', 'user/user-permissions.php', 3, 'AUTH_METHOD=sql');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 921, 'menu_admin_users', '', 'user/user-delete.php', 3, 'AUTH_METHOD=sql');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 921, 'menu_admin_users', '', 'user/user-add.php', 3, 'AUTH_METHOD=sql');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'realname', 'Real Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'contact_email', 'Contact Email');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'lastlogin_time', 'Last Login Time');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'lastlogin_ipaddress', 'Last Login Location');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'tbl_lnk_permissions', 'permissions');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_permissions', 'User Permissions');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'id_user', 'User ID');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_view', 'User Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_password', 'User Password');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_info', 'User Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_options', 'User Options');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'user_delete', 'Delete User');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_admin_users', 'User Management');


--
-- IPv6 support improvements
--
ALTER TABLE `users` CHANGE `ipaddress` `ipaddress` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;
ALTER TABLE `users_blacklist` CHANGE `ipaddress` `ipaddress` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;

ALTER TABLE `users_sessions` DROP `ipaddress`;
ALTER TABLE `users_sessions` ADD `ipv4` VARCHAR( 14 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `authkey`;
ALTER TABLE `users_sessions` ADD `ipv6` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `ipv4` ;


--
-- Make sure phone home is disabled
--
UPDATE `config` SET `value` = 'disabled' WHERE `config`.`name` = 'PHONE_HOME';
UPDATE `config` SET `value` = '0' WHERE `config`.`name` = 'PHONE_HOME_TIMER';
UPDATE `config` SET `value` = '0' WHERE `config`.`name` = 'SUBSCRIPTION_ID';


--
-- Version 1.5.1 to 1.6.0 upgrade
--

--
-- Fix incorrectly sized IPv4 field
--
ALTER TABLE `users_sessions` CHANGE `ipv4` `ipv4` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

--
-- When using LDAP, don't go back to the backend server every page load
--
INSERT INTO `config` (`name`, `value`) VALUES ('AUTH_PERMS_CACHE', 'enabled');

--
-- IPv6 messages
--
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_reverse_ipv6', 'Reverse Domain (IPv6)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'ipv6_network', 'IPv6 Network Range');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'ipv6_help', 'Note');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_ipv6_help', 'This interface allows you to setup a reverse DNS record for an IPv6 network range, by specifying the range along with a CIDR value. ');


--
-- Fixes for MySQL STRICT mode
--

ALTER TABLE `users_sessions` CHANGE `ipv4` `ipv4` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `ipv6` `ipv6` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE `users` CHANGE `username` `username` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `realname` `realname` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `password_salt` `password_salt` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `contact_email` `contact_email` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `ipaddress` `ipaddress` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE `dns_records` CHANGE `ttl` `ttl` INT( 11 ) NOT NULL DEFAULT '3600', CHANGE `prio` `prio` INT( 11 ) NOT NULL DEFAULT '0';
ALTER TABLE `logs` CHANGE `id_server` `id_server` INT( 11 ) NOT NULL DEFAULT '0', CHANGE `id_domain` `id_domain` INT( 11 ) NOT NULL DEFAULT '0', CHANGE `username` `username` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `log_type` `log_type` CHAR( 10 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `log_contents` `log_contents` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;
ALTER TABLE `dns_domains` CHANGE `soa_hostmaster` `soa_hostmaster` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `soa_serial` `soa_serial` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT '0', CHANGE `soa_refresh` `soa_refresh` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0', CHANGE `soa_retry` `soa_retry` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0', CHANGE `soa_expire` `soa_expire` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0', CHANGE `soa_default_ttl` `soa_default_ttl` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `dns_records` CHANGE `name` `name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `type` `type` VARCHAR( 6 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `content` `content` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE `name_servers` CHANGE `id_group` `id_group` INT( 11 ) NOT NULL DEFAULT '1', CHANGE `server_primary` `server_primary` TINYINT( 1 ) NOT NULL DEFAULT '0', CHANGE `server_record` `server_record` TINYINT( 1 ) NOT NULL DEFAULT '0', CHANGE `server_name` `server_name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `server_description` `server_description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL , CHANGE `server_type` `server_type` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `api_auth_key` `api_auth_key` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `api_sync_config` `api_sync_config` BIGINT( 20 ) NOT NULL DEFAULT '0', CHANGE `api_sync_log` `api_sync_log` BIGINT( 20 ) NOT NULL DEFAULT '0';


--
-- Disable obsolete config options
--

DELETE FROM `config` WHERE `config`.`name` = 'ZONE_DB_USERNAME';
DELETE FROM `config` WHERE `config`.`name` = 'ZONE_DB_HOST';
DELETE FROM `config` WHERE `config`.`name` = 'ZONE_DB_NAME';
DELETE FROM `config` WHERE `config`.`name` = 'ZONE_DB_PASSWORD';
DELETE FROM `config` WHERE `config`.`name` = 'ZONE_DB_TYPE';

INSERT INTO `config` (`name`, `value`) VALUES ('ZONE_DB_USERNAME', 'disabled');
INSERT INTO `config` (`name`, `value`) VALUES ('ZONE_DB_HOST', 'disabled');
INSERT INTO `config` (`name`, `value`) VALUES ('ZONE_DB_NAME', 'disabled');
INSERT INTO `config` (`name`, `value`) VALUES ('ZONE_DB_PASSWORD', 'disabled');
INSERT INTO `config` (`name`, `value`) VALUES ('ZONE_DB_TYPE', 'disabled');


--
-- New Route53/Cloud structure
--

CREATE TABLE IF NOT EXISTS `cloud_zone_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_name_server` int(10) unsigned NOT NULL,
  `id_domain` int(10) unsigned NOT NULL,
  `id_mapped` varchar(255) NOT NULL DEFAULT '',
  `soa_serial` bigint(20) NOT NULL,
  `delegated_ns` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


--
-- Various translation additions/improvements
--

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'id_group', 'Nameserver Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_amberstats', 'Assist the developers!');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_hosted', 'Hosted Cloud DNS Services');

--
-- New Resource records
--
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SSHFP', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'HINFO', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'LOC', '1', '1');


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20160001' WHERE name='SCHEMA_VERSION' LIMIT 1;


