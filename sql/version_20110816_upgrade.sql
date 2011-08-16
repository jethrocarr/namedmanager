
--
-- 1.0.0 to 1.1.0 upgrade
--

INSERT INTO `config` (`name`, `value`) VALUES ('PAGINATION_DOMAIN_RECORDS', '25');

INSERT INTO  `language` (`id` , `language` , `label` , `translation`) VALUES (NULL ,  'en_us',  'ipv4_autofill_forward',  'Create Forward Record');
INSERT INTO  `language` (`id` , `language` , `label` , `translation`) VALUES (NULL ,  'en_us',  'help_ipv4_autofill_forward',  'Automatically creates forward records for each IP in the specified domain.');



--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20110816' WHERE name='SCHEMA_VERSION' LIMIT 1;


