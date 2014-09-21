
[Subframe PHP framework](http://radoid.com/subframe/)
======================

Light and lightning fast PHP framework, intended for small and medium-sized websites built according to MVC and REST principles.
Its main objectives are maximum performance (through minimum server load and plentiful caching) and powerful but minimalistic elegance.

The framework is still under construction. MIT licence.

Requirements
------------

PHP version 5.3+ (support for namespaces and late static binding)

Getting Started
---------------

The suggested folder structure for the website is as follows:

	application
		controllers
		models
		views
		libraries
		tests
	public_html
	uploads
	logs
	cache
	vendor
		Subframe

Framework classes are kept in `vendor/Subframe` folder under namespace `Subframe`, according to the PSR-0.
Other frameworks also belong in the `vendor` folder,
and project-specific classes should be kept in `application/libraries` folder, so they can be loaded automatically.

The framework requires the user to implement autoloading function in the main `index.php` (the "front controller").
A minimal example is included. The simplest `index.php` (for the folder structure suggested above) could be as follows:

	define ('ROOT', $_SERVER['DOCUMENT_ROOT']);
	define ('HOME', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

	// Auto-loading mechanism written as a part of the configuration
	function __autoload($classname) {
		(@include ROOT.HOME."../application/models/$classname.php")
			or (@include ROOT.HOME."../application/libraries/$classname.php")
				or (@include ROOT.HOME."../vendor/" . str_replace('\\', '/', "$classname.php"))
					or trigger_error("Class $classname not found", E_USER_ERROR);
	}

	// Finding a controller and an action appropriate for the URI
	Subframe\Controller::action(null, null, ROOT.HOME.'../application/controllers/', ROOT.HOME.'../views/');

	// If no appropriate action was found, it's a "404 Not Found" situation
	Subframe\Controller::view('error', array('error' => 'Sorry, no content found at this address.'), 404);
