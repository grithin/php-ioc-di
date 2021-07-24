<?php
use PHPUnit\Framework\TestCase;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\DependencyInjector;
use \Grithin\ServiceLocator;
use \Grithin\IoC\{NotFound, ContainerException, MissingParam, Datum, Service, Call, Factory};


use \Grithin\GlobalFunctions;

# toggle to silence ppe and pp during debugging
GlobalFunctions::$silence = true;


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
class class10 extends class9{
	function __construct($x){
		$this->x = $x;
	}
}


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

class classD1{# no such classD2
	public function __construct(classD2 $bob){
		$this->bob = $bob;
	}
}
class classE1{# Missing param
	public function __construct($bob){
		$this->bob = $bob;
	}
}



#+ test_method_call, test_data_locator {
interface nInterface1{}
interface nInterface2 extends nInterface1{}

class nClass1 implements nInterface1, nInterface2{
	public $name;
	function __construct(){
		$this->name = 'bob';
	}
	function person(){
		$person = new StdClass;
		$person->name = $this->name;
		return $person;
	}
	static function person_static(){
		$person = new StdClass;
		$person->name = 'sue';
		return $person;
	}
}
#+ }



class Tests extends TestCase{
	use \Grithin\Phpunit\TestTrait;

	function test_ioc(){
		$sl = new ServiceLocator;
		$di = $sl->injector();

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
		$di = $sl->injector();


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
		$di = $sl->injector();

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
		$result = $di->call($closure, ['defaults'=>['bob'=>new Class9]]);
		$this->assertTrue($result['bob'] instanceof class6, 'inject with default override fails');

		# test default necessary
		$closure = function(interface1 $bob){
			return compact('bob');
		};
		$result = $di->call($closure, ['defaults'=>['bob'=>new class1_implements_1_1]]);
		$this->assertTrue($result['bob'] instanceof class1_implements_1_1, 'inject with default override fails');
	}

	function test_di_services(){
		$sl = new ServiceLocator;
		$di = $sl->injector();

		$closure = function(class5 $bob, $sue='sue', $dan='dan'){
			return compact('bob', 'sue', 'dan');
		};
		$sl->bind('class6');
		$result = $di->call_with($closure, ['bob'=>new Service('class9')]);

		$this->assertTrue($result['bob'] instanceof class9, 'service inject with override fails');
	}

	function test_check_all(){
		$sl = new ServiceLocator(['check_all'=>true]);
		$di = $sl->injector();
		$closure = function(interface1 $bob){
			return $bob;
		};
		$result = $di->call($closure);
		$this->assertTrue($result instanceof interface1, 'check all fails to resolve');
	}

	function test_psr11(){
		$sl = new ServiceLocator;
		$di = $sl->injector();
		$closure = function(\Psr\Container\ContainerInterface $x){
			return $x;
		};
		$result = $di->call($closure);
		$this->assertTrue($result instanceof \Grithin\PsrServiceLocator, 'PSR 11 fails injection');
		$this->assertTrue($result->sl instanceof \Grithin\ServiceLocator, 'PSR 11 fails injection');
	}

	function test_bad_type(){
		$sl = new ServiceLocator;
		$di = $sl->injector();
		$sl->bind('classC');

		$closure = function() use ($di){
			return $di->call('classB', ['with'=>['bob'=>'mill']]);
		};
		$result = $this->assert_no_exception($closure);
		$this->assertEquals('bill', $result->bob->bob);
	}

	function test_nonpublic(){
		$sl = new ServiceLocator;
		$di = $sl->injector();
		$closure = function() use ($di){
			$classC = new classC;
			return $di->call([$classC, 'bill']);
		};
		$result = $this->assert_exception($closure, 'no exception', 'Grithin\\IoC\\InjectionCallException');
	}
	function test_missing_param(){
		$sl = new ServiceLocator;
		$di = $sl->injector();
		$closure = function() use ($di){
			return $di->call('classE1');;
		};
		$result = $this->assert_exception($closure, '', MissingParam::class);
	}
	function test_sl_exception(){
		$sl = new ServiceLocator;
		$di = $sl->injector();
		$closure = function() use ($di){
			return $di->call('classD1');;
		};
		$result = $this->assert_exception($closure, '', ContainerException::class);
	}

	function test_singleton(){
		$sl = new ServiceLocator;
		$c1 = new class9;
		$c1->bob = 'bill';
		$sl->singleton(class9::class, $c1);
		$got = $sl->get(class9::class);
		$this->assertEquals('bill', $got->bob);

		# test overwriting
		$c2 = new class9;
		$c2->bob = 'bob';
		$sl->singleton(class9::class, $c2);
		$got = $sl->get(class9::class);
		$this->assertEquals('bob', $got->bob);
	}
	function test_override_options(){
		$sl = new ServiceLocator;
		$sl->bind(class10::class, class10::class, ['with'=>['bill']]);
		$sl->bind(class9::class, class10::class, ['with'=>['bob']]);
		$got = $sl->get(class9::class);
		$this->assertEquals('bob', $got->x);
	}

	function test_special_cases(){
		$sl = new ServiceLocator;

		# test Service.  Although, I can't think of a reason it would be used here
		$sl->bind(class10::class, class10::class, ['with'=>['bill']]);
		$sl->bind(class9::class, new Service(class10::class, ['with'=>['bob']]));
		$got = $sl->get(class9::class);
		$this->assertEquals('bob', $got->x);

		# test Datum fails on no Datum
		$sl->bind(class9::class, new Datum(class10::class), ['with'=>['bob']]);
		$closure = function() use ($sl){
			$got = $sl->get(class9::class);
		};
		$result = $this->assert_exception($closure, '', ContainerException::class);

		# test Datum resolution
		$sl->data_locator->set(class10::class, class10::class);
		$got = $sl->get(class9::class);
		$this->assertEquals('bob', $got->x);

		# test with overwrite
		$sl->bind(class9::class, new Datum(class10::class));
		$got = $sl->get(class9::class);
		$this->assertEquals('bill', $got->x);

		# test Datum injection
		$sl->bind(class10::class, class10::class, ['with'=>[new Datum('name')]]);
		$sl->data_locator->set('name', 'man');
		$got = $sl->get(class9::class);
		$this->assertEquals('man', $got->x);
	}

	function test_method_call(){
		$sl = new ServiceLocator;
		$di = $sl->injector();

		$sl->bind(nInterface1::class, nClass1::class);
		$person = $di->call('nInterface1::person');

		$this->assertTrue(is_object($person));
		$this->assertEquals('bob', $person->name);

		$person = $di->call('nInterface1::person_static');

		$this->assertTrue(is_object($person));
		$this->assertEquals('sue', $person->name);
	}
	function test_call(){
		$sl = new ServiceLocator;
		$di = $sl->injector();

		# resolves interface1 and calls method on resolved
		$sl->bind(nInterface1::class, nClass1::class);
		$sl->bind(nInterface2::class, new Call('nInterface1::person'));
		$person = $sl->get(nInterface2::class);

		$this->assertTrue(is_object($person));
		$this->assertEquals('bob', $person->name);
	}
	function test_data_locator(){
		$sl = new ServiceLocator;
		$dl = $sl->data_locator();
		$di = $sl->injector();

		$sl->bind(nInterface1::class, nClass1::class);

		# test Call datum auto set to lazy
		$dl->set('bob', new Call('nInterface1::person'));
		$person = $dl->get('bob');
		$this->assertTrue(is_object($person));
		$this->assertEquals('bob', $person->name);
		# since lazy, re-call should be same object
		$person->name = 'bill';
		$person = $dl->get('bob');
		$this->assertEquals('bill', $person->name);

		$dl->set_factory('bill', new Factory('nInterface1::person'));
		$person = $dl->get('bill');
		$this->assertEquals('bob', $person->name);

		# should produce a new instance with default name
		$person->name = 'bill';
		$person = $dl->get('bill');
		$this->assertEquals('bob', $person->name);
	}

	function test_upper_case_variable(){
		GlobalFunctions::$silence = false;
		$sl = new ServiceLocator;
		$dl = $sl->data_locator();
		$di = $sl->injector();

		$person = new StdClass;
		$person->name = 'bob';
		$sl->set('Request', $person);
		$closure = function($Request){ return $Request; };
		$person = $di->call($closure);
		$this->assertTrue(is_object($person));
		$this->assertEquals('bob', $person->name);

		# override with data  (prefer data)
		$dl->set('Request', 'bob');
		$person = $di->call($closure);
		$this->assertEquals('bob', $person);

	}
}