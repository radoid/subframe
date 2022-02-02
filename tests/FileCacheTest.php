<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase {

	private const DIR = __DIR__.'/files';

	public function testSet() {
		$value = new FileCache(self::DIR);

		$object = 123;
		$this->assertTrue($value->set('integer', $object));
		$this->assertEquals($object, $value->get('integer'));

		$object = 123.456;
		$this->assertTrue($value->set('float', $object));
		$this->assertEquals($object, $value->get('float'));

		$object = true;
		$this->assertTrue($value->set('boolean', $object));
		$this->assertEquals($object, $value->get('boolean'));

		$object = false;
		$this->assertTrue($value->set('boolean', $object));
		$this->assertEquals($object, $value->get('boolean'));

		$object = 'frane';
		$this->assertTrue($value->set('string', $object));
		$this->assertEquals($object, $value->get('string'));

		$object = ['ante', 'frane'];
		$this->assertTrue($value->set('array', $object));
		$this->assertEquals($object, $value->get('array'));

		$object = ['ante' => 'frane'];
		$this->assertTrue($value->set('array', $object));
		$this->assertEquals($object, $value->get('array'));

		$object = (object)['ante' => 'frane'];
		$this->assertTrue($value->set('object', $object));
		$this->assertEquals($object, $value->get('object'));
	}

	public function testGet() {
		$cache = new FileCache(self::DIR);

		$key = 'non-existent-key';
		$default = 'default-value';
		$this->assertFalse($cache->has($key));
		$this->assertEquals($default, $cache->get($key, $default));
	}

	public function testGetExpiryTime() {
		$cache = new FileCache(self::DIR);

		$ttl = 123;
		$time = time();
		$this->assertTrue($cache->set('key', 'value', $ttl));
		$this->assertEquals($time + $ttl, $cache->getExpiryTime('key'));
	}

	public function testDelete() {
		$cache = new FileCache(self::DIR);

		$this->assertTrue($cache->set('key', 'value'));
		$this->assertTrue($cache->has('key'));
		$this->assertTrue($cache->delete('key'));
		$this->assertFalse($cache->has('key'));
	}

	public function testClear() {
		$cache = new FileCache(self::DIR);

		$this->assertTrue($cache->clear());
		foreach (scandir(self::DIR) as $filename)
			$this->assertTrue($filename[0] == '.');
	}

}
