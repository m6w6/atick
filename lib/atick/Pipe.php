<?php

namespace atick;

class Pipe implements Able
{
	/**
	 * Output producing proc
	 * @var \atick\Able
	 */
	protected $producer;

	/**
	 * Input consuming proc
	 * @var \atick\Able
	 */
	protected $consumer;

	/**
	 * Create a pipe between two procs or other pipes
	 * @param \atick\Able $producer
	 * @param \atick\Able $consumer
	 */
	function __construct(Able $producer, Able $consumer) {
		$this->producer = $producer;
		$this->consumer = $consumer;

		$this->producer->read(function($fd) {
			if (strlen($data = fread($fd, 8192))) {
				$this->consumer->write($data);
			}
		});
	}

	function __toString() {
		return "$this->producer | $this->consumer";
	}

	function close($what = self::CLOSED) {
		echo "PIPE KILL $this $what\n";
		return $this;
	}

	function stat() {
		echo "STAT $this\n";
		if (!($this->producer->stat() & self::READABLE)) {
			if ($this->consumer->stat() & self::WRITABLE) {
				$this->consumer->close(self::WRITABLE);
				return self::READABLE;
			} else {
				$this->consumer->close(self::READABLE);
				return self::CLOSED;
			}
		}
		return ($this->producer->stat() & self::WRITABLE)
			| ($this->consumer->stat() & self::READABLE);
	}

	function write($data) {
		return $this->producer->write($data);
	}

	function read($into) {
		$this->consumer->read($into);
		return $this;
	}

	function error($into) {
		$this->consumer->error($into);
		return $this;
	}

	function with(Ticker $ticker, callable $verify = null) {
		$this->producer->with($ticker, $verify ?: array($this, "stat"));
		$this->consumer->with($ticker, $verify ?: array($this, "stat"));
	}
}
