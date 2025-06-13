<?php
// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'lorinta_pbl1');
define('DB_USER', 'lorinta_pbl1');
define('DB_PASS', '123456789');
define('DB_CHARSET', 'utf8mb4');

// グローバル変数としてPDOを定義
$pdo = null;

// データベース接続
function getDBConnection() {
    global $pdo;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('データベース接続エラー: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// データベーステーブル作成
function createTables() {
    $pdo = getDBConnection();
    
    // 投稿テーブル
    $sql = "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        comment TEXT NOT NULL,
        recommendation VARCHAR(100) DEFAULT NULL,
        image_filename VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) DEFAULT NULL,
        INDEX idx_created_at (created_at),
        INDEX idx_ip_address (ip_address),
        INDEX idx_image_filename (image_filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // NGワードテーブル
    $sql = "CREATE TABLE IF NOT EXISTS ng_words (
        id INT AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_word (word)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // デフォルトのNGワードを挿入
    $defaultNGWords = [
        'バカ', 'アホ', '死ね', 'クソ', 'うざい', 'きもい',
        'ブス', 'デブ', '消えろ', 'ムカつく', 'spam', '広告', '宣伝'
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO ng_words (word) VALUES (?)");
    foreach ($defaultNGWords as $word) {
        $stmt->execute([$word]);
    }
}

// 初期化実行とグローバル変数への代入
$pdo = getDBConnection();
createTables();
?>