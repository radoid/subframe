<?php

use Subframe\File;

class FileTest extends \PHPUnit\Framework\TestCase {

	private const ROOT = __DIR__.'/files';

	public function setUp() {
		@unlink(self::ROOT.'/a/b/c/d.txt');
		@rmdir(self::ROOT.'/a/b/c');
		@rmdir(self::ROOT.'/a/b');
		@rmdir(self::ROOT.'/a');
	}

	public function testUnique() {
		$mode = 0777;
		$path = self::ROOT.'/a/b/c/d.txt';

		$unique1 = File::unique($path, $mode);
		$this->assertEquals($path, $unique1);
		$this->assertEquals($mode, $mode & fileperms(self::ROOT.'/a/b/c'));

		touch($unique1);
		$this->assertTrue(file_exists($unique1));

		$unique2 = File::unique($path, $mode);
		$this->assertNotEquals($path, $unique2);

		touch($unique2);
		$unique3 = File::unique($path, $mode);
		$this->assertNotEquals($unique1, $unique3);
		$this->assertNotEquals($unique2, $unique3);
	}

	public function testUniqueUri() {
		$mode = 0777;
		$uri = '/a/b/c/d.txt';

		$unique1 = File::uniqueUri(self::ROOT, $uri, $mode);
		$this->assertEquals($uri, $unique1);
		$this->assertEquals($mode, $mode & fileperms(self::ROOT.'/a/b/c'));

		touch(self::ROOT.$unique1);
		$this->assertTrue(file_exists(self::ROOT.$unique1));

		$unique2 = File::uniqueUri(self::ROOT, $uri, $mode);
		$this->assertNotEquals($uri, $unique2);

		touch(self::ROOT.$unique2);
		$unique3 = File::uniqueUri(self::ROOT, $uri, $mode);
		$this->assertNotEquals($unique1, $unique3);
		$this->assertNotEquals($unique2, $unique3);
	}

}
