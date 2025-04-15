<?php

$servername = "localhost";
$username = "";
$password = "";
$dbname = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
	echo "Connection failed: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8');
	exit();
}
?>
