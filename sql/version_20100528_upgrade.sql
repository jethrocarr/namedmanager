--
-- NAMEDMANAGER APPLICATION
--


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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20100528' WHERE name='SCHEMA_VERSION' LIMIT 1;


