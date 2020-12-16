<?php
namespace Subframe;

/**
 * Filesystem functions
 * @package Subframe PHP Framework
 */
class File {

	/**
	 * Ensures a file path is unique, modifying it if needed, creating directories along the path if needed
	 * @param string $destination The full path to the file
	 * @param bool $mkdir Should directories be created if they don't already exist
	 * @return string
	 */
	static public function unique($destination, $mkdir = true) {
		$parts = pathinfo($destination);
		if ($mkdir)
			@mkdir($parts['dirname'], 0777, true)
				and @chmod($parts['dirname'], 0777);
		$parts['basename'] = preg_replace('/\..*/', '', $parts['basename']);
		$parts['basename'] = self::safe($parts['basename']);
		$parts['extension'] = (@$parts['extension'] ? self::safe($parts['extension']) : '');
		$i = "";
		if (preg_match('~^(.*)(-\d+)$~', $parts['basename'], $matches)) {
			$parts['basename'] = $matches[1];
			$i = $matches[2];
		}
		while (file_exists("$parts[dirname]/$parts[basename]$i.$parts[extension]"))
			$i--;
		return "$parts[dirname]/$parts[basename]$i.$parts[extension]";
	}

	/**
	 * Generates safe, ASCII version of a file name
	 * @param string $name The file name (without directories)
	 * @return string
	 */
	static public function safe($name) {
		$name = str_replace(["\xC4\x8C", "\xC4\x86", "\xC5\xA0", "\xC4\x90", "\xC5\xBD"], ["c", "c", "s", "dj", "z"], $name);
		$name = str_replace(["\xC4\x8D", "\xC4\x87", "\xC5\xA1", "\xC4\x91", "\xC5\xBE"], ["c", "c", "s", "dj", "z"], $name);
		$name = str_replace(" ", "-", trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name))));
		return (strlen($name) ? $name : "1");
	}

}
