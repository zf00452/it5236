var mysql = require('./node_modules/mysql');
var config = require('./config.json');
var validator = require('./validation.js');

function formatErrorResponse(code, errs) {
	return JSON.stringify({ 
		error  : code,
		errors : errs
	});
}

exports.handler = (event, context, callback) => {
	//instruct the function to return as soon as the callback is invoked
	context.callbackWaitsForEmptyEventLoop = false;

	//validate input
	var errors = new Array();
	
	 // Validate the user input
	validator.validatePasswordHash(event.passwordHash, errors);
	
	if(errors.length > 0) {
		// This should be a "Bad Request" error
		callback(formatErrorResponse('BAD_REQUEST', errors));
	}else {
		//getConnection equivalent
		var conn = mysql.createConnection({
			host 	: config.dbhost,
			user 	: config.dbuser,
			password : config.dbpassword,
			database : config.dbname
		});
		//attempts to connect to the database
		conn.connect(function(err) {
		  	if (err)  {
				// This should be a "Internal Server Error" error
				
				callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
				
			} else {
				console.log("Connected!");
				var sql = "UPDATE users SET passwordhash= ? WHERE userid = ?";
				
				conn.query(sql, [event.passwordHash, event.userid], function (err, result) {
					if (err) {
						// This should be a "Internal Server Error" error
						callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
				  	}else {
						console.log("successful registration");
						callback(null, result);
					
					}
					
				});
			}
		});
	}
	
}//end handler