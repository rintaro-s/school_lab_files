<?php
require_once 'config.php';
require_once 'functions.php';

function getRecommendationPosts($limit = 50) {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT name, comment, recommendation, created_at 
                FROM posts 
                WHERE recommendation IS NOT NULL AND recommendation != ''
                ORDER BY created_at DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('おすすめ投稿取得エラー: ' . $e->getMessage());
        return [];
    }
}

$recommendations = getRecommendationPosts();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>おすすめ情報 - 未来掲示板</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            backdrop-filter: blur(15px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .header .nav-link {
            position: absolute;
            top: 20px;
            left: 20px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            min-width: 120px;
            text-align: center;
            cursor: pointer;
            z-index: 10;
        }

        .header .nav-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .header h1 {
            font-size: 3rem;
            background: linear-gradient(45deg, #667eea, #764ba2, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            position: relative;
        }

        .header h1::before {
            content: "🌟";
            position: absolute;
            left: -60px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            animation: sparkle 2s infinite;
        }

        .header h1::after {
            content: "🌟";
            position: absolute;
            right: -60px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            animation: sparkle 2s infinite 1s;
        }

        .header p {
            color: #555;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .recommendation-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .recommendation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            transition: left 0.8s ease;
        }

        .recommendation-card:hover::before {
            left: 100%;
        }

        .recommendation-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 50px rgba(102, 126, 234, 0.2);
        }

        .recommendation-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recommendation-title::before {
            content: "👍";
            font-size: 1.2rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            padding: 5px;
            border-radius: 50%;
            color: white;
        }

        .recommendation-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #333;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .recommendation-comment {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
            background: rgba(102, 126, 234, 0.05);
            padding: 12px;
            border-radius: 10px;
            font-style: italic;
        }

        .recommendation-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 12px;
        }

        .recommendation-author {
            font-weight: 600;
            color: #667eea;
        }

        .recommendation-date {
            opacity: 0.8;
        }

        .empty-recommendations {
            text-align: center;
            padding: 60px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .empty-recommendations h2 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .empty-recommendations p {
            font-size: 1.1rem;
            color: #666;
        }

        .floating-icons {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-icon {
            position: absolute;
            font-size: 2rem;
            opacity: 0.1;
            animation: float 8s ease-in-out infinite;
        }

        .stats-bar {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .stats-bar h3 {
            color: #667eea;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .stats-count {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @keyframes sparkle {
            0%, 100% { transform: translateY(-50%) scale(1); opacity: 1; }
            50% { transform: translateY(-50%) scale(1.2); opacity: 0.8; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            25% { transform: translateY(-30px) rotate(90deg); opacity: 0.2; }
            50% { transform: translateY(-60px) rotate(180deg); opacity: 0.1; }
            75% { transform: translateY(-30px) rotate(270deg); opacity: 0.15; }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .header h1::before,
            .header h1::after {
                display: none;
            }
            
            .recommendations-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .recommendation-card {
                padding: 20px;
            }
        }

        /* 自動更新インジケーター */
        .update-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .update-indicator.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="floating-icons">
        <div class="floating-icon" style="left: 10%; animation-delay: 0s;">🍜</div>
        <div class="floating-icon" style="left: 20%; animation-delay: 1s;">🍕</div>
        <div class="floating-icon" style="left: 30%; animation-delay: 2s;">📚</div>
        <div class="floating-icon" style="left: 40%; animation-delay: 3s;">🎵</div>
        <div class="floating-icon" style="left: 50%; animation-delay: 4s;">🎮</div>
        <div class="floating-icon" style="left: 60%; animation-delay: 5s;">🎬</div>
        <div class="floating-icon" style="left: 70%; animation-delay: 6s;">☕</div>
        <div class="floating-icon" style="left: 80%; animation-delay: 7s;">🛍️</div>
        <div class="floating-icon" style="left: 90%; animation-delay: 0.5s;">🌸</div>
    </div>

    <div class="update-indicator" id="updateIndicator">
        📺 更新中...
    </div>

    <div class="container">
        <div class="header">
            <a href="index.php" class="nav-link">← 掲示板に戻る</a>
            <h1>みんなのおすすめ</h1>
            <p>コミュニティが選んだ素敵な情報をお届け</p>
        </div>

        <div class="stats-bar">
            <h3>おすすめ総数</h3>
            <div class="stats-count"><?php echo count($recommendations); ?></div>
        </div>

        <?php if (empty($recommendations)): ?>
            <div class="empty-recommendations">
                <h2>🌟 まだおすすめがありません</h2>
                <p>みんながおすすめを投稿してくれるのを待っています！<br>
                掲示板でおすすめを共有してみませんか？</p>
            </div>
        <?php else: ?>
            <div class="recommendations-grid" id="recommendationsGrid">
                <?php foreach ($recommendations as $rec): ?>
                    <div class="recommendation-card">
                        <div class="recommendation-title">
                            <?php echo htmlspecialchars($rec['recommendation']); ?>
                        </div>
                        
                        <?php if (!empty($rec['comment'])): ?>
                            <div class="recommendation-comment">
                                "<?php echo htmlspecialchars(mb_substr($rec['comment'], 0, 150, 'UTF-8')); 
                                if (mb_strlen($rec['comment'], 'UTF-8') > 150) echo '...'; ?>"
                            </div>
                        <?php endif; ?>
                        
                        <div class="recommendation-meta">
                            <span class="recommendation-author">
                                by <?php echo htmlspecialchars($rec['name']); ?>
                            </span>
                            <span class="recommendation-date">
                                <?php echo date('m/d H:i', strtotime($rec['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // 自動更新機能
        let updateInterval;
        
        function showUpdateIndicator() {
            const indicator = document.getElementById('updateIndicator');
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }

        function autoUpdate() {
            fetch('recommendations.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    const newGrid = newDoc.getElementById('recommendationsGrid');
                    const currentGrid = document.getElementById('recommendationsGrid');
                    
                    if (newGrid && currentGrid && newGrid.innerHTML !== currentGrid.innerHTML) {
                        showUpdateIndicator();
                        currentGrid.innerHTML = newGrid.innerHTML;
                        animateNewCards();
                    }
                })
                .catch(error => console.log('更新エラー:', error));
        }

        function animateNewCards() {
            const cards = document.querySelectorAll('.recommendation-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            animateNewCards();
            
            // 30秒ごとに自動更新
            updateInterval = setInterval(autoUpdate, 30000);
        });

        // ページが非表示になったら更新を停止
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(updateInterval);
            } else {
                updateInterval = setInterval(autoUpdate, 30000);
            }
        });

        // カードホバー時のサウンド効果（オプション）
        document.querySelectorAll('.recommendation-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>