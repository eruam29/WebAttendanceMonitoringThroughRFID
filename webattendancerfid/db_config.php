<?php
// Database configuration
$servername = "srv1865.hstgr.io";
$username = "u659921429_Tech";
$password = "Tech2992";
$dbname = "u659921429_dbnew";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}


?>