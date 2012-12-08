
--
-- Added local Admin user default when running with SQL mode authentication
--
INSERT INTO `users` (`id`, `username`, `realname`, `password`, `password_salt`, `contact_email`, `time`, `ipaddress`) VALUES (1, 'setup', 'Setup Account', '14c2a5c3681b95582c3e01fc19f49853d9cdbb31', 'hctw8lbz3uhxl6sj8ixr', 'support@amberdms.com', 0, '');
INSERT INTO `users_permissions` (`id`, `userid`, `permid`) VALUES (1, 1, 2);
INSERT INTO `users_permissions` (`id`, `userid`, `permid`) VALUES (2, 1, 3);
INSERT INTO `permissions` (`id`, `value`, `description`) VALUES ('3', 'admin', 'Allows configuration of user accounts');
UPDATE `permissions` SET `description` = 'Full management over domains, records and application defaults.' WHERE `permissions`.`id` =2;

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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20121208' WHERE name='SCHEMA_VERSION' LIMIT 1;


