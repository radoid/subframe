<?php
// Handy constants, not part of the framework
define ('ROOT', $_SERVER['DOCUMENT_ROOT']);
define ('HOME', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

// Minimal auto-loading function, written as a short configuration set
function __autoload($classname) {
	(@include ROOT.HOME."../application/models/$classname.php")
		or (@include ROOT.HOME."../application/libraries/$classname.php")
			or (@include ROOT.HOME."../vendor/" . str_replace('\\', '/', "$classname.php"))
				or trigger_error("Class $classname not found", E_USER_ERROR);
}

// Finding a controller and an action appropriate for the URI
Subframe\Controller::action(null, null, ROOT.HOME."../application/controllers/", '/Users/ante/Sites/subframe/v5.3/application/views/');//ROOT.HOME."../views/");

// If no appropriate action found, it's a "404 Not Found" error
Subframe\Controller::view('error', array('error' => 'Sorry, no content found at this address!'), 404);
