<?php
// Handy constants, not part of the framework
define ('ROOT', $_SERVER['DOCUMENT_ROOT']);
define ('HOME', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

// Minimal auto-loading function, written as a short configuration set
function __autoload($classname) {
	(@include ROOT.HOME."../application/models/$classname.php")
		or (@include ROOT.HOME."../application/libraries/$classname.php")
			or (@include ROOT.HOME."../vendor/" . str_replace('\\', '/', "$classname.php"))
				or trigger_error("Class $classname not found.", E_USER_ERROR);
}

// Minimal front-controller class (the application)
class Application extends Subframe\Controller
{
	function __construct() {
		// Finding a controller-action pair appropriate for the URI
		self::action(null, null,
			ROOT.HOME."../application/controllers/",
			ROOT.HOME."../application/views/");

		// If no appropriate action found, it's a "404 Not Found" situation
		$this->view('error', array('error' => 'Sorry, no content found at this address.'), 404);
	}
}

new Application;
