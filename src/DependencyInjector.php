<?php
namespace Grithin;

use Grithin\IoC\{MissingParam, MethodVisibility, ContainerException};


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
	*/
	public function __construct($getter=null){
		if(!$getter){
			$getter = [$this, 'getter_default'];
		}
		$this->getter_set($getter);
	}
	public function getter_set($getter){
		if(!is_callable(($getter))){
			throw new \Exception('Getter is not callable');
		}
		$this->getter = $getter;
	}
	public function get($key, $options=[]){
		return ($this->getter)($key, $options);
	}
	public function getter_default($key, $options=[]){
		if(class_exists($key)){
			return new $key;
		}
		return new \Exception;
	}
	public function parameter_by_type($param){
		$type = $param->getType();
		if(!$type){
			throw new \Exception;
		}
		if($type instanceof ReflectionUnionType){
			# just get the first type of a union
			$type = $type->getTypes()[0];
		}
		if($type->isBuiltin()){ # don't try to resolve a Builtin type
			throw new \Exception;
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
				throw new IoC\InjectionCallException('object uncallable');
			}
		}elseif(is_array($thing)){
			return $this->method_call($thing[0], $thing[1], $options);
		}
		throw new IoC\InjectionCallException('uncallable');

	}

	public function class_construct($class, $options=[]){
		$params = $this->class_resolve_parameters($class, $options);
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

		$reflect = new \ReflectionMethod($class, $method);
		if(!$reflect->isPublic()){
			throw new MethodVisibility;
		}
		$params = $this->parameters_resolve($reflect->getParameters(), $options);

		return $reflect->invokeArgs(null, $params);
	}
	public function method_call($object, $method, $options=[]){
		#+ allow handling of static methods {
		if(!is_object($object)){
			return $this->static_method_call($object, $method, $options);
		}
		#+ }

		$reflect = new \ReflectionMethod($object, $method);
		if(!$reflect->isPublic()){
			throw new MethodVisibility;
		}
		$params = $this->parameters_resolve($reflect->getParameters(), $options);
		return $reflect->invokeArgs($object, $params);
	}
	# see parameters_resolve for $options
	public function method_resolve_parameters($class, $method, $options=[]){
		$reflect = new \ReflectionMethod($class, $method);
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}
	public function function_call($function, $options=[]){
		$params = $this->function_resolve_parameters($function, $options);
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
		with: < dictionary of parameters to inject by position or name, ahead of type declaration injection >;
		default:  < dictionary of parameters to inject by position or name, if type declaration fails >;
	*/
	public function parameters_resolve($params, $options){
		$defaults = ['default'=>[], 'with'=>[]];

		extract(array_merge($defaults, $options));

		$params_to_inject = [];
		foreach($params as $k=>$param){
			#+ handle injecting name or positional specific parameters {
			if(isset($with[$k])){
				$with[$k] = $this->service_resolve($with[$k]);
				# don't insert wrong types
				if($this->type_match($param, $with[$k])){
					$params_to_inject[$k] = $with[$k];
					continue;
				}

			}else{
				$name = $param->getName();
				if(isset($with[$name])){
					$with[$name] = $this->service_resolve($with[$name]);
					# don't insert wrong types
					if($this->type_match($param, $with[$name])){
						$params_to_inject[$k] = $with[$name];
						continue;
					}
				}
			}
			#+ }
			#+ handle type declared parameters {
			$container_exception = null;
			try{
				$value = $this->parameter_by_type($param);
				$params_to_inject[$k] = $value;
				continue;
			}catch(\Exception $e){
				if($e instanceof ContainerException){
					$container_exception = $e;
				}
			}

			#+ }
			#+ use defaulting name or positional values {
			if(isset($default[$k])){
				$default[$k] = $this->service_resolve($default[$k]);
				# don't insert wrong types
				if($this->type_match($param, $default[$k])){
					$params_to_inject[$k] = $default[$k];
					continue;
				}
			}else{
				$name = $param->getName();
				if(isset($default[$name])){
					$default[$name] = $this->service_resolve($default[$name]);
					# don't insert wrong types
					if($this->type_match($param, $default[$name])){
						$params_to_inject[$k] = $default[$name];
						continue;
					}
				}
			}
			#+ }
			#+ use the default provided by the function {
			if($param->isOptional()){
				$params_to_inject[$k] = $param->getDefaultValue();
				continue;
			}
			#+ }

			#+ parameter is missing, throw exception {\}
			if($container_exception){ # if SL returned exception, use that
				throw $container_exception;
			}
			throw new MissingParam($param->getName());
			#+ }

		}
		return $params_to_inject;
	}

	/** resolve parameters that are service objects */
	public function service_resolve($v){
		if($v instanceof IoC\Service){
			return $this->get($v->id, $v->options);
		}
		return $v;
	}

	/** check if a value matches the type of a parameter */
	public function type_match($param, $value){
		$type = $param->getType();
		if(!$type){
			return true;
		}
		if($type instanceof ReflectionUnionType){
			$types = $type->getTypes();
		}else{
			$types = [$type];
		}
		$value_type = gettype($value);
		foreach($types as $type){
			if($type->isBuiltin()){
				if($value_type == $type->getName()){
					return true;
				}
			}elseif(is_a($value, $type->getName())){
				return true;
			}
		}
	}
}
