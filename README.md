
[Subframe PHP framework](http://radoid.com/subframe/)
=====================================================

Light and lightning fast PHP framework, intended for small and medium-sized websites, built according to MVC and REST principles.
Its main objectives are maximum performance (through minimum server load and plentiful caching) and powerful but minimalistic elegance.

The framework is still under construction. MIT licence.

Requirements
------------

PHP version 7+.

Getting Started
---------------

Subframe is meant to be installed via Composer dependency manager. In the root directory of your project, run a command such as:

	composer require radoid/subframe

You'll probably want to route all the requests through an index.php file, and accomplish that via .htaccess file. In that case you'll need:

	RewriteEngine on
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule (.*)  index.php/$1  [L]

To be able to use any of the classes, you'd include Composer's auto-loading mechanism in your index.php:

	require 'vendor/autoload.php';

Controllers
-----------

Subframe is an MVC framework, passing the flow of execution to "controllers". There are several ways to route incoming requests to appropriate controllers. The simplest one is to use plain PHP closures (functions) and `Controller::route()`.

	// Homepage route
	Controller::route('GET', '/', function () {
		echo '<h1>Welcome to the homepage!</h1>';
	});

Your application will typically have many routes. A convention of ours is, when we succeed in finding the appropriate route for the request, its controller runs and execution stops; otherwise we continue to try more routes. In the very end, if no appropriate route was found, that means the "404 Not Found" situation, and it should be explained to the visitor. An example:

	// Homepage route
	Controller::route('GET', '/', function () {
		echo '<h1>Welcome to the homepage!</h1>';
	});

	// No route was found
	http_request_code('404');
	echo '<p>Sorry, no content at this address.</p>';

In practice, your application will be by far bigger, and you may want to structure your controllers out into classes and their methods, which are here called "actions". You can still dispatch requests via multiple `Controller::route()` calls, specifying classes and methods instead of simple functions like above, but there are alternative ways, explained below. You can also combine those approaches together, by trying them out one after another, since the convention is to stop execution after an appropriate route is found.

If your routes reside as methods (actions) in the same class (controller), you can use `Controller::dispatchInClass()`.

If your routes reside in multiple classes in the same directory, you can use `Controller::dispatchInDirectory()`. In this case, you can structure your controllers further across subdirectories.

If you use namespaces to structure out the controllers, possibly also across subdirectories, you can use `Controller::dispatchInNamespace()`.

Views / Templating
------------------

Front-end files containing HTML can be called "views" or "templates". Subframe comes with no templating engine, only bare functionality based on plain PHP templates displayed via include, providing them with data. Should you rather use a dedicated templating engine of your choice, bring it into the project via Composer.

It is suggested that templates be the only kind of PHP files included by relative paths, and their location be set by `set_include_path()`. Individual templates are output via `Controller::view()`, which takes the template filename and the data, optionally HTTP response code also.

	$this->view('homepage', ['title' => 'Welcome to the homepage']);

For the JSON output of data, there's `Controller::json()`.

	$this->json(['product' => 'Lamp', 'price' => 49.99]);

Models / Database Support
-------------------------

Subframe facilitates fetching data and manipulating them through "models". Models can be thought of as representing SQL database tables; you may want every database table to have its model. There are a number of useful general-purpose functions you inherit from the base Model class. E.g. `Model::get()` and `Model::getAll()` retrieve individual records by their IDs or all the records, respectively. `Model::fetch()` and `Model::fetchAll()` retrieve records for an arbitrary SQL query. All of these functions return objects of the "static" class, the class that was used when invoking, eg:

	$users = User::getAll();  // returns array of User objects

There are some general conventions in working with models. Models declare their fields as public class properties; these correspond to columns in the model's database table. When instantiating an object from a model class, the constructor can be given initialisation data (an associative array or object), which will fill only corresponding properties in the object that are declared. Models also declare their database table and their primary key in public class constants `TABLE` and `KEY`. An example model could be:

	class User extends Subframe\Model {
		public const TABLE = 'users', KEY = 'id';
		public $id, $username, $email, $avatar;
	}

The content of these objects can be accessed through their properties, and also obtained in its entirety as a string via PHP `__toString()` magic method; this string is SQL compatible (all values escaped), and can be used in SQL queries such as INSERT or UPDATE.
