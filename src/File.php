<?php
namespace Subframe;

/**
 * Filesystem functions
 * @package Subframe PHP Framework
 */
class File {

	/**
	 * Ensures a file path is unique, by appending a numeric suffix if needed.
	 * Optionally, creates directories along the given path, if needed.
	 * @param string $filepath The full desired path to the file
	 * @param int $mkdirMode Optional permissions for the directories, should they be created (if non-zero)
	 * @return string
	 */
	static public function unique($filepath, $mkdirMode = 0777) {
		$parts = pathinfo($filepath);
		$dir = $parts['dirname'];
		$base = $parts['filename'];
		$ext = $parts['extension'] ? ".$parts[extension]" : '';
		if ($mkdirMode)
			@mkdir($dir, $mkdirMode, true)
				and @chmod($dir, $mkdirMode);
		for ($i = ''; file_exists("$dir/$base$i$ext"); $i--);

		return "$dir/$base$i$ext";
	}

	/**
	 * Finds a unique file path, relative to a base directory, by appending a numeric suffix when needed..
	 * Optionally, creates directories along the given path, if needed.
	 * @param string $root The base directory
	 * @param string $filepath The desired path to the file, relative to the base directory
	 * @param int $mkdirMode Optional permissions for the directories, should they be created (if non-zero)
	 * @return string
	 */
	static public function uniqueUri(string $root, string $uri, $mkdirMode = 0777) {
		$root = rtrim($root, '/');
		$parts = pathinfo($uri);
		$base = "$parts[dirname]/$parts[filename]";
		$ext = $parts['extension'] ? ".$parts[extension]" : '';
		$dir = $root.'/'.ltrim($parts['dirname'], '/');
		if ($mkdirMode)
			@mkdir($dir, $mkdirMode, true)
				and @chmod($dir, $mkdirMode);
		for ($i = ''; file_exists($root.'/'.ltrim("$base$i$ext", '/')); $i--);

		return "$base$i$ext";
	}

	/**
	 * Generates safe, ASCII version of a file name
	 * @param string $name The file name (without directories)
	 * @return string
	 */
	static public function safe($name) {
		$name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
		$name = strlen($name) ? $name : '1';

		return $name;
	}

}
