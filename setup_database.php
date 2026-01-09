<?php
// Database Setup Script
$host = "localhost";
$username = "root";
$password = "";

// Connect to MySQL
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read and execute schema file
$sql = file_get_contents('database/schema.sql');

// Execute multi-query
if ($conn->multi_query($sql)) {
    echo "Database created successfully!<br>";
    echo "Tables created successfully!<br>";
    echo "<a href='index.html'>Go to Login Page</a>";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>