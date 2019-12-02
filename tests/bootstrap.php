<?php
spl_autoload_register(function ($class) {
	foreach (['Subframe\\' => __DIR__.'/../src/', 'Website\\' => __DIR__.'/routes/Website/'] as $prefix => $dir)
		if (strpos($class, $prefix) === 0) {
			$path = strtr($class, [$prefix => $dir, '\\' => '/']) . '.php';
			if (@include_once($path))
				break;
		}
});
