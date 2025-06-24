<?php
require_once __DIR__ . '/../blog/middleware/authMiddleware.php';
requireAdmin(); // This will now work correctly

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_role'])) {
        $userId = $_POST['user_id'];
        $roleId = $_POST['role_id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO user_role (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$userId, $roleId]);
            $_SESSION['success_message'] = "Role assigned successfully";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error assigning role: " . $e->getMessage();
        }
        header("Location: roles.php");
        exit;
    }
}

// Get all users and roles
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();

// Get current role assignments
$userRoles = $pdo->query("
    SELECT ur.user_id, ur.role_id, u.username, r.name as role_name 
    FROM user_role ur
    JOIN users u ON ur.user_id = u.id
    JOIN roles r ON ur.role_id = r.id
    ORDER BY u.username
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Role Management</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">Assign Role to User</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" name="user_id" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Role</label>
                        <select class="form-select" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign_role" class="btn btn-primary">Assign Role</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Current Role Assignments</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userRoles as $assignment): ?>
                            <tr>
                                <td><?= htmlspecialchars($assignment['username']) ?></td>
                                <td><?= htmlspecialchars($assignment['role_name']) ?></td>
                                <td>
                                    <form method="POST" action="remove_role.php" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $assignment['user_id'] ?>">
                                        <input type="hidden" name="role_id" value="<?= $assignment['role_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>