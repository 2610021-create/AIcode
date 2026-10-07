<?php
/**
 * mypage.php - norn AIコーディネート＆マイページ
 * 
 * =========================================================================
 * 【機能概要】
 * 4つのタブ切り替え:
 *   ① AIが提案するコーデ (items商品 × ユーザー手持ち服のセット提案)
 *   ② 持っている服       (ユーザー自身がAIに読み込ませた服DBのみ)
 *   ③ お気に入り         (ユーザーがお気に入り登録した商品のみ)
 *   ④ おすすめ           (手持ち服との相性をAIが判断した商品単体)
 * 
 * 条件選択機能:
 *   [カテゴリ] [季節] [スタイル] [アクセサリーの色] [服の色] [サイズ]
 *   最大3つまで選択可能（3つ選択で他は自動無効化）
 * =========================================================================
 */

// 共通関数の読み込み
if (file_exists('./includes/functions.php')) {
    require_once './includes/functions.php';
}

// データベース接続
$pdo = function_exists('getPDO') ? getPDO() : null;

// 仮のユーザーID（セッションがあればセッション優先）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? 4;

// -------------------------------------------------------------
// 1. パラメーターの取得と条件数カウント（最大3つまで）
// -------------------------------------------------------------
$active_tab = $_GET['tab'] ?? 'ai_coord'; // ai_coord | my_clothes | favorites | recommend

$filter_category  = trim($_GET['category'] ?? '');
$filter_season    = trim($_GET['season'] ?? '');
$filter_style     = trim($_GET['style'] ?? '');
$filter_acc_color = trim($_GET['acc_color'] ?? '');
$filter_color     = trim($_GET['color'] ?? '');
$filter_size      = trim($_GET['size'] ?? '');

// 選択されている条件の配列
$selected_filters = [];
if (!empty($filter_category))  $selected_filters['category']  = $filter_category;
if (!empty($filter_season))    $selected_filters['season']    = $filter_season;
if (!empty($filter_style))     $selected_filters['style']     = $filter_style;
if (!empty($filter_acc_color)) $selected_filters['acc_color'] = $filter_acc_color;
if (!empty($filter_color))     $selected_filters['color']     = $filter_color;
if (!empty($filter_size))      $selected_filters['size']      = $filter_size;

// サーバー側でも最大3つに制限（4つ目以降はカット）
$selected_count = count($selected_filters);
if ($selected_count > 3) {
    $selected_filters = array_slice($selected_filters, 0, 3, true);
    $filter_category  = $selected_filters['category'] ?? '';
    $filter_season    = $selected_filters['season'] ?? '';
    $filter_style     = $selected_filters['style'] ?? '';
    $filter_acc_color = $selected_filters['acc_color'] ?? '';
    $filter_color     = $selected_filters['color'] ?? '';
    $filter_size      = $selected_filters['size'] ?? '';
    $selected_count   = 3;
}

$is_filtered = ($selected_count > 0);

// マッピング定義
$categories_map = [
    'tops'    => 'トップス',
    'bottoms' => 'ボトムス',
    'skirt'   => 'スカート',
    'outer'   => 'アウター',
    'shoes'   => 'シューズ',
    'bags'    => 'バッグ',
    'acc'     => 'アクセサリー'
];

$seasons_map = [
    'spring' => '春',
    'summer' => '夏',
    'autumn' => '秋',
    'winter' => '冬',
    'all'    => 'オールシーズン'
];

$styles_map = [
    'casual'   => 'カジュアル',
    'clean'    => 'キレイめ',
    'street'   => 'ストリート',
    'mode'     => 'モード',
    'feminine' => 'フェミニン',
    'sporty'   => 'スポーティ',
    'vintage'  => 'ヴィンテージ'
];

$acc_colors_map = [
    'gold'   => 'ゴールド',
    'silver' => 'シルバー',
    'black'  => 'ブラック',
    'other'  => 'その他'
];

$colors_map = [
    'white'  => 'ホワイト',
    'black'  => 'ブラック',
    'gray'   => 'グレー',
    'navy'   => 'ネイビー',
    'beige'  => 'ベージュ',
    'brown'  => 'ブラウン',
    'blue'   => 'ブルー (青)',
    'green'  => 'グリーン',
    'red'    => 'レッド',
    'yellow' => 'イエロー'
];

$sizes_map = [
    'XS'   => 'XS',
    'S'    => 'S',
    'M'    => 'M',
    'L'    => 'L',
    'XL'   => 'XL',
    'FREE' => 'FREE'
];

// 日本語・英語の表記揺れ吸収用
function matchesFilterValue(?string $target, string $filterKey, string $filterVal, array $map): bool {
    if (empty($filterVal) || empty($target)) return true;
    $target = mb_strtolower(trim($target));
    $filterVal = mb_strtolower(trim($filterVal));
    $jpVal = mb_strtolower($map[$filterVal] ?? '');

    return (str_contains($target, $filterVal) || (!empty($jpVal) && str_contains($target, $jpVal)));
}

// -------------------------------------------------------------
// 2. データベースからデータ取得（またはフォールバックデータ）
// -------------------------------------------------------------

// テーブル存在チェック
function tableExists(?PDO $pdo, string $tableName): bool {
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $tableName]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// カラム存在チェック
function columnExists(?PDO $pdo, string $tableName, string $columnName): bool {
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE :col");
        $stmt->execute([':col' => $columnName]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// A. 持っている服（ユーザーがAIへ読み込ませた服）の取得
$userClothesTable = 'user_clothes';
if (!tableExists($pdo, $userClothesTable)) {
    if (tableExists($pdo, 'user_items')) {
        $userClothesTable = 'user_items';
    } elseif (tableExists($pdo, 'closet')) {
        $userClothesTable = 'closet';
    }
}

$my_clothes = [];
if ($pdo && tableExists($pdo, $userClothesTable)) {
    try {
        $sql = "SELECT * FROM `{$userClothesTable}` WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $my_clothes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $my_clothes = [];
    }
}

// DBに手持ちデータが無い、またはテーブル未作成時の初期デモデータ（実機テスト用）
if (empty($my_clothes)) {
    $my_clothes = [
        [
            'id' => 101,
            'name' => 'オーバーサイズ レザージャケット',
            'category' => 'outer',
            'season' => 'autumn',
            'style' => 'street',
            'color' => 'black',
            'size' => 'L',
            'image_url' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&auto=format&fit=crop&q=60',
            'created_at' => '2026-09-20'
        ],
        [
            'id' => 102,
            'name' => 'ヘビーウェイト ワイドスウェット',
            'category' => 'tops',
            'season' => 'autumn',
            'style' => 'casual',
            'color' => 'gray',
            'size' => 'M',
            'image_url' => 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=500&auto=format&fit=crop&q=60',
            'created_at' => '2026-09-22'
        ],
        [
            'id' => 103,
            'name' => 'ヴィンテージウォッシュ バギーデニム',
            'category' => 'bottoms',
            'season' => 'autumn',
            'style' => 'street',
            'color' => 'blue',
            'size' => 'M',
            'image_url' => 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=500&auto=format&fit=crop&q=60',
            'created_at' => '2026-09-25'
        ],
        [
            'id' => 104,
            'name' => 'チェーンマンテル シルバーネックレス',
            'category' => 'acc',
            'season' => 'all',
            'style' => 'street',
            'color' => 'silver',
            'size' => 'FREE',
            'image_url' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=500&auto=format&fit=crop&q=60',
            'created_at' => '2026-09-28'
        ],
        [
            'id' => 105,
            'name' => 'ハイネック リブニット',
            'category' => 'tops',
            'season' => 'winter',
            'style' => 'clean',
            'color' => 'white',
            'size' => 'S',
            'image_url' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=500&auto=format&fit=crop&q=60',
            'created_at' => '2026-10-01'
        ]
    ];
}

// B. norn items テーブルから商品の取得
$items = [];
if ($pdo && tableExists($pdo, 'items')) {
    try {
        $sql = "SELECT items.*, IF(likes.item_id IS NOT NULL, 1, 0) AS is_liked 
                FROM items 
                LEFT JOIN likes ON items.item_id = likes.item_id AND likes.user_id = :user_id 
                ORDER BY items.item_id DESC LIMIT 40";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $items = [];
    }
}

// DBに商品データが無い場合の初期デモデータ
if (empty($items)) {
    $items = [
        [
            'item_id' => 1,
            'name' => 'ベーシック リラックスドTシャツ',
            'brand_name' => 'norn basic',
            'price' => 3800,
            'category' => 'tops',
            'season' => 'autumn',
            'style' => 'casual',
            'color' => 'white',
            'size' => 'M',
            'image_url' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 1
        ],
        [
            'item_id' => 2,
            'name' => 'センタープレス ワイドタックパンツ',
            'brand_name' => 'URBAN MODE',
            'price' => 7900,
            'category' => 'bottoms',
            'season' => 'autumn',
            'style' => 'clean',
            'color' => 'black',
            'size' => 'M',
            'image_url' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 1
        ],
        [
            'item_id' => 3,
            'name' => 'ボリュームソール レザーローファー',
            'brand_name' => 'SOLIS Footwear',
            'price' => 11800,
            'category' => 'shoes',
            'season' => 'autumn',
            'style' => 'street',
            'color' => 'black',
            'size' => 'L',
            'image_url' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 0
        ],
        [
            'item_id' => 4,
            'name' => 'オーバーサイズ ショートモッズコート',
            'brand_name' => 'norn outerwear',
            'price' => 16500,
            'category' => 'outer',
            'season' => 'autumn',
            'style' => 'street',
            'color' => 'green',
            'size' => 'L',
            'image_url' => 'https://images.unsplash.com/photo-1544441893-675973e31985?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 0
        ],
        [
            'item_id' => 5,
            'name' => 'レザーライク スクエアショルダーバッグ',
            'brand_name' => 'AURA Studio',
            'price' => 5500,
            'category' => 'bags',
            'season' => 'all',
            'style' => 'clean',
            'color' => 'black',
            'size' => 'FREE',
            'image_url' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 1
        ],
        [
            'item_id' => 6,
            'name' => 'ドロップショルダー クルーネックニット',
            'brand_name' => 'norn knit',
            'price' => 6900,
            'category' => 'tops',
            'season' => 'autumn',
            'style' => 'clean',
            'color' => 'blue',
            'size' => 'M',
            'image_url' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=500&auto=format&fit=crop&q=60',
            'affiliate_url' => '#',
            'is_liked' => 0
        ]
    ];
}

// -------------------------------------------------------------
// 3. 各条件による共通フィルター処理関数
// -------------------------------------------------------------
function filterItemCollection(array $collection, array $filters, array $maps): array {
    if (empty($filters)) return $collection;

    return array_values(array_filter($collection, function ($item) use ($filters, $maps) {
        // 1. カテゴリ
        if (!empty($filters['category'])) {
            $cat = $item['category'] ?? '';
            if (!matchesFilterValue($cat, 'category', $filters['category'], $maps['categories'])) {
                return false;
            }
        }
        // 2. 季節
        if (!empty($filters['season'])) {
            $season = $item['season'] ?? '';
            // all（オールシーズン）の場合は常時通過
            if ($season !== 'all' && !empty($season)) {
                if (!matchesFilterValue($season, 'season', $filters['season'], $maps['seasons'])) {
                    return false;
                }
            }
        }
        // 3. スタイル
        if (!empty($filters['style'])) {
            $style = $item['style'] ?? '';
            if (!empty($style) && !matchesFilterValue($style, 'style', $filters['style'], $maps['styles'])) {
                return false;
            }
        }
        // 4. アクセサリーの色
        if (!empty($filters['acc_color'])) {
            $cat = $item['category'] ?? '';
            $color = $item['color'] ?? '';
            if ($cat === 'acc' || str_contains($cat, 'アクセサリー')) {
                if (!matchesFilterValue($color, 'color', $filters['acc_color'], $maps['acc_colors'])) {
                    return false;
                }
            }
        }
        // 5. 服の色
        if (!empty($filters['color'])) {
            $color = $item['color'] ?? '';
            if (!matchesFilterValue($color, 'color', $filters['color'], $maps['colors'])) {
                return false;
            }
        }
        // 6. サイズ
        if (!empty($filters['size'])) {
            $size = $item['size'] ?? '';
            if (!empty($size) && $size !== 'FREE' && strcasecmp($size, $filters['size']) !== 0) {
                return false;
            }
        }
        return true;
    }));
}

$allMaps = [
    'categories' => $categories_map,
    'seasons'    => $seasons_map,
    'styles'     => $styles_map,
    'acc_colors' => $acc_colors_map,
    'colors'     => $colors_map,
    'sizes'      => $sizes_map,
];

// 各タブ用のデータ抽出

// ② 持っている服（フィルター適用後）
$filtered_my_clothes = filterItemCollection($my_clothes, $selected_filters, $allMaps);

// ③ お気に入り（is_liked == 1 の商品にフィルター適用）
$favorite_items = array_filter($items, fn($i) => !empty($i['is_liked']));
$filtered_favorites = filterItemCollection($favorite_items, $selected_filters, $allMaps);

// ④ おすすめ（手持ちの服との相性をAIが判断した商品単体）
$filtered_recommend_items = filterItemCollection($items, $selected_filters, $allMaps);
// 相性対象とする手持ち服（先頭のアイテム等）
$primary_owned_item = !empty($filtered_my_clothes) ? $filtered_my_clothes[0] : ($my_clothes[0] ?? null);

// ① AIが提案するコーデ（items × 手持ち服の組み合わせ）
$ai_coordinated_looks = [];
$coord_pool_owned = !empty($filtered_my_clothes) ? $filtered_my_clothes : $my_clothes;
$coord_pool_items = !empty($filtered_recommend_items) ? $filtered_recommend_items : $items;

if (!empty($coord_pool_owned) || !empty($coord_pool_items)) {
    // 例：ルック1（ストリート・カジュアル）
    $look1_owned = array_values(array_filter($coord_pool_owned, fn($x) => ($x['category'] ?? '') === 'outer' || ($x['category'] ?? '') === 'tops'))[0] ?? ($coord_pool_owned[0] ?? null);
    $look1_norn1 = array_values(array_filter($coord_pool_items, fn($x) => ($x['category'] ?? '') === 'bottoms'))[0] ?? ($coord_pool_items[1] ?? null);
    $look1_norn2 = array_values(array_filter($coord_pool_items, fn($x) => ($x['category'] ?? '') === 'shoes'))[0] ?? ($coord_pool_items[2] ?? null);
    $look1_acc   = array_values(array_filter($coord_pool_owned, fn($x) => ($x['category'] ?? '') === 'acc'))[0] ?? null;

    $look1_items = array_values(array_filter([$look1_owned, $look1_norn1, $look1_norn2, $look1_acc]));
    if (!empty($look1_items)) {
        $ai_coordinated_looks[] = [
            'id' => 1,
            'title' => '秋のアーバンスタイル コーデ',
            'tags' => ['#秋コーデ', '#ストリートMIX', '#ワイドシルエット'],
            'comment' => 'お手持ちの「' . htmlspecialchars($look1_owned['name'] ?? 'アイテム') . '」をベースに、nornのトレンドボトムスとローファーを組み合わせました。メリハリのある大人ストリートな印象に仕上がります。',
            'items' => $look1_items
        ];
    }

    // 例：ルック2（きれいめリラックス）
    $look2_owned = array_values(array_filter($coord_pool_owned, fn($x) => ($x['category'] ?? '') === 'tops'))[0] ?? ($coord_pool_owned[1] ?? null);
    $look2_norn1 = array_values(array_filter($coord_pool_items, fn($x) => ($x['category'] ?? '') === 'outer' || ($x['category'] ?? '') === 'bottoms'))[0] ?? ($coord_pool_items[0] ?? null);
    $look2_norn2 = array_values(array_filter($coord_pool_items, fn($x) => ($x['category'] ?? '') === 'bags'))[0] ?? null;

    $look2_items = array_values(array_filter([$look2_owned, $look2_norn1, $look2_norn2]));
    if (!empty($look2_items)) {
        $ai_coordinated_looks[] = [
            'id' => 2,
            'title' => 'モノトーン・クリーンカジュアル',
            'tags' => ['#キレイめ', '#大人カジュアル', '#シンプル'],
            'comment' => 'ご自身の「' . htmlspecialchars($look2_owned['name'] ?? 'トップス') . '」にシックなブラックアイテムをプラス。カラーバランスを統一して洗練された清潔感を演出します。',
            'items' => $look2_items
        ];
    }
}

// URL生成ヘルパー関数
function buildAiUrl(string $tab, array $currentFilters = [], ?string $removeKey = null): string {
    $params = ['tab' => $tab];
    foreach ($currentFilters as $k => $v) {
        if ($k !== $removeKey && !empty($v)) {
            $params[$k] = $v;
        }
    }
    return 'mypage.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AIコーディネート＆マイページ - norn</title>
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- 共通スタイル -->
  <link rel="stylesheet" href="./assets/css/sidebar-nav.css">
  <link rel="stylesheet" href="./assets/css/header.css">
  <link rel="stylesheet" href="./assets/css/search.css">
  
  <!-- AIコーディネート専用スタイル -->
  <link rel="stylesheet" href="./assets/css/ai-coordination.css">
</head>
<body>
  <div class="app-layout">

    <!-- 左側サイドバーナビ -->
    <?php if (file_exists('./includes/sidebar-nav.php')) require_once './includes/sidebar-nav.php'; ?>

    <!-- 右側メインエリア -->
    <div class="main-wrapper">

      <!-- ヘッダー -->
      <?php if (file_exists('./includes/header.php')) require_once './includes/header.php'; ?>

      <!-- ========================================================================= -->
      <!-- ここから：mypage.php AIコーディング（AIコーデ提案機能）セクション -->
      <!-- ========================================================================= -->
      <div class="ai-container">

        <!-- ページ見出し -->
        <div class="ai-page-header">
          <div class="ai-page-title-row">
            <h1 class="ai-page-title">
              <i class="fa-solid fa-wand-magic-sparkles"></i> AI ファッションアシスタント
            </h1>
          </div>
          <p class="ai-page-subtitle">
            あなたの持っている服とnornのアイテムをAIが解析し、最適なコーディネートやおすすめを提案します。
          </p>
        </div>

        <!-- 4つのタブナビゲーション -->
        <nav class="ai-tab-nav" aria-label="AIコーデモード切り替え">
          <!-- ① AIが提案するコーデ -->
          <a href="<?= buildAiUrl('ai_coord', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'ai_coord') ? 'active' : '' ?>">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span>① AI提案コーデ</span>
            <span class="tab-badge"><?= count($ai_coordinated_looks) ?>組</span>
          </a>

          <!-- ② 持っている服 -->
          <a href="<?= buildAiUrl('my_clothes', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'my_clothes') ? 'active' : '' ?>">
            <i class="fa-solid fa-shirt"></i>
            <span>② 持っている服</span>
            <span class="tab-badge"><?= count($filtered_my_clothes) ?>着</span>
          </a>

          <!-- ③ お気に入り -->
          <a href="<?= buildAiUrl('favorites', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'favorites') ? 'active' : '' ?>">
            <i class="fa-solid fa-heart"></i>
            <span>③ お気に入り</span>
            <span class="tab-badge"><?= count($filtered_favorites) ?>件</span>
          </a>

          <!-- ④ おすすめ -->
          <a href="<?= buildAiUrl('recommend', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'recommend') ? 'active' : '' ?>">
            <i class="fa-solid fa-thumbs-up"></i>
            <span>④ おすすめ</span>
            <span class="tab-badge"><?= count($filtered_recommend_items) ?>件</span>
          </a>
        </nav>

        <!-- ===================================================================== -->
        <!-- 条件選択フォーム（最大3つまで選択可能・4つ目以降は選択不可） -->
        <!-- ===================================================================== -->
        <section class="condition-panel">
          <form method="GET" action="mypage.php" id="ai-condition-form">
            <!-- 現在のタブを保持 -->
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">

            <div class="condition-panel-header">
              <h2 class="condition-panel-title">
                <i class="fa-solid fa-sliders"></i> 条件を選択（3つまで）
              </h2>
              <span id="condition-count-badge" class="condition-counter-badge <?= ($selected_count >= 3) ? 'limit-reached' : '' ?>">
                <?= $selected_count ?> / 3 選択中
              </span>
            </div>

            <!-- 2列のセレクトボックス群 -->
            <div class="condition-grid">

              <!-- 1. カテゴリ -->
              <div class="condition-field">
                <label for="select-category" class="condition-label">
                  <i class="fa-solid fa-tags"></i> カテゴリ
                </label>
                <div class="condition-select-wrapper">
                  <select name="category" id="select-category" class="condition-select <?= !empty($filter_category) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($categories_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_category === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

              <!-- 2. 季節 -->
              <div class="condition-field">
                <label for="select-season" class="condition-label">
                  <i class="fa-solid fa-cloud-sun"></i> 季節
                </label>
                <div class="condition-select-wrapper">
                  <select name="season" id="select-season" class="condition-select <?= !empty($filter_season) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($seasons_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_season === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

              <!-- 3. スタイル -->
              <div class="condition-field">
                <label for="select-style" class="condition-label">
                  <i class="fa-solid fa-person"></i> スタイル
                </label>
                <div class="condition-select-wrapper">
                  <select name="style" id="select-style" class="condition-select <?= !empty($filter_style) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($styles_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_style === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

              <!-- 4. アクセサリーの色 -->
              <div class="condition-field">
                <label for="select-acc-color" class="condition-label">
                  <i class="fa-solid fa-ring"></i> アクセサリーの色
                </label>
                <div class="condition-select-wrapper">
                  <select name="acc_color" id="select-acc-color" class="condition-select <?= !empty($filter_acc_color) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($acc_colors_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_acc_color === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

              <!-- 5. 服の色 -->
              <div class="condition-field">
                <label for="select-color" class="condition-label">
                  <i class="fa-solid fa-palette"></i> 服の色
                </label>
                <div class="condition-select-wrapper">
                  <select name="color" id="select-color" class="condition-select <?= !empty($filter_color) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($colors_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_color === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

              <!-- 6. サイズ -->
              <div class="condition-field">
                <label for="select-size" class="condition-label">
                  <i class="fa-solid fa-ruler-combined"></i> サイズ
                </label>
                <div class="condition-select-wrapper">
                  <select name="size" id="select-size" class="condition-select <?= !empty($filter_size) ? 'has-value' : '' ?>">
                    <option value="">指定なし</option>
                    <?php foreach ($sizes_map as $key => $name): ?>
                      <option value="<?= $key ?>" <?= ($filter_size === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
              </div>

            </div>

            <!-- アラート & ボタン -->
            <div class="condition-actions">
              <div class="condition-limit-msg <?= ($selected_count >= 3) ? 'alert' : '' ?>" id="condition-limit-alert" style="<?= ($selected_count >= 3) ? '' : 'display:none;' ?>">
                <i class="fa-solid fa-circle-exclamation"></i>
                条件は最大3つまでです。別の条件を選ぶには、選択済みの項目を1つ「指定なし」に戻してください。
              </div>

              <div class="condition-button-group">
                <?php if ($is_filtered): ?>
                  <a href="<?= buildAiUrl($active_tab) ?>" class="btn-ai-reset" id="btn-condition-reset">
                    <i class="fa-solid fa-rotate-left"></i> 条件をリセット
                  </a>
                <?php endif; ?>
                <button type="submit" class="btn-ai-apply">
                  <i class="fa-solid fa-magnifying-glass"></i> この条件で絞り込む
                </button>
              </div>
            </div>

          </form>
        </section>

        <!-- 適用中条件のチップ表示 -->
        <?php if ($is_filtered): ?>
          <div class="active-conditions-row">
            <span class="active-cond-title"><i class="fa-solid fa-filter"></i> 適用中の条件:</span>
            <?php if (!empty($filter_category)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'category') ?>" class="cond-chip" title="解除">
                カテゴリ: <?= htmlspecialchars($categories_map[$filter_category] ?? $filter_category) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <?php if (!empty($filter_season)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'season') ?>" class="cond-chip" title="解除">
                季節: <?= htmlspecialchars($seasons_map[$filter_season] ?? $filter_season) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <?php if (!empty($filter_style)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'style') ?>" class="cond-chip" title="解除">
                スタイル: <?= htmlspecialchars($styles_map[$filter_style] ?? $filter_style) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <?php if (!empty($filter_acc_color)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'acc_color') ?>" class="cond-chip" title="解除">
                アクセ色: <?= htmlspecialchars($acc_colors_map[$filter_acc_color] ?? $filter_acc_color) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <?php if (!empty($filter_color)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'color') ?>" class="cond-chip" title="解除">
                服の色: <?= htmlspecialchars($colors_map[$filter_color] ?? $filter_color) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <?php if (!empty($filter_size)): ?>
              <a href="<?= buildAiUrl($active_tab, $selected_filters, 'size') ?>" class="cond-chip" title="解除">
                サイズ: <?= htmlspecialchars($filter_size) ?>
                <i class="fa-solid fa-xmark remove-chip"></i>
              </a>
            <?php endif; ?>

            <a href="<?= buildAiUrl($active_tab) ?>" style="font-size: 12px; color: #4f46e5; text-decoration: underline; margin-left: 6px;">すべて解除</a>
          </div>
        <?php endif; ?>


        <!-- ===================================================================== -->
        <!-- タブ別コンテンツ表示エリア -->
        <!-- ===================================================================== -->

        <!-- ------------------------------------------------------------------- -->
        <!-- ① AIが提案するコーデ (ai_coord) -->
        <!-- ------------------------------------------------------------------- -->
        <?php if ($active_tab === 'ai_coord'): ?>
          <section class="tab-content-section">
            <?php if (empty($ai_coordinated_looks)): ?>
              <div class="ai-empty-state">
                <i class="fa-solid fa-wand-magic-sparkles ai-empty-icon"></i>
                <h3 class="ai-empty-title">条件に一致するコーディネートを作成できませんでした</h3>
                <p class="ai-empty-desc">条件を緩和するか、持っている服を追加すると新しいコーデが提案されます。</p>
                <a href="<?= buildAiUrl('ai_coord') ?>" class="btn-ai-apply">条件をリセットする</a>
              </div>
            <?php else: ?>
              <div class="ai-coord-grid">
                <?php foreach ($ai_coordinated_looks as $look): ?>
                  <div class="ai-coord-card">
                    <div class="ai-coord-card-header">
                      <div>
                        <h3 class="ai-coord-title">
                          <i class="fa-solid fa-sparkles" style="color: #6366f1;"></i>
                          <?= htmlspecialchars($look['title']) ?>
                        </h3>
                        <div class="ai-coord-tags">
                          <?php foreach ($look['tags'] as $tag): ?>
                            <span class="ai-coord-tag"><?= htmlspecialchars($tag) ?></span>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>

                    <!-- AI解説コメント -->
                    <div class="ai-coord-comment">
                      <i class="fa-solid fa-robot"></i>
                      <span><?= htmlspecialchars($look['comment']) ?></span>
                    </div>

                    <!-- コーデを構成するアイテムたち（手持ち服 ＋ norn商品） -->
                    <div class="ai-coord-items-grid">
                      <?php foreach ($look['items'] as $item): ?>
                        <?php 
                          $is_owned = isset($item['id']) && !isset($item['item_id']); 
                        ?>
                        <div class="ai-look-item">
                          <!-- アイテムの出自バッジ -->
                          <?php if ($is_owned): ?>
                            <span class="ai-item-source-badge badge-my-closet">
                              <i class="fa-solid fa-shirt"></i> 持っている服
                            </span>
                          <?php else: ?>
                            <span class="ai-item-source-badge badge-norn-item">
                              <i class="fa-solid fa-bag-shopping"></i> nornアイテム
                            </span>
                          <?php endif; ?>

                          <div class="ai-look-item-thumb">
                            <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>" loading="lazy">
                          </div>

                          <span class="ai-look-item-cat"><?= htmlspecialchars($categories_map[$item['category'] ?? ''] ?? ($item['category'] ?? 'アイテム')) ?></span>
                          <h4 class="ai-look-item-name"><?= htmlspecialchars($item['name'] ?? '') ?></h4>

                          <?php if (!$is_owned && isset($item['price'])): ?>
                            <div class="ai-look-item-price">¥<?= number_format($item['price']) ?></div>
                            <a href="<?= htmlspecialchars($item['affiliate_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn-look-link buy">
                              ショップで見る <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                          <?php else: ?>
                            <div class="ai-look-item-price" style="color: #10b981;">クローゼット登録品</div>
                            <span class="btn-look-link my"><i class="fa-solid fa-check"></i> コーデに適用中</span>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

        <!-- ------------------------------------------------------------------- -->
        <!-- ② 持っている服 (my_clothes) -->
        <!-- ------------------------------------------------------------------- -->
        <?php elseif ($active_tab === 'my_clothes'): ?>
          <section class="tab-content-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <p style="color: #4b5563; font-size: 14px; margin: 0;">
                あなたがAIに登録した服のうち、選択条件に一致するアイテムを表示しています。
              </p>
              <button type="button" class="btn-ai-apply" style="font-size: 13px; padding: 7px 14px;">
                <i class="fa-solid fa-camera"></i> 新しい服をAIに読み込ませる
              </button>
            </div>

            <?php if (empty($filtered_my_clothes)): ?>
              <div class="ai-empty-state">
                <i class="fa-solid fa-shirt ai-empty-icon"></i>
                <h3 class="ai-empty-title">条件に一致する手持ちの服が見つかりませんでした</h3>
                <p class="ai-empty-desc">条件を変更するか、新しくクローゼットに服を登録してください。</p>
                <a href="<?= buildAiUrl('my_clothes') ?>" class="btn-ai-apply">条件をリセットする</a>
              </div>
            <?php else: ?>
              <div class="ai-cards-grid">
                <?php foreach ($filtered_my_clothes as $item): ?>
                  <div class="ai-standard-card">
                    <div class="ai-card-image-wrap">
                      <span class="ai-item-source-badge badge-my-closet" style="top: 10px; left: 10px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI解析済み
                      </span>
                      <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>" loading="lazy">
                    </div>

                    <div class="ai-card-content">
                      <span class="ai-card-brand"><?= htmlspecialchars($categories_map[$item['category'] ?? ''] ?? ($item['category'] ?? 'クローゼット')) ?></span>
                      <h3 class="ai-card-title"><?= htmlspecialchars($item['name'] ?? '') ?></h3>

                      <div class="ai-card-meta">
                        <?php if (!empty($item['season'])): ?>
                          <span class="meta-pill"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($seasons_map[$item['season']] ?? $item['season']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['style'])): ?>
                          <span class="meta-pill"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($styles_map[$item['style']] ?? $item['style']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['color'])): ?>
                          <span class="meta-pill"><i class="fa-solid fa-droplet"></i> <?= htmlspecialchars($colors_map[$item['color']] ?? $item['color']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['size'])): ?>
                          <span class="meta-pill"><?= htmlspecialchars($item['size']) ?></span>
                        <?php endif; ?>
                      </div>

                      <div class="ai-card-price" style="color: #6b7280; font-size: 12px;">
                        登録日: <?= htmlspecialchars(substr($item['created_at'] ?? '2026-10', 0, 10)) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

        <!-- ------------------------------------------------------------------- -->
        <!-- ③ お気に入り (favorites) -->
        <!-- ------------------------------------------------------------------- -->
        <?php elseif ($active_tab === 'favorites'): ?>
          <section class="tab-content-section">
            <p style="color: #4b5563; font-size: 14px; margin: 0 0 16px;">
              あなたがお気に入りに登録したアイテムから、条件に一致する商品を表示しています。
            </p>

            <?php if (empty($filtered_favorites)): ?>
              <div class="ai-empty-state">
                <i class="fa-regular fa-heart ai-empty-icon"></i>
                <h3 class="ai-empty-title">条件に一致するお気に入り商品がありません</h3>
                <p class="ai-empty-desc">商品一覧でお気に入りを追加するか、絞り込み条件をリセットしてください。</p>
                <a href="<?= buildAiUrl('favorites') ?>" class="btn-ai-apply">条件をリセットする</a>
              </div>
            <?php else: ?>
              <div class="ai-cards-grid">
                <?php foreach ($filtered_favorites as $item): ?>
                  <div class="ai-standard-card">
                    <button type="button" class="card-fav-btn active" title="お気に入り登録中">
                      <i class="fa-solid fa-heart"></i>
                    </button>

                    <a href="<?= htmlspecialchars($item['affiliate_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="ai-card-image-wrap">
                      <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>" loading="lazy">
                    </a>

                    <div class="ai-card-content">
                      <span class="ai-card-brand"><?= htmlspecialchars($item['brand_name'] ?? 'norn') ?></span>
                      <h3 class="ai-card-title"><?= htmlspecialchars($item['name'] ?? '') ?></h3>

                      <div class="ai-card-meta">
                        <?php if (!empty($item['color'])): ?>
                          <span class="meta-pill">カラー: <?= htmlspecialchars($item['color']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['size'])): ?>
                          <span class="meta-pill">サイズ: <?= htmlspecialchars($item['size']) ?></span>
                        <?php endif; ?>
                      </div>

                      <div class="ai-card-price">¥<?= number_format($item['price'] ?? 0) ?></div>

                      <a href="<?= htmlspecialchars($item['affiliate_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn-look-link buy">
                        ショップで見る <i class="fa-solid fa-arrow-up-right-from-square"></i>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

        <!-- ------------------------------------------------------------------- -->
        <!-- ④ おすすめ (recommend) -->
        <!-- ------------------------------------------------------------------- -->
        <?php elseif ($active_tab === 'recommend'): ?>
          <section class="tab-content-section">
            <div style="background: #eef2ff; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: #3730a3; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-wand-magic-sparkles" style="font-size: 18px;"></i>
              <div>
                AIがあなたの持っている服（クローゼット）との相性を判定し、相乗効果の高いアイテムを単体でおすすめしています。
              </div>
            </div>

            <?php if (empty($filtered_recommend_items)): ?>
              <div class="ai-empty-state">
                <i class="fa-solid fa-thumbs-up ai-empty-icon"></i>
                <h3 class="ai-empty-title">条件に一致するおすすめ商品が見つかりませんでした</h3>
                <p class="ai-empty-desc">条件を少し広げてお試しください。</p>
                <a href="<?= buildAiUrl('recommend') ?>" class="btn-ai-apply">条件をリセットする</a>
              </div>
            <?php else: ?>
              <div class="ai-cards-grid">
                <?php foreach ($filtered_recommend_items as $index => $item): ?>
                  <?php
                    // 組み合わせ元となる手持ち服の決定
                    $matchTarget = $my_clothes[$index % count($my_clothes)] ?? $primary_owned_item;
                  ?>
                  <div class="ai-standard-card">
                    <button type="button" class="card-fav-btn <?= !empty($item['is_liked']) ? 'active' : '' ?>">
                      <i class="<?= !empty($item['is_liked']) ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                    </button>

                    <a href="<?= htmlspecialchars($item['affiliate_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="ai-card-image-wrap">
                      <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>" loading="lazy">
                    </a>

                    <div class="ai-card-content">
                      <!-- AI相性判定バッジ -->
                      <?php if ($matchTarget): ?>
                        <div class="ai-match-badge">
                          <i class="fa-solid fa-sparkles"></i>
                          <div>
                            <strong>お手持ちの「<?= htmlspecialchars($matchTarget['name']) ?>」</strong>と好相性！
                          </div>
                        </div>
                      <?php endif; ?>

                      <span class="ai-card-brand"><?= htmlspecialchars($item['brand_name'] ?? 'norn') ?></span>
                      <h3 class="ai-card-title"><?= htmlspecialchars($item['name'] ?? '') ?></h3>

                      <div class="ai-card-meta">
                        <?php if (!empty($item['color'])): ?>
                          <span class="meta-pill">カラー: <?= htmlspecialchars($item['color']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['style'])): ?>
                          <span class="meta-pill"><?= htmlspecialchars($styles_map[$item['style']] ?? $item['style']) ?></span>
                        <?php endif; ?>
                      </div>

                      <div class="ai-card-price">¥<?= number_format($item['price'] ?? 0) ?></div>

                      <a href="<?= htmlspecialchars($item['affiliate_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn-look-link buy">
                        ショップで見る <i class="fa-solid fa-arrow-up-right-from-square"></i>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

      </div>
      <!-- ========================================================================= -->
      <!-- ここまで：mypage.php AIコーディング（AIコーデ提案機能）セクション -->
      <!-- ========================================================================= -->

    </div>
  </div>

  <!-- AI機能 専用スクリプト -->
  <script src="./assets/js/ai-coordination.js"></script>
</body>
</html>
