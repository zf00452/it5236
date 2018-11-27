<?php

// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

// Declare a set of variables to hold the username and password for the user
$username = "";
$password = "";

// Declare an empty array of error messages
$errors = array();

// If someone has clicked their email validation link, then process the request
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

	if (isset($_GET['id'])) {
		
		$success = $app->processEmailValidation($_GET['id'], $errors);
		if ($success) {
			$message = "Email address validated. You may login.";
		}

	}

}

// If someone is attempting to login, process their request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	// Pull the username and password from the <form> POST
	$username = $_POST['username'];
	$password = $_POST['password'];

	// Attempt to login the user and capture the result flag
	$result = $app->login($username, $password, $errors);

	// Check to see if the login attempt succeeded
	if ($result == TRUE) {

		// Redirect the user to the topics page on success
		header("Location: list.php");
		exit();

	}

}

if (isset($_GET['register']) && $_GET['register']== 'success') {
	$message = "Registration successful. Please check your email. A message has been sent to validate your address.";
}


?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>zachfrantz.me</title>
	<meta name="description" content="Zach Frant's website for cameras">
	<meta name="author" content="Zach Frantz">
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<!--1. Display Errors if any exists 
	2. Display Login form (sticky):  Username and Password -->

<body>
	<?php include 'include/header.php'; ?>

	<h2>Login</h2>

	<?php include('include/messages.php'); ?>
	
	<div>
		<form method="post" action="login.php" id="usernameForm">
			
			<input type="text" name="username" id="usernameField" placeholder="Username" value="<?php echo $usernameField; ?>" />
			<br/>

			<input type="password" name="password" id="password" placeholder="Password" value="<?php echo $password; ?>" />
			<br/>

			<input type="submit" id ="submit" value="Login" name="login" /><br>
						<input type="radio" name="form" value="local" id ="saveLocal"> Save to Local Storage<br>
			<input type="radio" name="form" value="session" id="saveSession"> Save to Session Storage<br>
			<input type="radio" name="form" value="none" id="noSave"> Save to Neither (remove storage)<br>
		</form>
		</form>
		<form action="login.php">

	</div>
	<a href="register.php">Need to create an account?</a>
	<br/>
	<a href="reset.php">Forgot your password?</a>
	<?php include 'include/footer.php'; ?>
	<script src="js/site.js"></script>
	<?php
/*use OTPHP\TOTP;

use OTPHP\HOTP;

$hotp = new HOTP();
$hotp->at(1000); // e.g. will return '123456'
$hotp->verify('123456', 1000); // Will return true
$hotp->verify('123456', 1000); // Will return false as the current counter is now 1001
*/
	?>
	
</body>
</html>
