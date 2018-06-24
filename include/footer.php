<p class="footer">Copyright &copy; <?php echo date("Y"); ?> Russell Thackston</p>
<?php

if ($_COOKIE['debug'] == "true") {
	if (isset($debugMessages)) {
		foreach ($debugMessages as $msg) {
			var_dump($msg);
		}
	}
}
	
?>