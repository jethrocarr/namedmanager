--
-- NAMEDMANAGER APPLICATION
--


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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20100531' WHERE name='SCHEMA_VERSION' LIMIT 1;


