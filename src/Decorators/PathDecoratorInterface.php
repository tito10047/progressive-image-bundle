<?php

namespace Tito10047\ProgressiveImageBundle\Decorators;

interface PathDecoratorInterface {

	public function decorate(string $path, array $context = []):string;

}