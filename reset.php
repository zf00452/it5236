<?php

// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {

} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	// Grab or initialize the input values
	$username = $_POST['username'];
	$question = $_POST['question'];
	$answer = $_POST['answer'];

	// Validate the user input
	if (empty($username)) {
		$errors[] = "Please provide your username";
	} else {
		if (empty($question)) {
			// We have a username but no question, so load the security question from the database
			$sql = "SELECT question FROM users WHERE username = '$username'";
			echo $sql;
			$result = True;
			$result = $conn->query($sql);
			
			// If we get back a row, then the username is valid and we have their security question
			if ($result->num_rows > 0) {
				$row = $result->fetch_assoc();
				$question = $row['question']; 
			} else {
				$errors[] = "Unknown username";
			}
		} else if (!empty($answer)) {
			// We have a username and a question, so check the answer against the database
			$sql = "SELECT password FROM users WHERE username = '$username' AND answer = '$answer'";
			echo $sql;
			$result = True;
			$result = $conn->query($sql);
			
			// If we get back a row, then the username/answer combination is valid
			if ($result->num_rows > 0) {
				$row = $result->fetch_assoc();
				$password = $row['password']; 
			} else {
				$errors[] = "Wrong answer";
			}
		} else {
			// How did we get here?
			$errors[] = "Please answer the security question";
		}
	}
}
?>

<!doctype html>

<html lang="en">
<head>
	<meta charset="utf-8">
	<title>russellthackston.me</title>
	<meta name="description" content="Russell Thackston's personal website for IT 5233">
	<meta name="author" content="Russell Thackston">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="css/style.css">	
</head>
<body>
	<script src="js/site.js"></script>

	<?php include 'include/header.php'; ?>
	<h2>Retrieve Password</h2>
	<?php if (sizeof($errors) > 0) { ?>
			<div class="error">The following errors were detected:
	<?php
				foreach ($errors as $error) {
					echo "<br/>";
					echo "&bullet; $error";
				}
	?>
			</div>
	<?php } ?>
	<form method="post" action="reset.php">
		<?php if (!empty($password)) { ?>
			<span>You password is: <?php echo $password; ?></span>
		<?php } else if (!empty($question)) { ?>
			<label for="answer">Please answer your security question:
			<br/>
			<input type="text" name="question2" id="question2" disabled="disabled" value="<?php echo $question; ?>" /> <br>
			</label>
			<input type="hidden" name="question" id="question" value="<?php echo $question; ?>" />
			<input type="password" name="answer" maxlength="10" id="answer" />
			<input type="hidden" name="username" id="username"  value="<?php echo $username; ?>" />
			<input type="submit" value="Next" />
		<?php } else { ?>
			<input type="text"   name="username" maxlength="10" id="username" placeholder="Enter your username" required="required" onblur="checkLength10(this)" />
			<input type="submit" value="Next" />
		<?php } ?>
	</form>
	<a href="register.php">Need to create an account?</a>
	<?php include 'include/footer.php'; ?>
</body>
</html>
