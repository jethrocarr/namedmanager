
--
-- 1.3.0 to 1.4.0 upgrade
--

CREATE TABLE `name_servers_groups` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`group_name` VARCHAR( 255 ) CHARACTER SET ucs2 COLLATE ucs2_general_ci NOT NULL ,
`group_description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `dns_domains_groups` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`id_domain` INT UNSIGNED NOT NULL ,
`id_group` INT UNSIGNED NOT NULL
) ENGINE = InnoDB;


ALTER TABLE `name_servers` ADD `id_group` INT NOT NULL DEFAULT '1' AFTER `id` ;

INSERT INTO `name_servers_groups` (`id`, `group_name`, `group_description`) VALUES ('1', 'default', 'Default Nameserver Group');
INSERT INTO dns_domains_groups (id_domain, id_group) SELECT id, '1' FROM dns_domains;

INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 503, 'menu_servers', 'menu_servers_groups', 'servers/groups.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 520, 'menu_servers_groups', 'menu_servers_groups_view', 'servers/groups.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups', 'menu_servers_groups_add', 'servers/group-add.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups_view', '', 'servers/group-view.php', 2, '');
INSERT INTO `menu` (`id`, `priority`, `parent`, `topic`, `link`, `permid`, `config`) VALUES(NULL, 521, 'menu_servers_groups_view', '', 'servers/group-delete.php', 2, '');

INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'help_domain_group_selection', 'Select the group or groups that this domain belongs to - groups allow domains to be located on specific sets of name servers which is useful for segregation purposes (eg internal vs external name servers).');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'config_logging', 'Logging Configuration');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'domain_groups', 'Domain Server Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'server_group', 'Server Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups', 'Manage Server Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups_view', 'View Groups');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'menu_servers_groups_add', 'Add Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_name', 'Name');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_description', 'Description');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_members', 'Server Group Members');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_details', 'Server Group Details');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_member_servers', 'Member Name Servers');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_member_domains', 'Member Domains');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'group_delete', 'Delete Group');
INSERT INTO `language` (`id`, `language`, `label`, `translation`) VALUES (NULL, 'en_us', 'server_domains', 'Server Domain Settings');

ALTER TABLE `dns_records` ADD INDEX ( `id_domain` );



--
-- Set Schema Version
--

UPDATE `config` SET `value` = '20120426' WHERE name='SCHEMA_VERSION' LIMIT 1;


