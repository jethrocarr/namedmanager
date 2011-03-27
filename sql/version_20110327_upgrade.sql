--
-- NAMEDMANAGER APPLICATION
--


--
-- 1.0.0 alpha 4 to 1.0.0 alpha 5 upgrade
--

INSERT INTO  `menu` (`id` ,`priority` ,`parent` ,`topic` ,`link` ,`permid`) VALUES (NULL ,  '320',  'menu_domains',  'menu_domains_import',  'domains/import.php',  '2');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'file_bind_8', 'Bind 8/9 Compatible Zonefile');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_domains_import', 'Import Domain');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_import_guide', 'All the records that have been processed by NamedManager from the uploaded zonefile are displayed below. Double-check that everything appears correctly - there may be some records that need adjusting, or some that are no longer required (eg old NS records).

You can check/uncheck the import button to include/exclude records from the import process if they are no longer desired.')

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'records_not_imported', 'Warning: No records were imported into the application!');

INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SRV', '1', '1');
INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'SPF', '1', '1');



--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20110327' WHERE name='SCHEMA_VERSION' LIMIT 1;


