--
-- New content size records 255 -> 1024
--

ALTER TABLE `dns_records` MODIFY content VARCHAR(1024);

--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20171107' WHERE name='SCHEMA_VERSION' LIMIT 1;


