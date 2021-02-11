<?php
use PHPUnit\Framework\TestCase;
use Subframe\OpcodeCache;

class OpcodeCacheTest extends TestCase {

	private const DIR = __DIR__.'/cache';
	private OpcodeCache $cache;

	public function setUp(): void {
		$this->cache = new OpcodeCache(self::DIR);
	}

	public function testSetAndGet(): void {
		$value = 123;
		$this->assertTrue($this->cache->set('integer', $value));
		$this->assertEquals($value, $this->cache->get('integer'));

		$value = 123.456;
		$this->assertTrue($this->cache->set('float', $value));
		$this->assertEquals($value, $this->cache->get('float'));

		$value = true;
		$this->assertTrue($this->cache->set('boolean', $value));
		$this->assertEquals($value, $this->cache->get('boolean'));

		$value = false;
		$this->assertTrue($this->cache->set('boolean', $value));
		$this->assertEquals($value, $this->cache->get('boolean'));

		$value = 'frane';
		$this->assertTrue($this->cache->set('string', $value));
		$this->assertEquals($value, $this->cache->get('string'));

		$value = ['ante', 'frane'];
		$this->assertTrue($this->cache->set('array', $value));
		$this->assertEquals($value, $this->cache->get('array'));

		$value = ['ante' => 'frane'];
		$this->assertTrue($this->cache->set('array', $value));
		$this->assertEquals($value, $this->cache->get('array'));

		$value = (object)['ante' => 'frane'];
		$this->assertTrue($this->cache->set('object', $value));
		$this->assertEquals($value, $this->cache->get('object'));
	}

	public function testItemExpires(): void {
		$content = '234';
		$filename = '2';
		$this->assertTrue($this->cache->set($filename, $content, 1));
		$this->assertEquals($content, $this->cache->get($filename));
		sleep(2);
		$this->assertNull($this->cache->get($filename));
		$this->assertFalse(file_exists(self::DIR . $filename));
	}

	public function testPrune(): void {
		$content = '345';
		$filename = '3';
		$this->assertTrue($this->cache->set($filename, $content, 1));
		$this->assertEquals($content, $this->cache->get($filename));
		sleep(2);
		$this->assertTrue($this->cache->prune($filename));
		$this->assertFalse(file_exists(self::DIR . $filename));
	}

	public function testNonexistentFails(): void {
		$key = 'non-existent-key';
		$this->assertNull($this->cache->get($key));
	}

	public function testDelete(): void {
		$prefix = 'key';
		$key = 'key-extended';

		$this->assertTrue($this->cache->set($key, 'value'));
		$this->assertNotNull($this->cache->get($key));
		$this->assertTrue($this->cache->delete($prefix));
		$this->assertNull($this->cache->get($key));

		$this->assertTrue($this->cache->set($key, 'value'));
		$this->assertNotNull($this->cache->get($key));
		$this->assertTrue($this->cache->delete($key));
		$this->assertNull($this->cache->get($key));
	}

	public function testFlush(): void {
		$this->assertTrue($this->cache->set('key', 'value'));
		$this->assertNotNull($this->cache->get('key'));
		
		$this->assertTrue($this->cache->delete(''));
		foreach (scandir(self::DIR) as $filename)
			$this->assertTrue($filename == '.' || $filename == '..');
	}

}
