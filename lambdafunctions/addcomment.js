var mysql = require('./node_modules/mysql');
var config = require('./config.json');
var validator = require('./validation.js');

//errors
function formatErrorResponse(code, errs) {
	return JSON.stringify({
		error  : code,
		errors : errs
	});
}

exports.handler = (event, context, callback) => {
	//instruct the function to return as soon as the callback is invoked
	context.callbackWaitsForEmptyEventLoop = false;

	var conn = mysql.createConnection({
		host 	: config.dbhost,
		user 	: config.dbuser,
		password : config.dbpassword,
		database : config.dbname
	});

	//prevent timeout from waiting event loop
	context.callbackWaitsForEmptyEventLoop = false;

	//attempts to connect to the database
	conn.connect(function(err) {
		if (err)  {
			// This should be a "Internal Server Error" error
			callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
			setTimeout(function() {conn.end();}, 3000);
		};

		console.log("Connected!");
		var sql = "INSERT INTO comments (commentid, commenttext, commentposted, commentuserid, commentthingid, commentattachmentid) " +
		    "VALUES (?, ?, now(), ?, ?, ?)";
		conn.query(sql, [event.commentid, event.commenttext, event.commentuserid, event.commentthingid, event.commentattachmentid], function (err, result) {
			if (err) {
				callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
				setTimeout(function() {conn.end();}, 3000);
			} else {
				console.log("successful comment addition");
				callback(null, "Comment Added");
				setTimeout(function() {conn.end();}, 3000);
			}
		});
	});//end of connection function

}// end of exports.handler