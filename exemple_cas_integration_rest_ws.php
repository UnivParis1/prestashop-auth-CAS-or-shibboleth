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
		 * Exemple for integration class.cas_ldap_saml
		 *
		 * @author	Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
		 * @package	WebService connection abstraction
		 * @subpackage	Exemple of integration CAS in REST WS
		 */
require_once("class.cas_ldap_saml.php");
	
		/**
		 * Implementation CAS
		 *
		 * @return	string	HTML code or JSON String
		 */
	
	switch ($_GET['type']){
		case 'ldap':
			$cas_conn = new cas_ldap_saml('ldap',$_GET['cas_host'],443,'/cas',$_GET[''],389,$_GET['DC=univ-paris1, dc=fr']);
		break;
		case 'saml':
			$cas_conn = new cas_ldap_saml('saml',$_GET['cas_host'],443,'/cas');
		break;
		
		default:
			die("Type unknow");
		break;
	}
	
	switch ($_GET['inf']){
		case 'forceConn':
			 $cas_conn->getCasAthentification();
			 exit;
		break;
		case 'urlLogin'
			echo $cas_conn->getLinkCasServerLogin($_GET['urlReturn']);
		break;
		case 'urlLogout':
			echo $cas_conn->getLinkCasServerLogout($_GET['urlReturn']);
		break;
		case 'info':
			echo $cas_conn->getInformation();
		break;
	}
	
?>