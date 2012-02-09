
--
-- 1.1.0 to 1.2.0 upgrade
--

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'sync_status_zones', 'Zonefile Status');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'ipv4_autofill_reverse_from_forward', 'Create Records From Existing');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_ipv4_autofill_reverse_from_forward', 'Automatically find any existing A records for the IP range being added and set the reverse records to them where possible.');


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20120209' WHERE name='SCHEMA_VERSION' LIMIT 1;


