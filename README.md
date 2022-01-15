
[![](https://raw.githubusercontent.com/radoid/subframe/art/subframe-350.png)](https://radoid.com/subframe/)

Subframe PHP Microframework
===========================

Light and lightning fast PHP framework, intended for small and medium-sized websites, built according to MVC and REST principles.
Its main objectives are maximum performance, through minimum server load and plentiful caching, and powerful but minimalistic elegance.


Requirements
------------

PHP version 7.2+


Getting Started
---------------

Subframe is available as a library to be installed via Composer:

	composer require radoid/subframe

Your app will probably be routed through the main `index.php` file. To be able to use Subframe classes, include Composer's auto-loading mechanism in that file, possibly like so:

	require '../vendor/autoload.php';

For `index.php` to be invoked, set up a redirection on your web server. All requests (except those for existing files) should be redirected to that file. In case of Apache web server, this is done using `.htaccess` file, such as here:

	RewriteEngine on
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule .*  index.php

In case of Nginx web server, the same is achieved using the following directive in your site configuration:

	location / {
		try_files $uri $uri/ /index.php?$query_string;
	}


License
-------

The Subframe framework is licensed under the [MIT license](https://en.wikipedia.org/wiki/MIT_License).
