<?php

namespace atick;

class Proc implements Able
{
	/**
	 * Command string
	 * @var string
	 */
	protected $command;

	/**
	 * Process handle
	 * @var resource
	 */
	protected $proc;

	/**
	 * Proc's pipes
	 * @var array
	 */
	protected $pipes;

	protected $read;
	protected $error;

	/**
	 * @param string $command
	 * @param string $cwd
	 * @param array $env
	 * @throws \RuntimeException
	 */
	function __construct($command, $cwd = null, array $env = null) {
		$this->command = $command;
		$this->proc = proc_open($command, [["pipe","r"],["pipe","w"],["pipe","w"]], $this->pipes, $cwd, $env);

		if (!is_resource($this->proc) || !($status = proc_get_status($this->proc))) {
			throw new \RuntimeException("Could not open proc '$command': " . error_get_last()["message"]);
		}

		stream_set_blocking($this->pipes[1], false);
		stream_set_blocking($this->pipes[2], false);
	}

	/**
	 * Returns the command string
	 * @return string
	 */
	function __toString() {
		return (string) $this->command;
	}

	/**
	 * Cleanup pipes and proc handle
	 */
	function __destruct() {
		$this->close();
	}

	/**
	 * @inheritdoc
	 * @implements \aticker\Able
	 * @param \atick\Ticker $ticker
	 * @param callable $verify
	 */
	function with(Ticker $ticker, callable $verify = null) {
		if (is_callable($this->read)) {
			$ticker->read($this->pipes[1], $this->read, $verify ?: array($this, "stat"));
		} elseif (is_resource($this->read)) {
			$ticker->read($this->pipes[1], function($fd) {
				if (strlen($data = fread($fd, 8192))) {
					fwrite($this->read, $data);
				}
			}, $verify ?: array($this, "stat"));
		} else {
			$ticker->read($this->pipes[1], function($fd) {
				/* nirvana */
				fread($fd, 8192);
			}, $verify ?: array($this, "stat"));
		}

		if (is_callable($this->error)) {
			$ticker->read($this->pipes[2], $this->error, $verify ?: array($this, "stat"));
		} elseif (is_resource($this->error)) {
			$ticker->read($this->pipes[2], function($fd) {
				if (strlen($data = fread($fd, 8192))) {
					fwrite($this->error, $data);
				}
			}, $verify ?: array($this, "stat"));
		} else {
			$ticker->read($this->pipes[2], function($fd) {
				/* nirvana */
				fread($fd, 8192);
			}, $verify ?: array($this, "stat"));
		}
	}

	function stat() {
		echo "STAT $this\n";
		if ($this->proc && proc_get_status($this->proc)["running"]) {
			if ((isset($this->pipes[1]) && is_resource($this->pipes[1]) && !feof($this->pipes[1]))
			&&  (isset($this->pipes[2]) && is_resource($this->pipes[2]) && !feof($this->pipes[2]))
			) {
				if (isset($this->pipes[0]) && is_resource($this->pipes[0]) && !feof($this->pipes[0])) {
					return self::READABLE | self::WRITABLE;
				}
				return self::READABLE;
			}
		}
		$this->close();
		return self::CLOSED;
	}

	function close($what = self::CLOSED) {
		echo "PROC KILL $this $what\n";

		if (!$what || ($what & self::WRITABLE)) {
			if (is_resource($this->pipes[0])) {
				fclose($this->pipes[0]);
			}
			$this->pipes[0] = null;
		}

		if (!$what || ($what & self::READABLE)) {
			if (is_resource($this->pipes[1])) {
				fclose($this->pipes[1]);
			}
			$this->pipes[1] = null;
			if (is_resource($this->read)) {
				fclose($this->read);
			}
			if (is_resource($this->pipes[2])) {
				fclose($this->pipes[2]);
			}
			$this->pipes[2] = null;
			if (is_resource($this->error)) {
				fclose($this->error);
			}
		}

		if (!$what && is_resource($this->proc)) {
			proc_close($this->proc);
			$this->proc = null;
		}
	}

	/**
	 * @inheritdoc
	 * @implements \aticker\Able
	 * @param string $data
	 * @return int
	 */
	function write($data) {
		return fwrite($this->pipes[0], $data);
	}

	/**
	 * Where to read STDOUT into
	 * @param resource|callable $into
	 * @return \atick\Proc
	 * @throws \InvalidArgumentException
	 */
	function read($into) {
		if (is_resource($into) || is_callable($into)) {
			$this->read = $into;
		} else {
			throw new \InvalidArgumentException("Not a valid resource or callback");
		}
		return $this;
	}

	/**
	 * Where to pass STDERR into
	 * @param resource|callable $into
	 * @return \atick\Proc
	 * @throws \InvalidArgumentException
	 */
	function error($into) {
		if (is_resource($into) || is_callable($into)) {
			$this->error = $into;
		} else {
			throw new \InvalidArgumentException("Not a valid resource or callback");
		}
		return $this;
	}
}
