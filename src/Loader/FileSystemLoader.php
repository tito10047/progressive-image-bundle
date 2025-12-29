<?php

namespace Tito10047\ProgressiveImageBundle\Loader;

use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;

class FileSystemLoader implements LoaderInterface {

	/**
	 * @var false|resource
	 */
	private $file;

	public function load(string $path) {
		if (!file_exists($path) || !is_file($path)) {
			throw new PathResolutionException("Path $path does not exist or is not a file.");
		}
		return $this->file = fopen($path, 'r');
	}

	public function __destruct() {
		if (!$this->file || !is_resource($this->file)){
			$this->file = null;
			return;
		}
		fclose($this->file);
		$this->file = null;
	}
}