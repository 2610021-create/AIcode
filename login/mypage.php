<?php require_once '../includes/functions.php'; ?>

<?php
// セッションを使うために開始
session_start();

// データベースに接続
$pdo = getPDO();

// ========================================
// ログインしているユーザーを取得
// ========================================

// login_check.phpで保存したセッションのuser_idを確認
if (!isset($_SESSION['user_id'])) {
    // ログインしていなければloginフォルダのlogin.phpへ戻す
    header('Location: ../login/login.php');
    exit;
}

// セッションからユーザーIDを取得
$user_id = $_SESSION['user_id'];

// ========================================
// usersテーブルからユーザー情報を取得
// ========================================

$sql = "
    SELECT
        image,
        name,
        user_id,
        email,
        gender,
        age,
        height,
        weight,
        role,
        created_at
    FROM users
    WHERE user_id = ?
";

// SQLを準備
$stmt = $pdo->prepare($sql);

// ユーザーIDを入れて実行
$stmt->execute([$user_id]);

// ユーザー情報を取得
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ユーザー情報が取得できなかった場合
if (!$user) {
    echo 'ユーザー情報が見つかりません。';
    exit;
}

// ========================================
// 表示用のデータ
// ========================================

// 名前
$name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');

// ユーザーID
$display_id = htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8');

// ========================================
// プロフィール画像
// ========================================

if (!empty($user['image'])) {
    $profile_image = '../' . htmlspecialchars(
        $user['image'],
        ENT_QUOTES,
        'UTF-8'
    );
} else {
    $profile_image = '../images/default_user.png';
}

// ========================================
// お気に入り商品（仮データ）
// ========================================

$favorites = [
    [
        'image' => '../images/shirt.jpg',
        'name' => 'オーバーサイズシャツ',
        'price' => '¥5,990'
    ],
    [
        'image' => '../images/sneaker.jpg',
        'name' => 'スニーカー',
        'price' => '¥7,390'
    ],
    [
        'image' => '../images/tshirt.jpg',
        'name' => 'シンプルTシャツ',
        'price' => '¥2,490'
    ],
    [
        'image' => '../images/pants.jpg',
        'name' => 'ワイドパンツ',
        'price' => '¥5,490'
    ],
    [
        'image' => '../images/cap.jpg',
        'name' => 'キャップ',
        'price' => '¥3,290'
    ]
];

// ========================================
// 閲覧履歴（仮データ）
// ========================================

$history = [
    [
        'image' => '../images/parka.jpg',
        'name' => 'パーカー',
        'price' => '¥5,490'
    ],
    [
        'image' => '../images/black_sneaker.jpg',
        'name' => 'スニーカー',
        'price' => '¥7,390'
    ],
    [
        'image' => '../images/blue_knit.jpg',
        'name' => 'シャツ',
        'price' => '¥4,990'
    ],
    [
        'image' => '../images/bag.jpg',
        'name' => 'トートバッグ',
        'price' => '¥3,990'
    ],
    [
        'image' => '../images/knit.jpg',
        'name' => 'ニット',
        'price' => '¥4,990'
    ]
];

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ</title>

    <!-- マイページ専用CSS -->
    <link rel="stylesheet" href="../assets/css/mypage.css">
</head>

<body>

<div class="mypage-container">

    <!-- ========================================
        ページタイトル
    ========================================= -->

    <div class="bottom-area2">
        <div class="page-title">
            <h1>マイページ</h1>
            <p>
                登録情報の確認・変更や、購入履歴の管理ができます。
            </p>
        </div>

        <div class="page_banner">
            <p>
                好きな服でもっと好きな自分に。
            </p>
        </div>
    </div>

    <!-- ========================================
        上段
    ========================================= -->

    <div class="top-area">

        <!-- ====================================
            プロフィール
        ===================================== -->

        <div class="profile-box">
            <div class="profile-main">
                <!-- プロフィール画像 -->
                <div class="profile-image-area">
                    <img
                        src="<?= $profile_image ?>"
                        alt="プロフィール画像"
                        class="profile-image"
                    >
                </div>

                <!-- ユーザー情報 -->
                <div class="profile-info">
                    <h2><?= $name ?></h2>
                    <!-- プロフィール編集 -->
                    <a href="profile_edit.php" class="edit-button">
                        ✎ プロフィールを編集
                    </a>
                </div>
            </div>

            <!-- ラッキーカラー -->
            <div class="lucky-color">
                <span class="star">✧</span>
                <strong>今日のラッキーカラー</strong>
                <div class="color-list">
                    <span class="color purple"></span>
                    <span class="color blue"></span>
                    <span class="color green"></span>
                    <span class="color white"></span>
                </div>
            </div>

            <p class="color-text">
                パープル・ブルー・グリーン・ホワイト
            </p>
        </div>

        <!-- ====================================
            右側
        ===================================== -->

        <div class="right-top">
            <!-- 基本情報 -->
            <a href="profile.php" class="menu-card">
                <div>
                    <span class="menu-icon">♙</span>
                    <strong>基本情報</strong>
                </div>
                <span class="arrow">›</span>
            </a>

            <!-- パスワード変更 -->
            <div class="password-card">
                <div class="password-title">
                    <span class="menu-icon">♙</span>
                    <strong>パスワード変更</strong>
                </div>
                <p>現在のパスワードを変更できます。</p>
                <a href="password_change.php" class="change-button">
                    変更する　›
                </a>
            </div>
        </div>

        <!-- ====================================
            ★ AIコーディネート検索（カード全体をクリックで画面遷移）
        ===================================== -->

        <div class="ai-card" onclick="location.href='../coordinate/coordinate.php'" style="cursor: pointer;" title="クリックしてAIコーディネート画面へ">

            <h2>
                ✨ AIコーディネート検索
            </h2>

            <h3>
                あなたの服に合うコーデを見つけよう
            </h3>

            <p>
                自分の服を登録すると、<br>
                AIがその服に合うアイテムや<br>
                コーディネートを提案してくれます。
            </p>

            <a
                href="../coordinate/coordinate.php"
                class="ai-button"
            >
                コーデを検索する　›
            </a>

        </div>

    </div>

    <!-- ========================================
        商品エリア
    ========================================= -->

    <div class="products-area">

        <!-- ====================================
            お気に入り
        ===================================== -->

        <div class="product-section">
            <div class="section-title">
                <h2>♡　お気に入り商品</h2>
                <a href="../favorite/favorite.php">すべて見る　›</a>
            </div>

            <div class="product-list">
                <?php foreach ($favorites as $item): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img
                                src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <span class="favorite-mark">♡</span>
                        </div>
                        <p class="product-name">
                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="product-price">
                            <?= htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ====================================
            閲覧履歴
        ===================================== -->

        <div class="product-section">
            <div class="section-title">
                <h2>◉　閲覧履歴</h2>
                <a href="../history/history.php">すべて見る　›</a>
            </div>

            <div class="product-list">
                <?php foreach ($history as $item): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img
                                src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <span class="favorite-mark">♡</span>
                        </div>
                        <p class="product-name">
                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="product-price">
                            <?= htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ========================================
        その他
    ========================================= -->

    <div class="bottom-area">
        <div class="other-box">
            <div class="section-title">
                <h2>▤　その他</h2>
                <a href="#">すべて見る　›</a>
            </div>

            <div class="notice">
                <span>📢　お知らせ</span>
                <span class="notice-count">2</span>
                <span class="arrow">›</span>
            </div>

            <div class="notice-item">
                <span>新作アイテムが入荷しました！</span>
                <span>2025.09.12</span>
                <span>›</span>
            </div>

            <div class="notice-item">
                <span>夏のクリアランスセール開催中！</span>
                <span>2025.09.08</span>
                <span>›</span>
            </div>
        </div>

        <!-- 下部バナー -->
        <div class="bottom-banner">
            <p>
                あなたの「好き」が<br>
                きっと見つかる
            </p>
        </div>
    </div>

</div>

</body>
</html>
