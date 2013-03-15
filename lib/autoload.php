<?php

spl_autoload_register(function($c) {
	if (substr($c, 0, 6) === "atick\\") {
		return include_once __DIR__ . "/" . strtr($c, "\\", "/") . ".php";
	}
});
