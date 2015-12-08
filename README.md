
[Subframe PHP framework](http://radoid.com/subframe/)
=====================================================

Light and lightning fast PHP framework, intended for small and medium-sized websites, built according to MVC and REST principles.
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

Framework classes are kept in `vendor/Subframe` folder, under namespace `Subframe`.
Other frameworks also belong in the same `vendor` folder,
and project-specific classes are suggested to be kept in `application/libraries` folder, and also be loaded automatically.

The framework requires the developer to hand-craft the class-loading `__autoload()` function in the main `index.php` file,
which is intended to be read as a configuration set.

A minimal example is included. The simplest `index.php` (for the folder structure as suggested above) could be as follows:

	<?php
	define ('ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/');

	// Auto-loading mechanism written as a kind of a configuration set
	function __autoload($classname) {
		(@include ROOT."../application/models/$classname.php")
		or (@include ROOT."../application/libraries/$classname.php")
		or (@include ROOT."../vendor/" . str_replace('\\', '/', "$classname.php"))
		or trigger_error("Class $classname not found", E_USER_ERROR);
	}

	// Finding a controller and an action appropriate for the URI
	Subframe\Controller::action(null, null,
			ROOT.'../application/controllers/',
			ROOT.'../application/views/');

	// If no appropriate action was found, it's a "404 Not Found" situation
	header('HTTP/1.1 404 Not Found');
	echo 'Sorry, no content found at this address.';
