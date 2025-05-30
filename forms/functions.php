<?php
require_once 'config.php';

function addPost($name, $comment, $recommendation = '') {
    try {
        $pdo = getDBConnection();
        $sql = "INSERT INTO posts (name, comment, recommendation, ip_address) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return $stmt->execute([$name, $comment, $recommendation, $ip]);
    } catch (Exception $e) {
        error_log('投稿エラー: ' . $e->getMessage());
        return false;
    }
}

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

function hasNGWords($text) {
    if (empty($text)) {
        return false;
    }
    
    try {
        $pdo = getDBConnection();
        $sql = "SELECT word FROM ng_words";
        $stmt = $pdo->query($sql);
        $ngWords = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($ngWords as $ngWord) {
            if (strpos($text, $ngWord) !== false) {
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log('NGワードチェックエラー: ' . $e->getMessage());
        return false;
    }
}

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

function canPost($ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    try {
        $pdo = getDBConnection();
        $sql = "SELECT COUNT(*) FROM posts 
                WHERE ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        
        $hourCount = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM posts 
                WHERE ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        
        $dayCount = $stmt->fetchColumn();
        
        return $hourCount < 5 && $dayCount < 20;
    } catch (Exception $e) {
        error_log('投稿制限チェックエラー: ' . $e->getMessage());
        return true;
    }
}

function sanitizeText($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

function validateLength($text, $maxLength) {
    return mb_strlen($text, 'UTF-8') <= $maxLength;
}
?>