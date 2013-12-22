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
-- Set Schema Version
--

UPDATE `config` SET `value` = '20131222' WHERE name='SCHEMA_VERSION' LIMIT 1;


