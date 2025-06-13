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
    
    // 手書きモードの場合はおすすめを空にする（commentも空にする）
    if ($input_mode === 'handwriting') {
        $recommendation = '';
        $comment = ''; // 手書きの場合はコメントを空にする
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
    <title>みんなの交流ノート - Community Note</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Comic Sans MS', '手書き風フォント', cursive, sans-serif;
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 50%, #fd79a8 100%);
            min-height: 100vh;
            color: #2d3436;
            font-size: 1.2rem; /* フォントサイズを少し大きく */
            line-height: 1.6; /* 行間を広げて読みやすく */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            border: 3px dashed #fd79a8;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(253, 121, 168, 0.2);
            position: relative;
            transform: rotate(-1deg);
            margin: 20px;
        }

        .header .nav-links {
            position: absolute;
            top: 10px;
            right: 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link {
            background: linear-gradient(45deg, #fd79a8, #fdcb6e);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-link.recommendations {
            background: linear-gradient(45deg, #00b894, #55a3ff);
            font-size: 0.9rem;
            padding: 10px 20px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .nav-link:hover {
            transform: translateY(-2px) rotate(2deg);
            box-shadow: 0 5px 15px rgba(253, 121, 168, 0.4);
        }

        .header h1 {
            font-size: 2.5rem; /* 少し小さくしてバランスを調整 */
            color: #fd79a8;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transform: rotate(1deg);
        }

        .header p {
            color: #636e72;
            font-size: 1.1rem; /* 読みやすいサイズに調整 */
            font-weight: 500;
        }

        .notebook-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 2px solid #ddd;
            transform: rotate(0.5deg);
            position: relative;
        }

        .notebook-section::before {
            content: '';
            position: absolute;
            left: 40px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #fd79a8;
            opacity: 0.3;
        }

        .section-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #2d3436;
            display: flex;
            align-items: center;
            border-bottom: 2px dashed #fd79a8;
            padding-bottom: 10px;
        }

        .section-title::before {
            content: "✏️";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            padding-left: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3436;
            font-size: 1.2rem; /* ラベルの文字を大きく */
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px dashed #fd79a8;
            border-radius: 12px;
            font-size: 1.2rem; /* 入力欄の文字を大きく */
            background: #fff9f0;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control::placeholder {
            color: #b2bec3; /* プレースホルダーの文字色を薄く */
            font-size: 1rem; /* プレースホルダーの文字を少し小さく */
        }

        .btn {
            background: linear-gradient(45deg, #fd79a8, #fdcb6e);
            color: white;
            border: none;
            padding: 18px 35px;
            border-radius: 25px;
            font-size: 1.3rem; /* ボタンの文字を大きく */
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(253, 121, 168, 0.3);
            border: 3px solid white;
            font-family: inherit;
        }

        .btn:hover {
            transform: translateY(-3px) rotate(-1deg);
            box-shadow: 0 8px 25px rgba(253, 121, 168, 0.5);
        }

        .notes-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 2px solid #ddd;
            transform: rotate(-0.3deg);
            position: relative;
        }

        .notes-section::before {
            content: '';
            position: absolute;
            left: 40px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #74b9ff;
            opacity: 0.3;
        }

        .notes-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: #2d3436;
            display: flex;
            align-items: center;
            border-bottom: 2px dashed #74b9ff;
            padding-bottom: 10px;
        }

        .notes-title::before {
            content: "📝";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .note-card {
            background: linear-gradient(135deg, #fff9e6, #fff3d4);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #fdcb6e;
            transition: all 0.3s ease;
            position: relative;
            transform: rotate(0.2deg);
            box-shadow: 0 4px 15px rgba(253, 203, 110, 0.2);
        }

        .note-card:nth-child(even) {
            transform: rotate(-0.3deg);
            background: linear-gradient(135deg, #e8f4ff, #d6e9ff);
            border-color: #74b9ff;
            box-shadow: 0 4px 15px rgba(116, 185, 255, 0.2);
        }

        .note-card:hover {
            transform: scale(1.02) rotate(0deg);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #fdcb6e;
            padding-bottom: 8px;
        }

        .post-name {
            font-weight: 700;
            color: #2d3436;
            font-size: 1.2rem;
            position: relative;
        }

        .post-name::before {
            content: "👤";
            margin-right: 8px;
        }

        .post-date {
            color: #636e72;
            font-size: 0.9rem;
            font-style: italic;
        }

        .post-comment {
            margin-bottom: 15px;
            line-height: 1.8;
            color: #2d3436;
            font-size: 1.1rem;
            padding-left: 15px;
            border-left: 3px solid #fdcb6e;
        }

        .note-card:nth-child(even) .post-comment {
            border-left-color: #74b9ff;
        }

        .post-recommendation {
            background: linear-gradient(45deg, #00b894, #55a3ff);
            color: white;
            padding: 12px 18px;
            border-radius: 20px;
            font-size: 0.95rem;
            display: inline-block;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transform: rotate(-1deg);
        }

        .post-recommendation::before {
            content: "✨ おすすめ: ";
            font-weight: 700;
        }

        .handwriting-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            border: 3px solid #fdcb6e;
            margin-bottom: 15px;
            display: block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            transform: rotate(1deg);
        }

        .note-card:nth-child(even) .handwriting-image {
            border-color: #74b9ff;
            transform: rotate(-1deg);
        }

        .handwriting-image:hover {
            transform: scale(1.05) rotate(0deg);
        }

        .handwriting-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #2d3436;
            font-weight: 600;
            font-size: 1rem;
        }

        .input-mode-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: rgba(253, 121, 168, 0.1);
            padding: 8px;
            border-radius: 20px;
            border: 2px dashed #fd79a8;
        }

        .mode-btn {
            flex: 1;
            padding: 12px 18px;
            border: none;
            border-radius: 15px;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-family: inherit;
        }

        .mode-btn.active {
            background: linear-gradient(45deg, #fd79a8, #fdcb6e);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(253, 121, 168, 0.3);
        }

        .canvas-container {
            background: white;
            border: 3px dashed #fd79a8;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
            transform: rotate(0.5deg);
        }

        .canvas-container.active {
            display: block;
        }

        .drawing-tools {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
            background: rgba(253, 121, 168, 0.1);
            padding: 10px;
            border-radius: 12px;
        }

        .tool-btn {
            padding: 8px 12px;
            border: 2px solid #fd79a8;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .tool-btn:hover, .tool-btn.active {
            background: #fd79a8;
            color: white;
            transform: scale(1.1);
        }

        .color-picker {
            width: 50px;
            height: 35px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #fd79a8;
        }

        #drawingCanvas {
            border: 2px solid #ddd;
            border-radius: 12px;
            cursor: crosshair;
            display: block;
            margin: 0 auto;
            background: white;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .empty-notes {
            text-align: center;
            padding: 40px;
            color: #636e72;
            font-size: 1.3rem;
            background: linear-gradient(135deg, #ffeaa7, #fab1a0);
            border-radius: 15px;
            border: 2px dashed #fdcb6e;
            transform: rotate(-1deg);
        }

        .empty-notes::before {
            content: "📖";
            display: block;
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .image-error {
            color: #fd79a8;
            font-style: italic;
            padding: 15px;
            background: linear-gradient(135deg, #fff0f5, #ffe4e1);
            border-radius: 12px;
            border: 2px dashed #fd79a8;
            text-align: center;
            margin-bottom: 15px;
        }

        .image-error .error-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fd79a8;
        }

        .image-error .error-message {
            margin-bottom: 12px;
            color: #636e72;
        }

        .recommendations-btn {
            display: inline-block;
            background: linear-gradient(45deg, #00b894, #55a3ff);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
            border: 2px solid white;
        }

        .recommendations-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.4);
        }

        .recommendations-section {
            background: linear-gradient(135deg, #e8f8f5, #d1f2eb);
            border: 2px solid #00b894;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .recommendations-section h3 {
            color: #00b894;
            margin-bottom: 10px;
            font-size: 1.5rem; /* 見出しを大きくして目立たせる */
        }

        .recommendations-section p {
            color: #636e72;
            font-size: 1.1rem; /* 説明文を読みやすく */
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .notebook-section, .notes-section {
                padding: 15px;
                transform: none;
            }
            
            .note-card {
                transform: none;
            }
            
            .nav-links {
                position: static;
                justify-content: center;
                margin-bottom: 10px;
            }
        }

        .sticker {
            position: absolute;
            font-size: 2rem;
            opacity: 0.7;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating-stickers {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="floating-stickers">
        <div class="sticker" style="top: 10%; left: 5%; animation-delay: 0s;">🌟</div>
        <div class="sticker" style="top: 20%; right: 10%; animation-delay: 1s;">🎨</div>
        <div class="sticker" style="top: 60%; left: 3%; animation-delay: 2s;">💝</div>
        <div class="sticker" style="bottom: 20%; right: 5%; animation-delay: 3s;">🌈</div>
        <div class="sticker" style="bottom: 10%; left: 50%; animation-delay: 4s;">✨</div>
    </div>

    <div class="container">
        <div class="header">
            <div class="nav-links">
                <a href="en_index.php" class="nav-link">English</a>
                <a href="ko_index.php" class="nav-link">한국어</a>
                <a href="recommendations.php" class="nav-link recommendations">🌟 みんなのおすすめ</a>
                <a href="admin.php" class="nav-link">⚙️ 管理</a>
            </div>
            <h1>🌸 みんなの共有ノート 🌸</h1>
            <p>おえかきしたり、コメントを書いたり、みんなで自由にあそぼう！</p>
        </div>

        <div class="recommendations-section">
            <h3>📺 みんなのおすすめをチェック！</h3>
            <p>みんなが投稿してくれたおすすめの音楽、映画、お菓子などをまとめて見れるよ♪</p>
            <a href="recommendations.php" class="recommendations-btn">✨ おすすめページを見る</a>
        </div>

        <div class="notebook-section">
            <h2 class="section-title">自由にかいてみよう！</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">🎉 <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">😅 <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="postForm">
                <div class="form-group">
                    <label for="name">✏️ なまえ（なんでもいいよ！）</label>
                    <input type="text" id="name" name="name" class="form-control" required maxlength="50" placeholder="あなたのなまえをおしえてね">
                </div>

                <div class="form-group">
                    <label>🎨 どんな風に書く？</label>
                    <div class="input-mode-toggle">
                        <button type="button" class="mode-btn active" data-mode="text">📝 文字で書く</button>
                        <button type="button" class="mode-btn" data-mode="handwriting">🖌️ お絵描きする</button>
                    </div>
                </div>

                <input type="hidden" id="input_mode" name="input_mode" value="text">
                <input type="hidden" id="canvas_data" name="canvas_data" value="">

                <div class="form-group" id="text-input-group">
                    <label for="comment">💭 いまの気持ちや楽しかったことをを書こう！</label>
                    <textarea id="comment" name="comment" class="form-control" maxlength="1000" placeholder="きょうあったたのしいこと、すきなおんがく、おいしかったたべもの、なんでもきがるにかいてね♪"></textarea>
                </div>

                <div class="canvas-container" id="canvas-container">
                    <label>🎨 自由にお絵描きしてね</label>
                    <div class="drawing-tools">
                        <button type="button" class="tool-btn active" data-tool="pen">🖊️ ペン</button>
                        <button type="button" class="tool-btn" data-tool="eraser">🧽 消しゴム</button>
                        <input type="color" class="color-picker" id="colorPicker" value="#ff6b9d" title="色を選ぶ">
                        <label for="brushSize">太さ:</label>
                        <input type="range" id="brushSize" class="size-slider" min="1" max="20" value="5">
                        <button type="button" class="tool-btn" id="undoBtn">↶ 戻る</button>
                        <button type="button" class="tool-btn" id="clearBtn">🗑️ 全部消す</button>
                    </div>
                    <canvas id="drawingCanvas" width="600" height="350"></canvas>
                </div>

                <div class="form-group" id="recommendation-group">
                    <label for="recommendation">✨ みんなにおすすめしたいもの（あったら書いて！）</label>
                    <input type="text" id="recommendation" name="recommendation" class="form-control" maxlength="100" placeholder="すきなうた、えいが、おかし、なんでも♪">
                </div>

                <button type="submit" class="btn">🌟 ノートに書き込む 🌟</button>
            </form>
        </div>

        <div class="notes-section">
            <h2 class="notes-title">みんなが書いてくれたもの</h2>
            
            <?php if (empty($posts)): ?>
                <div class="empty-notes">
                    まだだれもかいてないよ<br>
                    さいしょにかいてくれる？
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="note-card">
                        <div class="post-header">
                            <div class="post-name"><?php echo htmlspecialchars($post['name']); ?></div>
                            <div class="post-date"><?php echo date('m/d H:i', strtotime($post['created_at'])); ?></div>
                        </div>
                        
                        <?php if (!empty($post['image_filename'])): ?>
                            <?php 
                            $image_path = __DIR__ . '/images/' . $post['image_filename'];
                            $web_image_path = 'images/' . $post['image_filename'];
                            ?>
                            <?php if (file_exists($image_path)): ?>
                                <div class="handwriting-label">🎨 お絵描きメッセージ</div>
                                <img src="<?php echo htmlspecialchars($web_image_path); ?>" 
                                     alt="お絵描きメッセージ" 
                                     class="handwriting-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="image-error" style="display: none;">
                                    <div class="error-title">🎨 お絵描きが見れないよ</div>
                                    <div class="error-message">でも大丈夫！みんなのおすすめページで他の楽しいコンテンツが見れるよ♪</div>
                                    <a href="recommendations.php" class="recommendations-btn">🌟 おすすめページを見る</a>
                                </div>
                            <?php else: ?>
                                <div class="image-error">
                                    <div class="error-title">🎨 お絵描きが見つからないよ</div>
                                    <div class="error-message">そんな時は、みんなのおすすめページをチェックしてみてね！</div>
                                    <a href="recommendations.php" class="recommendations-btn">🌟 おすすめページを見る</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($post['comment'])): ?>
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
        ctx.fillStyle = '#fff9f0';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // 文字数カウンター機能
        function addCharacterCounter(element, maxLength, displayElement) {
            element.addEventListener('input', function() {
                const current = this.value.length;
                const remaining = maxLength - current;
                displayElement.textContent = `${current}/${maxLength}`;
                displayElement.style.color = remaining < 10 ? '#e74c3c' : '#666';
            });
        }

        // フォーム送信時のバリデーション
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn');
            const name = document.getElementById('name').value.trim();
            const mode = inputModeField.value;
            
            if (!name) {
                btn.disabled = true;
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
        });

        // 文字数カウンターの設定
        const nameInput = document.getElementById('name');
        const commentInput = document.getElementById('comment');
        const recommendationInput = document.getElementById('recommendation');

        // カウンター要素作成
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

        // ステッカーアニメーション
        function createFloatingSticker() {
            const stickers = ['🌟', '💖', '🌈', '✨', '🎵', '🌸', '🎨', '💝'];
            const sticker = document.createElement('div');
            sticker.className = 'sticker';
            sticker.textContent = stickers[Math.random() * stickers.length | 0];
            sticker.style.left = Math.random() * 100 + '%';
            sticker.style.top = '-50px';
            sticker.style.animationDelay = Math.random() * 2 + 's';
            sticker.style.animationDuration = (Math.random() * 3 + 3) + 's';
            document.querySelector('.floating-stickers').appendChild(sticker);

            setTimeout(() => {
                sticker.remove();
            }, 6000);
        }

        setInterval(createFloatingSticker, 3000);
    </script>
</body>
</html>