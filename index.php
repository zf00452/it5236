 <?php
	
// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

// Declare an empty array of error messages
$errors = array();

?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>zachfrantz.me</title>
	<meta name="description" content="Zach Frantz's website">
	<meta name="author" content="Zach Frantz">
	<link rel="stylesheet" type="text/css" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
	<?php include 'include/header.php'; ?>
	<h2>What Camera Should You Buy In 2018?</h2>
	<p>
		2018 is full of exciting releases with big players like nikon and canon finally releasing full-frame mirrorless cameras and Sony bringing out the revolutionary A7III. But smaller contendors like FujiFilm and Olympus have surprised many with impressive releases. So what camera is right for you?	</p>
	<br>
	<img src="camera.png" alt="camera" width="380" height="300">
	<?php include 'include/footer.php'; ?>
	<script src="js/site.js"></script>
</body>
</html>
