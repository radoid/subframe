<?php
use PHPUnit\Framework\TestCase;
use Subframe\Container;

class ClassWithoutParams {}

class ClassWithScalarParam {
	public function __construct(public int $param) {}
}

class ClassWithObjectParam {
	public function __construct(public ClassWithoutParams $param) {}
}

class ClassWithMethod {
	public function method() {}
}

class ContainerTest extends TestCase {

	public function testSetObject(): void {
		$object = (object)['key' => 'value'];
		Container::set('name', $object);
		$this->assertSame($object, Container::get('name'));
		Container::unset('name');
		$this->assertNull(Container::get('name'));
	}

	public function testSetFunction(): void {
		$object = (object)['key' => 'value'];
		Container::set('name', fn() => $object);
		$this->assertSame($object, Container::get('name'));
	}

	public function testCannotGetUnknown(): void {
		$this->assertNull(Container::get('unknown'));
	}

	public function testCanMakeClassWithoutParams(): void {
		$instance = Container::make(ClassWithoutParams::class);
		$this->assertInstanceOf(ClassWithoutParams::class, $instance);
	}

	public function testCannotMakeClassWithScalars(): void {
		$this->expectException(Exception::class);
		Container::make(ClassWithScalarParam::class);
	}

	public function testCanMakeClassWithObjects(): void {
		$instance = Container::make(ClassWithObjectParam::class);
		$this->assertInstanceOf(ClassWithObjectParam::class, $instance);
		$this->assertInstanceOf(ClassWithoutParams::class, $instance->param);
	}

	public function testCanMakeClassWithSpecificObjects(): void {
		$specific = new ClassWithoutParams();
		Container::set(ClassWithoutParams::class, $specific);
		$instance = Container::make(ClassWithObjectParam::class);
		$this->assertSame($specific, $instance->param);
		Container::unset(ClassWithoutParams::class);
	}

	public function testCanCallWithoutParams(): void {
		$callable = function () { return 123; };
		$this->assertTrue(Container::canInjectParameters($callable));
		$this->assertEquals(123, Container::call($callable));
	}

	public function testCanCallWithClassParams(): void {
		$callable = function (ClassWithoutParams $param) { return $param; };
		$this->assertTrue(Container::canInjectParameters($callable));
		$this->assertInstanceOf(ClassWithoutParams::class, Container::call($callable));

		$callable = function (ClassWithObjectParam $param) { return $param; };
		$this->assertTrue(Container::canInjectParameters($callable));
		$instance = Container::call($callable);
		$this->assertInstanceOf(ClassWithObjectParam::class, $instance);
		$this->assertInstanceOf(ClassWithoutParams::class, $instance->param);
	}

	public function testCannotCallWithScalarParamsMissing(): void {
		$callable = function (int $param1, ClassWithoutParams $param2) {};
		$this->assertFalse(Container::canInjectParameters($callable));
		$this->expectException(Exception::class);
		Container::call($callable);
	}

	public function testCanCallWithScalarParams(): void {
		$callable = function (int $param1, ClassWithoutParams $param2) { return [$param1, $param2]; };
		$this->assertTrue(Container::canInjectParameters($callable, [123]));
		$result = Container::call($callable, [123]);
		$this->assertEquals(123, $result[0]);
		$this->assertInstanceOf(ClassWithoutParams::class, $result[1]);
	}

	public function testCanCallWithOptionalsSkipped(): void {
		$callable = function (int $param1, ?int $param2 = null) { return [$param1, $param2]; };
		$this->assertTrue(Container::canInjectParameters($callable, [123]));
		$this->assertEquals([123, null], Container::call($callable, [123]));

		$callable = function (int $param1, ?ClassWithoutParams $param2 = null) { return [$param1, $param2]; };
		Container::set(ClassWithoutParams::class, new ClassWithoutParams());
		$this->assertTrue(Container::canInjectParameters($callable, [123]));
		$result = Container::call($callable, [123]);
		$this->assertEquals(123, $result[0]);
		$this->assertInstanceOf(ClassWithoutParams::class, $result[1]);

		Container::unset(ClassWithoutParams::class);
		$this->assertTrue(Container::canInjectParameters($callable, [123]));
		$this->assertEquals([123, null], Container::call($callable, [123]));
	}

	public function testCanInjectParamsIntoFunction(): void {
		$function = function () {};
		$this->assertTrue(Container::canInjectParameters($function));
		$params = Container::injectParameters($function);
		$this->assertIsArray($params);
		$this->assertEmpty($params);
	}

	public function testCanInjectParamsIntoObjectMethod(): void {
		$object = new class { function method() {} };
		$callable = [$object, 'method'];
		$this->assertTrue(Container::canInjectParameters($callable));
		$params = Container::injectParameters($callable);
		$this->assertIsArray($params);
		$this->assertEmpty($params);
	}

	public function testCanInjectParamsIntoClassMethod(): void {
		$callable = [ClassWithMethod::class, 'method'];
		$this->assertTrue(Container::canInjectParameters($callable));
		$params = Container::injectParameters($callable);
		$this->assertIsArray($params);
		$this->assertEmpty($params);
	}

	public function testCannotInjectScalarParams(): void {
		$callable = function (int $param) {};
		$this->assertFalse(Container::canInjectParameters($callable));
		$this->expectException(Exception::class);
		Container::injectParameters($callable);
	}

	public function testCannotInjectStringsAsIntegers(): void {
		$callable = function (int $param) {};
		$this->assertFalse(Container::canInjectParameters($callable, ['example']));
		$this->expectException(Exception::class);
		Container::injectParameters($callable, ['example']);
	}

	public function testCanInjectClassParams(): void {
		$callable = function (ClassWithoutParams $param) { return $param; };
		$this->assertTrue(Container::canInjectParameters($callable));
		$params = Container::injectParameters($callable);
		$this->assertTrue(count($params) == 1);
		$this->assertInstanceOf(ClassWithoutParams::class, $params[0]);
	}

	public function testDoesntInjectOptionalParamsIfNotDefined(): void {
		$callable = function (?ClassWithoutParams $param = null) {};
		$this->assertTrue(Container::canInjectParameters($callable));
		$params = Container::injectParameters($callable);
		$this->assertNull($params[0]);

		$callable = function (int $param1, ClassWithoutParams $param2, ?ClassWithoutParams $param3 = null) {};
		$this->assertTrue(Container::canInjectParameters($callable, [123]));
		$params = Container::injectParameters($callable, [123]);
		$this->assertTrue(count($params) == 3);
		$this->assertEquals(123, $params[0]);
		$this->assertInstanceOf(ClassWithoutParams::class, $params[1]);
		$this->assertNull($params[2]);
	}

	public function testCanInjectClassesBeforeOptionals(): void {
		$callable = function (ClassWithoutParams $param1, ?int $param2 = null) {};
		$this->assertTrue(Container::canInjectParameters($callable, [1]));
		$params = Container::injectParameters($callable);
		$this->assertInstanceOf(ClassWithoutParams::class, $params[0]);
		$this->assertNull($params[1]);
	}

	public function testCannotInjectTooFewParams(): void {
		$callable = function (int $param) {};
		$this->assertFalse(Container::canInjectParameters($callable));
		$this->expectException(Exception::class);
		Container::injectParameters($callable);
	}

	public function testCannotInjectTooManyParams(): void {
		$callable = function () {};
		$this->assertFalse(Container::canInjectParameters($callable, [123]));
		$this->expectException(Exception::class);
		Container::injectParameters($callable, [123]);
	}

}
