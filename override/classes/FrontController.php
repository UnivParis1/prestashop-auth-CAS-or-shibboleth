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
*  @version  Release: $Revision: 14390 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class FrontController extends FrontControllerCore
{
	
	public function preProcess()
	{
		//global $cookie;
		//include_once(_PS_MODULE_DIR_.'uppsauthservice/classes/cas_ldap_saml.php');
	
		if (self::$cookie->caslogin && !self::$cookie->id_customer)
				self::$cookie->caslogin = 0;

		//$upps = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'upps_configuration`');

      /*  $type = trim($upps['type']);
        $cas_host = trim($upps['cas_host']);
        $ldap_server = trim($upps['ldap_host']);
        $route_dn = trim($upps['base_dn']);
        $inf = trim($upps['info']);
        $url_return = urlencode($upps['return_url']);
        $ldap_user = trim($upps['access_dn']);
        $ldap_passwd = $upps['password'];
  	
  	    //file Debug pour voir ce que PHPCAS retourne
		$fileDebug='/webhome/prestacas/html/modules/uppsauthservice/errorCas.log';	
		
       /* Appel de la classe CAS du module 
       		et appel des parametres en fonction du type */
   /*    $cas_conn = new cas_ldap_saml($type, $cas_host, 443, '/cas/',$ldap_server,389,$route_dn,$ldap_user,$ldap_passwd,true,$fileDebug);
			
		//test authentification CAS
		if ($cas_conn->isAuthentified())
		{
			$jsonInfo = $cas_conn->getInformation();
			$arrayData = Tools::jsonDecode($jsonInfo);
			if ($arrayData->ok)
		        $this->updateSsoCustomer($arrayData);
		}
	*/
	}

	private function updateSsoCustomer($arrayWsLDAP = array())
	{
		global $cookie;
		$customer = new Customer();
		// Check if customer exists
		$isAuthentified = $customer->getByEmail($arrayWsLDAP->mail);

		if ($isAuthentified OR $customer->id)
		{
			//TODO : update customer with LDAP informations
			$array_search=array("(",")","#","@");
			$array_replace=array("","","","");
			$customer->lastname=str_replace($array_search, $array_replace, (string)$arrayWsLDAP->sn);
			
			$customer->firstname = $arrayWsLDAP->givenName;
			$customer->passwd = md5(uniqid(rand(),true));
			$years = Tools::substr($arrayWsLDAP->Up1Birthday, 0, 4);
			$months = Tools::substr($arrayWsLDAP->Up1Birthday, 4, 2);
			$days = Tools::substr($arrayWsLDAP->Up1Birthday, 6, 2);
			if (!@checkdate($months, $days, $years) AND !($months == '' AND $days == '' AND $years == ''))
				$customer->birthday = (empty($years) ? '' : (int)($years).'-'.(int)($months).'-'.(int)($days));
			$gender_search=array("M.","Mme","Mlle");
			$gender_replace=array("1","2","2");
			$customer->id_gender = str_replace($gender_search, $gender_replace, $arrayWsLDAP->supannCivilite);

			// gestion des groupes
			$arrayGroups = explode(',',$arrayWsLDAP->groups);
			foreach ($arrayGroups as $group) {
				$idGroup = Db::getInstance()->getValue('SELECT * FROM '._DB_PREFIX_.'group_lang WHERE 
					`name` = "'.$group.'" AND `id_lang` ='.(int)$cookie->id_lang);

				if (!$idGroup && strlen($group)) 
				{
					$objGroup = new Group();
					$objGroup->name[(int)$cookie->id_lang] = $group;
					$objGroup->price_display_method = Product::getTaxCalculationMethod();
					$objGroup->add();
					$insertGroup[] = $objGroup->id;
				}
				else
					$insertGroup[] = $idGroup;	
			}
			if (isset($insertGroup) || !empty($insertGroup)) {
				$customer->cleanGroups();
				$customer->addGroups($insertGroup);
				$customer->id_default_group = $insertGroup[0];
			}

			if (!$customer->update()) 
			{
				$this->errors[] = Tools::displayError('An error occurred while updating your account. Please, contact your administrator.');
				Tools::redirect('authentication.php');
			}
			else
			{
				/* Preparing address */
				$address = new Address(Address::getFirstCustomerAddressId($customer->id, true));
				$arrayPostalAddress = explode('$',$arrayWsLDAP->postalAddress);
				/* US customer: normalize the address */
				if ($address->id_country == Country::getByIso('US'))
				{
					include_once(_PS_TAASC_PATH_.'AddressStandardizationSolution.php');
					$normalize = new AddressStandardizationSolution;
					$address->address1 = $normalize->AddressLineStandardization($address->address1);
					$address->address2 = $normalize->AddressLineStandardization($address->address2);
				}
				else 
				{
					$address->address1 = $arrayPostalAddress[0];
					$address->address2 = '';
				}
				$id_country = Country::getIdByName(Configuration::get('PS_LANG_DEFAULT'),$arrayPostalAddress[2]);
				$address->id_country = $id_country;
				
				$address->phone = $this->formatFrenchPhoneNumber($arrayWsLDAP->telephoneNumber);
				$address->phone_mobile = $this->formatFrenchPhoneNumber($arrayWsLDAP->mobile);
				$replaceSearch = array('{','}');
				$replaceReplace = array('','');
				$address->company = str_replace($replaceSearch, $replaceReplace, $arrayWsLDAP->company);
				$address->city = Tools::substr($arrayPostalAddress[1],6);
				$postcode = Tools::substr($arrayPostalAddress[1],0,5);
				$zip_code_format = Country::getZipCodeFormat((int)($id_country));
				if (Country::getNeedZipCode((int)($id_country)))
				{
					if (($postcode) AND $zip_code_format)
					{
						$zip_regexp = '/^'.$zip_code_format.'$/ui';
						$zip_regexp = str_replace(' ', '( |)', $zip_regexp);
						$zip_regexp = str_replace('-', '(-|)', $zip_regexp);
						$zip_regexp = str_replace('N', '[0-9]', $zip_regexp);
						$zip_regexp = str_replace('L', '[a-zA-Z]', $zip_regexp);
						$zip_regexp = str_replace('C', Country::getIsoById((int)($id_country)), $zip_regexp);
						if (!preg_match($zip_regexp, $postcode))
							$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is invalid.').'<br />'.Tools::displayError('Must be typed as follows:').' '.str_replace('C', Country::getIsoById((int)($id_country)), str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
					}
					elseif ($zip_code_format)
						$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is required.');
					elseif ($postcode AND !preg_match('/^[0-9a-zA-Z -]{4,9}$/ui', $postcode))
						$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is invalid.');
					$address->postcode = $postcode;
				}
				if (Country::isNeedDniByCountryId($address->id_country) AND (!Tools::getValue('dni') OR !Validate::isDniLite(Tools::getValue('dni'))))
					$this->errors[] = Tools::displayError('Identification number is incorrect or has already been used.');
				elseif (!Country::isNeedDniByCountryId($address->id_country))
					$address->dni = NULL;

				if (!sizeof($this->errors))
				{
					if (!$country = new Country($address->id_country, Configuration::get('PS_LANG_DEFAULT')) OR !Validate::isLoadedObject($country))
						die(Tools::displayError());
					if ((int)($country->contains_states) AND !(int)($address->id_state))
						$this->errors[] = Tools::displayError('This country requires a state selection.');
					else
						$address->update();
				}		
				if (sizeof($this->errors))
				{
					$this->errors[] = Tools::displayError('An error occured during address updating. Please, contact administrator system');
					Tools::redirect('authentication.php');
				}	
			}
			// Updating cookie
			self::$cookie->id_compare = isset(self::$cookie->id_compare) ? self::$cookie->id_compare: CompareProduct::getIdCompareByIdCustomer($customer->id);
			self::$cookie->id_customer = (int)($customer->id);
			self::$cookie->customer_lastname = $customer->lastname;
			self::$cookie->customer_firstname = $customer->firstname;
			self::$cookie->logged = 1;
			self::$cookie->is_guest = $customer->isGuest();
			self::$cookie->passwd = $customer->passwd;
			self::$cookie->email = $customer->email;
			self::$cookie->group = $customer->id_default_group;
			if (Configuration::get('PS_CART_FOLLOWING') AND (empty(self::$cookie->id_cart) OR Cart::getNbProducts(self::$cookie->id_cart) == 0))
				self::$cookie->id_cart = (int)(Cart::lastNoneOrderedCart((int)($customer->id)));
			/* Update cart address */
			self::$cart->id_carrier = 0;
			self::$cart->id_address_delivery = Address::getFirstCustomerAddressId((int)($customer->id));
			self::$cart->id_address_invoice = Address::getFirstCustomerAddressId((int)($customer->id));
			// If a logged guest logs in as a customer, the cart secure key was already set and needs to be updated
			self::$cart->secure_key = $customer->secure_key;
			self::$cart->update();
		}				
	}

	private function formatFrenchPhoneNumber($phoneNumber, $international = false)
    {
		//Supprimer tous les caractères qui ne sont pas des chiffres
		$phoneNumber = preg_replace('/[^0-9]+/', '', $phoneNumber);
		//Garder les 9 derniers chiffres
		$phoneNumber = substr($phoneNumber, -9);
		//On ajoute +33 si la variable $international vaut true et 0 dans tous les autres cas
		$motif = $international ? '+33 (\1) \2 \3 \4 \5' : '0\1 \2 \3 \4 \5';
		$phoneNumber = preg_replace('/(\d{1})(\d{2})(\d{2})(\d{2})(\d{2})/', $motif, $phoneNumber);

		return $phoneNumber;
    }	
}

?>
