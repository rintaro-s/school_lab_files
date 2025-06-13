<?php
// HTTPメソッドチェック
$allowed_methods = ['GET', 'POST', 'HEAD'];
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($request_method, $allowed_methods)) {
    http_response_code(405); // Method Not Allowed
    header('Allow: ' . implode(', ', $allowed_methods));
    exit('Method Not Allowed');
}

session_start();
require_once 'config.php';
require_once 'functions.php';

// データベース接続チェック
if (!isset($pdo) || $pdo === null) {
    $_SESSION['error'] = "データベース接続エラー: config.phpを確認してください。データベース設定またはテーブル構造に問題があります。";
}

// 投稿処理（POST-Redirect-GETパターン）
if ($request_method === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $recommendation = isset($_POST['recommendation']) ? trim($_POST['recommendation']) : '';
    $input_mode = isset($_POST['input_mode']) ? $_POST['input_mode'] : 'text';
    
    // 手書きモードの場合はおすすめを空にする
    if ($input_mode === 'handwriting') {
        $recommendation = '';
    }
    
    // 手書きモードの場合の画像処理
    $image_filename = null;
    if ($input_mode === 'handwriting' && isset($_POST['canvas_data']) && !empty($_POST['canvas_data'])) {
        $canvas_data = $_POST['canvas_data'];
        // data:image/png;base64, を除去
        $image_data = str_replace('data:image/png;base64,', '', $canvas_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        
        if ($decoded_image !== false) {
            // imagesディレクトリが存在しない場合は作成
            $images_dir = __DIR__ . '/images';
            if (!file_exists($images_dir)) {
                mkdir($images_dir, 0777, true);
            }
            
            $image_filename = 'handwriting_' . time() . '_' . rand(1000, 9999) . '.png';
            $image_path = $images_dir . '/' . $image_filename;
            
            if (!file_put_contents($image_path, $decoded_image)) {
                $_SESSION['error'] = "画像の保存に失敗しました";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
    
    // バリデーション
    if (empty($name)) {
        $_SESSION['error'] = "名前は必須です";
    } elseif ($input_mode === 'text' && empty($comment)) {
        $_SESSION['error'] = "コメントは必須です";
    } elseif ($input_mode === 'handwriting' && empty($image_filename)) {
        $_SESSION['error'] = "手書き入力が必要です";
    } elseif (!validateLength($name, 50) || !validateLength($comment, 1000) || !validateLength($recommendation, 100)) {
        $_SESSION['error'] = "文字数制限を超えています";
    } elseif (!canPost()) {
        $_SESSION['error'] = "投稿制限に達しています（1時間に5回まで）";
    } elseif (hasNGWords($comment) || hasNGWords($name) || hasNGWords($recommendation)) {
        $_SESSION['error'] = "不適切な言葉が含まれています";
    } else {
        $result = addPost($name, $comment, $recommendation, $image_filename);
        if ($result === true) {
            $_SESSION['success'] = "投稿が完了しました！";
        } else {
            $_SESSION['error'] = "投稿に失敗しました: " . $result;
        }
    }
    
    // POST後にリダイレクトしてリロード問題を防ぐ
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// セッションからメッセージを取得して削除
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// 投稿一覧を取得
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
            top: 5px;
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
            font-size: 0.8rem;
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

        .handwriting-image {
            max-width: 100%;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            margin-bottom: 10px;
        }

        .input-mode-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 10px;
        }

        .mode-btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mode-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .canvas-container {
            background: white;
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 15px;
            display: none;
        }

        .canvas-container.active {
            display: block;
        }

        .drawing-tools {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tool-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tool-btn:hover, .tool-btn.active {
            background: #667eea;
            color: white;
        }

        .color-picker {
            width: 40px;
            height: 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .size-slider {
            margin: 0 10px;
        }

        #drawingCanvas {
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: crosshair;
            display: block;
            margin: 0 auto;
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
　　　　 <a href="en_index.php" class="nav-link">🌎English</a>
               <a href="ko_index.php" class="nav-link">🌎한국어</a>
               <a href="recommendations.php" class="nav-link">📺 おすすめ表示</a>
               <a href="admin.php" class="nav-link">⚙️ 管理</a>
            </div>
            <h1>お客様同士の交流ノート</h1>
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

            <form method="POST" action="" id="postForm">
                <div class="form-group">
                    <label for="name">お名前 *</label>
                    <input type="text" id="name" name="name" class="form-control" required maxlength="50" placeholder="あなたのお名前を入力してください">
                </div>

                <div class="form-group">
                    <label>入力方法を選択</label>
                    <div class="input-mode-toggle">
                        <button type="button" class="mode-btn active" data-mode="text">✏️ テキスト入力</button>
                        <button type="button" class="mode-btn" data-mode="handwriting">🎨 手書き入力</button>
                    </div>
                </div>

                <input type="hidden" id="input_mode" name="input_mode" value="text">
                <input type="hidden" id="canvas_data" name="canvas_data" value="">

                <div class="form-group" id="text-input-group">
                    <label for="comment">コメント *</label>
                    <textarea id="comment" name="comment" class="form-control" maxlength="1000" placeholder="みんなに伝えたいことを書いてください"></textarea>
                </div>

                <div class="canvas-container" id="canvas-container">
                    <label>手書きでコメントを書いてください *</label>
                    <div class="drawing-tools">
                        <button type="button" class="tool-btn active" data-tool="pen">✏️ ペン</button>
                        <button type="button" class="tool-btn" data-tool="eraser">🧹 消しゴム</button>
                        <input type="color" class="color-picker" id="colorPicker" value="#000000" title="色を選択">
                        <label for="brushSize">太さ:</label>
                        <input type="range" id="brushSize" class="size-slider" min="1" max="20" value="3">
                        <button type="button" class="tool-btn" id="undoBtn">↶ 戻る</button>
                        <button type="button" class="tool-btn" id="clearBtn">🗑️ 全消去</button>
                    </div>
                    <canvas id="drawingCanvas" width="600" height="300"></canvas>
                </div>

                <div class="form-group" id="recommendation-group">
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
                        <?php if (!empty($post['image_filename'])): ?>
                            <?php 
                            $image_path = __DIR__ . '/images/' . $post['image_filename'];
                            if (file_exists($image_path)): 
                            ?>
                                <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" alt="手書きコメント" class="handwriting-image">
                            <?php else: ?>
                                <div class="post-comment" style="color: #888; font-style: italic;">画像が見つかりません</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="post-comment"><?php echo nl2br(htmlspecialchars($post['comment'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($post['recommendation'])): ?>
                            <div class="post-recommendation"><?php echo htmlspecialchars($post['recommendation']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 入力モード切り替え
        const modeButtons = document.querySelectorAll('.mode-btn');
        const textInputGroup = document.getElementById('text-input-group');
        const canvasContainer = document.getElementById('canvas-container');
        const recommendationGroup = document.getElementById('recommendation-group');
        const inputModeField = document.getElementById('input_mode');
        const commentField = document.getElementById('comment');

        modeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                modeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const mode = this.getAttribute('data-mode');
                inputModeField.value = mode;
                
                if (mode === 'text') {
                    textInputGroup.style.display = 'block';
                    canvasContainer.classList.remove('active');
                    recommendationGroup.style.display = 'block';
                    commentField.required = true;
                } else {
                    textInputGroup.style.display = 'none';
                    canvasContainer.classList.add('active');
                    recommendationGroup.style.display = 'none';
                    commentField.required = false;
                    // おすすめフィールドをクリア
                    document.getElementById('recommendation').value = '';
                }
            });
        });

        // キャンバス描画機能
        const canvas = document.getElementById('drawingCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let currentTool = 'pen';
        let currentColor = '#000000';
        let currentSize = 3;
        let undoStack = [];

        // 描画状態を保存
        function saveState() {
            undoStack.push(canvas.toDataURL());
            if (undoStack.length > 20) {
                undoStack.shift();
            }
        }

        // 初期状態を保存
        saveState();

        // ツール切り替え
        document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tool-btn[data-tool]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTool = this.getAttribute('data-tool');
            });
        });

        // 色選択
        document.getElementById('colorPicker').addEventListener('change', function() {
            currentColor = this.value;
        });

        // ブラシサイズ
        document.getElementById('brushSize').addEventListener('input', function() {
            currentSize = this.value;
        });

        // 戻る
        document.getElementById('undoBtn').addEventListener('click', function() {
            if (undoStack.length > 1) {
                undoStack.pop();
                const img = new Image();
                img.onload = function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0);
                };
                img.src = undoStack[undoStack.length - 1];
            }
        });

        // 全消去
        document.getElementById('clearBtn').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            saveState();
        });

        // 描画イベント
        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;

            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.lineWidth = currentSize;
            ctx.lineCap = 'round';

            if (currentTool === 'pen') {
                ctx.globalCompositeOperation = 'source-over';
                ctx.strokeStyle = currentColor;
            } else if (currentTool === 'eraser') {
                ctx.globalCompositeOperation = 'destination-out';
            }

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                ctx.beginPath();
                saveState();
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // タッチイベント（モバイル対応）
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchend', function(e) {
            e.preventDefault();
            const mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });

        // キャンバス初期化
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // フォーム送信時のバリデーション
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn');
            const name = document.getElementById('name').value.trim();
            const mode = inputModeField.value;
            
            if (!name) {
                e.preventDefault();
                alert('名前は必須です');
                return;
            }
            
            if (mode === 'text') {
                const comment = document.getElementById('comment').value.trim();
                if (!comment) {
                    e.preventDefault();
                    alert('コメントは必須です');
                    return;
                }
            } else if (mode === 'handwriting') {
                // キャンバスデータを取得
                const canvasData = canvas.toDataURL('image/png');
                document.getElementById('canvas_data').value = canvasData;
                
                // 空のキャンバスかチェック
                const emptyCanvas = document.createElement('canvas');
                emptyCanvas.width = canvas.width;
                emptyCanvas.height = canvas.height;
                const emptyCtx = emptyCanvas.getContext('2d');
                emptyCtx.fillStyle = 'white';
                emptyCtx.fillRect(0, 0, emptyCanvas.width, emptyCanvas.height);
                
                if (canvasData === emptyCanvas.toDataURL('image/png')) {
                    e.preventDefault();
                    alert('手書き入力が必要です');
                    return;
                }
            }
            
            btn.textContent = '投稿中...';
            btn.style.opacity = '0.7';
            btn.disabled = true;
        });

        // 文字数カウンター
        function addCharacterCounter(element, maxLength, displayElement) {
            element.addEventListener('input', function() {
                const current = this.value.length;
                const remaining = maxLength - current;
                displayElement.textContent = `${current}/${maxLength}`;
                displayElement.style.color = remaining < 10 ? '#e74c3c' : '#666';
            });
        }

        // 文字数カウンター追加
        const nameInput = document.getElementById('name');
        const commentInput = document.getElementById('comment');
        const recommendationInput = document.getElementById('recommendation');

        // カウンター表示要素を作成
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

        // 初期表示
        nameCounter.textContent = '0/50';
        commentCounter.textContent = '0/1000';
        recommendationCounter.textContent = '0/100';

        // パーティクルアニメーション
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