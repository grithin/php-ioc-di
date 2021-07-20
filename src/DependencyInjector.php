<?php
namespace Grithin;

/**
Consideration

Say we have some parameter (App $app).  There are three scenarios of injection:
1.	we want to directly inject, not relying on a service locator
2.	we want to auto inject, using a service locator
3.	we want to inject something only if the service locator did not find a service
`default` options allows the 3rd and `with` option allows the 1st.

if a parameter is optional, this could mean:
1.	code wants a dependency but shouldn't fail if it doesn't have it
2.	code wants to use it's own value unless it is forced to use provided value
In the case of #2, it is likely that the parameter is not typed to something DI will inject, and so, DI can attempt it anyway without problem (it will end up not injecting anything)

*/


class DependencyInjector{
	/** params
	< getter > < the callable that gets unresolved dependencies >
	< options >
		service_resolve: < if a parameter ends up being of type Grithin\IoC\Service, resolve it >
	*/
	public function __construct($getter=null, $options=[]){
		if(!$getter){
			$getter = [$this, 'getter_default'];
		}
		$this->getter_set($getter);

		$defaults = ['service_resolve' => false];
		$options = array_merge($defaults, $options);
		$this->service_resolve = $options['service_resolve'];
	}
	public function getter_set($getter){
		if(!is_callable(($getter))){
			throw new \Exception('Getter is not callable');
		}
		$this->getter = $getter;
	}
	public function get($key){
		return ($this->getter)($key);
	}
	public function getter_default($key){
		if(class_exists($key)){
			return new $key;
		}
		return new \Exception;
	}
	public function parameter_by_type($param){
		$type = $param->getType();
		if(!$type){
			return new \Exception;
		}
		if($type instanceof ReflectionUnionType){
			# just get the first type of a union
			$type = $type->getTypes()[0];
		}
		if($type->isBuiltin()){ # don't try to resolve a Builtin type
			return new \Exception;
		}
		$type_name = $type->getName();
		return $this->get($type_name);
	}

	/** call with positional and named parameters */
	public function call_with($thing, $with, $options=[]){
		$options['with'] = $with;
		return $this->call($thing, $options);
	}

	/** call a thing with DI */
	# see parameters_resolve for $options
	public function call($thing, $options=[]){
		if(is_string($thing)){
			if(class_exists($thing)){
				return $this->class_construct($thing, $options);
			}
			if(function_exists($thing)){
				return $this->function_call($thing, $options);
			}
			$parts = explode('::', $thing);
			if(count($parts) == 2){
				if(class_exists($parts[0]) && method_exists($parts[0], $parts[1])){
					return $this->static_method_call($parts[0], $parts[1], $options);
				}
			}
		}elseif($thing instanceof \Closure){
			return $this->function_call($thing, $options);
		}elseif(is_object($thing)){
			if(method_exists($thing, '__invoke')){
				return $this->method_call($thing, '__invoke', $options);
			}else{
				throw new \Exception('object uncallable');
			}
		}elseif(is_array($thing)){
			return $this->method_call($thing[0], $thing[1], $options);
		}
		throw new \Exception('uncallable');

	}

	public function class_construct($class, $options=[]){
		$options = array_merge($options, ['false_on_missing'=>true]);
		$params = $this->class_resolve_parameters($class, $options);
		if($params === false){
			throw new \Exception('missing parameter');
		}
		$reflect = new \ReflectionClass($class);
		return $reflect->newInstanceArgs($params);
	}

	# see parameters_resolve for $options
	public function class_resolve_parameters($class, $options=[]){
		$class = new \ReflectionClass($class);
		$reflect = $class->getConstructor();
		if(!$reflect){
			return [];
		}
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}
	public function static_method_call($class, $method, $options=[]){
		$options = array_merge($options, ['false_on_missing'=>true]);
		$params = $this->method_resolve_parameters($class, $method, $options);
		if($params === false){
			throw new \Exception('missing parameter');
		}
		$reflect = new \ReflectionMethod($class, $method);
		return $reflect->invokeArgs(null, $params);
	}
	public function method_call($object, $method, $options=[]){
		#+ allow handling of static methods {
		if(!is_object($object)){
			return $this->static_method_call($object, $method, $options);
		}
		#+ }
		$options = array_merge($options, ['false_on_missing'=>true]);
		$params = $this->method_resolve_parameters($object, $method, $options);
		if($params === false){
			throw new \Exception('missing parameter');
		}
		$reflect = new \ReflectionMethod($object, $method);
		return $reflect->invokeArgs($object, $params);
	}
	# see parameters_resolve for $options
	public function method_resolve_parameters($class, $method, $options=[]){
		$reflect = new \ReflectionMethod($class, $method);
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}
	public function function_call($function, $options=[]){
		$options = array_merge($options, ['false_on_missing'=>true]);
		$params = $this->function_resolve_parameters($function, $options);
		if($params === false){
			throw new \Exception('missing parameter');
		}
		$reflect = new \ReflectionFunction($function);
		return $reflect->invokeArgs($params);
	}

	# see parameters_resolve for $options
	public function function_resolve_parameters($function, $options=[]){
		$reflect = new \ReflectionFunction($function);
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}

	/**
	< options >
		false_on_missing: < t: bool > < whether to return false if there is a missing parameter >
		with: < dictionary of parameters to inject by position or name, ahead of type declaration injection >;
		default:  < dictionary of parameters to inject by position or name, if type declaration fails >;
	*/
	public function parameters_resolve($params, $options){
		$defaults = ['default'=>[], 'with'=>[], 'false_on_missing'=>false];

		extract(array_merge($defaults, $options));

		$params_to_inject = [];
		foreach($params as $k=>$param){
			#+ handle injecting name or positional specific parameters {
			if(isset($with[$k])){
				$params_to_inject[$k] = $with[$k];
				continue;
			}else{
				$name = $param->getName();
				if(isset($with[$name])){
					$params_to_inject[$k] = $with[$name];
					continue;
				}
			}
			#+ }
			#+ handle type declared parameters {
			$value = $this->parameter_by_type($param);
			if(!($value instanceof \Exception)){ #< it was resolved
				$params_to_inject[$k] = $value;
				continue;
			}
			#+ }
			#+ use defaulting name or positional values {
			if(isset($default[$k])){
				$params_to_inject[$k] = $default[$k];
				continue;
			}else{
				$name = $param->getName();
				if(isset($default[$name])){
					$params_to_inject[$k] = $default[$name];
					continue;
				}
			}
			#+ }
			#+ use the default provided by the function {
			if($param->isOptional()){
				$params_to_inject[$k] = $param->getDefaultValue();
				continue;
			}

			if($options['false_on_missing']){
				return false;
			}
			#+ }
		}
		#+ resolve services {
		if($this->service_resolve){
			foreach($params_to_inject as &$v){
				if($v instanceof IoC\Service){
					$v = $this->get($v->id);
				}
			}
		}

		#+ }
		return $params_to_inject;
	}
}
