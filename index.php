<?php
session_start();
require 'db.php';

// Search functionality
$search = '';
if (isset($_GET['search'])){
    $search = trim($_GET['search']);
}

// Pagination
$perPage = 2;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Base query - removed users.avatar from the select
$query = "
    SELECT 
        posts.*, 
        users.username,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
    FROM posts 
    JOIN users ON posts.user_id = users.id 
";

// Add search condition if search term exists
if (!empty($search)) {
    $query .= " WHERE posts.title LIKE :search OR posts.content LIKE :search";
}

$query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// Prepare and execute the query
$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
}

$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM posts";
if (!empty($search)) {
    $countQuery .= " WHERE title LIKE :search OR content LIKE :search";
}
$countStmt = $pdo->prepare($countQuery);
if (!empty($search)) {
    $countStmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
}
$countStmt->execute();
$totalPosts = $countStmt->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

// Check if user has liked each post
if (isset($_SESSION['user_id'])) {
    foreach ($posts as &$post) {
        $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post['id'], $_SESSION['user_id']]);
        $post['user_liked'] = $stmt->fetch() ? true : false;
    }
    unset($post);
}
?>

<!-- Rest of your HTML code remains the same, just modify the avatar display part -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Blog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary:rgb(255, 255, 255);
            --primary-light: rgba(67, 97, 238, 0.1);
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --dark-light: #343a40;
            --success: #4cc9f0;
            --danger: #f72585;
            --danger-light: rgba(247, 37, 133, 0.1);
            --warning: #f8961e;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 5px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --transition-fast: all 0.15s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background-color: rgba(244, 245, 250, 0.9);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #fff, #e0e0e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: 500;
            transition: var(--transition-fast);
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .post-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .post {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .post.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .post:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .post-media-container {
            position: relative;
            overflow: hidden;
            background-color: #000;
        }
        
        .post-media {
            width: 100%;
            height: 250px;
            object-fit:contain;
            cursor: pointer;
            transition: var(--transition);
            display: block;
        }
        
        .post:hover .post-media {
            transform: scale(1.03);
            opacity: 0.9;
        }
        
        .post-media-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0.1));
            opacity: 0;
            transition: var(--transition);
            display: flex;
            align-items: flex-end;
            padding: 15px;
            color: white;
        }
        
        .post:hover .post-media-overlay {
            opacity: 1;
        }
        
        .post-content {
            padding: 20px;
            position: relative;
        }
        
        .post-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
            line-height: 1.3;
        }
        
        .post-text {
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 15px;
        }
        
        .post-user {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-light);
        }
        
        .post-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .btn-edit {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-edit:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-delete {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .auth-links {
            display: flex;
            gap: 10px;
        }
        
        .auth-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 5px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .auth-links a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .welcome-message {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .welcome-message span {
            font-weight: 500;
        }
        
        /* Like button styles */
        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--gray);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            padding: 5px;
            border-radius: 50%;
        }
        
        .like-btn.liked {
            color: var(--danger);
            background-color: var(--danger-light);
        }
        
        .like-btn:hover {
            transform: scale(1.1);
        }
        
        .like-count {
            margin-left: 5px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Comment section */
        .comments-section {
            margin-top: 15px;
            border-top: 1px solid var(--gray-light);
            padding-top: 15px;
        }
        
        .comment-form {
            display: flex;
            margin-top: 15px;
            gap: 10px;
        }
        
        .comment-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 20px;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .comment-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .comment-submit {
            padding: 10px 20px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .comment-submit:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .comment {
            margin-top: 10px;
            font-size: 0.9rem;
            padding: 10px 15px;
            background-color: var(--gray-light);
            border-radius: 15px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .comment-author {
            font-weight: 600;
            color: var(--dark);
            margin-right: 5px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.95);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            opacity: 1;
            display: flex;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .modal-media {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .modal-close {
            position: absolute;
            top: 30px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            transition: var(--transition);
            background: rgba(0,0,0,0.5);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            transform: rotate(90deg);
            background: rgba(255,255,255,0.2);
        }
        
        /* Search form styles */
        .search-container {
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .search-container:focus-within {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid var(--gray-light);
            border-radius: 30px;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05), 0 0 0 3px var(--primary-light);
        }
        
        .search-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-clear {
            background-color: var(--gray-light);
            color: var(--dark);
            border: none;
            padding: 12px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-clear:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            gap: 8px;
        }
        
        .page-item {
            list-style: none;
        }
        
        .page-link {
            display: block;
            padding: 10px 18px;
            background-color: white;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            min-width: 45px;
            text-align: center;
        }
        
        .page-link:hover {
            background-color: var(--gray-light);
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        
        .page-item.disabled .page-link {
            color: var(--gray);
            pointer-events: none;
            background-color: var(--gray-light);
        }
        
        /* No results message */
        .no-results {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            grid-column: 1 / -1;
        }
        
        .no-results-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        /* Loading spinner */
        .spinner {
            display: none;
            width: 40px;
            height: 40px;
            margin: 30px auto;
            border: 4px solid var(--gray-light);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        /* Animations */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes zoomIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
        }
        
        .fab:hover {
            background-color: var(--secondary);
            transform: translateY(-5px) scale(1.1);
        }
        
        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: rgba(0,0,0,0.7);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
        }
        
        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top:hover {
            background-color: var(--dark);
            transform: translateY(-3px);
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: var(--dark);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }
        
        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--dark) transparent transparent transparent;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Dark mode toggle */
        .dark-mode-toggle {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--dark);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
        }
        
        .dark-mode-toggle:hover {
            transform: rotate(30deg) scale(1.1);
        }
        
        /* Dark mode styles */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        body.dark-mode .post,
        body.dark-mode .search-container,
        body.dark-mode .no-results,
        body.dark-mode .page-link:not(.active) {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        body.dark-mode .post-title,
        body.dark-mode .comment-author {
            color: #ffffff;
        }
        
        body.dark-mode .post-text,
        body.dark-mode .comment {
            color: #b0b0b0;
        }
        
        body.dark-mode .comment {
            background-color: #2d2d2d;
        }
        
        body.dark-mode .search-input,
        body.dark-mode .comment-input {
            background-color: #2d2d2d;
            border-color: #3d3d3d;
            color: #e0e0e0;
        }
        
        body.dark-mode .search-input:focus,
        body.dark-mode .comment-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.3);
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .post-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                width: 100%;
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .nav-links a {
                margin: 5px;
            }
            
            .welcome-message {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-btn, .btn-clear {
                width: 100%;
                justify-content: center;
            }
            
            .post-media {
                height: 200px;
            }
            
            .dark-mode-toggle {
                bottom: 90px;
                left: 20px;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .fab {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .scroll-top {
                bottom: 80px;
                right: 20px;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 15px;
            }
            
            .post-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .post-content {
                padding: 15px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
        .admin-panel-link {
    color: white;
    text-decoration: none;
    margin-left: 15px;
    font-weight: 500;
    transition: var(--transition-fast);
    padding: 8px 15px;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.admin-panel-link:hover {
    background-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}
    </style>
</head>
<body>
<header>
    <div class="container header-content">
        <h1 class="animate__animated animate__fadeIn">Modern Blog</h1>
        <div class="nav-links">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="welcome-message animate__animated animate__fadeIn">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
                    <div>
                        <a href="posts/create_post.php">
                            <i class="fas fa-plus"></i>
                            <span class="tooltiptext">Create a new post</span>
                        </a>
                        <?php 
                        // Check if user is admin and show admin panel link
                        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        
                        if ($user && $user['is_admin']): ?>
                            <a href="admin_dashboard.php" class="admin-panel-link">
                                <i class="fas fa-cog"></i>
                                <span class="tooltiptext">Admin Dashboard</span>
                            </a>
                        <?php endif; ?>
                        <?php 
                        // Check if user is admin and show admin panel link
                        $stmt = $pdo->prepare("SELECT is_editor FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        
                        if ($user && $user['is_editor']): ?>
                            <a href="editor_manage.php" class="admin-panel-link">
                                <i class="fas fa-cog"></i>
                                <span class="tooltiptext">Editor Dashboard</span>
                            </a>
                        <?php endif; ?>
                        <a href="auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="tooltiptext">Logout from your account</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-links animate__animated animate__fadeIn">
                    <a href="auth/login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        <span class="tooltiptext">Login to your account</span>
                    </a>
                    <a href="auth/register.php">
                        <i class="fas fa-user-plus"></i>
                        <span class="tooltiptext">Create a new account</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
    
    <div class="container">
        <!-- Search Form -->
        <div class="search-container animate__animated animate__fadeInUp">
            <form method="GET" class="search-form" id="searchForm">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search posts by title or content..."
                       value="<?= htmlspecialchars($search) ?>"
                       aria-label="Search posts">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if(!empty($search)): ?>
                    <a href="?" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Loading Spinner -->
        <div class="spinner" id="loadingSpinner"></div>
        
        <!-- Posts Grid -->
        <div class="post-grid" id="postGrid">
            <?php if(empty($posts)): ?>
                <div class="no-results animate__animated animate__fadeIn">
                    <div class="no-results-icon">
                        <i class="far fa-folder-open"></i>
                    </div>
                    <h3>No posts found</h3>
                    <p>There are no posts matching your search criteria.</p>
                    <a href="?" class="btn btn-primary mt-3">
                        <i class="fas fa-stream"></i> View All Posts
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($posts as $post): ?>
                    <div class="post animate__animated" data-post-id="<?= $post['id'] ?>">
                        <?php if($post['is_video'] && $post['video_path']): ?>
                            <div class="post-media-container">
                                <video class="post-media" controls onclick="zoomMedia(this)">
                                    <source src="<?= htmlspecialchars($post['video_path']) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                
                            </div>
                        <?php elseif($post['image_path']): ?>
                            <div class="post-media-container">
                                <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post image" class="post-media" onclick="zoomMedia(this)">
                                
                            </div>
                        <?php else: ?>
                            <div class="post-media-container" style="background-color: #f0f0f0; height: 150px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-alt" style="font-size: 3rem; color: #999;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content">
                            <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
                            <p class="post-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                            
                            <!-- Like button and count -->
                            <div class="post-meta">
                                <div class="post-user">
                                    <?php if(!empty($post['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($post['avatar']) ?>" alt="User avatar" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar" style="background-color: #<?= substr(md5($post['username']), 0, 6) ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                            <?= strtoupper(substr($post['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($post['username']) ?></span>
                                </div>
                                <div>
                                    <button class="like-btn <?= isset($post['user_liked']) && $post['user_liked'] ? 'liked' : '' ?>" 
                                            onclick="toggleLike(<?= $post['id'] ?>, this)"
                                            aria-label="<?= isset($post['user_liked']) && $post['user_liked'] ? 'Unlike this post' : 'Like this post' ?>">
                                        <i class="<?= isset($post['user_liked']) && $post['user_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                        <span class="like-count"><?= $post['like_count'] ?></span>
                                    </button>
                                    <button class="like-btn" style="margin-left: 10px;" onclick="focusCommentInput(<?= $post['id'] ?>)">
                                        <i class="far fa-comment"></i> <span class="like-count"><?= $post['comment_count'] ?></span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Comments section -->
                            <div class="comments-section">
                                <div id="comments-<?= $post['id'] ?>">
                                    <!-- Comments will be loaded here -->
                                </div>
                                
                                <?php if(isset($_SESSION['user_id'])): ?>
                                    <form class="comment-form" onsubmit="addComment(event, <?= $post['id'] ?>)">
                                        <input type="text" class="comment-input" placeholder="Add a comment..." required aria-label="Add a comment">
                                        <button type="submit" class="comment-submit">Post</button>
                                    </form>
                                <?php else: ?>
                                    <div style="text-align: center; margin-top: 10px;">
                                        <a href="auth/login.php" class="btn btn-sm btn-primary">Login to comment</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
                                <div class="post-actions">
                                    <a href="posts/edit_post.php?id=<?= $post['id'] ?>" class="btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="posts/delete_post.php?id=<?= $post['id'] ?>" 
                                       class="btn btn-delete" 
                                       onclick="return confirmDelete(event)">
                                       <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
<?php if($totalPages > 1): ?>
    <ul class="pagination animate__animated animate__fadeInUp">
        <?php if($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page-1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                    &laquo; Previous
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">&laquo; Previous</span>
            </li>
        <?php endif; ?>
        
        <?php 
        // Show first page + current page with neighbors
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=1'.(!empty($search) ? '&search='.urlencode($search) : '').'">1</a></li>';
            if ($start > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>
        
        <?php
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?page='.$totalPages.(!empty($search) ? '&search='.urlencode($search) : '').'">'.$totalPages.'</a></li>';
        }
        ?>
        
        <?php if($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                    Next &raquo;
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">Next &raquo;</span>
            </li>
        <?php endif; ?>
    </ul>
<?php endif; ?>
    </div>
    
    <!-- Floating Action Button -->
    <?php if(isset($_SESSION['user_id'])): ?>
        <a href="posts/create_post.php" class="fab animate__animated animate__fadeInUp" id="fabButton" aria-label="Create new post">
            <i class="fas fa-plus"></i>
        </a>
    <?php endif; ?>
    
    <!-- Scroll to Top Button -->
    <div class="scroll-top" id="scrollTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <!-- Dark Mode Toggle -->
    <div class="dark-mode-toggle" id="darkModeToggle" aria-label="Toggle dark mode">
        <i class="fas fa-moon"></i>
    </div>
    
    <!-- Modal for zoomed media -->
    <div id="postModal" class="modal">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="modal-content" id="zoomedContent">
            <!-- Content will be inserted here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // DOM Elements
        const postGrid = document.getElementById('postGrid');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const scrollTopBtn = document.getElementById('scrollTop');
        const darkModeToggle = document.getElementById('darkModeToggle');
        const fabButton = document.getElementById('fabButton');
        
        // Initialize animations for posts
        document.addEventListener('DOMContentLoaded', () => {
            // Animate posts one by one
            const posts = document.querySelectorAll('.post');
            posts.forEach((post, index) => {
                setTimeout(() => {
                    post.classList.add('animate__fadeInUp', 'visible');
                }, index * 100);
            });
            
            // Load comments for each post
            posts.forEach(post => {
                const postId = post.dataset.postId;
                loadComments(postId);
            });
            
            // Check if dark mode is enabled in localStorage
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Add pulse animation to FAB
            if (fabButton) {
                setInterval(() => {
                    fabButton.classList.add('animate__pulse');
                    setTimeout(() => {
                        fabButton.classList.remove('animate__pulse');
                    }, 1000);
                }, 5000);
            }
        });
        
        // Scroll to top button
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Dark mode toggle
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('darkMode', 'disabled');
                darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
        
        // Confirm delete action with sweet alert
        function confirmDelete(event) {
            event.preventDefault();
            const deleteUrl = event.currentTarget.getAttribute('href');
            
            // Create a modal-like confirmation
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '2000';
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="margin-bottom: 20px;">Confirm Deletion</h3>
                    <p>Are you sure you want to delete this post? This action cannot be undone.</p>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px;">
                        <button id="cancelDelete" style="padding: 8px 15px; background: #f0f0f0; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
                        <button id="confirmDelete" style="padding: 8px 15px; background: #f72585; color: white; border: none; border-radius: 5px; cursor: pointer;">Delete</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            document.getElementById('cancelDelete').addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            document.getElementById('confirmDelete').addEventListener('click', () => {
                window.location.href = deleteUrl;
            });
            
            return false;
        }
        
        // Media zoom functionality
        function zoomMedia(mediaElement) {
            const modal = document.getElementById('postModal');
            const zoomedContent = document.getElementById('zoomedContent');
            
            // Clear previous content
            zoomedContent.innerHTML = '';
            
            if (mediaElement.tagName === 'IMG') {
                const img = document.createElement('img');
                img.src = mediaElement.src;
                img.className = 'modal-media';
                img.alt = 'Enlarged post media';
                zoomedContent.appendChild(img);
            } else if (mediaElement.tagName === 'VIDEO') {
                const video = document.createElement('video');
                video.src = mediaElement.querySelector('source').src;
                video.controls = true;
                video.autoplay = true;
                video.className = 'modal-media';
                zoomedContent.appendChild(video);
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            const modal = document.getElementById('postModal');
            const video = modal.querySelector('video');
            
            if (video) {
                video.pause();
            }
            
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside the image
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('postModal');
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Like functionality with Axios
        async function toggleLike(postId, button) {
            try {
                button.disabled = true;
                
                const response = await axios.post('actions/like.php', {
                    post_id: postId
                }, {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = response.data;
                
                if (data.success) {
                    const icon = button.querySelector('i');
                    const countSpan = button.querySelector('.like-count');
                    
                    if (data.action === 'liked') {
                        button.classList.add('liked');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        
                        // Add heart animation
                        button.innerHTML = `<i class="fas fa-heart"></i> <span class="like-count">${data.like_count}</span>`;
                        button.querySelector('i').classList.add('animate__animated', 'animate__heartBeat');
                        setTimeout(() => {
                            button.querySelector('i').classList.remove('animate__animated', 'animate__heartBeat');
                        }, 1000);
                    } else {
                        button.classList.remove('liked');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                    
                    countSpan.textContent = data.like_count;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while processing your like. Please try again.');
            } finally {
                button.disabled = false;
            }
        }
        
        // Comment functionality
        async function addComment(event, postId) {
            event.preventDefault();
            const form = event.target;
            const input = form.querySelector('.comment-input');
            const content = input.value.trim();
            
            if (!content) return;
            
            try {
                const response = await axios.post('actions/comment.php', {
                    post_id: postId,
                    content: content
                }, {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = response.data;
                
                if (data.success) {
                    // Clear the input
                    input.value = '';
                    
                    // Reload comments
                    loadComments(postId);
                    
                    // Update comment count
                    const commentCountElement = document.querySelector(`.post[data-post-id="${postId}"] .fa-comment`).parentNode;
                    const currentCount = parseInt(commentCountElement.textContent.trim());
                    commentCountElement.innerHTML = `<i class="far fa-comment"></i> <span class="like-count">${currentCount + 1}</span>`;
                    
                    // Show success animation
                    const commentSection = form.closest('.comments-section');
                    commentSection.querySelector('#comments-' + postId).classList.add('animate__animated', 'animate__flash');
                    setTimeout(() => {
                        commentSection.querySelector('#comments-' + postId).classList.remove('animate__animated', 'animate__flash');
                    }, 1000);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while posting your comment. Please try again.');
            }
        }
        
        // Focus comment input when comment button is clicked
        function focusCommentInput(postId) {
            const commentInput = document.querySelector(`.post[data-post-id="${postId}"] .comment-input`);
            if (commentInput) {
                commentInput.focus();
            } else {
                window.location.href = `auth/login.php?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
            }
        }
        
        // Load comments for a post
        async function loadComments(postId) {
            try {
                const response = await axios.get(`actions/get_comments.php?post_id=${postId}`);
                const comments = response.data;
                
                const container = document.getElementById(`comments-${postId}`);
                container.innerHTML = '';
                
                if (comments.length === 0) {
                    container.innerHTML = '<p style="color: #999; text-align: center; font-size: 0.9rem;">No comments yet</p>';
                    return;
                }
                
                // Show only the last 3 comments initially
                const commentsToShow = comments.slice(-3);
                
                commentsToShow.forEach(comment => {
                    const commentDiv = document.createElement('div');
                    commentDiv.className = 'comment';
                    commentDiv.innerHTML = `
                        <span class="comment-author">${comment.username}</span>: ${comment.content}
                    `;
                    container.appendChild(commentDiv);
                });
                
                // Add "View more" if there are more comments
                if (comments.length > 3) {
                    const viewMore = document.createElement('button');
                    viewMore.className = 'btn btn-sm';
                    viewMore.style.marginTop = '10px';
                    viewMore.style.width = '100%';
                    viewMore.style.textAlign = 'center';
                    viewMore.style.background = 'none';
                    viewMore.style.color = 'var(--primary)';
                    viewMore.innerHTML = '<i class="fas fa-chevron-down"></i> View more comments';
                    viewMore.addEventListener('click', () => {
                        container.innerHTML = '';
                        comments.forEach(comment => {
                            const commentDiv = document.createElement('div');
                            commentDiv.className = 'comment';
                            commentDiv.innerHTML = `
                                <span class="comment-author">${comment.username}</span>: ${comment.content}
                            `;
                            container.appendChild(commentDiv);
                        });
                    });
                    container.appendChild(viewMore);
                }
            } catch (error) {
                console.error('Error loading comments:', error);
                const container = document.getElementById(`comments-${postId}`);
                container.innerHTML = '<p style="color: #f72585; text-align: center; font-size: 0.9rem;">Error loading comments</p>';
            }
        }
        
        // Infinite scroll functionality
        let isLoading = false;
        let currentPage = <?= $page ?>;
        const totalPages = <?= $totalPages ?>;
        
        window.addEventListener('scroll', () => {
            if (isLoading || currentPage >= totalPages) return;
            
            const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
            const isNearBottom = scrollTop + clientHeight >= scrollHeight - 200;
            
            if (isNearBottom) {
                loadMorePosts();
            }
        });
        
        async function loadMorePosts() {
            isLoading = true;
            currentPage++;
            
            if (currentPage > totalPages) {
                isLoading = false;
                return;
            }
            
            loadingSpinner.style.display = 'block';
            
            try {
                const searchParam = '<?= !empty($search) ? '&search='.urlencode($search) : '' ?>';
                const response = await fetch(`?page=${currentPage}${searchParam}`);
                const text = await response.text();
                
                // Create a temporary div to parse the HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = text;
                
                // Extract the posts from the response
                const newPosts = tempDiv.querySelector('.post-grid').innerHTML;
                
                // Append new posts to the grid
                postGrid.insertAdjacentHTML('beforeend', newPosts);
                
                // Animate the new posts
                const allPosts = document.querySelectorAll('.post');
                allPosts.forEach((post, index) => {
                    if (index >= (currentPage - 1) * <?= $perPage ?>) {
                        setTimeout(() => {
                            post.classList.add('animate__fadeInUp', 'visible');
                        }, (index % <?= $perPage ?>) * 100);
                    }
                });
                
                // Load comments for new posts
                const newPostElements = Array.from(allPosts).slice(-<?= $perPage ?>);
                newPostElements.forEach(post => {
                    const postId = post.dataset.postId;
                    loadComments(postId);
                });
                
            } catch (error) {
                console.error('Error loading more posts:', error);
                currentPage--; // Revert page increment on error
            } finally {
                loadingSpinner.style.display = 'none';
                isLoading = false;
            }
        }
        
        // Enhanced search with debounce
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('searchForm').submit();
                }, 800);
            });
        }
    </script>
</body>
</html>