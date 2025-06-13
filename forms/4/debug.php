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
        .warning { color: orange; }
        .image-test { max-width: 200px; margin: 10px 0; border: 1px solid #ddd; }
        .upload-form { margin: 20px 0; }
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

    <div class="section">
        <h2>画像アップロードテスト</h2>
        <form class="upload-form" method="post" enctype="multipart/form-data">
            <input type="file" name="test_image" accept="image/*">
            <input type="submit" value="画像をテストアップロード">
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
            echo "<h3>アップロード結果:</h3>";
            $upload = $_FILES['test_image'];
            
            // アップロード情報の詳細表示
            echo "<pre>";
            echo "ファイル名: " . htmlspecialchars($upload['name']) . "\n";
            echo "MIMEタイプ: " . htmlspecialchars($upload['type']) . "\n";
            echo "ファイルサイズ: " . $upload['size'] . " bytes\n";
            echo "一時ファイル: " . htmlspecialchars($upload['tmp_name']) . "\n";
            echo "エラーコード: " . $upload['error'] . "\n";
            echo "</pre>";
            
            // エラーチェック
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'ファイルサイズがphp.iniのupload_max_filesizeを超えています',
                    UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがHTMLフォームのMAX_FILE_SIZEを超えています',
                    UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
                    UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
                    UPLOAD_ERR_NO_TMP_DIR => '一時フォルダがありません',
                    UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
                    UPLOAD_ERR_EXTENSION => 'PHPの拡張モジュールによってアップロードが停止されました'
                ];
                echo '<p class="error">✗ アップロードエラー: ' . ($error_messages[$upload['error']] ?? '不明なエラー') . '</p>';
            } else {
                // 画像ファイルかチェック
                $image_info = getimagesize($upload['tmp_name']);
                if ($image_info === false) {
                    echo '<p class="error">✗ 有効な画像ファイルではありません</p>';
                } else {
                    echo '<p class="success">✓ 有効な画像ファイルです</p>';
                    echo "<p>画像サイズ: {$image_info[0]} x {$image_info[1]} px</p>";
                    echo "<p>画像タイプ: " . image_type_to_mime_type($image_info[2]) . "</p>";
                    
                    // 画像をimagesディレクトリに保存
                    $images_dir = __DIR__ . '/images';
                    if (!file_exists($images_dir)) {
                        mkdir($images_dir, 0777, true);
                    }
                    
                    $filename = 'test_' . date('Y-m-d_H-i-s') . '_' . basename($upload['name']);
                    $filepath = $images_dir . '/' . $filename;
                    
                    if (move_uploaded_file($upload['tmp_name'], $filepath)) {
                        echo '<p class="success">✓ 画像保存成功: ' . htmlspecialchars($filename) . '</p>';
                        echo '<img src="images/' . htmlspecialchars($filename) . '" class="image-test" alt="テスト画像">';
                    } else {
                        echo '<p class="error">✗ 画像保存失敗</p>';
                        echo '<p class="error">権限エラーまたはディスクスペース不足の可能性があります</p>';
                    }
                }
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>既存画像の表示テスト</h2>
        <?php
        $images_dir = __DIR__ . '/images';
        if (file_exists($images_dir)) {
            $images = glob($images_dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            if (count($images) > 0) {
                echo '<p class="success">✓ ' . count($images) . '個の画像ファイルが見つかりました</p>';
                foreach ($images as $image) {
                    $filename = basename($image);
                    $relative_path = 'images/' . $filename;
                    
                    // ファイル情報取得
                    $filesize = filesize($image);
                    $image_info = getimagesize($image);
                    
                    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #eee;'>";
                    echo "<p><strong>" . htmlspecialchars($filename) . "</strong></p>";
                    echo "<p>サイズ: " . number_format($filesize) . " bytes</p>";
                    
                    if ($image_info) {
                        echo "<p>解像度: {$image_info[0]} x {$image_info[1]} px</p>";
                        echo "<p>MIMEタイプ: " . htmlspecialchars($image_info['mime']) . "</p>";
                        
                        // 画像表示テスト
                        echo "<img src='{$relative_path}' class='image-test' alt='" . htmlspecialchars($filename) . "' onerror='this.style.border=\"2px solid red\"; this.alt=\"画像読み込みエラー\";'>";
                    } else {
                        echo '<p class="error">✗ 画像情報の取得に失敗</p>';
                    }
                    echo "</div>";
                }
            } else {
                echo '<p class="warning">! 画像ファイルが見つかりません</p>';
            }
        } else {
            echo '<p class="error">✗ imagesディレクトリが存在しません</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>PHP画像処理拡張の確認</h2>
        <?php
        $extensions = ['gd', 'imagick', 'exif'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                echo "<p class=\"success\">✓ {$ext} 拡張が利用可能です</p>";
                if ($ext === 'gd') {
                    $gd_info = gd_info();
                    echo "<ul>";
                    foreach (['JPEG Support', 'PNG Support', 'GIF Read Support', 'WebP Support'] as $support) {
                        if (isset($gd_info[$support]) && $gd_info[$support]) {
                            echo "<li class=\"success\">✓ {$support}</li>";
                        } else {
                            echo "<li class=\"error\">✗ {$support}</li>";
                        }
                    }
                    echo "</ul>";
                }
            } else {
                echo "<p class=\"error\">✗ {$ext} 拡張が利用できません</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>Webアクセス可能性テスト</h2>
        <?php
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $current_dir = __DIR__;
        $images_dir = $current_dir . '/images';
        
        echo "<p>Document Root: " . htmlspecialchars($document_root) . "</p>";
        echo "<p>Current Directory: " . htmlspecialchars($current_dir) . "</p>";
        echo "<p>Images Directory: " . htmlspecialchars($images_dir) . "</p>";
        
        // 相対パス計算
        if (!empty($document_root) && strpos($current_dir, $document_root) === 0) {
            $relative_path = str_replace($document_root, '', $current_dir);
            $web_images_path = $relative_path . '/images';
            echo "<p class=\"success\">✓ Web相対パス: " . htmlspecialchars($web_images_path) . "</p>";
        } else {
            echo "<p class=\"error\">✗ Document Rootからの相対パス計算失敗</p>";
        }
        
        // .htaccess確認
        $htaccess_path = $current_dir . '/.htaccess';
        if (file_exists($htaccess_path)) {
            echo "<p class=\"warning\">! .htaccessファイルが存在します - 画像アクセスに影響する可能性があります</p>";
            echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
        } else {
            echo "<p class=\"success\">✓ .htaccessファイルは存在しません</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>画像直接アクセステスト</h2>
        <?php
        if (file_exists($images_dir)) {
            $images = glob($images_dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            if (count($images) > 0) {
                echo '<p class="success">✓ ' . count($images) . '個の画像ファイルが見つかりました</p>';
                
                foreach (array_slice($images, 0, 5) as $image) { // 最初の5個のみテスト
                    $filename = basename($image);
                    $relative_path = 'images/' . $filename;
                    $full_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $base_url = dirname($full_url) . '/';
                    $image_url = $base_url . $relative_path;
                    
                    // ファイル情報取得
                    $filesize = filesize($image);
                    $image_info = getimagesize($image);
                    
                    echo "<div style='margin: 15px 0; padding: 15px; border: 1px solid #eee; border-radius: 8px;'>";
                    echo "<h4>" . htmlspecialchars($filename) . "</h4>";
                    echo "<p>ファイルサイズ: " . number_format($filesize) . " bytes</p>";
                    echo "<p>相対パス: " . htmlspecialchars($relative_path) . "</p>";
                    echo "<p>完全URL: <a href='" . htmlspecialchars($image_url) . "' target='_blank'>" . htmlspecialchars($image_url) . "</a></p>";
                    
                    if ($image_info) {
                        echo "<p>解像度: {$image_info[0]} x {$image_info[1]} px</p>";
                        echo "<p>MIMEタイプ: " . htmlspecialchars($image_info['mime']) . "</p>";
                        
                        // 画像表示テスト（複数の方法）
                        echo "<h5>表示テスト:</h5>";
                        
                        // 方法1: 相対パス
                        echo "<div style='margin: 10px 0;'>";
                        echo "<p>方法1 - 相対パス:</p>";
                        echo "<img src='{$relative_path}' style='max-width: 200px; border: 2px solid #ddd;' 
                                   alt='{$filename}' 
                                   onload='this.nextElementSibling.innerHTML=\"✓ 読み込み成功\"; this.nextElementSibling.className=\"success\";'
                                   onerror='this.nextElementSibling.innerHTML=\"✗ 読み込み失敗\"; this.nextElementSibling.className=\"error\";'>";
                        echo "<p>読み込み中...</p>";
                        echo "</div>";
                        
                        // 方法2: Data URL
                        echo "<div style='margin: 10px 0;'>";
                        echo "<p>方法2 - Data URL:</p>";
                        $image_data = base64_encode(file_get_contents($image));
                        $data_url = 'data:' . $image_info['mime'] . ';base64,' . $image_data;
                        echo "<img src='{$data_url}' style='max-width: 200px; border: 2px solid #ddd;' alt='{$filename}'>";
                        echo "<p class='success'>✓ Data URL表示成功</p>";
                        echo "</div>";
                        
                        // ファイル権限チェック
                        $perms = substr(sprintf('%o', fileperms($image)), -4);
                        echo "<p>ファイル権限: " . $perms . " (" . (is_readable($image) ? "読み取り可能" : "読み取り不可") . ")</p>";
                        
                    } else {
                        echo '<p class="error">✗ 画像情報の取得に失敗</p>';
                    }
                    echo "</div>";
                }
            } else {
                echo '<p class="warning">! 画像ファイルが見つかりません</p>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>サーバー設定確認</h2>
        <?php
        echo "<p>allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "</p>";
        echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "</p>";
        echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
        echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
        echo "<p>memory_limit: " . ini_get('memory_limit') . "</p>";
        echo "<p>max_execution_time: " . ini_get('max_execution_time') . "</p>";
        
        // MIME Type設定確認
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            if (in_array('mod_mime', $modules)) {
                echo '<p class="success">✓ Apache mod_mime が有効です</p>';
            } else {
                echo '<p class="warning">! Apache mod_mime が見つかりません</p>';
            }
        }
        ?>
    </div>

    <p><a href="index.php">← メインページに戻る</a></p>
</body>
</html>
