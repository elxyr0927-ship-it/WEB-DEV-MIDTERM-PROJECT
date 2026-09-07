<?php


function getConnection(): PDO
{
    $host = 'localhost';
    $db   = 'yocor_express';
    $user = 'root';
    $pass = 'root';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
