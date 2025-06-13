<?php
require_once 'config.php';

/**
 * 投稿を追加する
 */
function addPost($name, $comment, $recommendation = '', $image_filename = null) {
    global $pdo;
    
    // データベース接続チェック
    if (!isset($pdo) || $pdo === null) {
        return "データベース接続エラー: PDO オブジェクトが初期化されていません。config.php を確認してください。";
    }
    
    try {
        // テーブル存在チェック
        $checkTable = $pdo->query("SHOW TABLES LIKE 'posts'");
        if ($checkTable->rowCount() == 0) {
            return "テーブルエラー: 'posts' テーブルが存在しません。以下のSQLでテーブルを作成してください：\n\nCREATE TABLE posts (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(50) NOT NULL,\n    comment TEXT,\n    recommendation VARCHAR(100),\n    image_filename VARCHAR(255),\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n);";
        }
        
        // カラム存在チェック
        $columns = $pdo->query("DESCRIBE posts")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('image_filename', $columns)) {
            return "テーブル構造エラー: 'image_filename' カラムが存在しません。以下のSQLでカラムを追加してください：\n\nALTER TABLE posts ADD COLUMN image_filename VARCHAR(255) AFTER recommendation;";
        }
        
        $sql = "INSERT INTO posts (name, comment, recommendation, image_filename, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            return "SQL準備エラー: " . implode(", ", $pdo->errorInfo());
        }
        
        $result = $stmt->execute([$name, $comment, $recommendation, $image_filename]);
        
        if (!$result) {
            return "SQL実行エラー: " . implode(", ", $stmt->errorInfo());
        }
        
        return true;
    } catch (PDOException $e) {
        return "データベースエラー: " . $e->getMessage() . "\n\n考えられる原因：\n1. データベース接続設定の確認\n2. テーブル構造の確認\n3. 権限の確認";
    } catch (Exception $e) {
        return "一般エラー: " . $e->getMessage();
    }
}

/**
 * 投稿一覧を取得する
 */
function getPosts($limit = 20, $offset = 0) {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT id, name, comment, recommendation, created_at 
                FROM posts 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('投稿取得エラー: ' . $e->getMessage());
        return [];
    }
}

/**
 * NGワードをチェックする
 */
function hasNGWords($text) {
    $ngWords = ['spam', 'バカ', 'アホ', '死ね'];
    
    foreach ($ngWords as $word) {
        if (stripos($text, $word) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * NGワードを追加する
 */
function addNGWord($word) {
    try {
        $pdo = getDBConnection();
        $sql = "INSERT IGNORE INTO ng_words (word) VALUES (?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$word]);
    } catch (Exception $e) {
        error_log('NGワード追加エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * NGワード一覧を取得する
 */
function getNGWords() {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT id, word, created_at FROM ng_words ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('NGワード取得エラー: ' . $e->getMessage());
        return [];
    }
}

/**
 * NGワードを削除する
 */
function deleteNGWord($id) {
    try {
        $pdo = getDBConnection();
        $sql = "DELETE FROM ng_words WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log('NGワード削除エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 投稿を削除する（管理者用）
 */
function deletePost($id) {
    try {
        $pdo = getDBConnection();
        $sql = "DELETE FROM posts WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log('投稿削除エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 投稿数を取得する
 */
function getPostCount() {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT COUNT(*) FROM posts";
        $stmt = $pdo->query($sql);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('投稿数取得エラー: ' . $e->getMessage());
        return 0;
    }
}

/**
 * IPアドレスベースの投稿制限チェック（改善版）
 */
function canPost($ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    try {
        $pdo = getDBConnection();
        // 1時間以内の投稿数をチェック
        $sql = "SELECT COUNT(*) FROM posts 
                WHERE ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        
        $hourCount = $stmt->fetchColumn();
        
        // 1日以内の投稿数もチェック
        $sql = "SELECT COUNT(*) FROM posts 
                WHERE ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        
        $dayCount = $stmt->fetchColumn();
        
        return $hourCount < 5 && $dayCount < 20; // 1時間に5回、1日に20回まで
    } catch (Exception $e) {
        error_log('投稿制限チェックエラー: ' . $e->getMessage());
        return true;
    }
}

/**
 * テキストをサニタイズする
 */
function sanitizeText($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

/**
 * 文字数制限チェック
 */
function validateLength($text, $maxLength) {
    return mb_strlen($text, 'UTF-8') <= $maxLength;
}
?>