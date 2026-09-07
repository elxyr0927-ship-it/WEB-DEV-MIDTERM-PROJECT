<?php
$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
$id      = $_GET['id'] ?? null;

require 'database/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo  = getConnection();
$sql  = "SELECT id, username, email, age FROM user WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Registered</title>
</head>

<body>

    <h1 style="color: green;">Student registered successfully!</h1>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>ID</th>
            <td><?= htmlspecialchars($student['id']) ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?= htmlspecialchars($student['username']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= htmlspecialchars($student['email']) ?></td>
        </tr>
        <tr>
            <th>Age</th>
            <td><?= htmlspecialchars($student['age']) ?></td>
        </tr>
    </table>

    <p><a href="index.php">Register another student</a></p>

</body>

</html>