<?php
use PHPUnit\Framework\TestCase;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\DependencyInjector;
use \Grithin\ServiceLocator;
use \Grithin\IoC\NotFound;
use \Grithin\IoC\Service;

use \Grithin\GlobalFunctions;

# toggle to silence ppe and pp during debugging
# GlobalFunctions::$silence = true;


interface interface1{}
interface interface1_1 extends interface1{}

interface interface2{}
interface interface2_1 extends interface2{}

interface interface3{}
interface interface3_1 extends interface3{}

class class1{}
class class1_implements_1 implements interface1{};
class class1_implements_1_1 implements interface1_1{}



class class2_construct{
	public function __construct($bob='bob'){
		$this->bob = $bob;
	}
}

class class3_construct_implements_1_1 implements interface1_1{
	public function __construct(interface2 $bob){
		$this->bob = $bob;
	}
}
class class3_construct_implements_2_1 implements interface2_1{}


class class4_construct{
	public function __construct(interface1_1 $sue, interface2 $bob){
		$this->sue = $sue;
		$this->bob = $bob;
	}
}

abstract class class5{}
class class6 extends class5{}
class class7_construct{
	public function __construct(class5 $bob){
		$this->bob = $bob;
	}
}
class class9 extends class5{}


function function1(class7_construct $bob){
	return [$bob];
}
class class8{
	public function function1(class7_construct $bob){
		return function1($bob);
	}
	static public function function2(class7_construct $bob){
		return function1($bob);
	}
}

class classB{
	public function __construct(classC $bob){
		$this->bob = $bob;
	}
}
class classC{
	public function __construct($bob='bill'){
		$this->bob = $bob;
	}
	protected function bill(){}
}


class Tests extends TestCase{
	use Bootstrap\Test;

	function test_ioc(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();

		$sl->bind('class3_construct_implements_2_1');


		/*
		This should
		-	maintain default for param bob
		*/
		$result = $di->class_construct('class2_construct');
		$this->assertEquals('bob', $result->bob, 'param default not maintained');



		/*
		This should:
		-	use class_construct
		-	see class3_construct_implements_1_1::__construct requires (interface2 bob)
		-	check services for anything that implements interface2
		-	see that class3_construct_implements_2_1 implements interface2_1, which extends interface2
		-	construct class3_construct_implements_2_1 for requirement
		-	fill class3_construct_implements_1_1::__construct(interface2 bob) with class3_construct_implements_2_1 instance
		-	return class3_construct_implements_1_1 with bob as class3_construct_implements_2_1
		*/
		$result = $di->call('class3_construct_implements_1_1');
		$this->assertTrue($result instanceof class3_construct_implements_1_1, 'incorrect return from di->call');
		$this->assertTrue($result->bob instanceof class3_construct_implements_2_1, 'incorrect injection from di->call');


		/*
		This should
		-	...
		-	see param $sue requires interface1_1, which is now available in service class3_construct_implements_1_1 (which was added automatically).
		-	class3_construct_implements_1_1 should then be constructed, filling `sue`
		*/
		$sl->bind('class3_construct_implements_1_1');
		$result = $di->call('class4_construct');
		$this->assertTrue($result instanceof class4_construct, 'incorrect return from di->call');
		$this->assertTrue($result->sue instanceof class3_construct_implements_1_1, 'incorrect injection from di->call');
		$this->assertTrue($result->bob instanceof class3_construct_implements_2_1, 'incorrect injection from di->call');

		/*
		This should
		-	resolve the abstract class for the constructor
		*/
		$sl->bind('class6');
		$result = $di->call('class7_construct');
		$this->assertTrue($result instanceof class7_construct, 'incorrect return from di->call');
		$this->assertTrue($result->bob instanceof class6, 'incorrect injection from di->call');
	}

	function test_di_calls(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();


		# test function with IoC injection
		$sl->bind('class7_construct');
		$sl->bind('class6');
		$return = $di->call('function1');
		$this->assertTrue(is_array($return) && $return[0] instanceof class7_construct, 'function using di->call fail');

		# test closure
		$closure = function(class7_construct $bob){
			return [$bob];
		};
		$return = $di->call($closure);
		$this->assertTrue(is_array($return) && $return[0] instanceof class7_construct, 'closure using di->call fail');

		# test method
		$class8 = new class8;
		$return = $di->call([$class8, 'function1']);
		$this->assertTrue(is_array($return) && $return[0] instanceof class7_construct, 'method using di->call fail');

		# test static method by string
		$return = $di->call('class8::function2');
		$this->assertTrue(is_array($return) && $return[0] instanceof class7_construct, 'static method by string using di->call fail');

		# test static method by array
		$return = $di->call(['class8','function2']);
		$this->assertTrue(is_array($return) && $return[0] instanceof class7_construct, 'static method by array using di->call fail');
	}

	function test_di_inject_with(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();

		$closure = function(class5 $bob, $sue='sue', $dan='dan'){
			return compact('bob', 'sue', 'dan');
		};

		$sl->bind('class6');

		# test override
		$result = $di->call_with($closure, ['bob'=>new Class9, 2=>'bob']);
		$this->assertTrue($result['bob'] instanceof class9, 'inject with override fails');
		$this->assertEquals('sue', $result['sue'], 'param default not maintained');
		$this->assertEquals('bob', $result['dan'], 'param positional override fails');

		# test default unnecessary
		$result = $di->call($closure, ['default'=>['bob'=>new Class9]]);
		$this->assertTrue($result['bob'] instanceof class6, 'inject with default override fails');

		# test default necessary
		$closure = function(interface1 $bob){
			return compact('bob');
		};
		$result = $di->call($closure, ['default'=>['bob'=>new class1_implements_1_1]]);
		$this->assertTrue($result['bob'] instanceof class1_implements_1_1, 'inject with default override fails');
	}

	function test_di_services(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();

		$closure = function(class5 $bob, $sue='sue', $dan='dan'){
			return compact('bob', 'sue', 'dan');
		};
		$sl->bind('class6');
		$result = $di->call_with($closure, ['bob'=>new Service('class9')]);
		$this->assertTrue($result['bob'] instanceof class9, 'service inject with override fails');
	}

	function test_check_all(){
		$sl = new ServiceLocator(['check_all'=>true]);
		$di = $sl->injector_get();
		$closure = function(interface1 $bob){
			return $bob;
		};
		$result = $di->call($closure);
		$this->assertTrue($result instanceof interface1, 'check all fails to resolve');
	}

	function test_psr11(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();
		$closure = function(\Psr\Container\ContainerInterface $x){
			return $x;
		};
		$result = $di->call($closure);
		$this->assertTrue($result instanceof \Grithin\PsrServiceLocator, 'PSR 11 fails injection');
		$this->assertTrue($result->sl instanceof \Grithin\ServiceLocator, 'PSR 11 fails injection');
	}

	function test_bad_type(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();
		$sl->bind('classC');

		$closure = function() use ($di){
			return $di->call('classB', ['with'=>['bob'=>'mill']]);
		};
		$result = $this->assert_no_exception($closure);
		$this->assertEquals('bill', $result->bob->bob);
	}

	function test_nonpublic(){
		$sl = new ServiceLocator;
		$di = $sl->injector_get();
		$closure = function() use ($di){
			$classC = new classC;
			return $di->call([$classC, 'bill']);
		};
		$result = $this->assert_exception($closure, 'no exception', 'Grithin\\IoC\\InjectionCallException');
	}
}