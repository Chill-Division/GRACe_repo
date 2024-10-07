<?php
require_once 'config.php';
session_start(); // Start the session

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the login form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get username and password from the form
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Basic input validation
    if (empty($username) || empty($password)) {
        header("Location: index.php?error=Username and password are required");
        exit();
    }

    // Sanitize input data (prevent SQL injection)
    $username = $conn->real_escape_string($username);

    // Query the database to find the user
    $sql = "SELECT id, password FROM Users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $hashedPassword = $row['password'];

        // Verify the password
        if (password_verify($password, $hashedPassword)) {
            // Password is correct, create a session
            $_SESSION['user_id'] = $row['id'];

            // Update the last_logged_in timestamp (optional)
            $updateSql = "UPDATE Users SET last_logged_in = NOW() WHERE id = " . $row['id'];
            $conn->query($updateSql);

            // Redirect to the home page or another protected page
            header("Location: tracking.php"); // Adjust the redirect URL as needed
            exit();
        } else {
            // Incorrect password
            header("Location: index.php?error=Incorrect username or password");
            exit();
        }
    } else {
        // User not found
        header("Location: index.php?error=Incorrect username or password");
        exit();
    }
}

$conn->close();
?>
