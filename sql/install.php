<?php

$sql = [];

$sql[] = 'CREATE TABLE `'._DB_PREFIX_.'authservice` (
  `id_authservice` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum(\'shibboleth\',\'cas\',\'ldap\') DEFAULT NULL,
  `auth_key` varchar(128) NOT NULL DEFAULT \'\',
  `id_customer` int(11) DEFAULT NULL,
  `id_address` int(11) DEFAULT NULL,
  `date_add` datetime DEFAULT NULL,
  `date_upd` datetime DEFAULT NULL,
  PRIMARY KEY (`id_authservice`),
  KEY `auth_key` (`auth_key`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
