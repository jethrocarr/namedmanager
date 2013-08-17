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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20130817' WHERE name='SCHEMA_VERSION' LIMIT 1;


