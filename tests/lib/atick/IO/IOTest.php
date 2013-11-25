<?php

namespace atick;

include __DIR__."/../../../setup.inc";

class IOTest extends \PHPUnit_Framework_TestCase {
	protected $ticker;
	
	function setUp() {
		$this->ticker = new Ticker;
	}
	
	function testIO() {
		$gzip = new IO\Process("gzip -1");
		$base = new IO\Process("base64");
		$func = new IO\Filter(function($f, $data, $eof) {
			return strrev($data);
		});
		
		fwrite($gzip->getInput(), "Hello World!\n");
		fclose($gzip->getInput());
		
		$ticker = new Ticker;
		$ticker->pipe($gzip, $base, $func, "fpassthru");

		ob_start();
		while($ticker(1));
		$this->assertStringMatchesFormat("\nAAAAN0HFd3NACQeUJp8LPjwVJnczIN/AEI%sAIs4H", ob_get_contents());
	}
}
