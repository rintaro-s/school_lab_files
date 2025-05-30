<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if ($_POST && isset($_POST['name']) && isset($_POST['comment'])) {
    $name = trim($_POST['name']);
    $comment = trim($_POST['comment']);
    $recommendation = isset($_POST['recommendation']) ? trim($_POST['recommendation']) : '';
    
    if (empty($name) || empty($comment)) {
        $_SESSION['error'] = "名前とコメントは必須です";
    } elseif (!validateLength($name, 50) || !validateLength($comment, 1000) || !validateLength($recommendation, 100)) {
        $_SESSION['error'] = "文字数制限を超えています";
    } elseif (!canPost()) {
        $_SESSION['error'] = "投稿制限に達しています（1時間に5回まで）";
    } elseif (hasNGWords($comment) || hasNGWords($name) || hasNGWords($recommendation)) {
        $_SESSION['error'] = "不適切な言葉が含まれています";
    } else {
        if (addPost($name, $comment, $recommendation)) {
            $_SESSION['success'] = "投稿が完了しました！";
        } else {
            $_SESSION['error'] = "投稿に失敗しました";
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$posts = getPosts();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>未来掲示板 - Future Board</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .header .nav-links {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .nav-link {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
        }

        .form-title::before {
            content: "✨";
            margin-right: 10px;
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
            padding: 15px;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .posts-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .posts-title {
            font-size: 1.5rem;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
        }

        .posts-title::before {
            content: "💬";
            margin-right: 10px;
        }

        .post-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .post-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .post-name {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
        }

        .post-date {
            color: #888;
            font-size: 0.9rem;
        }

        .post-comment {
            margin-bottom: 15px;
            line-height: 1.6;
            color: #333;
        }

        .post-recommendation {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            display: inline-block;
        }

        .post-recommendation::before {
            content: "👍 おすすめ: ";
            font-weight: 600;
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .empty-posts {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1.1rem;
        }

        .empty-posts::before {
            content: "🌟";
            font-size: 2rem;
            display: block;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-section, .posts-section {
                padding: 20px;
            }
        }

        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <div class="floating-particles">
        <div class="particle" style="width: 10px; height: 10px; left: 10%; animation-delay: 0s;"></div>
        <div class="particle" style="width: 15px; height: 15px; left: 20%; animation-delay: 1s;"></div>
        <div class="particle" style="width: 8px; height: 8px; left: 30%; animation-delay: 2s;"></div>
        <div class="particle" style="width: 12px; height: 12px; left: 80%; animation-delay: 3s;"></div>
        <div class="particle" style="width: 20px; height: 20px; left: 90%; animation-delay: 4s;"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="nav-links">
                <a href="recommendations.php" class="nav-link">📺 おすすめ表示</a>
                <a href="admin.php" class="nav-link">⚙️ 管理</a>
            </div>
            <h1>いい感じだったことを共有する場所</h1>
            <p>みんなでつながるコミュニティ</p>
        </div>

        <div class="form-section">
            <h2 class="form-title">新しい投稿</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">お名前 *</label>
                    <input type="text" id="name" name="name" class="form-control" required maxlength="50" placeholder="あなたのお名前を入力してください">
                </div>

                <div class="form-group">
                    <label for="comment">コメント *</label>
                    <textarea id="comment" name="comment" class="form-control" required maxlength="1000" placeholder="みんなに伝えたいことを書いてください"></textarea>
                </div>

                <div class="form-group">
                    <label for="recommendation">おすすめ（任意）</label>
                    <input type="text" id="recommendation" name="recommendation" class="form-control" maxlength="100" placeholder="おすすめしたいものがあれば教えてください">
                </div>

                <button type="submit" class="btn">投稿する</button>
            </form>
        </div>

        <div class="posts-section">
            <h2 class="posts-title">みんなの投稿</h2>
            
            <?php if (empty($posts)): ?>
                <div class="empty-posts">
                    まだ投稿がありません<br>
                    最初の投稿をしてみませんか？
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <div class="post-header">
                            <div class="post-name"><?php echo htmlspecialchars($post['name']); ?></div>
                            <div class="post-date"><?php echo date('Y/m/d H:i', strtotime($post['created_at'])); ?></div>
                        </div>
                        <div class="post-comment"><?php echo nl2br(htmlspecialchars($post['comment'])); ?></div>
                        <?php if (!empty($post['recommendation'])): ?>
                            <div class="post-recommendation"><?php echo htmlspecialchars($post['recommendation']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn');
            const name = document.getElementById('name').value.trim();
            const comment = document.getElementById('comment').value.trim();
            
            if (!name || !comment) {
                e.preventDefault();
                alert('名前とコメントは必須です');
                return;
            }
            
            if (name.length > 50) {
                e.preventDefault();
                alert('名前は50文字以内で入力してください');
                return;
            }
            
            if (comment.length > 1000) {
                e.preventDefault();
                alert('コメントは1000文字以内で入力してください');
                return;
            }
            
            btn.textContent = '投稿中...';
            btn.style.opacity = '0.7';
            btn.disabled = true;
        });

        function addCharacterCounter(element, maxLength, displayElement) {
            element.addEventListener('input', function() {
                const current = this.value.length;
                const remaining = maxLength - current;
                displayElement.textContent = `${current}/${maxLength}`;
                displayElement.style.color = remaining < 10 ? '#e74c3c' : '#666';
            });
        }

        const nameInput = document.getElementById('name');
        const commentInput = document.getElementById('comment');
        const recommendationInput = document.getElementById('recommendation');

        const nameCounter = document.createElement('small');
        nameCounter.style.float = 'right';
        nameCounter.style.color = '#666';
        nameInput.parentNode.appendChild(nameCounter);

        const commentCounter = document.createElement('small');
        commentCounter.style.float = 'right';
        commentCounter.style.color = '#666';
        commentInput.parentNode.appendChild(commentCounter);

        const recommendationCounter = document.createElement('small');
        recommendationCounter.style.float = 'right';
        recommendationCounter.style.color = '#666';
        recommendationInput.parentNode.appendChild(recommendationCounter);

        addCharacterCounter(nameInput, 50, nameCounter);
        addCharacterCounter(commentInput, 1000, commentCounter);
        addCharacterCounter(recommendationInput, 100, recommendationCounter);

        nameCounter.textContent = '0/50';
        commentCounter.textContent = '0/1000';
        recommendationCounter.textContent = '0/100';

        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.width = Math.random() * 20 + 5 + 'px';
            particle.style.height = particle.style.width;
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            document.querySelector('.floating-particles').appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 6000);
        }

        setInterval(createParticle, 2000);
    </script>
</body>
</html>