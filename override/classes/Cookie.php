<?php
/*
* 2007-2012 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2012 PrestaShop SA
*  @version  Release: $Revision: 16938 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class Cookie extends CookieCore
{
	
	/**
	  * Delete cookie
	  */
	public function logout()
	{
		$this->_content = array();
		$this->_setcookie();
		unset($_COOKIE[$this->_name]);
		$this->_modified = true;
		$this->write();
	}

	/**
	  * Soft logout, delete everything links to the customer
	  * but leave there affiliate's informations
	  */
	public function mylogout()
	{
		global $link;

		if ($_COOKIE['PHPSESSID']) {
			// Test du type d'authentification
			$upps = Db::getInstance()->getRow('SELECT `type`, `shib_login`, 
									`cas_host`, `cas_url`,
									`uri_login`,`cas_service`   
									FROM `'._DB_PREFIX_.'upps_configuration` WHERE `id_box` = 1');
			switch ($upps['type']) {
			 	case 'shib':
			 		$ret = Tools::file_get_contents($upps['shib_logout']);
			 		break;
			 	default:
			 		$ret = Tools::file_get_contents(Tools::getProtocol(true).$upps['cas_host'].'/'.($upps['cas_url'] ? $upps['cas_url'].'/' : '').
										'logout?'.$upps['cas_service'].'='.$link->getPageLink('authentication.php', true));
			 		break;	
			 } 
			// Set expiration time to -1hr (will cause browser deletion)
			setcookie("PHPSESSID", false, time() - 3600);
			// Unset key
			unset($_COOKIE["PHPSESSID"]);
		}
		unset($this->_content['id_compare']);
		unset($this->_content['id_customer']);
		unset($this->_content['id_guest']);
		unset($this->_content['is_guest']);
		unset($this->_content['id_connections']);
		unset($this->_content['customer_lastname']);
		unset($this->_content['customer_firstname']);
		unset($this->_content['passwd']);
		unset($this->_content['logged']);
		unset($this->_content['email']);
		unset($this->_content['id_cart']);
		unset($this->_content['id_address_invoice']);
		unset($this->_content['id_address_delivery']);
		unset($this->_content['group']);
		unset($this->_content['caslogin']);
		
		$this->_modified = true;
		$this->write();
	}
}
