<?php
// Enforce strict typing
declare(strict_types=1);

$db_host = 'localhost';
$db_name = 'kami';
$db_user = 'root'; // Default XAMPP user
$db_pass = '';     // Default XAMPP password is blank

try {
    // Establish the PDO connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // Set PDO to throw exceptions on errors (Crucial for debugging)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable emulated prepared statements for maximum security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    // Kill the script and log the error if connection fails
    die("Database connection failed: " . $e->getMessage());
}
?>