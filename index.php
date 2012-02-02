<?php
/**
 * Example of a Phatso application
 */

$ROUTES = array(
	'/'           => 'Index',  // The specific URL "/" goes here
	'/hello/(.*)' => 'Hello',  // Any URL starting with "/hello/" goes here
	                           // Everything after "/hello/" is an inline parameter
);

require_once('phatso.php');

/* Uncomment this block if you require a database.
 *
require_once('db/pdo.php');
$conn = array(
	'dsn'  => 'mysql:host=localhost;dbname=phatso',
	'user' => 'root',
	'pass' => ''
);
$db = new Database($conn, true);
 *
 */

$app = new Phatso();
//$app->db =& $db;
$app->run($ROUTES);

function exec_Index(&$app, $params) {
	$app->render('index.php');
}

function exec_Hello(&$app, $params) {
	// Inline parameters will show up in $params
	// To access normal GET/POST params, use the PHP globals $_GET and $_POST
	$app->set('params', $params);
	$app->render('hello.php');
}

?>
