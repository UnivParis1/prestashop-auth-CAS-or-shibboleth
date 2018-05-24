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
 		/**
		 * Class Shibbolleth abstraction connection 
		 *
		 * @author	Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
		 * @package	WebService connection abstraction
		 * @subpackage	class.shibbolleth
		 */
        class shibbolleth{
        	
        	/**
			 * The class constructor
			 *
			 * @param	array	$server: Environement Server Attribute	
			 * @return nothing
			 */
        	public function shibbolleth($server){
        		
                $this->server=$server;
        		
        	}//end of public function shibolleth
        	
        	/**
			 * The get information method
			 *	
			 * @return string	json response with all shib information
			 */
        	public function getInformation(){
        		$tabreturn=array();
                $tabreturn['debugMessage']='';
                $tabreturn['ok']=false;
                foreach ($this->server as $key => $value){
        			$tabreturn[$key]=$value;
        		}
        		$tabreturn['ok']=true;
        		
        		return json_encode($tabreturn);
        	}//end of function getInformation()
        	
        	
        	/**
			 * GetLinkSibLogout : method that return URL for logout shibbolleth
			 *
			 * @param	string 	default empty, URL return of Shib service
			 * @return	string 	HTML code
			 */
        	public function getLinkShibLogout($returnUrl=''){
        		if(empty($returnUrl){
        			$retour='<a class="shibolleth_link" href="/Shibboleth.sso/Logout?return='.$this->server['HTTP_SHIB_LOGOUTURL'];
        			$retour.='%3Freturn%3Dhttps%3A%2F%2Fshib.kuleuven.be%2Flogout.shtml">[%logout%]</a>';
        		}else{
        			$retour='<a class="shibolleth_link" href="/Shibboleth.sso/Logout?return='.$returnUrl;
        			$retour.='%3Freturn%3Dhttps%3A%2F%2Fshib.kuleuven.be%2Flogout.shtml">[%logout%]</a>';
        		}
        		return $retour;
        	}//end of public function getLinkShibLogout
        	
        	/**
			 * GetLinkSibLogin : method that return URL for login shibbolleth
			 *
			 * @param	string 	default empty, URL return of Shib service
			 * @return		string 	HTML code
			 */
        	public function getLinkShibLogin($returnUrl=''){
        		if(empty($returnUrl){
        			$retour='<a class="shibolleth_link" href="/Shibboleth.sso/Logint?return='.$this->server['HTTP_SHIB_LOGINURL'];
        			$retour.='%3Freturn%3Dhttps%3A%2F%2Fshib.kuleuven.be%2Flogout.shtml">[%logout%]</a>';
        		}else{
        			$retour='<a class="shibolleth_link" href="/Shibboleth.sso/Login?return='.$returnUrl;
        			$retour.='%3Freturn%3Dhttps%3A%2F%2Fshib.kuleuven.be%2Flogout.shtml">[%login%]</a>';
        		}
        		return $retour;
        	}//end of public function getLinkShibLogin
        	
        }//end of class 
?> 