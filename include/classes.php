<?php

class Application {

	public function setup() {

		// Check to see if the client has a cookie called "debug" with a value of "true"
		// If it does, turn on error reporting
		if ($_COOKIE['debug'] == "true") {
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}
	}
	
	// Writes a message to the debug message array for printing in the footer.
	public function debug($message) {
		global $debugMessages;
		$debugMessages[] = $message;
	}
	
	// Creates a database connection
	protected function getConnection() {

		// Import the database credentials
		require('credentials.php');	

		// Create the connection
		try {
			$dbh = new PDO("mysql:host=$servername;dbname=$serverdb", $serverusername, $serverpassword);
		} catch (PDOException $e) {
			print "Error connecting to the database.";
			die();
		}

		// Return the newly created connection
		return $dbh;
	}
	
	public function auditlog($context, $message){

		// Connect to the database
		$dbh = $this->getConnection();
		
		$sessionid = $_COOKIE['sessionid'];
		$errors = [];
		
		$user = $this->getSessionUser($sessionid, $errors, TRUE);
		$userid = $user["userid"];
			
		$ipaddress = $_SERVER["REMOTE_ADDR"];
		
		if (is_array($message)){
			$message = implode( ",", $message);
		}

		// Construct a SQL statement to perform the insert operation
		$sql = "INSERT INTO auditlog (context, message, logdate, ipaddress, userid) VALUES (:context, :message, NOW(), :ipaddress, :userid)";

		// Run the SQL select and capture the result code
		$stmt = $dbh->prepare($sql);
		$stmt->bindParam(":context", $context);
		$stmt->bindParam(":message", $message);
		$stmt->bindParam(":ipaddress", $ipaddress);
		$stmt->bindParam(":userid", $userid);
		$result = $stmt->execute();
		$dbh = NULL;
		
	}
	
	// Registers a new user
	public function register($username, $password, $email, $registrationcode, &$errors) {
		
		$this->auditlog("register", "attempt: $username, $email, $registrationcode");

		// Validate the user input
		if (empty($username)) {
			$errors[] = "Missing username";
		}
		if (empty($password)) {
			$errors[] = "Missing password";
		}
		if (empty($email)) {
			$errors[] = "Missing email";
		}
		if (empty($registrationcode)) {
			$errors[] = "Missing registration code";
		}
	
		// Only try to insert the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
	
			// Connect to the database
			$dbh = $this->getConnection();

			// Check the registration codes table for the code provided
			$goodcode = FALSE;
			$sql = "SELECT COUNT(*) FROM registrationcodes WHERE LOWER(registrationcode) = LOWER(:code)";
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(':code', $registrationcode);
			$result = $stmt->execute();
			if ($result) {
				if ($row = $stmt->fetch()) {
					if ($row[0] == 1) {
						$goodcode = TRUE;
					}
				}
			}

			// If the code is bad, then return error
			if (!$goodcode) {
				$errors[] = "Bad registration code";
				$this->auditlog("register", "bad registration code: $registrationcode");

			}
			
			// Hash the user's password
			$passwordhash = password_hash($password, PASSWORD_DEFAULT);

			// Create a new user ID
			$userid = bin2hex(random_bytes(16));
			
			// Construct a SQL statement to perform the insert operation
			$sql = "INSERT INTO users (userid, username, passwordhash, email, registrationcode) " .
				"VALUES (:userid, :username, :passwordhash, :email, :registrationcode)";
	
			// Run the SQL insert and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(':userid', $userid);
			$stmt->bindParam(':username', $username);
			$stmt->bindParam(':passwordhash', $passwordhash);
			$stmt->bindParam(':email', $email);
			$stmt->bindParam(':registrationcode', $registrationcode);
			$result = $stmt->execute();
	
			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {
				
				$arr = $stmt->errorInfo();
				
				// Check for duplicate userid/username/email
				if ($arr[1] == 1062) {
					if (substr($arr[2], -7, 6) == "userid") {
						$errors[] = "An unexpected registration error occurred. Please try again in a few minutes.";
						$this->debug($stmt->errorInfo());
						$this->auditlog("register error", $stmt->errorInfo());
						
					} else if (substr($arr[2], -9, 8) == "username") {
						$errors[] = "That username is not available.";
						$this->auditlog("register", "duplicate username: $username");												
					} else if (substr($arr[2], -6, 5) == "email") {
						$errors[] = "That email has already been registered.";
						$this->auditlog("register", "duplicate email: $email");						
					} else {
						$errors[] = "An unexpected error occurred.";
						$this->debug($stmt->errorInfo());
						$this->auditlog("register error", $stmt->errorInfo());					
					}
				} else {
					$errors[] = "An unexpected error occurred.";
					$this->debug($stmt->errorInfo());
					$this->auditlog("register error", $stmt->errorInfo());					
				}
			} else {
				$this->auditlog("register", "success: $userid, $username, $email");					
			}
	
			// Close the connection
			$dbh = NULL;
	
		} else {
			$this->auditlog("register validation error", $errors);					
		}
	
		// Return TRUE if there are no errors, otherwise return FALSE
		if (sizeof($errors) == 0){
			return TRUE; 
		} else {
			return FALSE;
		}
	}

	// Creates a new session in the database for the specified user
	public function newSession($userid, &$errors) {
		
		// Check for a valid userid
		if (empty($userid)) {
			$errors[] = "Missing userid";
			$this->auditlog("session", "missing userid");
		}

		// Only try to query the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
			
			// Create a new session ID
			$sessionid = bin2hex(random_bytes(25));
	
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the insert operation
			$sql = "INSERT INTO usersessions (usersessionid, userid, expires) VALUES (:sessionid, :userid, DATE_ADD(NOW(), INTERVAL 7 DAY))";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":sessionid", $sessionid);
			$stmt->bindParam(":userid", $userid);
			$result = $stmt->execute();

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {

				$errors[] = "An unexpected error occurred";
				$this->debug($stmt->errorInfo());
				$this->auditlog("new session error", $stmt->errorInfo());
				return NULL;
			
			} else {

				// Store the session ID as a cookie in the browser
				setcookie('sessionid', $sessionid, time()+60*60*24*30);
				$this->auditlog("session", "new session id: $sessionid for user = $userid");

				// Return the session ID
				return $sessionid;

			}

		}

	}

	// Retrieves an existing session from the database for the specified user
	public function getSessionUser($sessionid, &$errors, $suppressLog=FALSE) {

		// Check for a valid session ID
		if (empty($sessionid)) {
			$errors[] = "Missing sessionid";
			//$this->auditlog("session", "missing session id");

		} else {
			
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the insert operation
			$sql = "SELECT usersessions.userid, email, username, registrationcode FROM usersessions left join users on usersessions.userid = users.userid WHERE usersessionid = :sessionid AND expires > now()";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":sessionid", $sessionid);
			$result = $stmt->execute();

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {

				$errors[] = "An unexpected error occurred";
				$this->debug($stmt->errorInfo());
				
				// In order to prevent recursive calling of audit log function 
				if (!$suppressLog){
					$this->auditlog("session error", $stmt->errorInfo());
				}
				
				return NULL;
			
			} else {

				$row = $stmt->fetch();

				// Return the user details
				return $row;

			}
			
		}

	}

	// Retrieves an existing session from the database for the specified user
	public function isAdmin(&$errors, $userid) {

		// Check for a valid user ID
		if (empty($userid)) {
			$errors[] = "Missing userid";
			return FALSE;
		}

		// Connect to the database
		$dbh = $this->getConnection();
	
		// Construct a SQL statement to perform the insert operation
		$sql = "SELECT isadmin FROM users WHERE userid = :userid";

		// Run the SQL select and capture the result code
		$stmt = $dbh->prepare($sql);
		$stmt->bindParam(":userid", $userid);
		$result = $stmt->execute();

		// If the query did not run successfully, add an error message to the list
		if ($result === FALSE) {

			$errors[] = "An unexpected error occurred";
			$this->debug($stmt->errorInfo());
			$this->auditlog("isadmin error", $stmt->errorInfo());
		
			return FALSE;
		
		} else {

			$row = $stmt->fetch();
			$isadmin = $row['isadmin'];

			// Return the isAdmin flag
			return $isadmin == 1;

		}
	}

	// Logs in an existing user and will return the $errors array listing any errors encountered
	public function login($username, $password, &$errors) {
		
		$this->debug("Login attempted");
		$this->auditlog("login", "attempt: $username, password length = ".strlen($password));


		// Validate the user input
		if (empty($username)) {
			$errors[] = "Missing username";
		}
		if (empty($password)) {
			$errors[] = "Missing password";
		}

		// Only try to query the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
	
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the insert operation
			$sql = "SELECT userid, passwordhash FROM users " . 
				"WHERE username = :username";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":username", $username);
			$result = $stmt->execute();

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {

				$errors[] = "An unexpected error occurred";
				$this->debug($stmt->errorInfo());
				$this->auditlog("login error", $stmt->errorInfo());


			// If the query did not return any rows, add an error message for bad username/password
			} else if ($stmt->rowCount() == 0) {

				$errors[] = "Bad username/password combination";
				$this->auditlog("login", "bad username: $username");


			// If the query ran successfully and we got back a row, then the login succeeded
			} else {

				// Get the row from the result
				$row = $stmt->fetch();

				// Check the password
				if (!password_verify($password, $row['passwordhash'])) {

					$errors[] = "Bad username/password combination";
					$this->auditlog("login", "bad password: password length = ".strlen($password));

				} else {
	
					// Create a new session for this user ID in the database
					$userid = $row['userid'];
					$sessionid = $this->newSession($userid, $errors);
					$this->auditlog("login", "success: $username, $userid");
					
				}

			}

			// Close the connection
			$dbh = NULL;
	
		} else {
			$this->auditlog("login validation error", $errors);					
		}

	
		// Return TRUE if there are no errors, otherwise return FALSE
		if (sizeof($errors) == 0){
			return TRUE; 
		} else {
			return FALSE;
		}
	}

	// Logs out the current user based on session ID
	public function logout() {
		
		$sessionid = $_COOKIE['sessionid'];

		// Only try to query the data into the database if there are no validation errors
		if (!empty($sessionid)) {
	
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the insert operation
			$sql = "DELETE FROM usersessions WHERE usersessionid = :sessionid OR expires < now()";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":sessionid", $sessionid);
			$result = $stmt->execute();

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {

				$errors[] = "An unexpected error occurred";
				$this->debug($stmt->errorInfo());
				$this->auditlog("logout error", $stmt->errorInfo());


			// If the query ran successfully, then the logout succeeded
			} else {

				// Clear the session ID cookie
				setcookie('sessionid', '', time()-3600);
				$this->auditlog("logout", "successful: $sessionid");

			}

			// Close the connection
			$dbh = NULL;
	
		}
	
	}

	// Checks for logged in user and redirects to login if not found with "page=protected" indicator in URL.
	public function protectPage(&$errors, $isAdmin = FALSE) {

		// Get the session ID from the browser cookies
		$sessionid = $_COOKIE['sessionid'];

		// If there is no session ID, then the user is not logged in
		if (empty($sessionid)) {
			
			// Redirect the user to the login page
			header("Location: login.php?page=protected");
			$this->auditlog("protect page", "no session");
			exit();

		} else {
			
			// Get the user ID from the session record
			$user = $this->getSessionUser($sessionid, $errors);
			$userid = $user["userid"];
	
			// If there is no user ID in the session, then the user is not logged in
			if(empty($userid)) {
				// Redirect the user to the login page
				$this->auditlog("protect page", "no userid: $sessionid");
				header("Location: login.php?page=protected");
				exit();
	
			} else if ($isAdmin)  {
				
				// Get the isAdmin flag from the database
				$isAdminDB = $this->isAdmin($errors, $userid);
				
				if (!$isAdminDB) {
					
					// Redirect the user to the home page
					$this->auditlog("protect page", "not admin");
					header("Location: index.php?page=protectedAdmin");
					exit();

				}

			}

		}

	}

	// Get a list of things from the database and will return the $errors array listing any errors encountered
	public function getThings(&$errors) {

		// Assume an empty list of things
		$things = array();

		// Connect to the database
		$dbh = $this->getConnection();
		
		// Get the session ID from the browser cookies
		$sessionid = $_COOKIE['sessionid'];
		
		// Get the user id from the session
		$user = $this->getSessionUser($sessionid, $errors);
		$registrationcode = $user["registrationcode"];

	
		// Construct a SQL statement to perform the select operation
		$sql = "SELECT thingid, thingname, convert_tz(things.thingcreated,@@session.time_zone,'America/New_York') as thingcreated, thinguserid, thingattachmentid, thingregistrationcode FROM things LEFT JOIN users ON things.thinguserid = users.userid WHERE thingregistrationcode = :registrationcode ORDER BY things.thingcreated ASC";

		// Run the SQL select and capture the result code
		$stmt = $dbh->prepare($sql);
		$stmt->bindParam(":registrationcode", $registrationcode);
		$result = $stmt->execute();

		// If the query did not run successfully, add an error message to the list
		if ($result === FALSE) {

			$errors[] = "An unexpected error occurred.";
			$this->debug($stmt->errorInfo());
			$this->auditlog("getthings error", $stmt->errorInfo());
			
		// If the query ran successfully, then get the list of things
		} else {

			// Get all the rows
			$things = $stmt->fetchAll();

		}

		// Close the connection
		$dbh = NULL;

		// Return the list of things
		return $things;

	}

	// Get a single thing from the database and will return the $errors array listing any errors encountered
	public function getThing($thingid, &$errors) {

		// Assume no thing exists for this thing id
		$thing = NULL;
		
		// Check for a valid thing ID
		if (empty($thingid)){
			$errors[] = "Missing thing ID";
		}

		if (sizeof($errors) == 0){ 

			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the select operation
			$sql = "SELECT things.thingid, things.thingname, convert_tz(things.thingcreated,@@session.time_zone,'America/New_York') as thingcreated, things.thinguserid, things.thingattachmentid, things.thingregistrationcode, username, filename " . 
				"FROM things LEFT JOIN users ON things.thinguserid = users.userid " . 
				"LEFT JOIN attachments ON things.thingattachmentid = attachments.attachmentid " .
				"WHERE thingid = :thingid";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":thingid", $thingid);
			$result = $stmt->execute();	

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {
	
				$errors[] = "An unexpected error occurred.";
				$this->debug($stmt->errorInfo());
				$this->auditlog("getthing error", $stmt->errorInfo());
	
			// If no row returned then the thing does not exist in the database.
			} else if ($stmt->rowCount() == 0) {
				
				$errors[] = "Thing not found";
				$this->auditlog("getThing", "bad thing id: $thingid");
				
			// If the query ran successfully and row was returned, then get the details of the thing				
			} else {
	
				// Get the thing
				$thing = $stmt->fetch();

			}
	
			// Close the connection
			$dbh = NULL;

		} else {
			$this->auditlog("getThing validation error", $errors);					
		}

		// Return the thing
		return $thing;

	}

	// Get a list of comments from the database
	public function getComments($thingid, &$errors) {
		
		// Assume an empty list of comments
		$comments = array();
		


		// Check for a valid thing ID
		if (empty($thingid)) {

			// Add an appropriate error message to the list
			$errors[] = "Missing thing ID";
			$this->auditlog("getComments validation error", $errors);	

		} else {
			
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the select operation
			$sql = "SELECT commentid, commenttext, convert_tz(comments.commentposted,@@session.time_zone,'America/New_York') as commentposted, username, attachmentid, filename " . 
				"FROM comments LEFT JOIN users ON comments.commentuserid = users.userid " . 
				"LEFT JOIN attachments ON comments.commentattachmentid = attachments.attachmentid " .
				"WHERE commentthingid = :thingid ORDER BY commentposted ASC";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":thingid", $thingid);
			$result = $stmt->execute();
	
			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {
	
				$errors[] = "An unexpected error occurred loading the comments.";
				$this->debug($stmt->errorInfo());
				$this->auditlog("getcomments error", $stmt->errorInfo());
	
			// If the query ran successfully, then get the list of comments
			} else {
	
				// Get all the rows
				$comments = $stmt->fetchAll();

			}
	
			// Close the connection
			$dbh = NULL;
	
		} 

		// Return the list of comments
		return $comments;

	}

	// Handles the saving of uploaded attachments and the creation of a corresponding record in the attachments table.	
	public function saveAttachment($dbh, $attachment, &$errors) {
		
		$attachmentid = NULL;

		// Check for an attachment
		if (isset($attachment) && isset($attachment['name']) && !empty($attachment['name'])) {

			// Get the list of valid attachment types and file extensions
			$attachmenttypes = $this->getAttachmentTypes($errors);

			// Construct an array containing only the 'extension' keys
			$extensions = array_column($attachmenttypes, 'extension');

			// Get the uploaded filename
			$filename = $attachment['name'];

			// Extract the uploaded file's extension
			$dot = strrpos($filename, ".");

			// Make sure the file has an extension and the last character of the name is not a "."
			if ($dot !== FALSE && $dot != strlen($filename)) {
				
				// Check to see if the uploaded file has an allowed file extension
				$extension = strtolower(substr($filename, $dot + 1));
				if (!in_array($extension, $extensions)) {
					
					// Not a valid file extension
					$errors[] = "File does not have a valid file extension";
					$this->auditlog("saveAttachment", "invalid file extension: $filename");

				}

			} else {

				// No file extension -- Disallow
				$errors[] = "File does not have a valid file extension";
				$this->auditlog("saveAttachment", "no file extension: $filename");

			}
			
			// Only attempt to add the attachment to the database if the file extension was good
			if (sizeof($errors) == 0) {

				// Create a new ID
				$attachmentid = bin2hex(random_bytes(16));
	
				// Construct a SQL statement to perform the insert operation
				$sql = "INSERT INTO attachments (attachmentid, filename) VALUES (:attachmentid, :filename)";
		
				// Run the SQL insert and capture the result code
				$stmt = $dbh->prepare($sql);
				$stmt->bindParam(":attachmentid", $attachmentid);
				$stmt->bindParam(":filename", $filename);
				$result = $stmt->execute();
		
				// If the query did not run successfully, add an error message to the list
				if ($result === FALSE) {
	
					$errors[] = "An unexpected error occurred storing the attachment.";
					$this->debug($stmt->errorInfo());
					$this->auditlog("saveAttachment error", $stmt->errorInfo());
	
				} else {
	
					// Move the file from temp folder to html attachments folder
					move_uploaded_file($attachment['tmp_name'], getcwd() . '/attachments/' . $attachmentid . '-' . $attachment['name']);
					$attachmentname = $attachment["name"];
					$this->auditlog("saveAttachment", "success: $attachmentname");
	
				}

			}
			
		}

		return $attachmentid;

	}

	// Adds a new thing to the database
	public function addThing($name, $attachment, &$errors) {

		// Get the session ID from the browser cookies
		$sessionid = $_COOKIE['sessionid'];

		// Get the user id from the session
		$user = $this->getSessionUser($sessionid, $errors);
		$userid = $user["userid"];
		$registrationcode = $user["registrationcode"];

		// Validate the user input
		if (empty($userid)) {
			$errors[] = "Missing user ID. Not logged in?";
		}
		if (empty($name)) {
			$errors[] = "Missing thing name";
		}
	
		// Only try to insert the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
	
			// Connect to the database
			$dbh = $this->getConnection();
			$attachmentid = $this->saveAttachment($dbh, $attachment, $errors);
			
			// Only try to insert the data into the database if the attachment successfully saved
			if (sizeof($errors) == 0) {

				// Create a new ID
				$thingid = bin2hex(random_bytes(16));
	
				// Add a record to the things table
				// Construct a SQL statement to perform the insert operation
				$sql = "INSERT INTO things (thingid, thingname, thingcreated, thinguserid, thingattachmentid, thingregistrationcode) VALUES (:thingid, :name, now(), :userid, :attachmentid, :registrationcode)";
		
				// Run the SQL insert and capture the result code
				$stmt = $dbh->prepare($sql);
				$stmt->bindParam(":thingid", $thingid);
				$stmt->bindParam(":name", $name);
				$stmt->bindParam(":userid", $userid);
				$stmt->bindParam(":attachmentid", $attachmentid);
				$stmt->bindParam(":registrationcode", $registrationcode);
				$result = $stmt->execute();
		
				// If the query did not run successfully, add an error message to the list
				if ($result === FALSE) {
					
					$errors[] = "An unexpected error occurred adding the thing to the database.";
					$this->debug($stmt->errorInfo());
					$this->auditlog("addthing error", $stmt->errorInfo());

				} else {

					$this->auditlog("addthing", "success: $name, id = $thingid");

				}

			}	

			// Close the connection
			$dbh = NULL;

		} else {
			$this->auditlog("addthing validation error", $errors);					
		}
	
		// Return TRUE if there are no errors, otherwise return FALSE
		if (sizeof($errors) == 0){
			return TRUE; 
		} else {
			return FALSE;
		}
	}

	// Adds a new comment to the database
	public function addComment($text, $thingid, $attachment, &$errors) {
		
		// Get the session ID from the browser cookies
		$sessionid = $_COOKIE['sessionid'];

		// Get the user id from the session
		$user = $this->getSessionUser($sessionid, $errors);
		$userid = $user["userid"];

		// Validate the user input
		if (empty($userid)) {
			$errors[] = "Missing user ID. Not logged in?";
		}
		if (empty($thingid)) {
			$errors[] = "Missing thing ID";
		}
		if (empty($text)) {
			$errors[] = "Missing comment text";
		}
	
		// Only try to insert the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
	
			// Connect to the database
			$dbh = $this->getConnection();

			$attachmentid = $this->saveAttachment($dbh, $attachment, $errors);

			// Only try to insert the data into the database if the attachment successfully saved
			if (sizeof($errors) == 0) {
			
				// Create a new ID
				$commentid = bin2hex(random_bytes(16));
				
				// Add a record to the Comments table
				// Construct a SQL statement to perform the insert operation
				$sql = "INSERT INTO comments (commentid, commenttext, commentposted, commentuserid, commentthingid, commentattachmentid) " . 
					"VALUES (:commentid, :text, now(), :userid, :thingid, :attachmentid)";
		
				// Run the SQL insert and capture the result code
				$stmt = $dbh->prepare($sql);
				$stmt->bindParam(":commentid", $commentid);
				$stmt->bindParam(":text", $text);
				$stmt->bindParam(":userid", $userid);
				$stmt->bindParam(":thingid", $thingid);
				$stmt->bindParam(":attachmentid", $attachmentid);
				$result = $stmt->execute();
		
				// If the query did not run successfully, add an error message to the list
				if ($result === FALSE) {
					$errors[] = "An unexpected error occurred saving the comment to the database.";
					$this->debug($stmt->errorInfo());
					$this->auditlog("addcomment error", $stmt->errorInfo());
				} else {
					$this->auditlog("addcomment", "success: $commentid");
				}

			}
	
			// Close the connection
			$dbh = NULL;
	
		} else {
			$this->auditlog("addcomment validation error", $errors);					
		}
	
		// Return TRUE if there are no errors, otherwise return FALSE
		if (sizeof($errors) == 0){
			return TRUE; 
		} else {
			return FALSE;
		}
	}

	// Get a list of users from the database and will return the $errors array listing any errors encountered
	public function getUsers(&$errors) {

		// Assume an empty list of topics
		$users = array();

		// Connect to the database
		$dbh = $this->getConnection();
	
		// Construct a SQL statement to perform the select operation
		$sql = "SELECT userid, username, email, isadmin FROM users ORDER BY username";

		// Run the SQL select and capture the result code
		$stmt = $dbh->prepare($sql);
		$result = $stmt->execute();

		// If the query did not run successfully, add an error message to the list
		if ($result === FALSE) {

			$errors[] = "An unexpected error occurred getting the user list.";
			$this->debug($stmt->errorInfo());
			$this->auditlog("getusers error", $stmt->errorInfo());

		// If the query ran successfully, then get the list of users
		} else {

			// Get all the rows
			$users = $stmt->fetchAll();
			$this->auditlog("getusers", "success");

		}

		// Close the connection
		$dbh = NULL;

		// Return the list of users
		return $users;

	}

	// Gets a single user from database and will return the $errors array listing any errors encountered
	public function getUser($userid, &$errors) {

		// Assume no user exists for this user id
		$user = NULL;

		// Validate the user input
		if (empty($userid)) {
			$errors[] = "Missing userid";
		} 
		
		if(sizeof($errors)== 0) {
			
			// Get the session ID from the browser cookies
			$sessionid = $_COOKIE['sessionid'];
	
			// Get the user id from the session
			$user = $this->getSessionUser($sessionid, $errors);
			$loggedinuserid = $user["userid"];
			$isadmin = FALSE;
	
			// Check to see if the user really is logged in and really is an admin
			if ($loggedinuserid != NULL) {
				$isadmin = $this->isAdmin($errors, $loggedinuserid);
			}
	
			// Stop people from viewing someone else's profile
			if (!$isadmin && $loggedinuserid != $userid) {

				$errors[] = "Cannot view other user";
				$this->auditlog("getuser", "attempt to view other user: $loggedinuserid");
				
			} else {
	
				// Only try to insert the data into the database if there are no validation errors
				if (sizeof($errors) == 0) {
			
					// Connect to the database
					$dbh = $this->getConnection();
				
					// Construct a SQL statement to perform the select operation
					$sql = "SELECT userid, username, email, isadmin FROM users WHERE userid = :userid";
			
					// Run the SQL select and capture the result code
					$stmt = $dbh->prepare($sql);
					$stmt->bindParam(":userid", $userid);
					$result = $stmt->execute();
		
					// If the query did not run successfully, add an error message to the list
					if ($result === FALSE) {
		
						$errors[] = "An unexpected error occurred retrieving the specified user.";
						$this->debug($stmt->errorInfo());
						$this->auditlog("getuser error", $stmt->errorInfo());
		
					// If the query did not return any rows, add an error message for invalid user id
					} else if ($stmt->rowCount() == 0) {
		
						$errors[] = "Bad userid";
						$this->auditlog("getuser", "bad userid: $userid");
		
					// If the query ran successfully and we got back a row, then the request succeeded
					} else {
		
						// Get the row from the result
						$user = $stmt->fetch();
		
					}
		
					// Close the connection
					$dbh = NULL;
			
				} else {
					$this->auditlog("getuser validation error", $errors);					
				}
			}
		} else {
			$this->auditlog("getuser validation error", $errors);					
		}

		// Return user if there are no errors, otherwise return NULL
		return $user;
	}


	// Updates a single user in the database and will return the $errors array listing any errors encountered
	public function updateUser($userid, $username, $email, $password, $isadminDB, &$errors) {

		// Assume no user exists for this user id
		$user = NULL;

		// Validate the user input
		if (empty($userid)) {

			$errors[] = "Missing userid";

		} 
		
		if(sizeof($errors) == 0) {
			
			// Get the session ID from the browser cookies
			$sessionid = $_COOKIE['sessionid'];
	
			// Get the user id from the session
			$user = $this->getSessionUser($sessionid, $errors);
			$loggedinuserid = $user["userid"];
			$isadmin = FALSE;
	
			// Check to see if the user really is logged in and really is an admin
			if ($loggedinuserid != NULL) {
				$isadmin = $this->isAdmin($errors, $loggedinuserid);
			}

			// Stop people from editing someone else's profile
			if (!$isadmin && $loggedinuserid != $userid) {

				$errors[] = "Cannot edit other user";
				$this->auditlog("getuser", "attempt to update other user: $loggedinuserid");
	
			} else {

				// Validate the user input
				if (empty($userid)) {
					$errors[] = "Missing userid";
				}
				if (empty($username)) {
					$errors[] = "Missing username";
				}
				if (empty($email)) {
					$errors[] = "Missing email;";
				}

				// Only try to update the data into the database if there are no validation errors
				if (sizeof($errors) == 0) {
			
					// Connect to the database
					$dbh = $this->getConnection();
				
					// Hash the user's password
					$passwordhash = password_hash($password, PASSWORD_DEFAULT);
	
					// Construct a SQL statement to perform the select operation
					$sql = 	"UPDATE users SET username=:username, email=:email " .
							($loggedinuserid != $userid ? ", isadmin=:isAdmin " : "") .
							(!empty($password) ? ", passwordhash=:passwordhash" : "") .
							" WHERE userid = :userid";
			
					// Run the SQL select and capture the result code
					$stmt = $dbh->prepare($sql);
					$stmt->bindParam(":username", $username);
					$stmt->bindParam(":email", $email);
					$adminFlag = ($isadminDB ? "1" : "0");
					if ($loggedinuserid != $userid) {
						$stmt->bindParam(":isAdmin", $adminFlag);
					}
					if (!empty($password)) {
						$stmt->bindParam(":passwordhash", $passwordhash);
					}
					$stmt->bindParam(":userid", $userid);
					$result = $stmt->execute();
		
					// If the query did not run successfully, add an error message to the list
					if ($result === FALSE) {
						$errors[] = "An unexpected error occurred saving the user profile. ";
						$this->debug($stmt->errorInfo());
						$this->auditlog("updateUser error", $stmt->errorInfo());
					} else {
						$this->auditlog("updateUser", "success");
					}
					
					// Close the connection
					$dbh = NULL;
				} else {
					$this->auditlog("updateUser validation error", $errors);					
				}
			}
		} else {
			$this->auditlog("updateUser validation error", $errors);					
		}

		// Return TRUE if there are no errors, otherwise return FALSE
		if (sizeof($errors) == 0){
			return TRUE; 
		} else {
			return FALSE;
		}
	}


	function getFile($name){
		return file_get_contents($name);
	}

	// Get a list of users from the database and will return the $errors array listing any errors encountered
	public function getAttachmentTypes(&$errors) {

		// Assume an empty list of topics
		$types = array();

		// Connect to the database
		$dbh = $this->getConnection();
	
		// Construct a SQL statement to perform the select operation
		$sql = "SELECT attachmenttypeid, name, extension FROM attachmenttypes ORDER BY name";

		// Run the SQL select and capture the result code
		$stmt = $dbh->prepare($sql);
		$result = $stmt->execute();

		// If the query did not run successfully, add an error message to the list
		if ($result === FALSE) {

			$errors[] = "An unexpected error occurred getting the attachment types list.";
			$this->debug($stmt->errorInfo());
			$this->auditlog("getattachmenttypes error", $stmt->errorInfo());

		// If the query ran successfully, then get the list of users
		} else {

			// Get all the rows
			$types = $stmt->fetchAll();
			$this->auditlog("getattachmenttypes", "success");

		}

		// Close the connection
		$dbh = NULL;

		// Return the list of users
		return $types;

	}

	// Creates a new session in the database for the specified user
	public function newAttachmentType($name, $extension, &$errors) {
		
		$attachmenttypeid = NULL;

		// Check for a valid name
		if (empty($name)) {
			$errors[] = "Missing name";
		}
		// Check for a valid extension
		if (empty($extension)) {
			$errors[] = "Missing extension";
		}

		// Only try to query the data into the database if there are no validation errors
		if (sizeof($errors) == 0) {
			
			// Create a new session ID
			$attachmenttypeid = bin2hex(random_bytes(25));
	
			// Connect to the database
			$dbh = $this->getConnection();
		
			// Construct a SQL statement to perform the insert operation
			$sql = "INSERT INTO attachmenttypes (attachmenttypeid, name, extension) VALUES (:attachmenttypeid, :name, :extension)";
	
			// Run the SQL select and capture the result code
			$stmt = $dbh->prepare($sql);
			$stmt->bindParam(":attachmenttypeid", $attachmenttypeid);
			$stmt->bindParam(":name", $name);
			$stmt->bindParam(":extension", strtolower($extension));
			$result = $stmt->execute();

			// If the query did not run successfully, add an error message to the list
			if ($result === FALSE) {

				$errors[] = "An unexpected error occurred";
				$this->debug($stmt->errorInfo());
				$this->auditlog("newAttachmentType error", $stmt->errorInfo());
				return NULL;

			}

		} else {

			$this->auditlog("newAttachmentType error", $errors);
			return NULL;

		}
		
		return $attachmenttypeid;
	}

}


?>