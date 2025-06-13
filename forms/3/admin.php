<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

$admin_password = 'admin123';

if ($_POST && isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "パスワードが違います";
    }
}

if (isset($_GET['logout'])) {
    $_SESSION['admin_logged_in'] = false;
    header('Location: admin.php');
    exit;
}

if ($_POST && isset($_POST['add_ng_word']) && $_SESSION['admin_logged_in']) {
    $word = trim($_POST['ng_word']);
    if (!empty($word)) {
        if (addNGWord($word)) {
            $success = "NGワードを追加しました";
        } else {
            $error = "NGワードの追加に失敗しました";
        }
    }
}

if ($_GET && isset($_GET['delete_ng']) && $_SESSION['admin_logged_in']) {
    if (deleteNGWord($_GET['delete_ng'])) {
        $success = "NGワードを削除しました";
    } else {
        $error = "NGワードの削除に失敗しました";
    }
}

if ($_GET && isset($_GET['delete_post']) && $_SESSION['admin_logged_in']) {
    if (deletePost($_GET['delete_post'])) {
        $success = "投稿を削除しました";
    } else {
        $error = "投稿の削除に失敗しました";
    }
}

if ($_SESSION['admin_logged_in']) {
    $posts = getPosts(50);
    $ngWords = getNGWords();
    $postCount = getPostCount();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - 未来掲示板</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            position: relative;
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .login-form, .admin-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
　　
        }

        .header .btn {
            position: absolute;
            top: 10px;
            left: 15px;
            display: flex;
            gap: 10px;
            
        } 
       

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
        }

        .btn-danger:hover {
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #229954);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .section-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .logout-link {
            position: absolute;
            top: 70px;  
            right: 20px;
            float: right;
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
        }

        .logout-link:hover {
            text-decoration: underline;
        }

        .post-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .admin-section {
                padding: 20px;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table th, .table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ 管理画面</h1>
            <a href="index.php" class="btn">掲示板に戻る</a> 
            <?php if ($_SESSION['admin_logged_in']): ?>
                <a href="?logout=1" class="logout-link">ログアウト</a>
                <a href="index.php" class="btn">掲示板に戻る</a>
            <?php endif; ?>
        </div>

        <?php if (!$_SESSION['admin_logged_in']): ?>
            <div class="login-form">
                <h2 class="section-title">管理者ログイン</h2>
                
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn">ログイン</button>
                </form>
            </div>
        <?php else: ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 統計情報 -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $postCount; ?></div>
                    <div class="stat-label">総投稿数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($ngWords); ?></div>
                    <div class="stat-label">NGワード数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('Y/m/d'); ?></div>
                    <div class="stat-label">今日の日付</div>
                </div>
            </div>

            <!-- NGワード管理 -->
            <div class="admin-section">
                <h2 class="section-title">NGワード管理</h2>
                
                <form method="POST" action="" style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="ng_word">新しいNGワードを追加</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="ng_word" name="ng_word" class="form-control" placeholder="NGワードを入力" required>
                            <button type="submit" name="add_ng_word" class="btn btn-success">追加</button>
                        </div>
                    </div>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NGワード</th>
                            <th>追加日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ngWords as $ngWord): ?>
                            <tr>
                                <td><?php echo $ngWord['id']; ?></td>
                                <td><?php echo htmlspecialchars($ngWord['word']); ?></td>
                                <td><?php echo date('Y/m/d H:i', strtotime($ngWord['created_at'])); ?></td>
                                <td>
                                    <a href="?delete_ng=<?php echo $ngWord['id']; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('このNGワードを削除しますか？')">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 投稿管理 -->
            <div class="admin-section">
                <h2 class="section-title">投稿管理</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名前</th>
                            <th>コメント</th>
                            <th>おすすめ</th>
                            <th>投稿日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td><?php echo $post['id']; ?></td>
                                <td><?php echo htmlspecialchars($post['name']); ?></td>
                                <td class="post-preview"><?php echo htmlspecialchars($post['comment']); ?></td>
                                <td class="post-preview"><?php echo htmlspecialchars($post['recommendation']); ?></td>
                                <td><?php echo date('Y/m/d H:i', strtotime($post['created_at'])); ?></td>
                                <td>
                                    <a href="?delete_post=<?php echo $post['id']; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('この投稿を削除しますか？')">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // 管理画面用のスクリプト
        document.addEventListener('DOMContentLoaded', function() {
            // 削除確認ダイアログ
            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('本当に削除しますか？この操作は取り消せません。')) {
                        e.preventDefault();
                    }
                });
            });

            // 統計カードのアニメーション
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'slideInUp 0.5s ease-out forwards';
            });
        });

        // CSS アニメーション
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>