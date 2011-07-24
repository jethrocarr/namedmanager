
--
-- 1.0.0 beta 3 to 1.0.0 upgrade
--

ALTER TABLE  `name_servers` ADD  `server_record` BOOLEAN NOT NULL AFTER  `server_primary`;
UPDATE `name_servers` SET server_record='1';

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(NULL, 'en_us', 'server_record', 'Use as NS Record');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES(NULL, 'en_us', 'server_record_option_help', 'Adds this name server to all domains as a public NS record.');

--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20110724' WHERE name='SCHEMA_VERSION' LIMIT 1;


