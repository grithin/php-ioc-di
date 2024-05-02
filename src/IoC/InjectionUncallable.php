<?php

namespace Grithin\IoC;


use Psg\Sr3\ComplexException;

class InjectionUncallable extends ComplexException implements InjectionCallExceptionInterface{};