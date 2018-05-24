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
require_once("CAS-1.3.2/CAS.php");

		/**
		 * Class CAS abstraction connection with LDAP or SAML option
		 *
		 * @author	Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
		 * @package	WebService connection abstraction
		 * @subpackage	class.cas_ldap_saml
		 */
        class cas_ldap_saml{
				
				/**
				 * The constructor class
				 *
				 * @param	string		$type: saml or ldap
				 * @param	string		$cas_host: Name, IP or URL of CAS server
				 * @param	string		$cas_port: default 443, CAS server port
				 * @param	string		$cas_context: default '/cas', cas canonical url context
				 * @param	string		$ldap_server: default empty, if type=ldap, IP or name of ldap server
				 * @param	string		$ldap_port: default 389, LDAP serve port
				 * @param	string		$ldap_rootDN: default empty, racine DN of LDAP server : exemple DC=univ-paris1, dc=fr
				 * @return	nothing, just construct class object
				 */
                public function cas_ldap_saml($type,$cas_host,$cas_port=443,$cas_context='/cas/',$ldap_server='',$ldap_port=389,$ldap_rootDN=''){
					
					if (empty($type){
						die("Type is not specified, please complete type by ldap or saml");	
					}else{
						$this->type=$type;
					}
					
					if(empty($cas_host) || empty($cas_port) || empty($cas_context) ){
						die("CAS information is incomplete, please verify and complete CAS informations");	
					}else{
						$this->cas_host=$cas_host;
						$this->cas_port=$cas_port;
						$this->cas_context=$cas_context;	
					}
					
					if($this->type=='cas_ldap'){
						if (empty($ldap_server) || empty($ldap_port) || empty($ldap_rootDN)){
							die("Ldap information is incomplete, plese verify and complete LDAP information");
						}else{
							$this->ldap_server=$ldap_server;
							$this->ldap_port=$ldap_port;
							$this->ldap_rootDN=$ldap_rootDN;
						}
					}
					
					//initialisation CAS Parameters
					$this->enableDebug();
					
					$this->initCas();
					
                }//end of public function cas_ldap_saml
                
                /**
				 * Private method for enabling CAS Debug mode
				 *
				 * @return nothing, just activate debug
				 */
                private function enableDebug(){
        			phpCAS::setDebug();	
                }//end of private function enableDebug()
                
                /**
				 * Private method for create a connection with CAS server
				 *
				 * @return nothing, initialise connection with CAS server
				 */
                private function initCas(){
                	phpCAS::client(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context);
        			phpCAS::setNoCasServerValidation();	
                }//end of public function initCas()
                
                /**
				 * Private method for know if an user is connected
				 *
				 * @return boolean 	if connected=true, else false
				 */
                private function isAuthentified(){
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
                public function getLinkCasServerLogin($returnUrl){
                	if(!$this->isAuthentified()){
                		$retour='<a class="cas_ldap_saml_link" ';
                		$retour.='href="https://'.$this->cas_host.$this->cas_context.'login?service=';
                		$retour.=$returnUrl;
                		$retour.='">[%login%]</a>';	
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
                public function getLinkCasServerLogout($returnUrl){
                	if($this->isAuthentified()){
                		$retour='<a class="cas_ldap_saml_link" ';
                		$retour.='href="https://'.$this->cas_host.$this->cas_context.'logout?service=';
                		$retour.=$returnUrl;
                		$retour.='">[%logout%]</a>';	
                	}else{
                		$retour="<strong>[%not connected%]</strong>";	
                	}
                	return $retour;
                }//end of public function getLinkCasServerLogout()
                
                /**
				 * Method that redirect Header to CAS server, the URL return is URL request
				 *
				 * @return	nothing 	just header is redirected
				 */
                public function getCasAthentification(){
                	if(!$this->isAuthentified()){
                		phpCAS::forceAuthentication();
                	}
                }//end of public function getCasAthentification()
                
                /**
				 * getInformation	Give User information about type specified
				 *	
				 * @return	string 	json initiative array with all user information
				 */
                public function getInformation(){
                	$tabreturn=array();
                	$tabreturn['debugMessage']='OK';
                	if($this->isAuthentified())
	                	if($this->type=="ldap"){
	                		$ds=ldap_connect ($server);
	                		if($ds){
		                        $sr=ldap_bind($ds);
		                        $dn="ou=people,dc=univ-paris1,dc=fr";
		                        $filtre="(uid=".phpCAS::getUser().")";
		                        $restriction = array( "cn", "mail");
		                        
		                        //search string
		                        $ls=ldap_search($ds, $dn, $filtre, $restriction);
	                        	
	                        	//return tab information
	                        	$tabreturn=ldap_get_entries($ds, $ls);
	                           	ldap_close($ds);
	                           	$tabreturn['ok']=true;
	                		}else{
	                        	$tabreturn['ok']=false;
	                        	$tabreturn['debugMessage']='LDAP Connect failled';
	                		}
	                	}
	                	if($this->type=="saml"){
	                		$tabreturn=array();
	                        $tabreturn=phpCAS::getAttributes();
	                        $tabreturn['ok']=true;
	                	}
                	}else{
                		$tabreturn['ok']=false;
	                    $tabreturn['debugMessage']='User not logged in CAS';	
                	}
                	
                	return json_encode($tabretur);
                }//end of public function getInformation()

        }//end of class
?>
