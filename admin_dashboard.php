<?php
session_start();
require 'db.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Get admin info
$admin = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$admin->execute([$_SESSION['admin_id']]);
$adminData = $admin->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .admin-header {
            background-color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-dark">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>Admin Panel</h4>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_posts.php">
                                <i class="fas fa-newspaper"></i>
                                Manage Posts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="admin-header d-flex justify-content-between align-items-center">
                    <h2>Dashboard</h2>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Welcome, <?= htmlspecialchars($adminData['username']) ?></span>
                        <?php if ($adminData['avatar']): ?>
                            <img src="<?= htmlspecialchars($adminData['avatar']) ?>" alt="Admin Avatar" width="40" height="40" class="rounded-circle">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <?= strtoupper(substr($adminData['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Quick Stats</h5>
                                <?php
                                // Get counts
                                $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                                $postsCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
                                $commentsCount = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
                                ?>
                                <div class="mb-3">
                                    <span class="text-muted">Total Users:</span>
                                    <span class="float-end fw-bold"><?= $usersCount ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Total Posts:</span>
                                    <span class="float-end fw-bold"><?= $postsCount ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Total Comments:</span>
                                    <span class="float-end fw-bold"><?= $commentsCount ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Activity</h5>
                                <?php
                                $recentPosts = $pdo->query("
                                    SELECT posts.*, users.username 
                                    FROM posts 
                                    JOIN users ON posts.user_id = users.id 
                                    ORDER BY created_at DESC 
                                    LIMIT 5
                                ")->fetchAll();
                                ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentPosts as $post): ?>
                                        <li class="list-group-item">
                                            <small class="text-muted"><?= date('M j, Y', strtotime($post['created_at'])) ?></small><br>
                                            <a href="../post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                                            <span class="text-muted">by <?= htmlspecialchars($post['username']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>