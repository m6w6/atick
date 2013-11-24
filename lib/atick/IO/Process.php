<?php

namespace atick\IO;

use atick\IO;

class Process implements IO
{
	/**
	 * Process handle
	 * @var resource
	 */
	protected $process;

	/**
	 * Process' stdio pipes
	 * @var array
	 */
	protected $pipes;

	/**
	 * @param string $command
	 * @param string $cwd
	 * @param array $env
	 * @throws \RuntimeException
	 */
	function __construct($command, $cwd = null, array $env = null) {
		$this->process = proc_open($command, [["pipe","r"],["pipe","w"],["pipe","w"]], $this->pipes, $cwd, $env);

		if (!is_resource($this->process) || !($status = proc_get_status($this->process))) {
			throw new \RuntimeException("Could not open proc '$command': " . error_get_last()["message"]);
		}

		stream_set_blocking($this->pipes[1], false);
		stream_set_blocking($this->pipes[2], false);
	}

	/**
	 * Cleanup pipes and proc handle
	 */
	function __destruct() {
		foreach ($this->pipes as $fd) {
			if (is_resource($fd)) {
				fclose($fd);
			}
		}
		proc_close($this->process);
	}

	/**
	 * @inheritdoc
	 * @return resource
	 */
	function getOutput() {
		return $this->pipes[1];
	}

	/**
	 * @inheritdoc
	 * @return resource
	 */
	function getInput() {
		return $this->pipes[0];
	}

	/**
	 * @inheritdoc
	 * @param resource $fd
	 * @return resource
	 */
	function __invoke($fd) {
		if ($fd) {
			copy($fd, $this->getInput());
		}
		return $this->getOutput();
	}
}
