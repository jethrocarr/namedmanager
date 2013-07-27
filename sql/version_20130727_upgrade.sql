
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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20130727' WHERE name='SCHEMA_VERSION' LIMIT 1;


