--
-- New Resource records
--
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SSHFP', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'HINFO', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'LOC', '1', '1');


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20150416' WHERE name='SCHEMA_VERSION' LIMIT 1;


