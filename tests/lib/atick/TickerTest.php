<?php

namespace atick;

include __DIR__."/../../setup.inc";

class TickerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var Ticker
	 */
	protected $ticker;

	protected function setUp() {
		$this->ticker = new Ticker;
	}

	public function testRegister() {
		$this->ticker->register();
		$this->ticker->unregister();
		$this->ticker->register();
		$this->ticker->unregister();
	}
	
	public function testTicks() {
		$file = fopen(__FILE__, "r");
		stream_set_blocking($file, false);
		$read = 0;
		
		declare(ticks=1);
		$this->ticker->register();
		$this->ticker->read($file, function ($file) use (&$read) {
			do {
				$data = fread($file, 4096);
				$read += strlen($data);
			} while (strlen($data));
			return feof($file);
		});
		
		$dummy = "This test is do tiny, ";
		$dummy.= "we don't have to do much.";
		
		$this->assertEquals(filesize(__FILE__), $read);
	}

	public function testBasic() {
		$r = $w = false;
		$this->assertCount(0, $this->ticker);
		
		$file = fopen(__FILE__, "r");
		stream_set_blocking($file, false);
		
		$this->ticker->read($file, function ($file) use (&$r) {
			return $r;
		});
		$this->assertCount(1, $this->ticker);
		$this->ticker->write($file, function ($file) use (&$w) {
			return $w;
		});
		$this->assertCount(2, $this->ticker);
		
		$this->assertSame(2, $this->ticker->wait());
		$r = true;
		$this->assertSame(1, $this->ticker->wait());
		$w = true;
		$this->assertSame(0, $this->ticker->wait());
	}

}
