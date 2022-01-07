<?php
echo 'start...';

use Subframe\Router;

spl_autoload_register(function ($class) {
	$class = str_replace(['\\', 'Subframe/'], ['/', ''], $class) .'.php';
	@(include __DIR__.'/'.$class)
		or @(include __DIR__.'/../../src/'.$class);
});

Router::fromRequestUri()->route('GET', '/', function () {
	echo 'home';
});
