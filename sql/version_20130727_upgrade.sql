
--
-- Fix incorrectly sized IPv4 field
--
ALTER TABLE `users_sessions` CHANGE `ipv4` `ipv4` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

--
-- When using LDAP, don't go back to the backend server every page load
--
INSERT INTO `config` (`name`, `value`) VALUES ('AUTH_PERMS_CACHE', 'enabled');

--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20130727' WHERE name='SCHEMA_VERSION' LIMIT 1;


