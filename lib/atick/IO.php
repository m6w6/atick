<?php

namespace atick\IO;

function copy($from, $to, $len = 4096) {
	$data = fread($from, $len);

	if (!strlen($data)) {
		if (feof($from)) {
			/* forward EOF */
			fclose($to);
		}
		return;
	}

	return fwrite($to, $data);
}

namespace atick;

interface IO
{
	/**
	 * Retrieve the output stream
	 * @return resource
	 */
	function getOutput();

	/**
	 * Retrieve the input stream
	 * @return resource
	 */
	function getInput();

	/**
	 * Pass input from FD to input and return output stream
	 * @param resource $fd
	 * @return resource
	 */
	function __invoke($fd);
}
