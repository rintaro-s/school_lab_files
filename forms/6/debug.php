<?php
// デバッグ情報表示
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サーバー情報</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>サーバー環境チェック</h1>
    
    <div class="section">
        <h2>基本情報</h2>
        <p>PHP Version: <?php echo phpversion(); ?></p>
        <p>Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
        <p>Request Method: <?php echo $_SERVER['REQUEST_METHOD'] ?? 'Unknown'; ?></p>
        <p>Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
        <p>Script Filename: <?php echo __FILE__; ?></p>
    </div>

    <div class="section">
        <h2>ディレクトリ確認</h2>
        <?php
        $current_dir = __DIR__;
        echo "<p>Current Directory: {$current_dir}</p>";
        
        if (is_writable($current_dir)) {
            echo '<p class="success">✓ ディレクトリは書き込み可能です</p>';
        } else {
            echo '<p class="error">✗ ディレクトリが書き込み不可です</p>';
        }

        $images_dir = $current_dir . '/images';
        if (file_exists($images_dir)) {
            echo '<p class="success">✓ imagesディレクトリが存在します</p>';
            if (is_writable($images_dir)) {
                echo '<p class="success">✓ imagesディレクトリは書き込み可能です</p>';
            } else {
                echo '<p class="error">✗ imagesディレクトリが書き込み不可です</p>';
            }
        } else {
            echo '<p class="error">✗ imagesディレクトリが存在しません</p>';
            if (mkdir($images_dir, 0777, true)) {
                echo '<p class="success">✓ imagesディレクトリを作成しました</p>';
            } else {
                echo '<p class="error">✗ imagesディレクトリの作成に失敗しました</p>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>ファイル確認</h2>
        <?php
        $files = ['config.php', 'functions.php', 'index.php'];
        foreach ($files as $file) {
            if (file_exists($file)) {
                echo "<p class=\"success\">✓ {$file} が存在します</p>";
            } else {
                echo "<p class=\"error\">✗ {$file} が見つかりません</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>データベース接続テスト</h2>
        <?php
        try {
            if (file_exists('config.php')) {
                require_once 'config.php';
                if (isset($pdo) && $pdo !== null) {
                    echo '<p class="success">✓ データベース接続成功</p>';
                    
                    // テーブル存在確認
                    $stmt = $pdo->query("SHOW TABLES LIKE 'posts'");
                    if ($stmt->rowCount() > 0) {
                        echo '<p class="success">✓ postsテーブルが存在します</p>';
                    } else {
                        echo '<p class="error">✗ postsテーブルが存在しません</p>';
                    }
                } else {
                    echo '<p class="error">✗ データベース接続失敗</p>';
                }
            } else {
                echo '<p class="error">✗ config.phpが見つかりません</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">✗ データベースエラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>HTTP Headers</h2>
        <?php
        foreach (getallheaders() as $name => $value) {
            echo "<p>{$name}: {$value}</p>";
        }
        ?>
    </div>

    <p><a href="index.php">← メインページに戻る</a></p>
</body>
</html>
