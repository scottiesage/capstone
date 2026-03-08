<?php
$host = "localhost";
$username = "root";        // your mysql username
$password = "";            // your mysql password
$database = "capstone"; // your database name

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>