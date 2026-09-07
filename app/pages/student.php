<?php

require_once __DIR__ . '/../database/config.php';

$pdo  = getConnection();
$sql  = "SELECT id, username, email, age FROM user ORDER BY id ASC";
$stmt = $pdo->query($sql);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>All Students</title>
</head>

<body>

    <h1>All Students</h1>

    <p><a href="index.php">Register a new student</a></p>

    <?php if (empty($students)): ?>

        <p>No students registered yet.</p>

    <?php else: ?>

        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Age</th>
            </tr>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['id']) ?></td>
                    <td><?= htmlspecialchars($student['username']) ?></td>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                    <td><?= htmlspecialchars($student['age']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

    <?php endif; ?>

</body>

</html>