<?php

/* **************************************************
 * Module UppsAuthService                          **
 * @author : Pierre Lefebvre - Nicolas Garabedian  **
 * @society : PrestaTer - SixAndStart              **
 * release : 0.9                                   **
 * last release : 20130206                         **
 * *************************************************/

if (!defined('_PS_VERSION_'))
exit;

class UppsAuthService extends Module
{
	private $_html = '';
	protected $_errors = '';
	protected $_languages = array();
	private $_table = 'upps_configuration';

	function __construct()
	{
		$this->name = 'uppsauthservice';
		$this->tab = 'front_office_features';
		$this->version = '0.9';
		$this->author = 'PrestaTer - Sixandstart';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('UPPS Authentification Service');
		$this->description = $this->l('UPPS CAS or Shibboleth Authentification.');

		$path = dirname(__FILE__);
		if (strpos(__FILE__, 'Module.php') != false)
			$path .= '/../modules/'.$this->name;
		require_once($path.'/classes/cas_ldap_saml_shib.php');
	}
	// Create tables and default values            
	private function _DBCreate() {
		/* Set database */
		if (!Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'upps_configuration` (
						`id_box` int(10) NOT NULL,
						`cas_host` varchar(255) NOT NULL,
						`cas_url` varchar(255) NULL,
						`uri_login` varchar(255) NOT NULL,
						`cas_service` varchar(255) NOT NULL,
						`cas_logout` varchar(6) NOT NULL,
						`ldap_host` varchar(255) NOT NULL,
						`ldap_host2` varchar(255) NULL,
						`base_dn` varchar(255) NOT NULL,
						`people_dn` varchar(255) NULL,
						`struct_dn` varchar(255) NULL,
						`access_dn` varchar(255) NULL,
						`password` varchar(64) NULL,
						`username` varchar(255) NOT NULL,
						`saml_uri` varchar(255) NOT NULL,
						`shib_login` varchar(255) NULL,
						`shib_logout` varchar(255) NULL,
						`return_url` varchar(255) NULL,
						`type` varchar(10) NOT NULL,
						`info` varchar(9) NOT NULL,
						`default_group` int(10)  NULL,
						`default_create` int(1)  NULL,
						PRIMARY KEY (`id_box`)
							) ENGINE='._MYSQL_ENGINE_.'  DEFAULT CHARSET=utf8'))
							return false;

		if (!Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'upps_configuration_lang` (
						`id_box` int(10) NOT NULL,
						`id_lang` int(10) NOT NULL,
						`title` varchar(255)  NULL,
						`body` text NULL,
						`button_label` varchar(255) NULL,
						PRIMARY KEY (`id_box`,`id_lang`)
						) ENGINE='._MYSQL_ENGINE_.'  DEFAULT CHARSET=utf8'))
			return false;

		$data = array('id_box' 		=> 1,
				'cas_host' 		=> pSQL('cas.univ-paris1.fr'),
				'uri_login'		=> pSQL('login'),
				'cas_service'	=> pSQL('service'),
				'cas_logout' 	=> pSQL('logout'),
				'ldap_host' 	=> pSQL('ldap2.univ-paris1.fr'),
				'base_dn' 		=> pSQL('DC=univ-paris1,DC=fr'),
				'username' 		=> pSQL('uid'),
				'saml_uri' 		=> pSQL('samlValidate'),
				'type' 			=> pSQL('ldap'),
				'info'			=> pSQL('urlorinfo'),
				'defaultcreate'  => 0); 


		if (!Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.$this->_table, $data, 'INSERT'))
			return false;	

		$languages = Language::getLanguages(false);
		foreach ($languages as $language)
		{
			$dataLang = array(
					'id_box' 		=> 1,
					'id_lang' 		=> (int)$language['id_lang']);
			if (!Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.$this->_table.'_lang', $dataLang, 'INSERT'))
				return false;	
		}

		return true;    
	}

	// Installation : copying override files
	private function _copyOverrideFiles()
	{
		if (!$this->copyModuleFiles(_PS_MODULE_DIR_.$this->name.'/override/classes/', _PS_ROOT_DIR_.'/override/classes/'))
			return false;
		if (!$this->copyModuleFiles(_PS_MODULE_DIR_.$this->name.'/override/controllers/', _PS_ROOT_DIR_.'/override/controllers/'))
			return false;
		if (!$this->copyModuleFiles(_PS_MODULE_DIR_.$this->name.'/override/tpl/', _PS_THEME_DIR_))
			return false;
		if (!$this->copyModuleFiles(_PS_MODULE_DIR_.$this->name.'/override/css/modules/', _PS_THEME_DIR_.'/css/modules/'))
			return false;
		return true;
	}

	// Uninstall : erase override files
	private function _EraseOverrideFiles()
	{
		unlink(_PS_ROOT_DIR_.'/override/classes/FrontController.php');
		unlink(_PS_ROOT_DIR_.'/override/classes/Cookie.php');

		unlink(_PS_ROOT_DIR_.'/override/controllers/AuthController.php');
		unlink(_PS_ROOT_DIR_.'/override/controllers/AddressController.php');
		unlink(_PS_ROOT_DIR_.'/override/controllers/PasswordController.php');

		if (!$this->eraseModuleFiles(_PS_THEME_DIR_.'css/modules/uppsauthservice/'))
			return false;

		unlink(_PS_THEME_DIR_.'addresses.tpl');
		unlink(_PS_THEME_DIR_.'identity.tpl');
		unlink(_PS_THEME_DIR_.'address.tpl');
		unlink(_PS_THEME_DIR_.'authentication.tpl');

		if (file_exists(_PS_ROOT_DIR_.'/override/classes/BACKUP_FrontController.php'))
			rename(_PS_ROOT_DIR_.'/override/classes/BACKUP_FrontController.php', 
					_PS_ROOT_DIR_.'/override/classes/FrontController.php');


		rename(_PS_THEME_DIR_.'BACKUP_addresses.tpl', _PS_THEME_DIR_.'addresses.tpl');
		rename(_PS_THEME_DIR_.'BACKUP_identity.tpl', _PS_THEME_DIR_.'identity.tpl');
		rename(_PS_THEME_DIR_.'BACKUP_address.tpl', _PS_THEME_DIR_.'address.tpl');
		rename(_PS_THEME_DIR_.'BACKUP_authentication.tpl', _PS_THEME_DIR_.'authentication.tpl');

		return true;
	}

	private function _DBDrop()
	{
		return (Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'upps_configuration`') 
				AND Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'upps_configuration_lang`'));
	}

	public function install()
	{
		if (!parent::install() OR !$this->_DBCreate() OR !$this->_copyOverrideFiles() OR !$this->registerHook('beforeAuthentication')
				OR !$this->registerHook('header'))
			return false;
		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() OR !$this->_DBDrop() OR !$this->_EraseOverrideFiles())
			return false;
		return true;
	}

	public function getContent()
	{
		$this->_html = '<h2>'.$this->displayName.'</h2>';
		$this->_postValidation();
		$this->includeAdminModulesFiles();
		$this->displayTabsConfig();
		return $this->_html;
	}

	private function _postValidation()
	{
		if (Tools::isSubmit('submitUPPSAuthService'))
		{
			$type = pSQL(Tools::getValue('type'));
			$casHost = urlencode(Tools::getValue('casHost'));
			$casURL = pSQL(Tools::getValue('casURL'),false);
			$uriLogin = pSQL(Tools::getValue('uriLogin'),false);
			$casService = pSQL(Tools::getValue('casService'),false);
			$casLogout = pSQL(Tools::getValue('casLogout'),false);
			$ldapHost = urlencode(Tools::getValue('ldapHost'));
			$ldapHost2 = pSQL(Tools::getValue('ldapHost2'),false);
			$baseDN = pSQL(Tools::getValue('baseDN'),false);
			$peopleDN = pSQL(Tools::getValue('peopleDN'),false);
			$structDN = pSQL(Tools::getValue('structDN'),false);
			$accessDN = pSQL(Tools::getValue('accessDN'),false);
			$password = pSQL(Tools::getValue('password'),false);
			$username = pSQL(Tools::getValue('username'),false);
			$samlURI = pSQL(Tools::getValue('samlURI'),false);
			$shibLogin = urlencode(Tools::getValue('shibLogin'));
			$shibLogout = urlencode(Tools::getValue('shibLogout'));
			$returnUrl = urlencode(Tools::getValue('returnUrl'));
			
			$this->_errors = NULL;
			if (!$type OR !Validate::isString($type))
				$this->_errors .= $this->l('Invalid Type of authentification').'<br />';
			if (!$casHost OR !Validate::isURL($casHost))
				$this->_errors .= $this->l('Invalid CAS HOST').'<br />';
			if (!Validate::isString($casURL))
				$this->_errors .= $this->l('Invalid CAS URL').'<br />';
			if (!$uriLogin OR !Validate::isString($uriLogin))
				$this->_errors .= $this->l('Invalid Uri Login').'<br />';
			if (!$casService OR !Validate::isString($casService))
				$this->_errors .= $this->l('Invalid Cas service').'<br />';
			if (!$casLogout OR !Validate::isString($casLogout))
				$this->_errors .= $this->l('Invalid CAS Logout').'<br />';
			if (!$ldapHost OR !Validate::isURL($ldapHost))
				$this->_errors .= $this->l('Invalid LDAP Host').'<br />';
			if (!Validate::isUrlOrEmpty($ldapHost2))
				$this->_errors .= $this->l('Invalid LDAP Host redondance').'<br />';
			if (!$baseDN OR !Validate::isString($baseDN))
				$this->_errors .= $this->l('Invalid base DN').'<br />';
			if (!Validate::isGenericName($peopleDN))
				$this->_errors .= $this->l('Invalid people DN').'<br />';
			if (!Validate::isGenericName($structDN))
				$this->_errors .= $this->l('Invalid structure DN').'<br />';
			if (!Validate::isString($accessDN))
				$this->_errors .= $this->l('Invalid access DN').'<br />';
			if (!Validate::isString($password))
				$this->_errors .= $this->l('Invalid password').'<br />';
			if (!Validate::isString($username))
				$this->_errors .= $this->l('Invalid username').'<br />';
			if (!$samlURI OR !Validate::isString($samlURI))
				$this->_errors .= $this->l('Invalid Saml URI').'<br />';
			if ($shibLogin AND !Validate::isString($shibLogin))
				$this->_errors .= $this->l('Invalid Shibboleth Login').'<br />';
			if ($shibLogout AND !Validate::isString($shibLogout))
				$this->_errors .= $this->l('Invalid Shibboleth Logout').'<br />';
			if (!Validate::isUrlOrEmpty($returnUrl))
				$this->_errors .= $this->l('Invalid Return URL').'<br />';
			
			if ($this->_errors) {
				$this->_errors = $this->l('Errors were found : ').'<br />'.$this->_errors;
				$this->_html .= $this->displayError($this->_errors);            
			}
			else {
				$this->_postProcess();
			}		
		}
		if (Tools::isSubmit('submitUPPSBoxAuthService'))
		{
			$languages = Language::getLanguages(false);
			foreach ($languages as $language) {
				if (strlen(Tools::getValue('box_title_'.$language['id_lang'])) > 255)
					$this->_errors .= $this->l('Title box is too long');
				if (strlen(Tools::getValue('box_body_'.$language['id_lang'])) > 1000)
					$this->_errors .= $this->l('Body box is too long');
				if (strlen(Tools::getValue('box_blabel_'.$language['id_lang'])) > 255)
					$this->_errors .= $this->l('Button label is too long');

			}
			if ($this->_errors)
			{
				$this->_errors = $this->l('Errors were found : ').'<br />'.$this->_errors;
				$this->_html .= $this->displayError($this->_errors);            
			}
			else {
				$this->_postProcess();
			}
		}
		if (Tools::isSubmit('submitGlobalConfiguration'))
		{
			$defgroup = Tools::getValue('defaultgroup');
			$defcreate = (int)(Tools::getValue('defaultcreate') == 'on' ? 1 : 0);
			$this->_errors = NULL;
			if (!$defgroup OR !Validate::isString($defgroup))
				$this->_errors .= $this->l('Invalid name').'<br />';
			elseif (strlen($defgroup) > 33) {
				$this->_errors .= $this->l('This name is too long').'<br />';
			}
			elseif ($defcreate != 0 AND $defcreate != 1) {
				$this->_errors .= $this->l('Disable create account : Invalid value').'<br />';
			}	
			

			if ($this->_errors) {
				$this->_errors = $this->l('Errors were found : ').'<br />'.$this->_errors;
				$this->_html .= $this->displayError($this->_errors);            
			}
			else {
				$this->_postGlobalProcess();
			}		
		}
	}

	private function _postGlobalProcess()
	{	
		global $currentIndex, $cookie;

		$groupName = Tools::getValue('defaultgroup');
		$idGroup = Db::getInstance()->getValue('SELECT * FROM '._DB_PREFIX_.'group_lang WHERE 
				`name` = "'.$groupName.'" AND `id_lang` ='.(int)$cookie->id_lang);

		if (!$idGroup) 
		{
			$objGroup = new Group();
			$objGroup->name[(int)$cookie->id_lang] = $groupName;
			$objGroup->price_display_method = Product::getTaxCalculationMethod();
			$objGroup->add();
			$idGroup = $objGroup->id;
		}

		$arrayGroup = array('default_group' => (int)$idGroup,
							'default_create' => (int)(Tools::getValue('defaultcreate') == 'on' ? 1 : 0));
		$return = Db::getInstance()->autoExecute(_DB_PREFIX_.$this->_table, $arrayGroup, 'UPDATE','`id_box` = 1');

		if (!$return)
			$this->_html .= $this->displayError("An error occured while processing your request");
		else
			$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}

	private function _postProcess()
	{	
		global $currentIndex;
		$languages = Language::getLanguages(false);

		if (Tools::isSubmit('submitUPPSAuthService'))
		{
			$uppsData = array(
					'type' 			=> pSQL(Tools::getValue('type')),
					'cas_host' 		=> urldecode(Tools::getValue('casHost')),
					'cas_url' 		=> pSQL(Tools::getValue('casURL'),false),
					'uri_login' 	=> pSQL(Tools::getValue('uriLogin')),
					'cas_service' 	=> pSQL(Tools::getValue('casService')),
					'cas_logout'	=> pSQL(Tools::getValue('casLogout'),false),
					'ldap_host' 	=> urldecode(Tools::getValue('ldapHost')),
					'ldap_host2'	=> pSQL(Tools::getValue('ldapHost2'),false),
					'base_dn' 		=> pSQL(Tools::getValue('baseDN'),false),
					'people_dn' 	=> pSQL(Tools::getValue('peopleDN'),false),
					'struct_dn' 	=> pSQL(Tools::getValue('structDN'),false),
					'access_dn' 	=> pSQL(Tools::getValue('accessDN'),false),
					'password' 		=> pSQL(Tools::getValue('password'),false),
					'username' 		=> pSQL(Tools::getValue('username'),false),
					'saml_uri' 		=> pSQL(Tools::getValue('samlURI'),false),
					'shib_login'	=> urldecode(Tools::getValue('shibLogin')),
					'shib_logout'	=> urldecode(Tools::getValue('shibLogout')),
					'return_url' 	=> urldecode(Tools::getValue('returnUrl')));

			$return = Db::getInstance()->autoExecute(_DB_PREFIX_.$this->_table, $uppsData, 'UPDATE','`id_box` = 1');			
		}

		if (Tools::isSubmit('submitUPPSBoxAuthService'))
		{
			foreach($languages as $language)
			{

				$uppsDataBox = array(
						'title'		 	=> pSQL(Tools::getValue('box_title_'.$language['id_lang'])),
						'body' 			=> pSQL(Tools::getValue('box_body_'.$language['id_lang'])),
						'button_label' 	=> pSQL(Tools::getValue('box_blabel_'.$language['id_lang'])));

				$return = Db::getInstance()->autoExecute(_DB_PREFIX_.$this->_table.'_lang', $uppsDataBox, 
						'UPDATE','`id_box` = 1 AND `id_lang` = '.(int)$language['id_lang']);					    
			}		
		}
		if (!$return)
			$this->_html .= $this->displayError("An error occured while processing your request");
		elseif (Tools::isSubmit('submitUPPSAuthService'))
			$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
		elseif (Tools::isSubmit('submitUPPSBoxAuthService')) 
			$this->_html .= $this->displayConfirmation($this->l('Box updated'));
	}

	public function displayForm()
	{
		$upps = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'upps_configuration`');
		$this->_html .='
		<script type="text/javascript">
			$(document).ready(function() {
				$("input[type=\'radio\']").each(function() {
					if ($("input[type=\'radio\']):checked").length > 0) {
						if($("input[type=\'radio\']):checked").val() == "saml")
						{
							$("div.ldap").children().hide();
							$("div.ldap").prev().hide();
							$("div.ldap").hide();
							$("div.shib").children().hide();
							$("div.shib").prev().hide();
							$("div.shib").hide();
							$("div.saml").children().show();
							$("div.saml").prev().show();
							$("div.saml").show();
						}
						else if($("input[type=\'radio\']):checked").val() == "ldap")
						{
							$("div.ldap").children().show();
							$("div.ldap").prev().show();
							$("div.ldap").show();
							$("div.shib").children().hide();
							$("div.shib").prev().hide();
							$("div.shib").hide();
							$("div.saml").children().show();
							$("div.saml").prev().show();
							$("div.saml").show();
						}
						else if($("input[type=\'radio\']):checked").val() == "shib")
						{
							$("div.ldap").children().hide();
							$("div.ldap").prev().hide();
							$("div.ldap").hide();
							$("div.shib").children().show();
							$("div.shib").prev().show();
							$("div.shib").show();
							$("div.saml").children().hide();
							$("div.saml").prev().hide();
							$("div.saml").hide();
						}
					}
				});

				$("[type=\'radio\']").on("click", function() {
					if($(this).val() == "saml")
					{
						$("div.ldap").children().hide();
						$("div.ldap").prev().hide();
						$("div.ldap").hide();
						$("div.shib").children().hide();
						$("div.shib").prev().hide();
						$("div.shib").hide();
						$("div.saml").children().show();
						$("div.saml").prev().show();
						$("div.saml").show();
					}
					else if($(this).val() == "ldap")
					{
						$("div.ldap").children().show();
						$("div.ldap").prev().show();
						$("div.ldap").show();
						$("div.shib").children().hide();
						$("div.shib").prev().hide();
						$("div.shib").hide();
						$("div.saml").children().show();
						$("div.saml").prev().show();
						$("div.saml").show();
					}
					else if($(this).val() == "shib")
					{
						$("div.ldap").children().hide();
						$("div.ldap").prev().hide();
						$("div.ldap").hide();
						$("div.shib").children().show();
						$("div.shib").prev().show();
						$("div.shib").show();
						$("div.saml").children().hide();
						$("div.saml").prev().hide();
						$("div.saml").hide();
					}
				});
			});
		</script>
		';
		$this->_html .= '
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset><legend><img src="'.$this->_path.'img/admin/control_panel_access.png" alt="" title="" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('AUTHENTIFICATION USE').'</label>
			<div class="margin-form">
			<input type="radio" name="type" id="type_ldap" value="ldap" '.(Tools::getValue('type', $upps['type']) == 'ldap' ? 'checked="checked" ' : '').'/>
			<label class="t" for="type_ldap">'.$this->l('CAS LDAP').'</label>
			<input type="radio" name="type" id="type_saml" value="saml" '.(Tools::getValue('type', $upps['type']) == 'saml' ? 'checked="checked" ' : '').'/>
			<label class="t" for="type_saml">'.$this->l('CAS SAML').'</label>
			<input type="radio" name="type" id="type_hybrid" value="shib" '.(Tools::getValue('type', $upps['type']) == 'shib' ? 'checked="checked" ' : '').'/>
			<label class="t" for="type_hybrid">'.$this->l('Hybrid').'</label>
			<p class="clear">'.$this->l('Choose your authentification mode').'</p>
			</div>
			<label>'.$this->l('CAS HOST').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="casHost" value="'.Tools::safeOutput(Tools::getValue('casHost',$upps['cas_host'])).'" />
			<p class="clear">'.$this->l('Please, write complete url (nomduserveurCAS.domaine:443)').'</p>
			</div>
			<label>'.$this->l('CAS URL').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="casURL" value="'.Tools::safeOutput(Tools::getValue('casURL',$upps['cas_url'])).'" />
			<p class="clear">'.$this->l('CAS server directory').'</p>
			</div>
			<label>'.$this->l('Uri Login').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="uriLogin" value="'.Tools::safeOutput(Tools::getValue('uriLogin',$upps['uri_login'])).'" />
			<p class="clear">'.$this->l('Uri login. Default : login').'</p>
			</div>
			<label>'.$this->l('CAS Service').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="casService" value="'.Tools::safeOutput(Tools::getValue('casService',$upps['cas_service'])).'" />
			<p class="clear">'.$this->l('CAS service. Default : service').'</p>
			</div>
			<label>'.$this->l('URI Logout').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="casLogout" value="'.Tools::safeOutput(Tools::getValue('casLogout',$upps['cas_logout'])).'" />
			<p class="clear">'.$this->l('Default value : logout').'</p>
			</div>
			<label>'.$this->l('LDAP HOST').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="ldapHost" value="'.Tools::safeOutput(Tools::getValue('ldapHost',$upps['ldap_host'])).'" />
			<p class="clear">'.$this->l('Please write complete url (ldap://nomduserveurldap.domaine:389 ou ldaps://nomduserveurldap.domaine:636)').'</p>
			</div>
			<label>'.$this->l('LDAP 2nde HOST').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="ldapHost2" value="'.Tools::safeOutput(Tools::getValue('ldapHost2',$upps['ldap_host2'])).'" />
			<p class="clear">'.$this->l('Second url in case of redundancy').'</p>
			</div>
			<label>'.$this->l('BASE DN').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="baseDN" value="'.Tools::safeOutput(Tools::getValue('baseDN',$upps['base_dn'])).'" />
			<p class="clear">'.$this->l('Base DN').'</p>
			</div>
			<label>'.$this->l('PEOPLE DN').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="peopleDN" value="'.Tools::safeOutput(Tools::getValue('peopleDN',$upps['people_dn'])).'" />
			<p class="clear">'.$this->l('people DN').'</p>
			</div>
			<label>'.$this->l('STRUCTURES DN').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="structDN" value="'.Tools::safeOutput(Tools::getValue('structDN',$upps['struct_dn'])).'" />
			<p class="clear">'.$this->l('Structures DN').'</p>
			</div>
			<label>'.$this->l('ACCES DN').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="accessDN" value="'.Tools::safeOutput(Tools::getValue('accessDN',$upps['access_dn'])).'" />
			<p class="clear">'.$this->l('Acces DN').'</p>
			</div>
			<label>'.$this->l('PASSWORD').'</label>
			<div class="margin-form ldap">
			<input type="password" size="60" name="password" value="'.Tools::safeOutput(Tools::getValue('password',$upps['password'])).'" />
			<p class="clear">'.$this->l('LDAP Password').'</p>
			</div>
			<label>'.$this->l('USERNAME').'</label>
			<div class="margin-form ldap">
			<input type="text" size="60" name="username" value="'.Tools::safeOutput(Tools::getValue('username',$upps['username'])).'" />
			<p class="clear">'.$this->l('Username').'</p>
			</div>
			<label>'.$this->l('URI SAML').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="samlURI" value="'.Tools::safeOutput(Tools::getValue('samlURI',$upps['saml_uri'])).'" />
			<p class="clear">'.$this->l('SAML url').'</p>
			</div>
			<label>'.$this->l('SHIBBOLETH URL LOGIN').'</label>
			<div class="margin-form shib">
			<input type="text" size="60" name="shibLogin" value="'.Tools::safeOutput(Tools::getValue('shibLogin',$upps['shib_login'])).'" />
			<p class="clear">'.$this->l('Shibboleth login url').'</p>
			</div>
			<label>'.$this->l('SHIBBOLETH URL LOGOUT').'</label>
			<div class="margin-form shib">
			<input type="text" size="60" name="shibLogout" value="'.Tools::safeOutput(Tools::getValue('shibLogout',$upps['shib_logout'])).'" />
			<p class="clear">'.$this->l('Shibboleth logout url').'</p>
			</div>
			<label>'.$this->l('RETURN URL').'</label>
			<div class="margin-form saml">
			<input type="text" size="60" name="returnUrl" value="'.Tools::safeOutput(Tools::getValue('returnUrl',$upps['return_url'])).'" />
			<p class="clear">'.$this->l('copy this url :https://nomduserveur.domaine/authentification?back=my-account.php').'</p>
			</div>
			<center><input type="submit" name="submitUPPSAuthService" value="'.$this->l('Save').'" class="button_large" /></center>
			</fieldset>
			</form>';
		return $this->_html;
	}

	public function displayBoxForm()
	{
		global $cookie;
		/* Languages preliminaries */
		$defaultLanguage = (int)(Configuration::get('PS_LANG_DEFAULT'));
		$languages = Language::getLanguages(false);
		$iso = Language::getIsoById((int)($cookie->id_lang));
		$divLangName = 'title¤cpara¤button_label';

		$ucl = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'upps_configuration_lang`');
		foreach ($ucl as $box) {
			$upps['title'][(int)$box['id_lang']] = $box['title'];
			$upps['body'][(int)$box['id_lang']] = $box['body'];
			$upps['button_label'][(int)$box['id_lang']] = $box['button_label'];
		}

		// TinyMCE
		if (_PS_VERSION_ >= "1.4.1.0") {
			$isoTinyMCE = (file_exists(_PS_ROOT_DIR_ . '/js/tiny_mce/langs/' . $iso . '.js') ? $iso : 'en');
			$ad = dirname($_SERVER ["PHP_SELF"]);
			$this->_html .= '<script type="text/javascript">
				var iso = \'' . $isoTinyMCE . '\' ;
			var pathCSS = \'' . _THEME_CSS_DIR_ . '\' ;
			var ad = \'' . $ad . '\' ;
			</script>';
			$this->_html .= '<script type="text/javascript" src="' . __PS_BASE_URI__ . 'js/tiny_mce/tiny_mce.js"></script>';
		}
		else {
			$this->_html .= '<script type="text/javascript" src="' . __PS_BASE_URI__ . 'js/tinymce/jscripts/tiny_mce/tiny_mce.js"></script>';
		}

		$this->_html .= '
			<script type="text/javascript">id_language = Number('.$defaultLanguage.');</script>
			<form method="post" action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'#tabs_2">
			<fieldset style="width: 905px;">
			<legend><img src="'.$this->_path.'img/admin/application_form_edit.png" alt="" title="" /> '.$this->l('Box Settings').'</legend>
			<label>'.$this->l('Header box title').'</label>
			<div class="margin-form">';

		foreach ($languages as $language)
		{
			$this->_html .= '
				<div id="title_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $defaultLanguage ? 'block' : 'none').';float: left;">
				<input type="text" name="box_title_'.$language['id_lang'].'" id="box_title_'.$language['id_lang'].'" size="64" value="'.(isset($upps['title'][$language['id_lang']]) ? $upps['title'][$language['id_lang']] : '').'" />
				</div>';
		}
		$this->_html .= $this->displayFlags($languages, $defaultLanguage, $divLangName, 'title', true);


		$this->_html .= '
			<p class="clear">'.$this->l('Title of the box').'</p>
			</div>
			<label>'.$this->l('Body').'</label>
			<div class="margin-form">';

		foreach ($languages as $language)
		{
			$this->_html .= '
				<div id="cpara_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $defaultLanguage ? 'block' : 'none').';float: left;">
				<textarea class="rte" cols="70" rows="10" id="box_body_'.$language['id_lang'].'" name="box_body_'.$language['id_lang'].'">'.(isset($upps['body'][$language['id_lang']]) ? $upps['body'][$language['id_lang']] : '').'</textarea>
				</div>';
		}
		$this->_html .= $this->displayFlags($languages, $defaultLanguage, $divLangName, 'body', true);

		$this->_html .= '
			<p class="clear">'.$this->l('Text of the box').'</p>
			</div>
			<label>'.$this->l('Label of connection button').'</label>
			<div class="margin-form">';

		foreach ($languages as $language)
		{
			$this->_html .= '
				<div id="box_blabel_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $defaultLanguage ? 'block' : 'none').';float: left;">
				<input type="text" name="box_blabel_'.$language['id_lang'].'" id="box_blabel_'.$language['id_lang'].'" size="64" value="'.(isset($upps['button_label'][$language['id_lang']]) ? $upps['button_label'][$language['id_lang']] : '').'" />
				</div>';
		}

		$this->_html .= $this->displayFlags($languages, $defaultLanguage, $divLangName, 'button_label', true);

		$this->_html .= '
			<p class="clear">'.$this->l('Label of button').'</p>
			</div>
			<center><input type="submit" name="submitUPPSBoxAuthService" value="'.$this->l('Update the box').'" class="button_large" /></center>
			</fieldset>
			</form>';
	}

	public function displayGlobalForm()
	{
		global $cookie;

		$defaultValue = Db::getInstance()->getRow('SELECT `default_group`, `default_create` FROM `'._DB_PREFIX_.'upps_configuration`');
		
		if (isset($defaultValue['default_group']) && !empty($defaultValue['default_group']))
			$group = new Group($defaultValue['default_group']);

		$this->_html .= '
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'#tabs_3" method="post">
			<fieldset><legend><img src="'.$this->_path.'img/admin/control_panel_access.png" alt="" title="" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('Default group ').'</label>
			<div class="margin-form">
			<input type="text" size="30" name="defaultgroup" value="'.Tools::safeOutput(Tools::getValue('defaultgroup',(isset($group) ? $group->name[$cookie->id_lang] : ''))).'" />
			<p class="clear">'.$this->l('Please, write default group for CAS members').'</p>
			</div>
			<label>'.$this->l('Disable default create account ').'</label>
			<div class="margin-form">
			<input type="checkbox" name="defaultcreate" value="on" '.((int)$defaultValue['default_create'] == 1 ? 'checked="checked" ' : '').'/>
			<p class="clear">'.$this->l('Hide default create account').'</p>
			</div>
			
			<center><input type="submit" name="submitGlobalConfiguration" value="'.$this->l('Save').'" class="button_large" /></center>
			</fieldset>
			</form>';
		return $this->_html;
	}

	private function includeAdminModulesFiles() {
		$defaultLanguage = intval(Configuration::get('PS_LANG_DEFAULT'));

		if (_PS_VERSION_ >= 1.4) {
			$this->_html .= '
				<link type="text/css" rel="stylesheet" href="' . $this->_path . 'js/jqueryui/1.8.9/themes/smoothness/jquery-ui-1.8.9.custom.css" />
				<script type="text/javascript" src="' . $this->_path . 'js/jqueryui/1.8.9/jquery-ui-1.8.9.custom.min.js"></script>
				';
		}
		else {
			$this->_html .= '<link type="text/css" rel="stylesheet" href="' . $this->_path . 'js/jqueryui/themes/default/ui.all.css" />
				<script type="text/javascript" src="' . $this->_path . 'js/ui.core.min.js"></script>
				<script type="text/javascript" src="' . $this->_path . 'js/ui.tabs.min.js"></script>
				';
		}


		$this->_html .= '
			<link type="text/css" rel="stylesheet" href="' . $this->_path . 'css/admin.css" />
			<script type="text/javascript" src="' . $this->_path . 'js/admin.js"></script>';


	}	
	private function displayTabsConfig() {
		$this->_html .= '
			<div id="wrapConfigTab">
			<ul style="height: 48px;" id="configTab">
			<li><a href="#tabs_1"><span><img src="' . $this->_path . 'img/admin/interface_preferences.png" /> ' . $this->l('URL Configuration') . '</span></a></li>
			<li><a href="#tabs_2"><span><img src="' . $this->_path . 'img/admin/form.png" /> ' . $this->l('Box Configuration') . '</span></a></li>
			<li><a href="#tabs_3"><span><img src="' . $this->_path . 'img/admin/interface_preferences.png" /> ' . $this->l('Global configuration') . '</span></a></li>
			</ul>';
		$this->_html .= '<div id="tabs_1">';
		$this->displayForm();
		$this->_html .= '</div>';
		$this->_html .= '<div id="tabs_2">';
		$this->displayBoxForm();
		$this->_html .= '</div>';
		$this->_html .= '<div id="tabs_3">';
		$this->displayGlobalForm();
		$this->_html .= '</div>
			</div>';

	}

	public function hookHeader()
	{
		Tools::addCSS(_THEME_CSS_DIR_.'modules/'.$this->name.'/authentication.css', 'all');

	}


	public function hookBeforeAuthentication($params)
	{
		//global $cookie;
		// Get informations to use WebService
		$upps = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'upps_configuration`');

		$type = trim($upps['type']);
		$cas_host = trim($upps['cas_host']);
		$ldap_server = trim($upps['ldap_host']);
		$route_dn = trim($upps['base_dn']);
		$inf = trim($upps['info']);
		$url_return = urlencode($upps['return_url']);
		$ldap_user = trim($upps['access_dn']);
		$ldap_passwd = $upps['password'];
		if($type=="ldap" || $type=="saml"){
			//echo "type ldap ou saml"; 
			//file Debug pour voir ce que PHPCAS retourne
			$fileDebug='/webhome/prestacas/html/modules/uppsauthservice/errorCas.log';	

			//instance de la classe avec les bons parametres, les 2 derniers permette d activer le debug et de specifier le fichier de log
			$cas_conn = new cas_ldap_saml_shib($type, $cas_host, 443, '/cas/',$ldap_server,389,$route_dn,$ldap_user,$ldap_passwd,false,$fileDebug);

			//test authentification CAS
			if($cas_conn->isAuthentified()){
				$jsonInfo = $cas_conn->getInformation();
				$arrayData = Tools::jsonDecode($jsonInfo);
				if ($arrayData->ok)
				{
					return $jsonInfo;

				}
				elseif ($arrayData->linkCasServer && !$arrayData->ok)
				{
					$cookie->isLogged = false;
				}
				else {
					//test en développement
					die($arrayData->debugMessage);
				}
			}
		}elseif($type=="shib"){
			//shibb managment
			$cas_conn = new cas_ldap_saml_shib($type);
			if($cas_conn->shibconnected()){
				$jsonInfo = $cas_conn->getInformation();
				$arrayData = Tools::jsonDecode($jsonInfo);
				if ($arrayData->ok)
				{
					return $jsonInfo;

				}elseif(!$arrayData->ok){
					$cookie->isLogged = false;
				}else{
					//test en développement
					die($arrayData->debugMessage);
				}
			}else{
				//die($arrayData->debugMessage);
				//echo ('Le retour de ISP n a pas fourni d information de connexion');
			}
		}else{
			die("Le type de connexion est non definie! contacter votre administrateur");
		}

	}

	/*************************************************************
	 ***************** Others functions ***************************
	 *************************************************************/

	/* copying directory and files and backup overrided files   */
	private function copyModuleFiles($src, $dst) 
	{
		$dir = opendir($src);
		$result = ($dir === false ? false : true);
		if ($result !== false)
		{
			if ($result === true)
			{
				while(false !== ( $file = readdir($dir)) ) 
				{ 
					if (( $file != '.' ) && ( $file != '..' ) && $result) 
					{ 
						if ( is_dir($src . '/' . $file) ) 
						{ 
							mkdir($dst. '/' . $file);
							$result = $this->copyModuleFiles($src . '/' . $file,$dst . '/' . $file); 
						} 
						else 
						{ 
							if (file_exists($dst . '/'. $file) && ($file != 'index.php') && !file_exists($dst . '/BACKUP_'. $file))
								rename($dst . '/'. $file, $dst . '/BACKUP_'. $file);
							$result = copy($src . '/' . $file,$dst . '/' . $file); 
						} 
					} 
				} 
				closedir($dir);
			}
		}
		return $result;
	}

	/* Erase directory and files */
	private function eraseModuleFiles($dir) 
	{
		$opendir = @opendir($dir);
		if (!$opendir) return;
		while($file = readdir($opendir)) 
		{
			if ($file == '.' || $file == '..') continue;
			if (is_dir($dir."/".$file)) 
			{
				$r = $this->eraseModuleFiles($dir."/".$file);
				if (!$r) return false;
			}
			else 
			{
				$r = @unlink($dir."/".$file);
				if (!$r) return false;
			}
		}
		closedir($opendir);
		$r= @rmdir($dir);
		if (!$r) return false;
		return true;
	}
}

?>
