
--
-- Added local Admin user default when running with SQL mode authentication
--
INSERT INTO `users` (`id`, `username`, `realname`, `password`, `password_salt`, `contact_email`, `time`, `ipaddress`) VALUES (1, 'setup', 'Setup Account', '14c2a5c3681b95582c3e01fc19f49853d9cdbb31', 'hctw8lbz3uhxl6sj8ixr', 'support@amberdms.com', 0, '');
INSERT INTO `users_permissions` (`id`, `userid`, `permid`) VALUES (1, 1, 2);


--
-- IPv6 support improvements
--
ALTER TABLE `users` CHANGE `ipaddress` `ipaddress` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;
ALTER TABLE `users_blacklist` CHANGE `ipaddress` `ipaddress` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;

ALTER TABLE `users_sessions` DROP `ipaddress`;
ALTER TABLE `users_sessions` ADD `ipv4` VARCHAR( 14 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `authkey`;
ALTER TABLE `users_sessions` ADD `ipv6` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `ipv4` ;


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20121208' WHERE name='SCHEMA_VERSION' LIMIT 1;


