<?php
$servername = "mysql";
$username = "lamp_user";
$password = "lamp_password123";
$dbname = "lamp_db";

echo "<h1>LAMP Stack with Docker Compose</h1>";

// Test PHP
echo "<h2>PHP Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Time: " . date('Y-m-d H:i:s') . "</p>";

// Test MySQL Connection
echo "<h2>Database Connection</h2>";
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connected to MySQL successfully!</p>";

    // Create a test table and insert data
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert sample data if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO users (name, email) VALUES 
            ('John Doe', 'john@example.com'),
            ('Jane Smith', 'jane@example.com'),
            ('Bob Johnson', 'bob@example.com')");
    }

    // Display users
    echo "<h3>Sample Users:</h3>";
    $stmt = $pdo->query("SELECT * FROM users");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Created At</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
}

// Test Apache
echo "<h2>Server Information</h2>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<h2>Access Points</h2>";
echo "<ul>";
echo "<li><a href='http://localhost:8080' target='_blank'>Web Application (Port 8080)</a></li>";
echo "<li><a href='http://localhost:8081' target='_blank'>phpMyAdmin (Port 8081)</a></li>";
echo "</ul>";
?>