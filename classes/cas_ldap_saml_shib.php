<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
 *  All rights reserved
 *
 * You can redistribute this file and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation;
 * either version 2 of the License, or (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This file is distributed in the hope that it will be useful for ministry,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the file!
 ***************************************************************/
/**
 * 
 *
 * 
 */
require_once("lib/CAS.php");
/**
 * Class CAS abstraction connection with LDAP or SAML option
 *
 * @author	Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
 * @package	WebService connection abstraction
 * @subpackage	class.cas_ldap_saml
 */
class cas_ldap_saml_shib
{
	/** @param	string		$type: saml or ldap **/
	public $type;
	/** @param	string		$cas_host: Name, IP or URL of CAS server **/
	public $cas_host;
	/** @param	integer		$cas_port: default 443, CAS server port **/
	public $cas_port;
	/** @param	string		$cas_context: default '/cas', cas canonical url context **/
	public $cas_context;
	/** @param	string		$ldap_server: default empty, if type=ldap, IP or name of ldap server **/
	public $ldap_server;
	/** @param	integer		$ldap_port: default 389, LDAP serve port **/
	public $ldap_port;
	/** @param	string		$ldap_rootDN: default empty, racine DN of LDAP server : exemple DC=univ-paris1, dc=fr **/
	public $ldap_rootDN;

	protected $filedsRequired = array('type', 'cas_host');

	protected $fieldsValidate = array('type' => 'isString',
			'$cas_host' => 'isString',
			'cas_port' => 'isInt',
			'cas_context' => 'isString',
			'ldap_server' => 'isUrlOrEmpty',
			'ldap_port' => 'isInt',
			'ldap_rootDN' => 'isString');

	/** @return	nothing, just construct class object **/
	public function cas_ldap_saml_shib($type='', $cas_host='', $cas_port=443, $cas_context='/cas/',
			$ldap_server='', $ldap_port=389, $ldap_rootDN='', $ldap_user='', $ldap_pwd='',$debug=false,$filedebug='') 
	{
		//echo "classe instancie  pour: ".$type."<br>";
		if (empty($type)){
			die("Type is not specified, please complete type by ldap or saml");	
		}else{
			$this->type=$type;
		}

		if($this->type=="ldap" || $this->type=="saml"){
			if(empty($cas_host) || empty($cas_port) || empty($cas_context) ){
				die("CAS information is incomplete, please verify and complete CAS informations");	
			}else{
				$this->cas_host=$cas_host;
				$this->cas_port=intval($cas_port);
				$this->cas_context=$cas_context;	
			}
		}

		if($this->type=='ldap'){
			if (empty($ldap_server) || empty($ldap_port) || empty($ldap_rootDN)){
				die("Ldap information is incomplete, plese verify and complete LDAP information");
			}else{
				$this->ldap_server=$ldap_server;
				$this->ldap_port=$ldap_port;
				$this->ldap_rootDN=$ldap_rootDN;
				$this->ldap_user=$ldap_user;
				$this->ldap_pwd=$ldap_pwd;
			}
		}
		if($this->type=="ldap" || $this->type=="saml"){
			//initialisation CAS Parameters
			if($debug) $this->enableDebug($filedebug);
			$this->initCas();
		}

		if($this->type=="shib"){
			//print_r($_SERVER);
			$this->server=$_SERVER;
			if(!empty($this->server['HTTP_REMOTE_USER'])){
				$this->shibUser=$this->server['HTTP_REMOTE_USER'];
			}else{
				$this->shibUser=false;
			}
		}

	}

	/**
	 * Private method for enabling CAS Debug mode
	 *
	 * @return nothing, just activate debug
	 */
	public function enableDebug($filename='')
	{
		phpCAS::setDebug($filename);	
	}//end of private function enableDebug()

	/**
	 * Private method for create a connection with CAS server
	 *
	 * @return nothing, initialise connection with CAS server
	 */
	public function initCas()
	{
		if ($this->type=='saml')
		{
			phpCAS::client(SAML_VERSION_1_1, $this->cas_host, $this->cas_port, $this->cas_context);
		}
		else
		{
			phpCAS::client(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context);
		}
		phpCAS::setNoCasServerValidation();	
	}//end of public function initCas()

	/**
	 * Private method for create a connection with CAS server
	 *
	 * @return nothing, initialise connection with CAS server
	 */
	public function initCasProxy()
	{
		if ($this->type=='saml')
		{
			phpCAS::proxy(SAML_VERSION_1_1, $this->cas_host, $this->cas_port, $this->cas_context);
		}
		else
		{
			phpCAS::proxy(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context);
		}
		phpCAS::setNoCasServerValidation();	
	}//end of public function initCas()

	/**
	 * Private method for know if an user is connected
	 *
	 * @return boolean 	if connected=true, else false
	 */
	public function isAuthentified()
	{
		//echo "is authentified is call <br>";
		if(phpCAS::isAuthenticated()){
			return true;	
		}else{
			return false;
		}	
	}//end of private function isAuthentified()

	/**
	 * getLinkCasServerLogin : method that return URL for login CAS server
	 *
	 * @param	string 	default empty, URL return of CAS service
	 * @return	string HTML code Url with label %xxx%
	 */
	public function getLinkCasServerLogin($returnUrl)
	{
		//echo "getLinkCasServerLogin is call <br>";
		if(!$this->isAuthentified()){
			$retour='https://'.$this->cas_host.$this->cas_context.'login?service=';
			$retour.=urlencode($returnUrl);
		}else{
			$retour="<strong>[%conected%]</strong>";	
		}
		return $retour;
	}//end of public function getLinkCasServer()

	/**
	 * getLinkCasServerLogout : method that return URL for logout CAS server
	 *
	 * @param	string 	default empty, URL return of CAS service
	 * @return	string HTML code Url with label %xxx%
	 */
	public function getLinkCasServerLogout($returnUrl)
	{
		//echo "getLinkCasServerLogout is call <br>";
		if($this->isAuthentified()){
			$retour='https://'.$this->cas_host.$this->cas_context.'logout?service=';
			if($returnUrl) $retour.=urlencode($returnUrl);
		}else{
			$retour="<strong>[%not connected%]</strong>";	
		}
		return $retour;
	}//end of public function getLinkCasServerLogout()

	public function shibconnected(){

		if($this->shibUser){
			return true;
		}else{
			return false;
		}
	}//end of public function shibconnected() 

	/**
	 * Method that redirect Header to CAS server, the URL return is URL request
	 *
	 * @return	nothing 	just header is redirected
	 */
	public function getCasAthentification()
	{
		//echo "getCasAthentification is call <br>";
		if(!$this->isAuthentified()){
			phpCAS::forceAuthentication();
		}
	}//end of public function getCasAthentification()

	/**
	 * getInformation	Give User information about type specified
	 *	
	 * @return	string 	json initiative array with all user information
	 */
	public function getInformation($urlLogOutReturn='')
	{
		$tabreturn=array();
		$tabreturn['debugMessage']='OK';
		if($this->type=="shib"){

			if(!empty($this->server['HTTP_SUPANNCIVILITE'])){
				$tabreturn['supannCivilite']=$this->server['HTTP_SUPANNCIVILITE'];
			}else{
				$tabreturn['supannCivilite']=9;
			}

			if(!empty($this->server['HTTP_GIVENNAME'])){
				$tabreturn['givenName']=$this->server['HTTP_GIVENNAME'];
			}elseif(!empty($this->server['HTTP_DISPLAYNAME'])){
				$tabreturn['givenName']=$this->server['HTTP_DISPLAYNAME'];
			}else{
				$tabreturn['givenName']='';
			}

			if(!empty($this->server['HTTP_SN'])){
				$tabreturn['sn']=$this->server['HTTP_SN'];
			}elseif(!empty($this->server['HTTP_DISPLAYNAME'])){
				$tabreturn['sn']=$this->server['HTTP_DISPLAYNAME'];
			}else{
				$tabreturn['sn']='';
			}

			$tabreturn['mail']=$this->server['HTTP_MAIL'];

			$tabreturn['passwd']=uniqid();

			if(!empty($this->server['HTTP_UP1BIRTHDAY'])){
				$tabreturn['Up1Birthday']=$this->server['HTTP_UP1BIRTHDAY'];
			}else{
				$tabreturn['Up1Birthday']='';
			}

			if(!empty($this->server['HTTP_TELEPHONENUMBER'])){
				$tabreturn['telephoneNumber']=$this->server['HTTP_TELEPHONENUMBER'];
			}else{
				$tabreturn['telephoneNumber']='+00 0 00 00 00 00';
			}

			if(!empty($this->server['HTTP_PAGER'])){
				$tabreturn['mobile']=$this->server['HTTP_PAGER'];

			}else{
				$tabreturn['mobile']='+00 0 00 00 00 00';

			}

			if(!empty($this->server['HTTP_POSTALADDRESS'])){
				$tabreturn['postalAddress']=$this->server['HTTP_POSTALADDRESS'];
			}else{
				$tabreturn['postalAddress']='';
			}

			if(!empty($this->server['HTTP_SUPANNETABLISSEMENT'])){
				$tabreturn['company']=$this->server['HTTP_SUPANNETABLISSEMENT'];
			}else{
				$tabreturn['company']='';
			}
			/***
			if(is_array($tabAttr['memberOf']) && !empty($tabAttr['memberOf']) ){
				foreach($tabAttr['memberOf'] as $chain){
					preg_match('#cn=(.*),#U',$chain,$result);
					if($result[1]) $tabgroup[]=$result[1];
				}
				$listGroup=implode(",",$tabgroup);
			}else{
				$listGroup='nobody';	
			}
			***/
			$listGroup='nobody';

			$tabreturn['groups']=$listGroup;
			$tabreturn['ok']=true;
			//$tabreturn['logoutUrl']=$this->getLinkCasServerLogout($urlLogOutReturn);

		}else{
			if($this->isAuthentified()){
				if($this->type=="ldap"){
					$ds=ldap_connect ($this->ldap_server);
					if($ds){

						// $ldapbind = @ldap_bind($ldapconn, $ldaprdn, $ldappass);
						$sr=ldap_bind($ds,$this->ldap_user,$this->ldap_pwd);
						$dn=$this->ldap_rootDN;
						$filtre="(uid=".phpCAS::getUser().")";

						//search string
						$restriction = array("supanncivilite","givenname","sn","mail","telephonenumber","up1birthday","pager","postaladdress","supannEtablissement","memberof");
						$ls=ldap_search($ds, $dn, $filtre,$restriction);
						//return tab information

						$tabAttr=ldap_get_entries($ds, $ls);

						/***
						  if( empty($tabAttr[0]['givenName'][0]) || empty($tabAttr[0]['sn'][0]) || empty($tabAttr[0]['mail'][0]) ){
						  $tabreturn['ok']=false;
						  $tabreturn['debugMessage']='User is logged, but information is incomplete';
						  }
						 ***/

						if(!empty($tabAttr[0]['supanncivilite'][0])){
							$tabreturn['supannCivilite']=$tabAttr[0]['supanncivilite'][0];
						}else{
							$tabreturn['supannCivilite']=9;
						}

						$tabreturn['givenName']=$tabAttr[0]['givenname'][0];
						$tabreturn['sn']=$tabAttr[0]['sn'][0];
						$tabreturn['mail']=$tabAttr[0]['mail'][0];
						$tabreturn['passwd']=uniqid();

						if(!empty($tabAttr[0]['up1birthday'][0])){
							$tabreturn['Up1Birthday']=$tabAttr[0]['up1birthday'][0];
						}else{
							$tabreturn['Up1Birthday']='';
						}

						if(!empty($tabAttr[0]['telephonenumber'][0])){
							$tabreturn['telephoneNumber']=$tabAttr[0]['telephonenumber'][0];
						}else{
							$tabreturn['telephoneNumber']='+00 0 00 00 00 00';
						}

						if(!empty($tabAttr[0]['pager'][0])){
							$tabreturn['mobile']=$tabAttr[0]['pager'][0];
						}else{
							$tabreturn['mobile']='+00 0 00 00 00 00';
						}

						if( !empty($tabAttr[0]['postaladdress'][0]) ){
							$tabreturn['postalAddress']=$tabAttr[0]['postaladdress'][0];
						}else{
							$tabreturn['postalAddress']='';
						}

						if(!empty($tabAttr[0]['supannetablissement'][0])){
							$tabreturn['company']=$tabAttr[0]['supannetablissement'][0];
						}else{
							$tabreturn['company']='';
						}

						if(!empty($tabAttr[0]['memberof'])){
							if(is_array($tabAttr[0]['memberof'])){
								foreach ($tabAttr[0]['memberof'] as $chain){
									preg_match('#cn=(.*),#U',$chain,$result);
									if(!empty($result[1])) $tabgroup[]=$result[1];
								}
								$listGroup=implode(",",$tabgroup);
							}else{
								$listGroup='nobody';
							}
						}else{
							$listGroup='nobody';
						}
						$tabreturn['groups']=$listGroup;

						ldap_close($ds);
						$tabreturn['ok']=true;
						$tabreturn['logoutUrl']=$this->getLinkCasServerLogout($urlLogOutReturn);
					}else{
						$tabreturn['ok']=false;
						$tabreturn['debugMessage']='LDAP Connect failled';
					}
				}
				if($this->type=="saml"){

					$tabAttr=phpCAS::getAttributes();

					/***
					  if(empty($tabAttr['givenName']) || empty($tabAttr['sn']) || empty($tabAttr['mail'])){
					  $tabreturn['ok']=false;
					  $tabreturn['debugMessage']='User is logged, but information is incomplete';
					  }
					 ***/
					if(!empty($tabAttr['supanncivilite'])){
						$tabreturn['supannCivilite']=$tabAttr['supanncivilite'];
					}else{
						$tabreturn['supannCivilite']=9;
					}


					$tabreturn['givenName']=$tabAttr['givenName'];
					$tabreturn['sn']=$tabAttr['sn'];
					$tabreturn['mail']=$tabAttr['mail'];

					$tabreturn['passwd']=uniqid();

					if(!empty($tabAttr['up1BirthDay'])){
						$tabreturn['Up1Birthday']=$tabAttr['up1BirthDay'];
					}else{
						$tabreturn['Up1Birthday']='';
					}

					if(!empty($tabAttr['telephoneNumber'])){
						$tabreturn['telephoneNumber']=$tabAttr['telephoneNumber'];
					}else{
						$tabreturn['telephoneNumber']='+00 0 00 00 00 00';
					}

					if(!empty($tabAttr['pager'])){
						$tabreturn['mobile']=$tabAttr['pager'];

					}else{
						$tabreturn['mobile']='+00 0 00 00 00 00';

					}
					if(!empty($tabAttr['postalAddress'])){
						$tabreturn['postalAddress']=$tabAttr['postalAddress'];
					}else{
						$tabreturn['postalAddress']='';
					}

					if(!empty($tabAttr['supannEtablissement'])){
						$tabreturn['company']=$tabAttr['supannEtablissement'];
					}else{
						$tabreturn['company']='';
					}

					if(is_array($tabAttr['memberOf']) && !empty($tabAttr['memberOf']) ){
						foreach($tabAttr['memberOf'] as $chain){
							preg_match('#cn=(.*),#U',$chain,$result);
							if($result[1]) $tabgroup[]=$result[1];
						}
						$listGroup=implode(",",$tabgroup);
					}else{
						$listGroup='nobody';	
					}
					$tabreturn['groups']=$listGroup;
					$tabreturn['ok']=true;
					$tabreturn['logoutUrl']=$this->getLinkCasServerLogout($urlLogOutReturn);
				}
			}else{
				$tabreturn['ok']=false;
				$tabreturn['debugMessage']='User not logged in CAS';	
			}
		}

		return json_encode($tabreturn);
	}//end of public function getInformation()

	/**
	 * getUrlOrIn	Give User information if user is logged in CAS or return HTML Code for URL of CAS
	 *	
	 * @return	string 	json initiative array with all user information or HTML URL Login Code 
	 */
	public function getUrlOrInf($urlReturn='')
	{
		if($this->isAuthentified()){
			return $this->getInformation();
		}else{
			if(!empty($urlReturn)){
				$tabreturn['tonurl']=urlencode($urlReturn);
				$tabreturn['linkCasServer']=$this->getLinkCasServerLogin($urlReturn);
				$tabreturn['ok']=false;
				$tabreturn['debugMessage']='User not logged in CAS';
				return json_encode($tabreturn);
			}else{
				$this->getCasAthentification();
			}
		}
	}//end of public function getUrlorInf

}//end of class
?>
