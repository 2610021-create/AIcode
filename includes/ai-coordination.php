<?php
/**
 * includes/ai-coordination.php
 * 
 * mypage.php 等の任意のページに直接 require / include して埋め込める
 * 「AIコーディネート機能」専用の独立コンポーネントです。
 */

// データベース接続がまだない場合の補完
if (!isset($pdo) && function_exists('getPDO')) {
    $pdo = getPDO();
}

if (!isset($userId)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $userId = $_SESSION['user_id'] ?? 4;
}

// パラメーター取得
$active_tab = $_GET['tab'] ?? 'ai_coord'; // ai_coord | my_clothes | favorites | recommend

$filter_category  = trim($_GET['category'] ?? '');
$filter_season    = trim($_GET['season'] ?? '');
$filter_style     = trim($_GET['style'] ?? '');
$filter_acc_color = trim($_GET['acc_color'] ?? '');
$filter_color     = trim($_GET['color'] ?? '');
$filter_size      = trim($_GET['size'] ?? '');

$selected_filters = [];
if (!empty($filter_category))  $selected_filters['category']  = $filter_category;
if (!empty($filter_season))    $selected_filters['season']    = $filter_season;
if (!empty($filter_style))     $selected_filters['style']     = $filter_style;
if (!empty($filter_acc_color)) $selected_filters['acc_color'] = $filter_acc_color;
if (!empty($filter_color))     $selected_filters['color']     = $filter_color;
if (!empty($filter_size))      $selected_filters['size']      = $filter_size;

// 最大3つまでの制限
if (count($selected_filters) > 3) {
    $selected_filters = array_slice($selected_filters, 0, 3, true);
    $filter_category  = $selected_filters['category'] ?? '';
    $filter_season    = $selected_filters['season'] ?? '';
    $filter_style     = $selected_filters['style'] ?? '';
    $filter_acc_color = $selected_filters['acc_color'] ?? '';
    $filter_color     = $selected_filters['color'] ?? '';
    $filter_size      = $selected_filters['size'] ?? '';
}
$selected_count = count($selected_filters);
$is_filtered = ($selected_count > 0);

// マッピング
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
$sizes_map = ['XS'=>'XS', 'S'=>'S', 'M'=>'M', 'L'=>'L', 'XL'=>'XL', 'FREE'=>'FREE'];

// 判定関数
if (!function_exists('matchesFilterValueComponent')) {
    function matchesFilterValueComponent(?string $target, string $filterVal, array $map): bool {
        if (empty($filterVal) || empty($target)) return true;
        $target = mb_strtolower(trim($target));
        $filterVal = mb_strtolower(trim($filterVal));
        $jpVal = mb_strtolower($map[$filterVal] ?? '');
        return (str_contains($target, $filterVal) || (!empty($jpVal) && str_contains($target, $jpVal)));
    }
}

// テーブル・カラム存在チェック
if (!function_exists('checkComponentTable')) {
    function checkComponentTable(?PDO $pdo, string $table): bool {
        if (!$pdo) return false;
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) { return false; }
    }
}

// データの取得 (持っている服)
$userClothesTable = 'user_clothes';
if (!checkComponentTable($pdo, $userClothesTable)) {
    if (checkComponentTable($pdo, 'user_items')) $userClothesTable = 'user_items';
    elseif (checkComponentTable($pdo, 'closet')) $userClothesTable = 'closet';
}

$my_clothes = [];
if ($pdo && checkComponentTable($pdo, $userClothesTable)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$userClothesTable}` WHERE user_id = :u");
        $stmt->execute([':u' => $userId]);
        $my_clothes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// デモ用手持ち服
if (empty($my_clothes)) {
    $my_clothes = [
        ['id'=>101, 'name'=>'オーバーサイズ レザージャケット', 'category'=>'outer', 'season'=>'autumn', 'style'=>'street', 'color'=>'black', 'size'=>'L', 'image_url'=>'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&auto=format&fit=crop&q=60', 'created_at'=>'2026-09-20'],
        ['id'=>102, 'name'=>'ヘビーウェイト ワイドスウェット', 'category'=>'tops', 'season'=>'autumn', 'style'=>'casual', 'color'=>'gray', 'size'=>'M', 'image_url'=>'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=500&auto=format&fit=crop&q=60', 'created_at'=>'2026-09-22'],
        ['id'=>103, 'name'=>'ヴィンテージウォッシュ バギーデニム', 'category'=>'bottoms', 'season'=>'autumn', 'style'=>'street', 'color'=>'blue', 'size'=>'M', 'image_url'=>'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=500&auto=format&fit=crop&q=60', 'created_at'=>'2026-09-25'],
        ['id'=>104, 'name'=>'チェーンマンテル シルバーネックレス', 'category'=>'acc', 'season'=>'all', 'style'=>'street', 'color'=>'silver', 'size'=>'FREE', 'image_url'=>'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=500&auto=format&fit=crop&q=60', 'created_at'=>'2026-09-28'],
        ['id'=>105, 'name'=>'ハイネック リブニット', 'category'=>'tops', 'season'=>'winter', 'style'=>'clean', 'color'=>'white', 'size'=>'S', 'image_url'=>'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=500&auto=format&fit=crop&q=60', 'created_at'=>'2026-10-01']
    ];
}

// items テーブル商品
$items = [];
if ($pdo && checkComponentTable($pdo, 'items')) {
    try {
        $stmt = $pdo->prepare("SELECT items.*, IF(likes.item_id IS NOT NULL, 1, 0) AS is_liked FROM items LEFT JOIN likes ON items.item_id = likes.item_id AND likes.user_id = :u ORDER BY items.item_id DESC LIMIT 30");
        $stmt->execute([':u' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if (empty($items)) {
    $items = [
        ['item_id'=>1, 'name'=>'ベーシック リラックスドTシャツ', 'brand_name'=>'norn basic', 'price'=>3800, 'category'=>'tops', 'season'=>'autumn', 'style'=>'casual', 'color'=>'white', 'size'=>'M', 'image_url'=>'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>1],
        ['item_id'=>2, 'name'=>'センタープレス ワイドタックパンツ', 'brand_name'=>'URBAN MODE', 'price'=>7900, 'category'=>'bottoms', 'season'=>'autumn', 'style'=>'clean', 'color'=>'black', 'size'=>'M', 'image_url'=>'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>1],
        ['item_id'=>3, 'name'=>'ボリュームソール レザーローファー', 'brand_name'=>'SOLIS Footwear', 'price'=>11800, 'category'=>'shoes', 'season'=>'autumn', 'style'=>'street', 'color'=>'black', 'size'=>'L', 'image_url'=>'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>0],
        ['item_id'=>4, 'name'=>'オーバーサイズ ショートモッズコート', 'brand_name'=>'norn outerwear', 'price'=>16500, 'category'=>'outer', 'season'=>'autumn', 'style'=>'street', 'color'=>'green', 'size'=>'L', 'image_url'=>'https://images.unsplash.com/photo-1544441893-675973e31985?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>0],
        ['item_id'=>5, 'name'=>'レザーライク スクエアショルダーバッグ', 'brand_name'=>'AURA Studio', 'price'=>5500, 'category'=>'bags', 'season'=>'all', 'style'=>'clean', 'color'=>'black', 'size'=>'FREE', 'image_url'=>'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>1],
        ['item_id'=>6, 'name'=>'ドロップショルダー クルーネックニット', 'brand_name'=>'norn knit', 'price'=>6900, 'category'=>'tops', 'season'=>'autumn', 'style'=>'clean', 'color'=>'blue', 'size'=>'M', 'image_url'=>'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=500&auto=format&fit=crop&q=60', 'affiliate_url'=>'#', 'is_liked'=>0]
    ];
}

// フィルター関数
if (!function_exists('filterComponentCollection')) {
    function filterComponentCollection(array $list, array $filters, array $maps): array {
        if (empty($filters)) return $list;
        return array_values(array_filter($list, function($it) use ($filters, $maps) {
            if (!empty($filters['category']) && !matchesFilterValueComponent($it['category'] ?? '', $filters['category'], $maps['cat'])) return false;
            if (!empty($filters['season'])) {
                $s = $it['season'] ?? '';
                if ($s !== 'all' && !empty($s) && !matchesFilterValueComponent($s, $filters['season'], $maps['season'])) return false;
            }
            if (!empty($filters['style']) && !empty($it['style']) && !matchesFilterValueComponent($it['style'], $filters['style'], $maps['style'])) return false;
            if (!empty($filters['acc_color'])) {
                $c = $it['category'] ?? '';
                if (($c === 'acc' || str_contains($c, 'アクセ')) && !matchesFilterValueComponent($it['color'] ?? '', $filters['acc_color'], $maps['acc_col'])) return false;
            }
            if (!empty($filters['color']) && !matchesFilterValueComponent($it['color'] ?? '', $filters['color'], $maps['col'])) return false;
            if (!empty($filters['size']) && !empty($it['size']) && $it['size'] !== 'FREE' && strcasecmp($it['size'], $filters['size']) !== 0) return false;
            return true;
        }));
    }
}

$cMaps = [
    'cat' => $categories_map,
    'season' => $seasons_map,
    'style' => $styles_map,
    'acc_col' => $acc_colors_map,
    'col' => $colors_map,
];

$filtered_my_clothes = filterComponentCollection($my_clothes, $selected_filters, $cMaps);
$favorite_items = array_filter($items, fn($i) => !empty($i['is_liked']));
$filtered_favorites = filterComponentCollection($favorite_items, $selected_filters, $cMaps);
$filtered_recommend_items = filterComponentCollection($items, $selected_filters, $cMaps);
$primary_owned_item = !empty($filtered_my_clothes) ? $filtered_my_clothes[0] : ($my_clothes[0] ?? null);

// ① AIコーデ生成
$ai_coordinated_looks = [];
$c_owned = !empty($filtered_my_clothes) ? $filtered_my_clothes : $my_clothes;
$c_items = !empty($filtered_recommend_items) ? $filtered_recommend_items : $items;
if (!empty($c_owned) || !empty($c_items)) {
    $l1_owned = array_values(array_filter($c_owned, fn($x) => in_array($x['category'] ?? '', ['outer','tops'])))[0] ?? ($c_owned[0] ?? null);
    $l1_it1   = array_values(array_filter($c_items, fn($x) => ($x['category'] ?? '') === 'bottoms'))[0] ?? ($c_items[1] ?? null);
    $l1_it2   = array_values(array_filter($c_items, fn($x) => ($x['category'] ?? '') === 'shoes'))[0] ?? ($c_items[2] ?? null);
    $l1_acc   = array_values(array_filter($c_owned, fn($x) => ($x['category'] ?? '') === 'acc'))[0] ?? null;

    $l1_set = array_values(array_filter([$l1_owned, $l1_it1, $l1_it2, $l1_acc]));
    if (!empty($l1_set)) {
        $ai_coordinated_looks[] = [
            'title' => '秋のアーバンスタイル コーデ',
            'tags' => ['#秋コーデ', '#ストリートMIX', '#相性抜群'],
            'comment' => 'お手持ちの「' . htmlspecialchars($l1_owned['name'] ?? 'アイテム') . '」を主役に、nornのトレンドボトムスと靴をミックス。統一感のあるシルエットを構築しました。',
            'items' => $l1_set
        ];
    }
}

if (!function_exists('renderAiUrl')) {
    function renderAiUrl(string $tab, array $filters = [], ?string $remove = null): string {
        $p = ['tab' => $tab];
        foreach ($filters as $k => $v) {
            if ($k !== $remove && !empty($v)) $p[$k] = $v;
        }
        $targetPage = basename($_SERVER['PHP_SELF'] ?? 'mypage.php');
        return $targetPage . '?' . http_build_query($p);
    }
}
?>

<div class="ai-container">
  <!-- ページ見出し -->
  <div class="ai-page-header">
    <div class="ai-page-title-row">
      <h1 class="ai-page-title">
        <i class="fa-solid fa-wand-magic-sparkles"></i> AI ファッションコーディネート
      </h1>
    </div>
    <p class="ai-page-subtitle">
      手持ちの服とnornの商品をAIが掛け合わせ、あなたに最適なスタイリングとおすすめを提案します。
    </p>
  </div>

  <!-- 4つのタブナビゲーション -->
  <nav class="ai-tab-nav">
    <a href="<?= renderAiUrl('ai_coord', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'ai_coord') ? 'active' : '' ?>">
      <i class="fa-solid fa-wand-magic-sparkles"></i>
      <span>① AI提案コーデ</span>
      <span class="tab-badge"><?= count($ai_coordinated_looks) ?>組</span>
    </a>
    <a href="<?= renderAiUrl('my_clothes', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'my_clothes') ? 'active' : '' ?>">
      <i class="fa-solid fa-shirt"></i>
      <span>② 持っている服</span>
      <span class="tab-badge"><?= count($filtered_my_clothes) ?>着</span>
    </a>
    <a href="<?= renderAiUrl('favorites', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'favorites') ? 'active' : '' ?>">
      <i class="fa-solid fa-heart"></i>
      <span>③ お気に入り</span>
      <span class="tab-badge"><?= count($filtered_favorites) ?>件</span>
    </a>
    <a href="<?= renderAiUrl('recommend', $selected_filters) ?>" class="ai-tab-btn <?= ($active_tab === 'recommend') ? 'active' : '' ?>">
      <i class="fa-solid fa-thumbs-up"></i>
      <span>④ おすすめ</span>
      <span class="tab-badge"><?= count($filtered_recommend_items) ?>件</span>
    </a>
  </nav>

  <!-- 条件選択パネル (最大3つまで) -->
  <section class="condition-panel">
    <form method="GET" action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'] ?? 'mypage.php')) ?>" id="ai-condition-form">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">

      <div class="condition-panel-header">
        <h2 class="condition-panel-title">
          <i class="fa-solid fa-sliders"></i> 条件を選択（3つまで）
        </h2>
        <span id="condition-count-badge" class="condition-counter-badge <?= ($selected_count >= 3) ? 'limit-reached' : '' ?>">
          <?= $selected_count ?> / 3 選択中
        </span>
      </div>

      <!-- 2列グリッド -->
      <div class="condition-grid">
        <!-- 1. カテゴリ -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-tags"></i> カテゴリ</label>
          <div class="condition-select-wrapper">
            <select name="category" class="condition-select <?= !empty($filter_category) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($categories_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_category === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>

        <!-- 2. 季節 -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-cloud-sun"></i> 季節</label>
          <div class="condition-select-wrapper">
            <select name="season" class="condition-select <?= !empty($filter_season) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($seasons_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_season === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>

        <!-- 3. スタイル -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-person"></i> スタイル</label>
          <div class="condition-select-wrapper">
            <select name="style" class="condition-select <?= !empty($filter_style) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($styles_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_style === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>

        <!-- 4. アクセサリーの色 -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-ring"></i> アクセサリーの色</label>
          <div class="condition-select-wrapper">
            <select name="acc_color" class="condition-select <?= !empty($filter_acc_color) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($acc_colors_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_acc_color === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>

        <!-- 5. 服の色 -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-palette"></i> 服の色</label>
          <div class="condition-select-wrapper">
            <select name="color" class="condition-select <?= !empty($filter_color) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($colors_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_color === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>

        <!-- 6. サイズ -->
        <div class="condition-field">
          <label class="condition-label"><i class="fa-solid fa-ruler-combined"></i> サイズ</label>
          <div class="condition-select-wrapper">
            <select name="size" class="condition-select <?= !empty($filter_size) ? 'has-value' : '' ?>">
              <option value="">指定なし</option>
              <?php foreach ($sizes_map as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($filter_size === $k) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
          </div>
        </div>
      </div>

      <!-- アラート & ボタン -->
      <div class="condition-actions">
        <div class="condition-limit-msg <?= ($selected_count >= 3) ? 'alert' : '' ?>" id="condition-limit-alert" style="<?= ($selected_count >= 3) ? '' : 'display:none;' ?>">
          <i class="fa-solid fa-circle-exclamation"></i> 条件は最大3つまでです。別の条件を選ぶには、選択済みの項目を解除してください。
        </div>

        <div class="condition-button-group">
          <?php if ($is_filtered): ?>
            <a href="<?= renderAiUrl($active_tab) ?>" class="btn-ai-reset" id="btn-condition-reset">
              <i class="fa-solid fa-rotate-left"></i> リセット
            </a>
          <?php endif; ?>
          <button type="submit" class="btn-ai-apply">
            <i class="fa-solid fa-magnifying-glass"></i> 条件を適用
          </button>
        </div>
      </div>
    </form>
  </section>

  <!-- 適用中条件チップ -->
  <?php if ($is_filtered): ?>
    <div class="active-conditions-row">
      <span class="active-cond-title"><i class="fa-solid fa-filter"></i> 選択中:</span>
      <?php foreach ($selected_filters as $k => $v): ?>
        <a href="<?= renderAiUrl($active_tab, $selected_filters, $k) ?>" class="cond-chip">
          <?= htmlspecialchars($k === 'category' ? ($categories_map[$v]??$v) : ($k === 'season' ? ($seasons_map[$v]??$v) : ($k === 'style' ? ($styles_map[$v]??$v) : ($k === 'acc_color' ? ($acc_colors_map[$v]??$v) : ($k === 'color' ? ($colors_map[$v]??$v) : $v))))) ?>
          <i class="fa-solid fa-xmark remove-chip"></i>
        </a>
      <?php endforeach; ?>
      <a href="<?= renderAiUrl($active_tab) ?>" style="font-size: 12px; color: #4f46e5; text-decoration: underline; margin-left: 6px;">すべて解除</a>
    </div>
  <?php endif; ?>

  <!-- ① AIが提案するコーデ -->
  <?php if ($active_tab === 'ai_coord'): ?>
    <div class="ai-coord-grid">
      <?php if (empty($ai_coordinated_looks)): ?>
        <div class="ai-empty-state">
          <i class="fa-solid fa-wand-magic-sparkles ai-empty-icon"></i>
          <h3 class="ai-empty-title">一致するコーディネートがありません</h3>
          <p class="ai-empty-desc">条件を変更するか、持っている服を追加してください。</p>
        </div>
      <?php else: ?>
        <?php foreach ($ai_coordinated_looks as $look): ?>
          <div class="ai-coord-card">
            <div class="ai-coord-card-header">
              <h3 class="ai-coord-title"><i class="fa-solid fa-sparkles" style="color:#6366f1;"></i> <?= htmlspecialchars($look['title']) ?></h3>
              <div class="ai-coord-tags">
                <?php foreach ($look['tags'] as $tg): ?><span class="ai-coord-tag"><?= htmlspecialchars($tg) ?></span><?php endforeach; ?>
              </div>
            </div>
            <div class="ai-coord-comment">
              <i class="fa-solid fa-robot"></i>
              <span><?= htmlspecialchars($look['comment']) ?></span>
            </div>
            <div class="ai-coord-items-grid">
              <?php foreach ($look['items'] as $it): 
                $is_owned = isset($it['id']) && !isset($it['item_id']);
              ?>
                <div class="ai-look-item">
                  <span class="ai-item-source-badge <?= $is_owned ? 'badge-my-closet' : 'badge-norn-item' ?>">
                    <i class="fa-solid <?= $is_owned ? 'fa-shirt' : 'fa-bag-shopping' ?>"></i> <?= $is_owned ? '持っている服' : 'nornアイテム' ?>
                  </span>
                  <div class="ai-look-item-thumb"><img src="<?= htmlspecialchars($it['image_url'] ?? '') ?>" alt="" loading="lazy"></div>
                  <span class="ai-look-item-cat"><?= htmlspecialchars($categories_map[$it['category'] ?? ''] ?? ($it['category'] ?? '')) ?></span>
                  <h4 class="ai-look-item-name"><?= htmlspecialchars($it['name'] ?? '') ?></h4>
                  <?php if (!$is_owned && isset($it['price'])): ?>
                    <div class="ai-look-item-price">¥<?= number_format($it['price']) ?></div>
                    <a href="<?= htmlspecialchars($it['affiliate_url'] ?? '#') ?>" target="_blank" class="btn-look-link buy">ショップで見る</a>
                  <?php else: ?>
                    <div class="ai-look-item-price" style="color: #10b981;">クローゼット登録品</div>
                    <span class="btn-look-link my">コーデに適用中</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <!-- ② 持っている服 -->
  <?php elseif ($active_tab === 'my_clothes'): ?>
    <div class="ai-cards-grid">
      <?php if (empty($filtered_my_clothes)): ?>
        <div class="ai-empty-state" style="grid-column: 1/-1;">
          <i class="fa-solid fa-shirt ai-empty-icon"></i>
          <h3 class="ai-empty-title">条件に合う持っている服がありません</h3>
        </div>
      <?php else: ?>
        <?php foreach ($filtered_my_clothes as $it): ?>
          <div class="ai-standard-card">
            <div class="ai-card-image-wrap">
              <span class="ai-item-source-badge badge-my-closet" style="top:10px;left:10px;"><i class="fa-solid fa-wand-magic-sparkles"></i> AI解析済</span>
              <img src="<?= htmlspecialchars($it['image_url'] ?? '') ?>" alt="" loading="lazy">
            </div>
            <div class="ai-card-content">
              <span class="ai-card-brand"><?= htmlspecialchars($categories_map[$it['category'] ?? ''] ?? '') ?></span>
              <h3 class="ai-card-title"><?= htmlspecialchars($it['name'] ?? '') ?></h3>
              <div class="ai-card-meta">
                <?php if (!empty($it['season'])): ?><span class="meta-pill"><?= htmlspecialchars($seasons_map[$it['season']] ?? $it['season']) ?></span><?php endif; ?>
                <?php if (!empty($it['style'])): ?><span class="meta-pill"><?= htmlspecialchars($styles_map[$it['style']] ?? $it['style']) ?></span><?php endif; ?>
                <?php if (!empty($it['color'])): ?><span class="meta-pill"><?= htmlspecialchars($colors_map[$it['color']] ?? $it['color']) ?></span><?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <!-- ③ お気に入り -->
  <?php elseif ($active_tab === 'favorites'): ?>
    <div class="ai-cards-grid">
      <?php if (empty($filtered_favorites)): ?>
        <div class="ai-empty-state" style="grid-column: 1/-1;">
          <i class="fa-regular fa-heart ai-empty-icon"></i>
          <h3 class="ai-empty-title">お気に入りに一致する商品がありません</h3>
        </div>
      <?php else: ?>
        <?php foreach ($filtered_favorites as $it): ?>
          <div class="ai-standard-card">
            <button type="button" class="card-fav-btn active"><i class="fa-solid fa-heart"></i></button>
            <div class="ai-card-image-wrap"><img src="<?= htmlspecialchars($it['image_url'] ?? '') ?>" alt="" loading="lazy"></div>
            <div class="ai-card-content">
              <span class="ai-card-brand"><?= htmlspecialchars($it['brand_name'] ?? 'norn') ?></span>
              <h3 class="ai-card-title"><?= htmlspecialchars($it['name'] ?? '') ?></h3>
              <div class="ai-card-price">¥<?= number_format($it['price'] ?? 0) ?></div>
              <a href="<?= htmlspecialchars($it['affiliate_url'] ?? '#') ?>" target="_blank" class="btn-look-link buy">ショップで見る</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <!-- ④ おすすめ -->
  <?php elseif ($active_tab === 'recommend'): ?>
    <div class="ai-cards-grid">
      <?php if (empty($filtered_recommend_items)): ?>
        <div class="ai-empty-state" style="grid-column: 1/-1;">
          <i class="fa-solid fa-thumbs-up ai-empty-icon"></i>
          <h3 class="ai-empty-title">おすすめ商品が見つかりませんでした</h3>
        </div>
      <?php else: ?>
        <?php foreach ($filtered_recommend_items as $idx => $it): 
          $targetOwned = $my_clothes[$idx % count($my_clothes)] ?? $primary_owned_item;
        ?>
          <div class="ai-standard-card">
            <button type="button" class="card-fav-btn <?= !empty($it['is_liked']) ? 'active' : '' ?>"><i class="<?= !empty($it['is_liked']) ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i></button>
            <div class="ai-card-image-wrap"><img src="<?= htmlspecialchars($it['image_url'] ?? '') ?>" alt="" loading="lazy"></div>
            <div class="ai-card-content">
              <?php if ($targetOwned): ?>
                <div class="ai-match-badge">
                  <i class="fa-solid fa-sparkles"></i>
                  <div><strong>手持ちの「<?= htmlspecialchars($targetOwned['name']) ?>」</strong>と相性抜群！</div>
                </div>
              <?php endif; ?>
              <span class="ai-card-brand"><?= htmlspecialchars($it['brand_name'] ?? 'norn') ?></span>
              <h3 class="ai-card-title"><?= htmlspecialchars($it['name'] ?? '') ?></h3>
              <div class="ai-card-price">¥<?= number_format($it['price'] ?? 0) ?></div>
              <a href="<?= htmlspecialchars($it['affiliate_url'] ?? '#') ?>" target="_blank" class="btn-look-link buy">ショップで見る</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
