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
    <title>未来掲示板 - Future Board</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Comic Sans MS', 'Verdana', sans-serif;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #feca57);
            background-size: 400% 400%;
            animation: rainbowMove 8s ease infinite;
            min-height: 100vh;
            color: #2c3e50;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes rainbowMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ゲーム風エフェクト背景 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.15) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
            animation: sparkleEffect 4s ease-in-out infinite;
        }

        @keyframes sparkleEffect {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 25px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            backdrop-filter: blur(15px);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 5px 15px rgba(0, 0, 0, 0.08);
            position: relative;
            border: 3px solid transparent;
            background-clip: padding-box;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 25px;
            padding: 3px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #feca57);
            background-size: 400% 400%;
            animation: borderRainbow 3s ease infinite;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: exclude;
            z-index: -1;
        }

        @keyframes borderRainbow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .nav-links {
            position: absolute;
            top: 10px;
            left: 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link {
            background: linear-gradient(45deg, #4ecdc4, #45b7d1);
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 18px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(78, 205, 196, 0.3);
        }

        .nav-link:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 5px 20px rgba(78, 205, 196, 0.5);
        }

        .gallery-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
            z-index: 10;
        }

        .gallery-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 25px rgba(255, 107, 107, 0.6);
        }

        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            position: relative;
            animation: textRainbow 2s ease-in-out infinite;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            font-weight: bold;
        }

        .challenge-subtitle {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 500;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.8);
            padding: 8px 15px;
            border-radius: 15px;
            display: inline-block;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.7);
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .step.active {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            transform: scale(1.1);
        }

        .step.completed {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
        }

        .countdown-bar-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            padding: 15px;
            z-index: 1000;
            display: none;
            animation: slideUp 0.5s ease;
        }

        .countdown-bar-container.active {
            display: block;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .countdown-bar {
            background: #34495e;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
            position: relative;
        }

        .countdown-progress {
            height: 100%;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            width: 100%;
            transition: width 1s linear;
            border-radius: 10px;
        }

        .countdown-text {
            text-align: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .step-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 25px;
            margin-bottom: 30px;
            backdrop-filter: blur(15px);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 5px 15px rgba(0, 0, 0, 0.08);
            display: none;
        }

        .step-section.active {
            display: block;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
        }

        .mode-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .mode-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border: 3px solid #e0e6ed;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .mode-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .mode-card:hover::before {
            left: 100%;
        }

        .mode-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .mode-card.selected {
            border-color: #ff6b6b;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
            transform: scale(1.05);
        }

        .mode-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .mode-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .mode-description {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .name-input-section {
            text-align: center;
        }

        .name-input-group {
            display: flex;
            gap: 15px;
            align-items: end;
            justify-content: center;
            margin-bottom: 20px;
        }

        .name-input-wrapper {
            flex: 1;
            max-width: 300px;
        }

        .confirm-btn {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
        }

        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.6);
        }

        .confirm-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .game-section {
            text-align: center;
        }

        .ready-message {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.1));
            border-radius: 15px;
            border-left: 4px solid #2ecc71;
        }

        .start-btn {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            border: none;
            padding: 20px 40px;
            border-radius: 25px;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
            animation: pulse 2s infinite;
        }

        .start-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 30px rgba(255, 107, 107, 0.6);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .challenge-area {
            display: none;
        }

        .challenge-area.active {
            display: block;
        }

        /* 既存のスタイルを調整 */
        .form-control {
            width: 100%;
            padding: 15px 18px;
            border: 3px solid #e0e6ed;
            border-radius: 15px;
            font-size: 1rem;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            transition: all 0.3s ease;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .form-control:focus {
            outline: none;
            border-color: #4ecdc4;
            background: white;
            box-shadow: 
                0 0 0 4px rgba(78, 205, 196, 0.2),
                0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .form-control::placeholder {
            color: #7f8c8d;
            font-style: italic;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .input-mode-toggle {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ecf0f1, #bdc3c7);
            padding: 6px;
            border-radius: 18px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .mode-btn {
            flex: 1;
            padding: 12px 18px;
            border: none;
            border-radius: 15px;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }

        .mode-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .mode-btn:hover::before {
            left: 100%;
        }

        .mode-btn.active {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        .canvas-container {
            background: white;
            border: 3px dashed #4ecdc4;
            border-radius: 20px;
            padding: 15px;
            margin-bottom: 15px;
            display: none;
            transition: all 0.3s ease;
        }

        .canvas-container.active {
            display: block;
            border-color: #ff6b6b;
            box-shadow: 0 5px 20px rgba(255, 107, 107, 0.3);
            animation: containerGlow 2s infinite;
        }

        @keyframes containerGlow {
            0%, 100% { box-shadow: 0 5px 20px rgba(255, 107, 107, 0.3); }
            50% { box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5); }
        }

        .drawing-tools {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
        }

        .tool-btn {
            padding: 8px 12px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .tool-btn:hover, .tool-btn.active {
            background: linear-gradient(45deg, #4ecdc4, #45b7d1);
            color: white;
            border-color: #4ecdc4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 205, 196, 0.4);
        }

        .color-picker {
            width: 45px;
            height: 35px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .size-slider {
            margin: 0 12px;
            cursor: pointer;
        }

        #drawingCanvas {
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            cursor: crosshair;
            display: block;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .challenge-hint {
            font-size: 0.95rem;
            color: #e74c3c;
            font-weight: bold;
            margin-top: 8px;
            padding: 10px 15px;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(255, 107, 107, 0.1));
            border-radius: 15px;
            border-left: 4px solid #e74c3c;
            text-align: center;
            animation: urgentPulse 1.5s infinite;
        }

        @keyframes urgentPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.02); }
        }

        .countdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .countdown-content {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            padding: 40px 60px;
            border-radius: 25px;
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: bounceIn 0.5s ease;
        }

        .countdown-number {
            font-size: 4rem;
            margin: 10px 0;
            animation: numberPulse 1s infinite;
        }

        @keyframes numberPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .posts-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 25px;
            backdrop-filter: blur(15px);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .posts-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .posts-title::before {
            content: "🏆";
            margin-right: 12px;
            font-size: 2rem;
        }

        .post-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #4ecdc4;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .post-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #4ecdc4, transparent);
            transition: left 0.8s ease;
        }

        .post-card:hover::before {
            left: 100%;
        }

        .post-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
            font-size: 1.5rem;
            animation: floatParticle 6s ease-in-out infinite;
        }

        @keyframes floatParticle {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.6; }
            50% { transform: translateY(-25px) rotate(180deg); opacity: 1; }
        }

        .empty-posts {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
            font-size: 1.1rem;
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.1), rgba(69, 183, 209, 0.1));
            border-radius: 18px;
            border: 2px dashed #4ecdc4;
        }

        @media (max-width: 768px) {
            .mode-selection {
                grid-template-columns: 1fr;
            }
            
            .name-input-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .name-input-wrapper {
                max-width: 100%;
            }
            
            .gallery-btn {
                position: static;
                margin-top: 15px;
                display: inline-block;
            }
        }

        /* アラート用のスタイル */
        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }

        .alert-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
    </style>
</head>
<body>
    <div class="floating-particles">
        <div class="particle" style="left: 5%; animation-delay: 0s;">🎨</div>
        <div class="particle" style="left: 15%; animation-delay: 1s;">✏️</div>
        <div class="particle" style="left: 25%; animation-delay: 2s;">🌟</div>
        <div class="particle" style="left: 35%; animation-delay: 3s;">🎮</div>
        <div class="particle" style="left: 45%; animation-delay: 4s;">💭</div>
        <div class="particle" style="left: 55%; animation-delay: 5s;">🚀</div>
        <div class="particle" style="left: 65%; animation-delay: 1.5s;">⭐</div>
        <div class="particle" style="left: 75%; animation-delay: 2.5s;">🎯</div>
        <div class="particle" style="left: 85%; animation-delay: 3.5s;">💫</div>
        <div class="particle" style="left: 95%; animation-delay: 4.5s;">🎊</div>
    </div>

    <!-- カウントダウンバー -->
    <div class="countdown-bar-container" id="countdownBarContainer">
        <div class="countdown-bar">
            <div class="countdown-progress" id="countdownProgress"></div>
        </div>
        <div class="countdown-text" id="countdownText">準備中...</div>
    </div>

    <div class="container">
        <div class="header">
            <div class="nav-links">
               <a href="en_index.php" class="nav-link">English</a>
               <a href="ko_index.php.php" class="nav-link">한국어</a>
               <a href="admin.php" class="nav-link">⚙️ 管理</a>
            </div>
            <a href="recommendations.php" class="gallery-btn">🏆 作品ギャラリー</a>
            
            <h1>スピードチャレンジ</h1>
            <div class="challenge-subtitle">20秒間のチャットチャレンジに挑戦しよう！</div>
            
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <span>1️⃣</span>
                    <span>モード選択</span>
                </div>
                <div class="step" id="step2">
                    <span>2️⃣</span>
                    <span>プレイヤー登録</span>
                </div>
                <div class="step" id="step3">
                    <span>3️⃣</span>
                    <span>チャレンジ</span>
                </div>
            </div>
        </div>

        <!-- ステップ1: モード選択 -->
        <div class="step-section active" id="modeSelection">
            <h2 class="step-title">🎮 チャレンジモードを選択しよう！</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">🎉 <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mode-selection">
                <div class="mode-card" data-mode="text">
                    <span class="mode-icon">💬</span>
                    <div class="mode-title">スピード文字</div>
                    <div class="mode-description">20秒で思いを文字にしよう！</div>
                </div>
                <div class="mode-card" data-mode="handwriting">
                    <span class="mode-icon">🎨</span>
                    <div class="mode-title">クイックドロー</div>
                    <div class="mode-description">20秒でお絵描きチャレンジ！</div>
                </div>
            </div>
        </div>

        <!-- ステップ2: 名前入力 -->
        <div class="step-section" id="nameInput">
            <h2 class="step-title">👤 プレイヤー名を登録しよう！</h2>
            <div class="name-input-section">
                <div class="name-input-group">
                    <div class="name-input-wrapper">
                        <label for="playerName" style="text-align: left; margin-bottom: 8px; display: block; font-weight: bold;">君の名前は？</label>
                        <input type="text" id="playerName" class="form-control" maxlength="50" placeholder="ニックネームでもOK！">
                    </div>
                    <button type="button" id="confirmNameBtn" class="confirm-btn" disabled>
                        ✅ 確定
                    </button>
                </div>
            </div>
        </div>

        <!-- ステップ3: ゲーム準備 -->
        <div class="step-section" id="gameReady">
            <h2 class="step-title">🚀 チャレンジ準備完了！</h2>
            <div class="game-section">
                <div class="ready-message" id="readyMessage">
                    準備中...
                </div>
                <button type="button" id="startChallengeBtn" class="start-btn">
                    🎯 チャレンジ開始！
                </button>
            </div>
        </div>

        <!-- チャレンジエリア -->
        <div class="challenge-area" id="challengeArea">
            <form method="POST" action="" id="postForm">
                <input type="hidden" id="input_mode" name="input_mode" value="text">
                <input type="hidden" id="canvas_data" name="canvas_data" value="">
                <input type="hidden" id="player_name" name="name" value="">

                <div class="form-section">
                    <div class="form-group" id="text-input-group">
                        <label for="comment">20秒で思いを書こう！</label>
                        <textarea id="comment" name="comment" class="form-control" maxlength="1000" placeholder="今日の気分、好きなもの、なんでもOK！スピードが命だ！"></textarea>
                    </div>

                    <div class="canvas-container" id="canvas-container">
                        <label>20秒でお絵描きチャレンジ！</label>
                        <div class="drawing-tools">
                            <button type="button" class="tool-btn active" data-tool="pen">✏️ ペン</button>
                            <button type="button" class="tool-btn" data-tool="eraser">🧹 消しゴム</button>
                            <input type="color" class="color-picker" id="colorPicker" value="#ff6b6b" title="色を選ぼう">
                            <label for="brushSize">太さ:</label>
                            <input type="range" id="brushSize" class="size-slider" min="1" max="20" value="5">
                            <button type="button" class="tool-btn" id="undoBtn">↶ 戻る</button>
                            <button type="button" class="tool-btn" id="clearBtn">🗑️ リセット</button>
                        </div>
                        <canvas id="drawingCanvas" width="600" height="300"></canvas>
                    </div>

                    <div class="form-group" id="recommendation-group">
                        <label for="recommendation">おすすめがあれば追加で！（オプション）</label>
                        <input type="text" id="recommendation" name="recommendation" class="form-control" maxlength="100" placeholder="面白い映画、美味しい食べ物など...">
                    </div>
                </div>
            </form>
        </div>

        <div class="posts-section">
            <h2 class="posts-title">チャレンジ結果発表！</h2>
            
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
                            <!-- 投稿日を非表示にしました -->
                        </div>
                        
                        <?php 
                        // デバッグ情報（開発中のみ表示）
                        $debug_mode = true; // 本番環境では false に設定
                        if ($debug_mode) {
                            echo "<!-- デバッグ: image_filename = '" . htmlspecialchars($post['image_filename'] ?? 'NULL') . "' -->";
                            echo "<!-- デバッグ: comment = '" . htmlspecialchars($post['comment'] ?? 'NULL') . "' -->";
                        }
                        ?>
                        
                        <?php if (!empty($post['image_filename'])): ?>
                            <?php 
                            $image_path = __DIR__ . '/images/' . $post['image_filename'];
                            $web_image_path = 'images/' . $post['image_filename'];
                            
                            if ($debug_mode) {
                                echo "<!-- デバッグ: フルパス = '" . htmlspecialchars($image_path) . "' -->";
                                echo "<!-- デバッグ: Webパス = '" . htmlspecialchars($web_image_path) . "' -->";
                                echo "<!-- デバッグ: ファイル存在 = " . (file_exists($image_path) ? 'true' : 'false') . " -->";
                            }
                            ?>
                            <div class="image-container">
                                <?php if (file_exists($image_path)): ?>
                                    <div class="handwriting-label" style="margin-bottom: 10px; color: #667eea; font-weight: 600; font-size: 0.9rem;">
                                        🎨 手書きメッセージ
                                    </div>
                                    <img src="<?php echo htmlspecialchars($web_image_path); ?>" 
                                         alt="手書きコメント" 
                                         class="handwriting-image"
                                         style="max-width: 100%; height: auto; display: block;"
                                         onload="console.log('✓ 画像読み込み成功:', '<?php echo htmlspecialchars($post['image_filename']); ?>', 'サイズ:', this.naturalWidth + 'x' + this.naturalHeight);"
                                         onerror="console.error('✗ 画像読み込み失敗:', '<?php echo htmlspecialchars($post['image_filename']); ?>'); this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="image-error" style="display: none; color: #e74c3c; font-style: italic; padding: 10px; background: #f8d7da; border-radius: 8px; margin-top: 10px;">
                                        ❌ 画像の読み込みに失敗しました<br>
                                        <small>ファイル: <?php echo htmlspecialchars($post['image_filename']); ?></small><br>
                                        <small>パス: <?php echo htmlspecialchars($web_image_path); ?></small>
                                        <details style="margin-top: 5px;">
                                            <summary style="cursor: pointer; color: #666;">詳細情報</summary>
                                            <small>フルパス: <?php echo htmlspecialchars($image_path); ?></small><br>
                                            <small>ファイルサイズ: <?php echo file_exists($image_path) ? filesize($image_path) . ' bytes' : 'ファイルなし'; ?></small><br>
                                            <small>権限: <?php echo file_exists($image_path) ? (is_readable($image_path) ? '読み取り可能' : '読み取り不可') : 'ファイルなし'; ?></small>
                                        </details>
                                    </div>
                                <?php else: ?>
                                    <div class="image-error" style="color: #e74c3c; font-style: italic; padding: 10px; background: #f8d7da; border-radius: 8px;">
                                        ❌ 画像ファイルが見つかりません<br>
                                        <small>ファイル: <?php echo htmlspecialchars($post['image_filename']); ?></small><br>
                                        <details style="margin-top: 5px;">
                                            <summary style="cursor: pointer; color: #666;">詳細情報</summary>
                                            <small>フルパス: <?php echo htmlspecialchars($image_path); ?></small><br>
                                            <small>Webパス: <?php echo htmlspecialchars($web_image_path); ?></small><br>
                                            <small>imagesディレクトリ存在: <?php echo is_dir(__DIR__ . '/images') ? 'はい' : 'いいえ'; ?></small><br>
                                            <small>imagesディレクトリ権限: <?php echo is_readable(__DIR__ . '/images') ? '読み取り可能' : '読み取り不可'; ?></small>
                                        </details>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($post['comment'])): ?>
                            <div class="post-comment"><?php echo nl2br(htmlspecialchars($post['comment'])); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($post['recommendation'])): ?>
                            <div class="post-recommendation"><?php echo htmlspecialchars($post['recommendation']); ?></div>
                        <?php endif; ?>
                        
                        <?php 
                        // 投稿に何も表示されない場合の警告（デバッグ用）
                        if ($debug_mode && empty($post['image_filename']) && empty($post['comment']) && empty($post['recommendation'])) {
                            echo '<div style="color: #ff6b6b; font-style: italic; padding: 10px; background: #ffe0e0; border-radius: 8px;">';
                            echo '⚠️ 画像の投稿を表示するには「作品ギャラリー」を選択してください';
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ゲーム状態管理
        let currentStep = 1;
        let selectedMode = null;
        let playerName = '';
        let gameTimer = null;
        let timeLeft = 20; // 10秒から20秒に変更
        let isGameActive = false;

        // DOM要素
        const modeCards = document.querySelectorAll('.mode-card');
        const playerNameInput = document.getElementById('playerName');
        const confirmNameBtn = document.getElementById('confirmNameBtn');
        const startChallengeBtn = document.getElementById('startChallengeBtn');
        const readyMessage = document.getElementById('readyMessage');
        const countdownBarContainer = document.getElementById('countdownBarContainer');
        const countdownProgress = document.getElementById('countdownProgress');
        const countdownText = document.getElementById('countdownText');

        // ステップ管理
        function showStep(stepNumber) {
            // すべてのセクションを非表示
            document.querySelectorAll('.step-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // 指定されたステップを表示
            const stepSections = ['modeSelection', 'nameInput', 'gameReady'];
            if (stepSections[stepNumber - 1]) {
                document.getElementById(stepSections[stepNumber - 1]).classList.add('active');
            }
            
            // ステップインジケーター更新
            document.querySelectorAll('.step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index + 1 < stepNumber) {
                    step.classList.add('completed');
                } else if (index + 1 === stepNumber) {
                    step.classList.add('active');
                }
            });
            
            currentStep = stepNumber;
        }

        // モード選択
        modeCards.forEach(card => {
            card.addEventListener('click', function() {
                modeCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedMode = this.getAttribute('data-mode');
                
                // 1秒後に次のステップへ
                setTimeout(() => {
                    showStep(2);
                }, 500);
            });
        });

        // 名前入力確認
        playerNameInput.addEventListener('input', function() {
            const name = this.value.trim();
            confirmNameBtn.disabled = name.length === 0;
        });

        confirmNameBtn.addEventListener('click', function() {
            playerName = playerNameInput.value.trim();
            if (playerName) {
                // 準備完了メッセージ更新
                const modeText = selectedMode === 'text' ? 'スピード文字' : 'クイックドロー';
                readyMessage.innerHTML = `
                    <strong>${playerName}</strong>さん、ようこそ！<br>
                    <strong>${modeText}</strong>モードで20秒チャレンジに挑戦します。<br>
                    準備はいいですか？
                `;
                showStep(3);
            }
        });

        // チャレンジ開始
        startChallengeBtn.addEventListener('click', function() {
            startChallenge();
        });

        function startChallenge() {
            // フォームに値設定
            document.getElementById('input_mode').value = selectedMode;
            document.getElementById('player_name').value = playerName;
            
            // チャレンジエリア表示
            document.getElementById('challengeArea').classList.add('active');
            
            // 適切な入力フィールド表示
            if (selectedMode === 'text') {
                document.getElementById('text-input-group').style.display = 'block';
                document.getElementById('canvas-container').classList.remove('active');
                document.getElementById('recommendation-group').style.display = 'none';  // おすすめ欄を非表示
                document.getElementById('comment').focus();
            } else {
                document.getElementById('text-input-group').style.display = 'none';
                document.getElementById('canvas-container').classList.add('active');
                document.getElementById('recommendation-group').style.display = 'none';
            }
            
            // カウントダウン開始
            startCountdown();
        }

        function startCountdown() {
            timeLeft = 20;  // 10秒から20秒に変更
            isGameActive = true;
            countdownBarContainer.classList.add('active');
            
            updateCountdownDisplay();
            
            gameTimer = setInterval(() => {
                timeLeft--;
                updateCountdownDisplay();
                
                if (timeLeft <= 0) {
                    clearInterval(gameTimer);
                    finishChallenge();
                }
            }, 1000);
        }

        function updateCountdownDisplay() {
            const percentage = (timeLeft / 20) * 100;  // 20秒ベースに変更
            countdownProgress.style.width = percentage + '%';
            countdownText.textContent = `⏰ 残り ${timeLeft}秒！ 頑張って！`;
            
            if (timeLeft <= 5) {  // 3秒から5秒に変更
                countdownProgress.style.background = 'linear-gradient(45deg, #e74c3c, #c0392b)';
                countdownText.style.color = '#ff6b6b';
            }
        }

        function finishChallenge() {
            isGameActive = false;
            countdownBarContainer.classList.remove('active');
            
            const form = document.getElementById('postForm');
            
            if (selectedMode === 'handwriting') {
                const canvasData = canvas.toDataURL('image/png');
                document.getElementById('canvas_data').value = canvasData;
            }
            
            // 成功メッセージ表示
            const successOverlay = document.createElement('div');
            successOverlay.className = 'countdown-overlay';
            successOverlay.innerHTML = `
                <div class="countdown-content">
                    <div>🎉 チャレンジ完了！</div>
                    <div class="countdown-number">🏆</div>
                    <div>結果を投稿中...</div>
                </div>
            `;
            document.body.appendChild(successOverlay);

            setTimeout(() => {
                form.submit();
            }, 2000);
        }

        // キャンバス描画機能
        const canvas = document.getElementById('drawingCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let currentTool = 'pen';
        let currentColor = '#ff6b6b';
        let currentSize = 5;
        let undoStack = [];

        function saveState() {
            undoStack.push(canvas.toDataURL());
            if (undoStack.length > 20) {
                undoStack.shift();
            }
        }

        saveState();

        document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tool-btn[data-tool]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTool = this.getAttribute('data-tool');
            });
        });

        document.getElementById('colorPicker').addEventListener('change', function() {
            currentColor = this.value;
        });

        document.getElementById('brushSize').addEventListener('input', function() {
            currentSize = this.value;
        });

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

        document.getElementById('clearBtn').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            saveState();
        });

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

        // タッチイベント
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

        // パーティクルアニメーション
        function createParticle() {
            const particles = ['🎨', '✏️', '🌟', '🎮', '💭', '🚀', '⭐', '🎯', '💫', '🎊'];
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.textContent = particles[Math.floor(Math.random() * particles.length)];
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            document.querySelector('.floating-particles').appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 6000);
        }

        setInterval(createParticle, 3000);
    </script>
</body>
</html>