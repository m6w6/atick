<?php

namespace atick;

interface Able
{
	const CLOSED = 0;
	const READABLE = 1;
	const WRITABLE = 2;

	/**
	 * Register any output streams with the ticker
	 * @param \atick\Ticker $ticker
	 * @param callable $verify
	 */
	function with(Ticker $ticker, callable $verify = null);

	/**
	 * Pass data to the input stream
	 * @param string $data
	 */
	function write($data);

	/**
	 * Where to send output to
	 * @param resource|callable $into
	 * @return \atick\Able
	 */
	function read($into);

	/**
	 * Where to send error output to
	 * @param resource|callable $into
	 * @return \atick\Able
	 */
	function error($into);

	/**
	 * Whether the pipe/proc is alive
	 * @return int
	 */
	function stat();

	/**
	 * Shutdown the pipe/proc
	 * @param int $what
	 */
	function close($what = self::CLOSED);
}
