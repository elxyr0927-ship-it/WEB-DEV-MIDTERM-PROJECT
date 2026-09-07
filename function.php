<?php

require 'database/config.php';
require 'validation.php';

if (!isset($_POST['add-student'])) {
    header('Location: index.php');
    exit;
}

$result = validateStudentInput($_POST);
$errors = $result['errors'];

if (!empty($errors)) {
    $message = implode(' ', $errors);
    header('Location: index.php?status=error&message=' . urlencode($message));
    exit;
}

try {
    $pdo = getConnection();

    $sql = "INSERT INTO user (username, email, age)
            VALUES (:username, :email, :age)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $result['data']['username']);
    $stmt->bindValue(':email', $result['data']['email']);
    $stmt->bindValue(':age', $result['data']['age'], PDO::PARAM_INT);
    $stmt->execute();

    $newId = $pdo->lastInsertId();
    header('Location: success.php?id=' . $newId);
    exit;
} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
}

exit;
