<?php

namespace Grithin\IoC;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFound extends ContainerException implements NotFoundExceptionInterface{};