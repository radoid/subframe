<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class OpcodeCacheTest extends TestCase {

	private const DIR = __DIR__.'/files';

	public function testSet() {
		$cache = new OpcodeCache(self::DIR);

		$value = 123;
		$this->assertTrue($cache->set('integer', $value));
		$this->assertEquals($value, $cache->get('integer'));

		$value = 123.456;
		$this->assertTrue($cache->set('float', $value));
		$this->assertEquals($value, $cache->get('float'));

		$value = true;
		$this->assertTrue($cache->set('boolean', $value));
		$this->assertEquals($value, $cache->get('boolean'));

		$value = false;
		$this->assertTrue($cache->set('boolean', $value));
		$this->assertEquals($value, $cache->get('boolean'));

		$value = 'frane';
		$this->assertTrue($cache->set('string', $value));
		$this->assertEquals($value, $cache->get('string'));

		$value = ['ante', 'frane'];
		$this->assertTrue($cache->set('array', $value));
		$this->assertEquals($value, $cache->get('array'));

		$value = ['ante' => 'frane'];
		$this->assertTrue($cache->set('array', $value));
		$this->assertEquals($value, $cache->get('array'));

		$value = (object)['ante' => 'frane'];
		$this->assertTrue($cache->set('object', $value));
		$this->assertEquals($value, $cache->get('object'));
	}

	public function testGet() {
		$cache = new OpcodeCache(self::DIR);

		$key = 'non-existent-key';
		$default = 'default-value';
		$this->assertFalse($cache->has($key));
		$this->assertEquals($default, $cache->get($key, $default));
	}

	public function testGetExpiryTime() {
		$cache = new OpcodeCache(self::DIR);

		$ttl = 123;
		$time = time();
		$this->assertTrue($cache->set('key', 'value', $ttl));
		$this->assertEquals($time + $ttl, $cache->getExpiryTime('key'));
	}

	public function testDelete() {
		$cache = new OpcodeCache(self::DIR);

		$this->assertTrue($cache->set('key', 'value'));
		$this->assertTrue($cache->has('key'));
		$this->assertTrue($cache->delete('key'));
		$this->assertFalse($cache->has('key'));
	}

	public function testClear() {
		$cache = new OpcodeCache(self::DIR);

		$this->assertTrue($cache->clear());
		foreach (scandir(self::DIR) as $filename)
			$this->assertTrue($filename[0] == '.');
	}

}
