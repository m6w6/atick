<?php

spl_autoload_register(function($c) {
	if (substr($c, 0, 6) == "atick\\") return require_once sprintf("%s/../lib/%s.php", __DIR__, strtr($c, "\\", "/"));
});
