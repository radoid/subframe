<?php
// Handy constants, not part of the framework
define ('ROOT', $_SERVER['DOCUMENT_ROOT']);
define ('HOME', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

// Minimal auto-loading function, written as a short configuration set
function __autoload($classname) {
	$filename = strtr($classname, ['_' => '/', '\\' => '/']).'.php';
	(@include ROOT.HOME."../application/models/$filename")
		or (@include ROOT.HOME."../application/libraries/$filename")
			or (@include ROOT.HOME."../vendor/$filename")
				or trigger_error("Class $classname not found.", E_USER_ERROR);
}

// Minimal front-controller class (the application)
class Application extends Subframe\Controller
{
	function init() {
		// Try to find a controller-action pair appropriate for the URI
		self::action($this->controllers, $this->views);

		// If no appropriate action has been found, it's a "404 Not Found" situation
		$this->view('error', ['error' => 'Sorry, no content has been found at this address.'], 404);
	}
}

new Application(
		ROOT.HOME."../application/controllers/",
		ROOT.HOME."../application/views/");
