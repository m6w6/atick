<?php

namespace atick;

/**
 * Asynchronnous resource handling, optionally (ab)using ticks
 * 
 * Example with ticks:
 * <code>
 * <?php
 * declare(ticks=1);
 *
 * $conn = new \pq\Connection;
 * $conn->execAsync("SELECT * FROM foo", function ($rs) {
 *     var_dump($rs);
 * });
 * 
 * $ticker = new \atick\Ticker;
 * $ticker->register();
 * $ticker->read($conn->socket, function($fd) use ($conn) {
 *     $conn->poll();
 *     if ($conn->busy) {
 *         return false;
 *     }
 *     $conn->getResult();
 *     return true;
 * });
 *
 * while (count($ticker));
 * ?>
 * </code>
 * 
 * And an example without ticks:
 * <code>
 * <?php
 * $conn = new \pq\Connection;
 * $conn->execAsync("SELECT * FROM foo", function ($r) {
 *     var_dump($r);
 * });
 * 
 * $ticker = new \atick\Ticker;
 * $ticker->read($conn->socket, function($fd) use ($conn) {
 *     $conn->poll();
 *     if ($conn->busy) {
 *         return false;
 *     }
 *     $conn->getResult();
 *     return true;
 * });
 * 
 * while($ticker());
 * ?>
 * </code>
 */
class Ticker implements \Countable
{
	/**
	 * @var array
	 */
	protected $read = array();
	
	/**
	 * @var array
	 */
	protected $write = array();
	
	/**
	 * Register the ticker as tick function
	 * @return \atick\Ticker
	 */
	function register() {
		register_tick_function(array($this, "__invoke"));
		return $this;
	}
	
	/**
	 * Unregister the ticker as tick function
	 * @return \atick\Ticker
	 */
	function unregister() {
		unregister_tick_function(array($this, "__invoke"));
		return $this;
	}
	
	function dispatch() {
		pcntl_signal_dispatch();
		return $this;
	}

	function on($signal, $action) {
		pcntl_signal($signal, $action);
		return $this;
	}

	/**
	 * The tick handler; calls atick\Ticker::wait(0)
	 * @return int
	 */
	function __invoke($timeout = 0) {
		return $this->wait($timeout);
	}
	
	/**
	 * Wait for read/write readiness on the watched fds
	 * @param float $timeout
	 * @return int count of wached fds
	 */
	function wait($timeout = 1) {
		$r = $w = $e = array();

		foreach ($this->read as $s) {
			is_resource($s[0]) and $r[] = $s[0];
		}

		foreach ($this->write as $s) {
			is_resource($s[0]) and $w[] = $s[0];
		}

		$t = (int) $timeout;
		$u = (int) (($timeout - $t) * 1000000);

		if (($r || $w) && stream_select($r, $w, $e, $t, $u)) {
			foreach ($r as $s) {
				$this->read[(int)$s][1]($s);
			}
			foreach ($w as $s) {
				$this->write[(int)$s][1]($s);
			}
		}

		return $this->count();
	}
	
	/**
	 * Returns the count of watched fds
	 * @implements \Countable
	 * @return int
	 */
	function count() {
		foreach ($this->read as $i => $s) {
			list($fd,,$verify) = $s;
			if (!$verify($fd)) {
				unset($this->read[$i]);
			}
		}

		foreach ($this->write as $i => $s) {
			list($fd,,$verify) = $s;
			if (!$verify($fd)) {
				unset($this->write[$i]);
			}
		}

		return count($this->read) + count($this->write);
	}

	/**
	 * Attach a read handler
	 * @param resource $fd
	 * @param callable $onread void($fd) the descriptor is readable, read data, now!
	 * @param callable $verify bool($fd) wheter the fd is still valid and should be watched
	 * @return \atick\Ticker
	 */
	function read($fd, callable $onread, callable $verify = null) {
		if ($fd instanceof IO) {
			$fd = $fd->getOutput();
		}
		$this->read[(int)$fd] = array($fd, $onread, $verify ?: function($fd) {
			return is_resource($fd) && !feof($fd);
		});
		return $this;
	}
	
	/**
	 * Attach a write handler
	 * @param resource $fd
	 * @param callable $onwrite void($fd) the descriptor is writable, write data.
	 * @param callable $verify bool($fd) wheter the fd is still valid and should be watched
	 * @return \atick\Ticker
	 */
	function write($fd, callable $onwrite, callable $verify = null) {
		if ($fd instanceof IO) {
			$fd = $fd->getInput();
		}
		$this->write[(int)$fd] = array($fd, $onwrite, $verify ?: function($fd) {
			return is_resource($fd) && !feof($fd);
		});
		return $this;
	}

	/**
	 * Pipe
	 * e.g. $ticker->pipe(STDIN, new IO\Process("gzip"), new IO\Process("base64"), STDOUT);
	 * @param IO ...
	 * @return \atick\Ticker
	 */
	function pipe(/*IO ...*/) {
		$io = func_get_args();
		reset($io);

		do {
			$r = current($io);
			$w = next($io);

			$this->read($r, $w ?: function($fd) {
				stream_copy_to_stream($fd, STDOUT);
			});
		} while ($w);

		return $this;
	}
}
