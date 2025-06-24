<?php
session_start();
require 'db.php';

$post_id = $_GET['id'] ?? 0;

// Fetch post details
$stmt = $pdo->prepare("
    SELECT 
        posts.*, 
        users.username,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    WHERE posts.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: index.php");
    exit();
}

// Check if user has liked the post
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    $post['user_liked'] = $stmt->fetch() ? true : false;
}

// Fetch comments
$stmt = $pdo->prepare("
    SELECT comments.*, users.username 
    FROM comments 
    JOIN users ON comments.user_id = users.id 
    WHERE post_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> | Modern Blog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .post-media {
            max-height: 60vh;
            object-fit: contain;
            background-color: #000;
            border-radius: 10px;
        }
        
        .like-btn.liked {
            color: var(--danger);
        }
        
        .comment-box {
            border-left: 3px solid var(--primary);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">Modern Blog</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="posts/create_post.php">
                                <i class="fas fa-plus"></i> New Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <?php if($post['is_video'] && $post['video_path']): ?>
                        <video class="post-media w-100" controls>
                            <source src="<?= htmlspecialchars($post['video_path']) ?>" type="video/mp4">
                        </video>
                    <?php elseif($post['image_path']): ?>
                        <img src="<?= htmlspecialchars($post['image_path']) ?>" class="post-media w-100" alt="Post image">
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h1 class="card-title"><?= htmlspecialchars($post['title']) ?></h1>
                        <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <button class="btn btn-sm like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                        onclick="toggleLike(<?= $post['id'] ?>, this)">
                                    <i class="<?= $post['user_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                    <span class="like-count"><?= $post['like_count'] ?></span>
                                </button>
                                <span class="ms-3 text-muted">
                                    <i class="far fa-comment"></i> <?= $post['comment_count'] ?> comments
                                </span>
                            </div>
                            <div class="text-muted">
                                <small>Posted by <?= htmlspecialchars($post['username']) ?> on <?= date('M j, Y', strtotime($post['created_at'])) ?></small>
                            </div>
                        </div>
                        
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
                            <div class="mt-3">
                                <a href="posts/edit_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="posts/delete_post.php?id=<?= $post['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('Are you sure?')">
                                   <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="far fa-comments"></i> Comments</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <form class="mb-4" id="commentForm" onsubmit="addComment(event, <?= $post['id'] ?>)">
                                <div class="form-group">
                                    <textarea class="form-control" id="commentInput" rows="3" placeholder="Add a comment..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary mt-2">Post Comment</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <a href="auth/login.php">Login</a> to post comments
                            </div>
                        <?php endif; ?>
                        
                        <div id="commentsContainer">
                            <?php foreach($comments as $comment): ?>
                                <div class="comment-box mb-3 p-3">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                        <small class="text-muted"><?= date('M j, Y g:i a', strtotime($comment['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 mt-2"><?= htmlspecialchars($comment['content']) ?></p>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($comments)): ?>
                                <p class="text-muted text-center">No comments yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <a href="admin_dashboard.php" class="btn btn-outline-primary mb-4">
                    <i class="fas fa-arrow-left"></i> Back to Admin Panel
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Like functionality
        async function toggleLike(postId, button) {
            try {
                const response = await fetch('actions/like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ post_id: postId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const icon = button.querySelector('i');
                    const countSpan = button.querySelector('.like-count');
                    
                    if (data.action === 'liked') {
                        button.classList.add('liked');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    } else {
                        button.classList.remove('liked');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                    countSpan.textContent = data.like_count;
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        // Comment functionality
        async function addComment(event, postId) {
            event.preventDefault();
            const form = event.target;
            const input = form.querySelector('#commentInput');
            const content = input.value.trim();
            
            if (!content) return;
            
            try {
                const response = await fetch('actions/comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ 
                        post_id: postId,
                        content: content
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Clear the input
                    input.value = '';
                    
                    // Reload comments
                    location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>