
--
-- 1.0.0 alpha 5 to 1.0.0 beta 1 upgrade
--

INSERT INTO `dns_record_types` (`id`, `type`, `user_selectable`, `is_standard`) VALUES (NULL, 'TXT', '1', '1');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_import_guide', 'All the records that have been processed by NamedManager from the uploaded zonefile are displayed below. Double-check that everything appears correctly - there may be some records that need adjusting, or some that are no longer required (eg old NS records).\n\nYou can check/uncheck the import button to include/exclude records from the import process if they are no longer desired.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_delete', 'Delete Domain');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'delete_confirm', 'Confirm Deletion');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_api', 'API Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_miscellaneous', 'Miscellaneous Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_records', 'Domain Records');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_type', 'Type');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_ttl', 'TTL');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_prio', 'Priority');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_name', 'Name/Origin');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_content', 'Content/Record');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_header_import', 'Import Record?');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'record_origin', 'Name/Origin');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'unmatched_import', 'Unmatched Records');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_notice_unmatched_rows', 'Not all records were imported successfully, please review the unmatched lines below - if they are desired, you can adjust the format in the file before upload or create the domain and then add these missed records manually');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_notice_no_unmatched_rows', 'All records in the zone file have been identified and imported into the array above. :-)');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'upload', 'Upload');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_upload_type', 'Import Source');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'import_upload_file', 'Zone File');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_admin_api_key', 'Key used to authenticate Nameserver-located scripts.');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'unmatched_import_help', 'Zonefile import is not always perfect, especially when importing from human-written text zone files. If there are any records that couldn\'t be matched, they will appear below for manual handling.');


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20110406' WHERE name='SCHEMA_VERSION' LIMIT 1;


