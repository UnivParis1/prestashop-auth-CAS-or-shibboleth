<?php
/*
* 
*  Surcharge controller Authentification
*/

class AuthController extends AuthControllerCore
{
	
	private function createAuthenticationCustomer($arrayWsLDAP)
	{
echo "function createAuthenticationCustomer()";				
		$customer = new Customer();
		$isAuthentified = $customer->getByEmail($arrayWsLDAP->mail);
		
		if (!$isAuthentified OR !$customer->id)
		{							
			/* Handle brute force attacks */
			sleep(1);
			// Customer doesn't exist : system creates him
			//TODO : create new customer with LDAP informations
			$create_account = 1;
			self::$smarty->assign('email_create', 1);
				
			/* Preparing customer */			
			$customer->email = $arrayWsLDAP->mail;
			$array_search=array("(",")","#","@");
			$array_replace=array("","","","");
						
			$customer->lastname = str_replace($array_search, $array_replace, (string)$arrayWsLDAP->sn);
			$customer->firstname = str_replace($array_search, $array_replace, (string)$arrayWsLDAP->givenName);


			$lastnameAddress = $customer->lastname;
			$firstnameAddress = $customer->firstname;
			
			$customer->passwd = md5(uniqid(rand(),true));
						
			if (!$arrayWsLDAP->telephoneNumber AND !$arrayWsLDAP->mobile)
				$this->errors[] = Tools::displayError('You must register at least one phone number');

			if (!empty($arrayWsLDAP->Up1Birthday)) {
				$years = Tools::substr($arrayWsLDAP->Up1Birthday, 0, 4);
				$months = Tools::substr($arrayWsLDAP->Up1Birthday, 4, 2);
				$days = Tools::substr($arrayWsLDAP->Up1Birthday, 6, 2);
				$customer->years = (int)$years;
				$customer->months = (int)$months;
				$customer->days = (int)$days;
				$customer->birthday = (empty($years) ? '' : (int)($years).'-'.(int)($months).'-'.(int)($days));
				
			}
			$gender_search=array("M.","Mme","Mlle");
			$gender_replace=array("1","2","2");
			if (!empty($arrayWsLDAP->supannCivilite))
				$customer->id_gender = str_replace($gender_search, $gender_replace, $arrayWsLDAP->supannCivilite);
			
			$this->errors = array_unique(array_merge($this->errors, $customer->validateControler()));

			$customer->active = 1;
			/* New Guest customer */
			$customer->is_guest = 0;	
			if (!$customer->add())
				$this->errors[] = Tools::displayError('An error occurred while creating your account.');
			else
			{
				$defaultGroup = Db::getInstance()->getValue('SELECT `default_group` FROM `'._DB_PREFIX_.'upps_configuration` 
												WHERE `id_box` = 1');
	
				$insertGroup[] = $defaultGroup; // groupe par défaut Université Paris 1
				if (!empty($arrayWsLDAP->groups))
					$arrayGroups = explode(',',$arrayWsLDAP->groups);
				if ($arrayGroups)
					foreach ($arrayGroups as $group) {
						$idGroup = Db::getInstance()->getValue('SELECT * FROM '._DB_PREFIX_.'group_lang WHERE 
							`name` = "'.$group.'" AND `id_lang` ='.(int)self::$cookie->id_lang);

						if (!$idGroup && strlen($group) < 33) 
						{
							$objGroup = new Group();
							$objGroup->name[(int)self::$cookie->id_lang] = $group;
							$objGroup->price_display_method = Product::getTaxCalculationMethod();
							$objGroup->add();
							$insertGroup[] = $objGroup->id;
						}
						else
							$insertGroup[] = $idGroup;	
					}
				$customer->cleanGroups();
				$customer->addGroups($insertGroup);
				$customer->id_default_group = $defaultGroup;
				$customer->update();
				
			}
			if (!empty($arrayWsLDAP->postalAddress))
			{	
				/* Preparing address */
				$address = new Address();
				$address->id_customer = 1;
				// Recuperation de l'id country
				$arrayPostalAddress = explode('$',$arrayWsLDAP->postalAddress);
				/* US customer: normalize the address */
				if ($address->id_country == Country::getByIso('US'))
				{
					include_once(_PS_TAASC_PATH_.'AddressStandardizationSolution.php');
					$normalize = new AddressStandardizationSolution;
					$address->address1 = $normalize->AddressLineStandardization($address->address1);
					$address->address2 = $normalize->AddressLineStandardization($address->address2);
				}
				else {
					$address->address1 = $arrayPostalAddress[0];
					$address->address2 = '';
				}
				$id_country = Country::getIdByName(Configuration::get('PS_LANG_DEFAULT'),$arrayPostalAddress[2]);
				$address->id_country = $id_country;
							
				$address->lastname= $lastnameAddress;			
				$address->firstname = $firstnameAddress;
				if (!empty($arrayWsLDAP->telephoneNumber))
					$address->phone = $this->formatFrenchPhoneNumber($arrayWsLDAP->telephoneNumber);
				if (!empty($arrayWsLDAP->mobile))
					$address->phone_mobile = $this->formatFrenchPhoneNumber($arrayWsLDAP->mobile);

				$address->city = Tools::substr($arrayPostalAddress[1],6);
				$replaceSearch = array('{','}');
				$replaceReplace = array('','');
				if (!empty($arrayWsLDAP->company))
					$address->company = str_replace($replaceSearch, $replaceReplace, $arrayWsLDAP->company);
				
				$address->alias = 'Adresse professionnelle';
			
				$this->errors = array_unique(array_merge($this->errors, $address->validateControler()));

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
					{
						
						//gestion de l'adresse
						$address->id_customer = (int)($customer->id);
						if (!$address->add())
							$this->errors[] = Tools::displayError('An error occurred while creating your address.');
					}
				}
			}	
						
			if (!$customer->is_guest)
			{
				if (!Mail::Send((int)self::$cookie->id_lang, 'account', Mail::l('Welcome!', (int)self::$cookie->id_lang),
						array('{firstname}' => $customer->firstname, '{lastname}' => $customer->lastname, 
						'{email}' => $customer->email, '{passwd}' => 'N/A', 
						$customer->email, $customer->firstname.' '.$customer->lastname)))
				$this->errors[] = Tools::displayError('Cannot send email');
			}
			self::$smarty->assign('confirmation', 1);
			self::$cookie->id_customer = (int)($customer->id);
			self::$cookie->customer_lastname = $customer->lastname;
			self::$cookie->customer_firstname = $customer->firstname;
			self::$cookie->passwd = $customer->passwd;
			self::$cookie->logged = 1;
			self::$cookie->email = $customer->email;
			self::$cookie->is_guest = !Tools::getValue('is_new_customer', 1);
			self::$cookie->group = $customer->id_default_group;
			self::$cookie->caslogin = 1;

			/* Update cart address */
			self::$cart->secure_key = $customer->secure_key;
			self::$cart->id_address_delivery = Address::getFirstCustomerAddressId((int)($customer->id));
			self::$cart->id_address_invoice = Address::getFirstCustomerAddressId((int)($customer->id));
			self::$cart->update();
			Module::hookExec('createAccount', array(
					'newCustomer' => $customer
			));
			if ($back = Tools::getValue('back'))
				Tools::redirect($back);
			Tools::redirect('my-account.php');		
		}
	}

	private function updateAuthenticationCustomer($arrayWsLDAP)
	{
		$customer = new Customer();
		$isAuthentified = $customer->getByEmail($arrayWsLDAP->mail);
		if ($isAuthentified OR $customer->id)
		{
			//TODO : update customer with LDAP informations
			$array_search=array("(",")","#","@");
			$array_replace=array("","","","");
			if (!empty($arrayWsLDAP->sn))
				$customer->lastname=str_replace($array_search, $array_replace, (string)$arrayWsLDAP->sn);		
			if (!empty($arrayWsLDAP->givenName))
				$customer->firstname = str_replace($array_search, $array_replace, (string)$arrayWsLDAP->givenName);
			$customer->passwd = md5(uniqid(rand(),true));
			if (!empty($arrayWsLDAP->Up1Birthday)) {
				$years = Tools::substr($arrayWsLDAP->Up1Birthday, 0, 4);
				$months = Tools::substr($arrayWsLDAP->Up1Birthday, 4, 2);
				$days = Tools::substr($arrayWsLDAP->Up1Birthday, 6, 2);
				$customer->years = (int)$years;
				$customer->months = (int)$months;
				$customer->days = (int)$days;
				$customer->birthday = (empty($years) ? '' : (int)($years).'-'.(int)($months).'-'.(int)($days));
			}
			$gender_search=array("M.","Mme","Mlle");
			$gender_replace=array(1,2,2);

			if (!empty($arrayWsLDAP->supannCivilite))
				$customer->id_gender = str_replace($gender_search, $gender_replace, $arrayWsLDAP->supannCivilite);

			// gestion des groupes
			$defaultGroup = Db::getInstance()->getValue('SELECT `default_group` FROM `'._DB_PREFIX_.'upps_configuration` 
												WHERE `id_box` = 1');
	
			$insertGroup[] = $defaultGroup; // groupe par défaut Université Paris 1
			if (!empty($arrayWsLDAP->groups))
				$arrayGroups = explode(',',$arrayWsLDAP->groups);
			if ($arrayGroups)
				foreach ($arrayGroups as $group) {
					$idGroup = Db::getInstance()->getValue('SELECT * FROM '._DB_PREFIX_.'group_lang WHERE 
						`name` = "'.$group.'" AND `id_lang` ='.(int)self::$cookie->id_lang);

					if (!$idGroup && strlen($group) < 33) 
					{
						$objGroup = new Group();
						$objGroup->name[(int)self::$cookie->id_lang] = $group;
						$objGroup->price_display_method = Product::getTaxCalculationMethod();
						$objGroup->add();
						$insertGroup[] = $objGroup->id;
					}
					else
						$insertGroup[] = $idGroup;	
				}
			$customer->cleanGroups();
			$customer->addGroups($insertGroup);
			$customer->id_default_group = $defaultGroup;
			if (!$customer->update()) {
				$this->errors[] = Tools::displayError('An error occurred while updating your account. Please contact your administrator.');
				Tools::redirect('authentication.php');
			}
			else
			{
				if (!empty($arrayWsLDAP->postalAddress))
				{
					/* Preparing address */
					$address = new Address(Address::getFirstCustomerAddressId($customer->id, true));

					$address->lastname= $customer->lastname;			
					$address->firstname = $customer->firstname;

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
						
					if ($arrayWsLDAP->telephoneNumber)
						$address->phone = $this->formatFrenchPhoneNumber($arrayWsLDAP->telephoneNumber);
					if ($arrayWsLDAP->mobile)
						$address->phone_mobile = $this->formatFrenchPhoneNumber($arrayWsLDAP->mobile);
					$replaceSearch = array('{','}');
					$replaceReplace = array('','');
					if ($arrayWsLDAP->company)
						$address->company = str_replace($replaceSearch, $replaceReplace, $arrayWsLDAP->company);
					$address->alias = 'Adresse professionnelle';
					
					$address->city = Tools::substr($arrayPostalAddress[1],6);
					$postcode = Tools::substr($arrayPostalAddress[1],0,5);
					$zip_code_format = Country::getZipCodeFormat((int)($id_country));
					if (Country::getNeedZipCode((int)($id_country)))
					{
						if (is_numeric($postcode) AND ($postcode) AND $zip_code_format)
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
						$this->errors[] = Tools::displayError('An error occured during address updateing. Please, contact administrator system');
						Tools::redirect('authentication.php');
					}
				}	
			}

			// update cookie	
			self::$cookie->id_compare = isset(self::$cookie->id_compare) ? self::$cookie->id_compare: CompareProduct::getIdCompareByIdCustomer($customer->id);
			self::$cookie->id_customer = (int)($customer->id);
			self::$cookie->customer_lastname = $customer->lastname;
			self::$cookie->customer_firstname = $customer->firstname;
			self::$cookie->logged = 1;
			self::$cookie->is_guest = $customer->isGuest();
			self::$cookie->passwd = $customer->passwd;
			self::$cookie->email = $customer->email;
			self::$cookie->group = $customer->id_default_group;
			self::$cookie->caslogin = 1;

	
			if (Configuration::get('PS_CART_FOLLOWING') AND (empty(self::$cookie->id_cart) OR Cart::getNbProducts(self::$cookie->id_cart) == 0))
				self::$cookie->id_cart = (int)(Cart::lastNoneOrderedCart((int)($customer->id)));
			self::$cart->id_carrier = 0;
			self::$cart->id_address_delivery = Address::getFirstCustomerAddressId((int)($customer->id));
			self::$cart->id_address_invoice = Address::getFirstCustomerAddressId((int)($customer->id));
			// If a logged guest logs in as a customer, the cart secure key was already set and needs to be updated
			self::$cart->secure_key = $customer->secure_key;
			Module::hookExec('authentication');

			if (!Tools::isSubmit('ajax'))
			{
				if ($back = Tools::getValue('back'))
					Tools::redirect($back);
				Tools::redirect('my-account.php');
			}
			if (Tools::isSubmit('ajax'))
			{
				$return = array(
					'hasError' => !empty($this->errors),
					'errors' => $this->errors,
					'token' => Tools::getToken(false)
				);
				die(Tools::jsonEncode($return));
			}
		}
		
	}

	public function preProcess()
	{
echo "function preProcess()";	
exit();	
		// Traitement pour Utilisateur CAS
		if ((!Tools::isSubmit('submitAccount') && 
			!Tools::isSubmit('submitGuestAccount') && 
			!Tools::isSubmit('SubmitCreate') && 
			!Tools::isSubmit('SubmitLogin')) && self::$cookie->caslogin)
		{	
			$customerCAS = Module::hookExec('beforeAuthentication');
			$arrayWsLDAP = Tools::jsonDecode($customerCAS);
			if (!empty($arrayWsLDAP))
			{	
				$email = $arrayWsLDAP->mail;
				if (empty($email))
					$this->errors[] = Tools::displayError('E-mail address required');
				elseif (!Validate::isEmail($email))
					$this->errors[] = Tools::displayError('Invalid e-mail address');
				elseif (empty($arrayWsLDAP->sn))
					$this->errors[] = Tools::displayError('Invalid lastname');
				elseif (empty($arrayWsLDAP->givenName))
					$this->errors[] = Tools::displayError('Invalid firstname');
				else
				{
					$customer = new Customer();
					$isAuthentified = $customer->getByEmail($email);
					if ($isAuthentified OR $customer->id)
						$this->updateAuthenticationCustomer($arrayWsLDAP);
					else
						$this->createAuthenticationCustomer($arrayWsLDAP);
				}
			}	
		}

		if (!self::$cookie->caslogin)
				self::$cookie->caslogin = 1;

		if (self::$cookie->isLogged() AND !Tools::isSubmit('ajax'))
			Tools::redirect('my-account.php');

		if (Tools::getValue('create_account'))
		{
			$create_account = 1;
			self::$smarty->assign('email_create', 1);
			self::$cookie->caslogin = 0;
		}

		if (Tools::isSubmit('SubmitCreate'))
		{
			if (!Validate::isEmail($email = Tools::getValue('email_create')) OR empty($email))
				$this->errors[] = Tools::displayError('Invalid e-mail address');
			elseif (Customer::customerExists($email, false, false))
			{
				$this->errors[] = Tools::displayError('An account is already registered with this e-mail, please fill in the password or request a new one.');
				$_POST['email'] = $_POST['email_create'];
				unset($_POST['email_create']);
			}
			// test if customer belongs to Paris 1 University
			elseif (Customer::customerExists($email, false, false))
			{
				$this->errors[] = Tools::displayError('An account is already registered with this e-mail, please fill in the password or request a new one.');
				$_POST['email'] = $_POST['email_create'];
				unset($_POST['email_create']);
			}
			else
			{
				$create_account = 1;
				self::$cookie->caslogin = 0;
				self::$smarty->assign('email_create', Tools::safeOutput($email));
				$_POST['email'] = $email;

			}
		}
		
		if (Tools::isSubmit('submitAccount') OR Tools::isSubmit('submitGuestAccount'))
		{
			$create_account = 1;
			if (Tools::isSubmit('submitAccount'))
				self::$smarty->assign('email_create', 1);
			/* New Guest customer */
			if (!Tools::getValue('is_new_customer', 1) AND !Configuration::get('PS_GUEST_CHECKOUT_ENABLED'))
				$this->errors[] = Tools::displayError('You cannot create a guest account.');
			if (!Tools::getValue('is_new_customer', 1))
				$_POST['passwd'] = md5(time()._COOKIE_KEY_);
			if (isset($_POST['guest_email']) AND $_POST['guest_email'])
				$_POST['email'] = $_POST['guest_email'];

			/* Preparing customer */
			$customer = new Customer();
			$lastnameAddress = $_POST['lastname'];
			$firstnameAddress = $_POST['firstname'];
			$_POST['lastname'] = $_POST['customer_lastname'];
			$_POST['firstname'] = $_POST['customer_firstname'];
			if (!Tools::getValue('phone') AND !Tools::getValue('phone_mobile'))
				$this->errors[] = Tools::displayError('You must register at least one phone number');

			if (!@checkdate(Tools::getValue('months'), Tools::getValue('days'), Tools::getValue('years')) AND !(Tools::getValue('months') == '' AND Tools::getValue('days') == '' AND Tools::getValue('years') == ''))
				$this->errors[] = Tools::displayError('Invalid date of birth');
			$customer->birthday = (empty($_POST['years']) ? '' : (int)($_POST['years']).'-'.(int)($_POST['months']).'-'.(int)($_POST['days']));

			$this->errors = array_unique(array_merge($this->errors, $customer->validateControler()));
			/* Preparing address */
			$address = new Address();
			$_POST['lastname'] = $lastnameAddress;
			$_POST['firstname'] = $firstnameAddress;
			$address->id_customer = 1;
			$this->errors = array_unique(array_merge($this->errors, $address->validateControler()));

			/* US customer: normalize the address */
			if ($address->id_country == Country::getByIso('US'))
			{
				include_once(_PS_TAASC_PATH_.'AddressStandardizationSolution.php');
				$normalize = new AddressStandardizationSolution;
				$address->address1 = $normalize->AddressLineStandardization($address->address1);
				$address->address2 = $normalize->AddressLineStandardization($address->address2);
			}

			$zip_code_format = Country::getZipCodeFormat((int)(Tools::getValue('id_country')));
			if (Country::getNeedZipCode((int)(Tools::getValue('id_country'))))
			{
				if (($postcode = Tools::getValue('postcode')) AND $zip_code_format)
				{
					$zip_regexp = '/^'.$zip_code_format.'$/ui';
					$zip_regexp = str_replace(' ', '( |)', $zip_regexp);
					$zip_regexp = str_replace('-', '(-|)', $zip_regexp);
					$zip_regexp = str_replace('N', '[0-9]', $zip_regexp);
					$zip_regexp = str_replace('L', '[a-zA-Z]', $zip_regexp);
					$zip_regexp = str_replace('C', Country::getIsoById((int)(Tools::getValue('id_country'))), $zip_regexp);
					if (!preg_match($zip_regexp, $postcode))
						$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is invalid.').'<br />'.Tools::displayError('Must be typed as follows:').' '.str_replace('C', Country::getIsoById((int)(Tools::getValue('id_country'))), str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
				}
				elseif ($zip_code_format)
					$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is required.');
				elseif ($postcode AND !preg_match('/^[0-9a-zA-Z -]{4,9}$/ui', $postcode))
					$this->errors[] = '<strong>'.Tools::displayError('Zip/ Postal code').'</strong> '.Tools::displayError('is invalid.');
			}
			if (Country::isNeedDniByCountryId($address->id_country) AND (!Tools::getValue('dni') OR !Validate::isDniLite(Tools::getValue('dni'))))
				$this->errors[] = Tools::displayError('Identification number is incorrect or has already been used.');
			elseif (!Country::isNeedDniByCountryId($address->id_country))
				$address->dni = NULL;

			if (!sizeof($this->errors))
			{
				if (Customer::customerExists(Tools::getValue('email')))
					$this->errors[] = Tools::displayError('An account is already registered with this e-mail, please fill in the password or request a new one.');
				if (Tools::isSubmit('newsletter'))
				{
					$customer->ip_registration_newsletter = pSQL(Tools::getRemoteAddr());
					$customer->newsletter_date_add = pSQL(date('Y-m-d H:i:s'));
				}
			
				if (!sizeof($this->errors))
				{
					if (!$country = new Country($address->id_country, Configuration::get('PS_LANG_DEFAULT')) OR !Validate::isLoadedObject($country))
						die(Tools::displayError());
					if ((int)($country->contains_states) AND !(int)($address->id_state))
						$this->errors[] = Tools::displayError('This country requires a state selection.');
					else
					{
						$customer->active = 1;
						/* New Guest customer */
						if (Tools::isSubmit('is_new_customer'))
							$customer->is_guest = !Tools::getValue('is_new_customer', 1);
						else
							$customer->is_guest = 0;
						if (!$customer->add())
							$this->errors[] = Tools::displayError('An error occurred while creating your account.');
						else
						{
							$address->id_customer = (int)($customer->id);
							if (!$address->add())
								$this->errors[] = Tools::displayError('An error occurred while creating your address.');
							else
							{
								if (!$customer->is_guest)
								{
									if (!Mail::Send((int)self::$cookie->id_lang, 'account', Mail::l('Welcome!', (int)self::$cookie->id_lang),
									array('{firstname}' => $customer->firstname, '{lastname}' => $customer->lastname, '{email}' => $customer->email, '{passwd}' => Tools::getValue('passwd')), $customer->email, $customer->firstname.' '.$customer->lastname))
										$this->errors[] = Tools::displayError('Cannot send email');
								}
								self::$smarty->assign('confirmation', 1);
								self::$cookie->id_customer = (int)($customer->id);
								self::$cookie->customer_lastname = $customer->lastname;
								self::$cookie->customer_firstname = $customer->firstname;
								self::$cookie->passwd = $customer->passwd;
								self::$cookie->logged = 1;
								self::$cookie->email = $customer->email;
								self::$cookie->is_guest = !Tools::getValue('is_new_customer', 1);
								self::$cookie->group = $customer->id_default_group;
								self::$cookie->caslogin = 0;
								/* Update cart address */
								self::$cart->secure_key = $customer->secure_key;
								self::$cart->id_address_delivery = Address::getFirstCustomerAddressId((int)($customer->id));
								self::$cart->id_address_invoice = Address::getFirstCustomerAddressId((int)($customer->id));
								self::$cart->update();
								Module::hookExec('createAccount', array(
									'_POST' => $_POST,
									'newCustomer' => $customer
								));
								if (Tools::isSubmit('ajax'))
								{
									$return = array(
										'hasError' => !empty($this->errors),
										'errors' => $this->errors,
										'isSaved' => true,
										'id_customer' => (int)self::$cookie->id_customer,
										'id_address_delivery' => self::$cart->id_address_delivery,
										'id_address_invoice' => self::$cart->id_address_invoice,
										'token' => Tools::getToken(false)
									);
									die(Tools::jsonEncode($return));
								}
								if ($back = Tools::getValue('back'))
									Tools::redirect($back);
								Tools::redirect('my-account.php');
							}
						}
					}
				}
			}
			if (sizeof($this->errors))
			{
				if (!Tools::getValue('is_new_customer')) {
					unset($_POST['passwd']);
					self::$cookie->caslogin = 0;
				}
					
				if (Tools::isSubmit('ajax'))
				{
					$return = array(
						'hasError' => !empty($this->errors),
						'errors' => $this->errors,
						'isSaved' => false,
						'id_customer' => 0
					);
					die(Tools::jsonEncode($return));
				}
			}
		}

		if (Tools::isSubmit('SubmitLogin'))
		{
			Module::hookExec('beforeAuthentication');
			$passwd = trim(Tools::getValue('passwd'));
			$email = trim(Tools::getValue('email'));
			if (empty($email))
				$this->errors[] = Tools::displayError('E-mail address required');
			elseif (!Validate::isEmail($email))
				$this->errors[] = Tools::displayError('Invalid e-mail address');
			elseif (empty($passwd))
				$this->errors[] = Tools::displayError('Password is required');
			elseif (Tools::strlen($passwd) > 32)
				$this->errors[] = Tools::displayError('Password is too long');
			elseif (!Validate::isPasswd($passwd))
				$this->errors[] = Tools::displayError('Invalid password');
			else
			{
				$customer = new Customer();
				$authentication = $customer->getByEmail(trim($email), trim($passwd));
				if (!$authentication OR !$customer->id)
				{
					/* Handle brute force attacks */
					sleep(1);
					$this->errors[] = Tools::displayError('Authentication failed');
				}
				else
				{			
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
					self::$cookie->update();
					/* Update cart address */
					self::$cart->id_carrier = 0;
					self::$cart->id_address_delivery = Address::getFirstCustomerAddressId((int)($customer->id));
					self::$cart->id_address_invoice = Address::getFirstCustomerAddressId((int)($customer->id));
					// If a logged guest logs in as a customer, the cart secure key was already set and needs to be updated
					self::$cart->secure_key = $customer->secure_key;
					Module::hookExec('authentication');
					if (!Tools::isSubmit('ajax'))
					{
						if ($back = Tools::getValue('back'))
							Tools::redirect($back);
						Tools::redirect('my-account.php');
					}
				}
			}
			if (Tools::isSubmit('ajax'))
			{
				$return = array(
					'hasError' => !empty($this->errors),
					'errors' => $this->errors,
					'token' => Tools::getToken(false)
				);
				die(Tools::jsonEncode($return));
			}
		}

		if (isset($create_account))
		{
			/* Select the most appropriate country */
			if (isset($_POST['id_country']) AND is_numeric($_POST['id_country']))
				$selectedCountry = (int)($_POST['id_country']);
			/* FIXME : language iso and country iso are not similar,
			 * maybe an associative table with country an language can resolve it,
			 * But for now it's a bug !
			 * @see : bug #6968
			 * @link:http://www.prestashop.com/bug_tracker/view/6968/
			elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
			{
				$array = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
				if (Validate::isLanguageIsoCode($array[0]))
				{
					$selectedCountry = Country::getByIso($array[0]);
					if (!$selectedCountry)
						$selectedCountry = (int)(Configuration::get('PS_COUNTRY_DEFAULT'));
				}
			}*/
			if (!isset($selectedCountry))
				$selectedCountry = (int)(Configuration::get('PS_COUNTRY_DEFAULT'));
			if (Configuration::get('PS_RESTRICT_DELIVERED_COUNTRIES'))
				$countries = Carrier::getDeliveredCountries((int)self::$cookie->id_lang, true, true);
			else
				$countries = Country::getCountries((int)self::$cookie->id_lang, true);


			self::$smarty->assign(array(
				'countries' => $countries,
				'sl_country' => (isset($selectedCountry) ? $selectedCountry : 0),
				'vat_management' => Configuration::get('VATNUMBER_MANAGEMENT')
			));

			/* Call a hook to display more information on form */
			self::$smarty->assign(array(
				'HOOK_CREATE_ACCOUNT_FORM' => Module::hookExec('createAccountForm'),
				'HOOK_CREATE_ACCOUNT_TOP' => Module::hookExec('createAccountTop')
			));
		}

		/* Generate years, months and days */
		if (isset($_POST['years']) AND is_numeric($_POST['years']))
			$selectedYears = (int)($_POST['years']);
		$years = Tools::dateYears();
		if (isset($_POST['months']) AND is_numeric($_POST['months']))
			$selectedMonths = (int)($_POST['months']);
		$months = Tools::dateMonths();

		if (isset($_POST['days']) AND is_numeric($_POST['days']))
			$selectedDays = (int)($_POST['days']);
		$days = Tools::dateDays();

		self::$smarty->assign(array(
			'years' => $years,
			'sl_year' => (isset($selectedYears) ? $selectedYears : 0),
			'months' => $months,
			'sl_month' => (isset($selectedMonths) ? $selectedMonths : 0),
			'days' => $days,
			'sl_day' => (isset($selectedDays) ? $selectedDays : 0)
		));
		self::$smarty->assign('newsletter', (int)Module::getInstanceByName('blocknewsletter')->active);
	}

	public function displayContent()
	{
		$this->processAddressFormat();
		$this->processCasBox();
		parent::displayContent();
		//self::$smarty->display(_PS_THEME_DIR_.'authenticationCAS.tpl');
	}

	protected function processAddressFormat()
	{
		$addressItems = array();
		$addressFormat = AddressFormat::getOrderedAddressFields(Configuration::get('PS_COUNTRY_DEFAULT'), false, true);
		$requireFormFieldsList = AddressFormat::$requireFormFieldsList;

		foreach ($addressFormat as $addressline)
			foreach (explode(' ', $addressline) as $addressItem)
				$addressItems[] = trim($addressItem);

		// Add missing require fields for a new user susbscription form
		foreach($requireFormFieldsList as $fieldName)
			if (!in_array($fieldName, $addressItems))
				$addressItems[] = trim($fieldName);

		foreach (array('inv', 'dlv') as $addressType)
			self::$smarty->assign(array($addressType.'_adr_fields' => $addressFormat, $addressType.'_all_fields' => $addressItems));
	}

	// Add CAS Authentification Box
	protected function processCasBox()
	{ 
		global $link;
		// cas Shibboleth 
		$cas = Db::getInstance()->getRow('SELECT `type`, `shib_login`, 
													`cas_host`, `cas_url`,
													`uri_login`,`cas_service`,`default_create`  FROM `'._DB_PREFIX_.'upps_configuration` 
												WHERE `id_box` = 1');
	
		if ($cas['type'] == 'shib')
			$linkCAS = $cas['shib_login'];
		else
			$linkCAS = Tools::getProtocol(true).$cas['cas_host'].'/'.($cas['cas_url'] ? $cas['cas_url'].'/' : '').
										$cas['uri_login'].'?'.$cas['cas_service'].'='.$link->getPageLink('authentication.php', true);
		
		$ucl = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'upps_configuration_lang`
											WHERE `id_lang` ='.self::$cookie->id_lang);
		self::$smarty->assign(array('request_url' => $linkCAS, 'box'=>$ucl, 'disabled_create_account' => $cas['default_create']));	
	}

	protected function formatFrenchPhoneNumber($phoneNumber, $international = false)
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

