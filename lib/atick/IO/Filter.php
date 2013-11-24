<?php

namespace atick\IO;

use atick\IO;

class Filter implements IO
{
	/**
	 * Input stream
	 * @var resource
	 */
	protected $input;

	/**
	 * Output stream
	 * @var resource
	 */
	protected $output;

	/**
	 * @param callable $func filter proc
	 * @param callable $ctor constructor
	 * @param callable $dtor destructor
	 */
	function __construct(callable $func, callable $ctor = null, callable $dtor = null) {
		/*
		 * We don't have pipe(2) support, so we'll use socketpair(2) instead.
		 */
		list($this->input, $this->output) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		stream_filter_append($this->input, "atick\\IO\\StreamFilter", STREAM_FILTER_WRITE, compact("func", "ctor", "dtor"));
		stream_set_blocking($this->output, false);
	}

	/**
	 * Cleanup socketpair(2) resources
	 */
	function __destruct() {
		if (is_resource($this->input)) {
			fclose($this->input);
		}
		if (is_resource($this->output)) {
			fclose($this->output);
		}
	}

	/**
	 * @inheritdoc
	 * @return resource
	 */
	function getOutput() {
		return $this->output;
	}

	/**
	 * @inheritdoc
	 * @return resource
	 */
	function getInput() {
		return $this->input;
	}

	/**
	 * @inheritdoc
	 * @param resource $fd
	 * @return resource
	 */
	function __invoke($fd = null) {
		if ($fd) {
			copy($fd, $this->getInput());
		}
		return $this->getOutput();
	}
}

class StreamFilter extends \php_user_filter
{
	public $filtername = "atick\\IO\\Func";
	public $params;

	function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$consumed += $bucket->datalen;
			$bucket->data = call_user_func($this->params["func"], $this, $bucket->data, $closing);
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}

	function onClose() {
		if (!empty($this->params["dtor"])) {
			call_user_func($this->params["dtor"], $this);
		}
	}

	function onCreate() {
		if (!empty($this->params["ctor"])) {
			call_user_func($this->params["ctor"], $this);
		}
	}
}

stream_filter_register("atick\\IO\\StreamFilter", "\\atick\\IO\\StreamFilter");
