<?php

namespace Grithin\IoC;

use Psr\Container\NotFoundExceptionInterface;

class DataNotFound extends ContainerException implements NotFoundExceptionInterface{};