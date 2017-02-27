--
-- Updated menu entries
--
UPDATE `menu` SET permid = 0 WHERE id = 184;

--
-- Updated permissions
--
UPDATE `permissions` SET `description` = 'Full management over domains, records and name servers.' WHERE id = 2;
UPDATE `permissions` SET `description` = 'Allows configuration of user accounts and application settings.' WHERE id = 3;


--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20160001' WHERE name = 'SCHEMA_VERSION' LIMIT 1;


