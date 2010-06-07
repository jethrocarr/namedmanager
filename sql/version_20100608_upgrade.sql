--
-- NAMEDMANAGER APPLICATION
--


--
-- 1.0.0 alpha 3 to 1.0.0 alpha 4 upgrade
--
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (366, 'en_us', 'reverse_ptr', 'Reverse PTR');


ALTER TABLE `dns_record_types` ADD `is_standard` BOOL NOT NULL AFTER `user_selectable`;

UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =4;
UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =5;
UPDATE `dns_record_types` SET `is_standard` = '1' WHERE `dns_record_types`.`id` =7;

INSERT INTO `config` (`name`, `value`) VALUES ('ADMIN_API_KEY', '');


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20100608' WHERE name='SCHEMA_VERSION' LIMIT 1;


