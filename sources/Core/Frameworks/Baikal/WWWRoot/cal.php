<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Jérôme Schneider <mail@jeromeschneider.fr>
*  All rights reserved
*
*  http://baikal-server.com
*
*  This script is part of the Baïkal Server project. The Baïkal
*  Server project is free software; you can redistribute it
*  and/or modify it under the terms of the GNU General Public
*  License as published by the Free Software Foundation; either
*  version 2 of the License, or (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

ini_set("session.cookie_httponly", 1);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

define("BAIKAL_CONTEXT", TRUE);
define("PROJECT_CONTEXT_BASEURI", "/");

if(file_exists(getcwd() . "/Core")) {
	# Flat FTP mode
	define("PROJECT_PATH_ROOT", getcwd() . "/");	#./
} else {
	# Dedicated server mode
	define("PROJECT_PATH_ROOT", dirname(getcwd()) . "/");	#../
}

if(!file_exists(PROJECT_PATH_ROOT . 'vendor/')) {
	die('<h1>Incomplete installation</h1><p>Ba&iuml;kal dependencies have not been installed. Please, execute "<strong>composer install</strong>" in the folder where you installed Ba&iuml;kal.');
}

require PROJECT_PATH_ROOT . 'vendor/autoload.php';

# Bootstraping Flake
\Flake\Framework::bootstrap();
# Bootstrapping Baïkal
\Baikal\Framework::bootstrap();

if(!defined("BAIKAL_CAL_ENABLED") || BAIKAL_CAL_ENABLED !== TRUE) {
	throw new ErrorException("Baikal CalDAV is disabled.", 0, 255, __FILE__, __LINE__);
}

# Backends
if( BAIKAL_DAV_AUTH_TYPE == "Digest" && !preg_match('/Windows-Phone-WebDAV-Client/i', $_SERVER['HTTP_USER_AGENT']))
    $authBackend = new \Sabre\DAV\Auth\Backend\PDO($GLOBALS["DB"]->getPDO());
else {
    switch (strtoupper(BAIKAL_DAV_AUTH_TYPE)) {
         case "MAIL":
              $authBackend = new \Baikal\Core\MailAuth($GLOBALS["DB"]->getPDO(), BAIKAL_AUTH_REALM);
              break;
         case "LDAP-USERBIND":
              $authBackend = new \Baikal\Core\LDAPUserBindAuth($GLOBALS["DB"]->getPDO(), BAIKAL_AUTH_REALM); 
              break;
         default:
              $authBackend = new \Baikal\Core\PDOBasicAuth($GLOBALS["DB"]->getPDO(), BAIKAL_AUTH_REALM);
    }
}

$principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($GLOBALS["DB"]->getPDO());
$calendarBackend = new \Sabre\CalDAV\Backend\PDO($GLOBALS["DB"]->getPDO());

# Directory structure
$nodes = array(
    new \Sabre\CalDAV\Principal\Collection($principalBackend),
    new \Sabre\CalDAV\CalendarRootNode($principalBackend, $calendarBackend),
);

# Initializing server
$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri(BAIKAL_CAL_BASEURI);

# Server Plugins
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, BAIKAL_AUTH_REALM));
$server->addPlugin(new \Sabre\DAVACL\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Plugin());

# And off we go!
$server->exec();
