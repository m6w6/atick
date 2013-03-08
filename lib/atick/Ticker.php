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
	
	/**
	 * The tick handler; calls atick\Ticker::wait(0)
	 * @return int
	 */
	function __invoke() {
		return $this->wait(0);
	}
	
	/**
	 * Wait for read/write readiness on the watched fds
	 * @param float $timeout
	 * @return int count of wached fds
	 */
	function wait($timeout = 1) {
		$r = $w = $e = array();
		foreach ($this->read as $s) {
			$r[] = $s[0];
		}
		foreach ($this->write as $s) {
			$w[] = $s[0];
		}
		$s = (int) $timeout;
		$u = (int) (($timeout - $s) * 1000000);
		if (($r || $w) && stream_select($r, $w, $e, $s, $u)) {
			foreach ($r as $s) {
				if ($this->read[(int)$s][1]($s)) {
					unset($this->read[(int)$s]);
				}
			}
			foreach ($w as $s) {
				if ($this->write[(int)$s][1]($s)) {
					unset($this->write[(int)$s]);
				}
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
		return count($this->read) + count($this->write);
	}
	
	/**
	 * Attach a read handler; let the callback return true, to stop watching the fd.
	 * @param resource $fd
	 * @param callable $cb
	 * @return \atick\Ticker
	 */
	function read($fd, callable $cb) {
		$this->read[(int)$fd] = array($fd, $cb);
		return $this;
	}
	
	/**
	 * Attach a write handler; let the callback return true, to stop watching the fd.
	 * @param resource $fd
	 * @param callable $cb
	 * @return \atick\Ticker
	 */
	function write($fd, callable $cb) {
		$this->write[(int)$fd] = array($fd, $cb);
		return $this;
	}
}
