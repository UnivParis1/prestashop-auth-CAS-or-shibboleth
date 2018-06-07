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
*  @version  Release: $Revision: 16943 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AddressController extends AddressControllerCore
{
	public function displayContent()
	{
		$firstAddress = 0;
		if ($this->_address->id_customer)
			$firstAddress = self::getFirstCustomerAddressId($this->_address->id_customer);
		self::$smarty->assign('firstAddress', $firstAddress);		
		$this->_processAddressFormat();
		self::$smarty->display(_PS_THEME_DIR_.'address.tpl');
	}

	private static function getFirstCustomerAddressId($id_customer)
	{
		$firstAdd = Db::getInstance()->getValue('
				SELECT `id_address`
				FROM `'._DB_PREFIX_.'address`
				WHERE `id_customer` = '.(int)$id_customer.' AND `deleted` = 0 AND `active` = 1 
				ORDER BY `id_address`');
		if ($firstAdd == (int)Tools::getValue('id_address', 0))
				return 1;
			else
				return 0;
	}
	
}

