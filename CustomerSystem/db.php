<?php
$host = getenv('MYSQLHOST') ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: 'manaklay_db';
$user = getenv('MYSQLUSER') ?: 'root'; 
$pass = getenv('MYSQLPASSWORD') ?: '123456';
$port = getenv('MYSQLPORT') ?: '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>