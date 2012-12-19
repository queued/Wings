<?php
define('DS', DIRECTORY_SEPARATOR);

require dirname(__FILE__) . DS . 'Wings' . DS . 'Exceptions' . DS . 'ExecutionException.php';
require dirname(__FILE__) . DS . 'Wings' . DS . 'Exceptions' . DS . 'FlyingException.php';
require dirname(__FILE__) . DS . 'Wings' . DS . 'Exceptions' . DS . 'FetchException.php';
require dirname(__FILE__) . DS . 'Wings' . DS . 'ORM.php';

// We need to configure the Wings to make it "flyable" :)
Wings\ORM::configure('mysql:host=localhost;dbname=wings');
Wings\ORM::authenticate('username', 'password');

// Let's fly! :)
$wings = Wings\ORM::fly('table_name');
$wings->select(array('field1', 'field2'), 'id = 1', 1);
// The above example will return this query (result): SELECT `field1`, `field2' FROM `table_name` WHERE (`id` = '1') LIMIT 0,1 DESC

// How many rows has been selected?
echo "Selected rows: " . (int) $wings->count() . "\n<br />\n";

// Executed queries
echo "Executed queries so far: " . (int) $wings->queries() . "\n";

// Prints the selected data
echo "<pre>";
print_r($wings->fetch());
echo "</pre>";

// Closes the connection
$wings->close();
?>