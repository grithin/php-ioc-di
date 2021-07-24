# IoC DI
A much smaller IoC Dependency Injector and Service Locator.

```sh
composer require grithin/ioc-di
```

## Why
-	PHP DI was too big
-	needed unfound features
	-	service as a parameter (am I the only one who's thought of this?)
	-	inject by name and position
-	wanted to see exactly how IoC DL was done in PHP

## Use

```php
interface interface1{}
interface interface1_1 extends interface1{}

class class1 implements interface1_1{}
class class2_construct{
	public function __construct(interface1 $bob){
		$this->bob = $bob;
	}
}
class class3 implements interface1_1{}

$sl = new ServiceLocator;
$di = $sl->injector_get();
$sl->bind(class1::class);

# IoC injection of constructor
$class2_instance = $di->call('class2_construct');

# overide by name
$class2_instance = $di->call_with('class2_construct', ['bob'=> new class3]);

# override by position
$class2_instance = $di->call_with('class2_construct', [0=> new class3]);

# default on not found
class class4_construct{
	public function __construct(class5 $bob){
		$this->bob = $bob;
	}
}
class class5{}
$class2_instance = $di->call('class4_construct', ['default'=>['bob'=>new class5]]);

# service as a parameter
$class2_instance = $di->call_with('class2_construct', ['bob'=> new \Grithin\IoC\Service('class3')]);


```


## ServiceLocator
Will also accept odd variables:
-	instances
	-	if singleton, will return on get
	-	if not singleton, will clone on get
-	string
	-	will try to resolve the string to either another service or a class
-	InterprettedInterface
	-	will interpret
-	non-string, non object, non-closure (arrays)
	-	if singleton, will return reference
	-	if not singleton, will return non-reference

### The Notion Of Options In Services

That an object is constructed with parameters other than those that are auto injected is sometimes the case.  To allow this, SL provides both:
-	DI options bound to the service, that are used each time `get` is called for the service
-	`get` call DI options, that can be used for instance specific parameters and to overwrite options bound to the service

An example of the utility of this can be seen in how SL implements PSR 11.

@SideNote A `get` with options instigating a `bind` should not bind the options to the new service.  If the get is being used with options, it is expected further `get`'s to that service will also use options.

## Data Locator
Should serve data, however there are times when it is desirable to make data just in time.  As such, ways are provided;
-	set(): with $thing equal to Closure or Grithin\IoC\Call
	-	this sets lazy
-	set_lazy(): this will execute the callable once when datum is requested
-	set_factory(): this will execute every time data is requested


## Special Type Objects
Using Grithin\IoC\SpecialTypeInterface

### Service Object
Used by: SL, DI

If you want to point to a service with options.
If you want to provide a parameter default as an object, but don't want to instantiate the object unless it is used, the Serivce object can b used
```php
use \Grithin\IoC\Service;
$service = new Service($service_name, $injection_options);
$injector->call('Bob::bill', ['defaults'=>[$service]]);
```

### Datum
Used by: SL, DI

If you want to point to a datum that might be set later.
If you want to set a parameter to a datum that will be set later.  The key will be resolved using the Data Locator when the Datum object is resolved.
```php
use \Grithin\IoC\Datum;
$sl->bind('ClassPerson', ['with'=>['name'=>new Datum('name')]]);
```

### Call
Used by: SL, DI, DL

If you want to point to the result of some callable.
```php
use \Grithin\IoC\Call;
$sl->bind('ClassPerson', ['with'=>['name'=>new Call('NameGetter::get_name')]]);
```


### Factory
Used by: SL, DI, DL

Used to indicate to DataLocator it should use thing as a factory



## Notes
By default, SL does not check all classes to resolve an interface or abstract class, it only checks what is within the services (by id).  You can make it check everything though:
```php
$sl = new ServiceLocator(['check_all'=>true]);
```


## FAQ
-	is this better than X?
	-	most definitely 100% better