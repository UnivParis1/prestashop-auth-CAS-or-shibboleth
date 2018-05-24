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
		 * Exemple for integration class.shibboleth
		 *
		 * @author	Nicolas Garabedian <nicolas.garabedian@sixandstart.fr>
		 * @package	WebService connection abstraction
		 * @subpackage	Exemple of integration SHIB in REST WS
		 */
require_once("class.shibboleth.php");
	
		/**
		 * Implementation SHIB
		 *
		 * @return	string	HTML code or JSON String
		 */
	
	$shib_conn = new shibbolleth($_SERVER);
	
	switch ($_GET['inf']){
		case 'urlLogin'
			echo $shib_conn->getLinkShibLogin($_GET['urlReturn']);
		break;
		case 'urlLogout':
			echo $shib_conn->getLinkShibLogout($_GET['urlReturn']);
		break;
		case 'info':
			echo $shib_conn->getInformation();
		break;
	}
	
?>