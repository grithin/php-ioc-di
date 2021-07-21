<?php
namespace Bootstrap;


$_ENV['root_folder'] = realpath(dirname(__FILE__).'/../../').'/';
require $_ENV['root_folder'] . '/vendor/autoload.php';

use \Grithin\Debug;

\Grithin\GlobalFunctions::init();

Trait Test{
	public $class;
	/**
	Most Test classes are testing a single class.  As such, setting $class and then calling the method
	via this function provides better reflection on error
	*/
	public function assert_method_result($expect, $input, $method, $message=''){
		$input_as_string = Debug::json_pretty($input);
		$message .= "\tMethod: $method\t\ninput: $input_as_string";
		$output = call_user_func_array([$this->class, $method], $input);
		$this->assertEquals($expect, $output, $message);
	}
	public function assert_exception($closure, $message='no exception produced'){
		try{
			$closure();
		}catch(\Exception $e){
			$this->assertTrue(true);
			return true;
		}
		$this->fail($message);
	}
	public function assert_no_exception($closure, $message='no exception produced'){
		try{
			$return = $closure();
		}catch(\Exception $e){
			$this->fail($message.' : '.$e->getMessage());
			return;
		}
		$this->assertTrue(true);
		return $return;
	}
}