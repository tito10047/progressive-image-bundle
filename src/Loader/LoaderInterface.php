<?php

namespace Tito10047\ProgressiveImageBundle\Loader;

interface LoaderInterface {

	/**
	 * @return resource
	 */
	public function load(string $path);

}