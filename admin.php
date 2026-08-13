<?php
session_start();

$password = 'studio222';
$json_file = __DIR__ . '/data.json';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (isset($_POST['login'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "パスワードが違います。";
    }
}


if (isset($_GET['download_template']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="import_template.csv"');
    // Excelで文字化けさせないためのBOM
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['タイトル', '大カテゴリID', '中カテゴリID', '小カテゴリID', '説明文(HTML可)', 'メインURL', 'PDF資料URL', '画像URL']);
    fputcsv($out, ['サンプルサウンド', 'original', 'ambient', 'deep-focus', '<p>ここに説明文を書きます</p>', 'https://youtube.com/...', '', 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?q=80&w=800&auto=format&fit=crop']);
    fclose($out);
    exit;
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$message = '';
$error = $error ?? '';

// データの読み込み
$data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : ['categories' => [], 'contents' => []];

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    // --- CSV一括インポート ---
    if (isset($_POST['import_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['csv_file']['tmp_name'];
            $csv_content = file_get_contents($tmp_name);
            // 文字化け対策: エンコーディングをUTF-8に変換
            $csv_content = mb_convert_encoding($csv_content, 'UTF-8', 'auto');
            
            $temp_utf8_file = tempnam(sys_get_temp_dir(), 'csv');
            file_put_contents($temp_utf8_file, $csv_content);
            
            $handle = fopen($temp_utf8_file, "r");
            $header_skipped = false;
            $new_items = [];
            
            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row))) continue;
                if (!$header_skipped) {
                    $header_skipped = true;
                    continue;
                }
                
                $title = isset($row[0]) ? htmlspecialchars(trim($row[0])) : '';
                $cat_main = isset($row[1]) ? htmlspecialchars(trim($row[1])) : '';
                $cat_mid = isset($row[2]) ? htmlspecialchars(trim($row[2])) : '';
                $cat_small = isset($row[3]) ? htmlspecialchars(trim($row[3])) : '';
                $desc = isset($row[4]) ? strip_tags(trim($row[4]), '<p><a><br><strong><em><ul><ol><li>') : '';
                $link = isset($row[5]) ? htmlspecialchars(trim($row[5])) : '';
                $pdf = isset($row[6]) ? htmlspecialchars(trim($row[6])) : '';
                $thumb = isset($row[7]) ? htmlspecialchars(trim($row[7])) : '';
                
                if ($title !== '') {
                    $new_items[] = [
                        'id' => uniqid('c_'),
                        'title' => $title,
                        'category' => $cat_main,
                        'middle_category' => $cat_mid,
                        'small_category' => $cat_small,
                        'description' => $desc,
                        'link_url' => $link,
                        'pdf_url' => $pdf,
                        'thumbnail' => $thumb,
                        'created_at' => date('c')
                    ];
                }
            }
            fclose($handle);
            unlink($temp_utf8_file);
            
            if (!empty($new_items)) {
                // CSVの上からの並びをサイトの上からの並びに一致させるため、逆順にして先頭に追加
                $new_items = array_reverse($new_items);
                foreach ($new_items as $item) {
                    array_unshift($data['contents'], $item);
                }
                if (file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    $message = count($new_items) . " 件のデータを一括インポートしました！";
                }
            } else {
                $message = "有効なデータが見つかりませんでした。";
            }
        } else {
            $message = "CSVファイルのアップロードに失敗しました。";
        }
    }

    // --- コンテンツ追加・更新 ---
    if (isset($_POST['add_content'])) {
        $edit_id = $_POST['edit_id'] ?? '';
        $title = htmlspecialchars($_POST['title'] ?? '');
        $category = htmlspecialchars($_POST['category'] ?? '');
        $middle_category = htmlspecialchars($_POST['middle_category'] ?? '');
        $small_category = htmlspecialchars($_POST['small_category'] ?? '');
        // TinyMCEからのHTML入力を許可（一部タグのみ）
        $description = strip_tags($_POST['description'] ?? '', '<p><a><br><strong><em><ul><ol><li>');
        $link_url = htmlspecialchars($_POST['link_url'] ?? '');
        $pdf_url = htmlspecialchars($_POST['pdf_url'] ?? '');
        
        $thumbnail_path = '';

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $name = basename($_FILES['thumbnail']['name']);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $new_name = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_dir . $new_name)) {
                $thumbnail_path = 'uploads/' . $new_name;
            } else {
                $message = "画像のアップロードに失敗しました。";
            }
        }

        if (empty($message)) {
            if ($edit_id !== '') {
                // 既存のコンテンツの更新
                foreach ($data['contents'] as &$item) {
                    if ($item['id'] === $edit_id) {
                        $item['title'] = $title;
                        $item['category'] = $category;
                        $item['middle_category'] = $middle_category;
                        $item['small_category'] = $small_category;
                        $item['description'] = $description;
                        $item['link_url'] = $link_url;
                        $item['pdf_url'] = $pdf_url;
                        
                        // 画像が新しくアップロードされた場合のみ更新
                        if ($thumbnail_path !== '') {
                            // 古い画像を削除
                            if (!empty($item['thumbnail'])) {
                                $old_path = __DIR__ . '/' . $item['thumbnail'];
                                if (file_exists($old_path)) { unlink($old_path); }
                            }
                            $item['thumbnail'] = $thumbnail_path;
                        }
                        break;
                    }
                }
                if (file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    $message = "コンテンツを更新しました。";
                }
            } else {
                // 新規コンテンツの追加
                $new_content = [
                    'id' => uniqid('c_'),
                    'title' => $title,
                    'category' => $category,
                    'middle_category' => $middle_category,
                    'small_category' => $small_category,
                    'description' => $description,
                    'link_url' => $link_url,
                    'pdf_url' => $pdf_url,
                    'thumbnail' => $thumbnail_path,
                    'created_at' => date('c')
                ];
                array_unshift($data['contents'], $new_content);
                if (file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    $message = "コンテンツを配備しました。";
                }
            }
        }
    }

    // --- コンテンツ削除 ---
    if (isset($_POST['delete_content'])) {
        $content_id = $_POST['content_id'];
        $deleted = false;
        
        $data['contents'] = array_filter($data['contents'], function($item) use ($content_id, &$deleted) {
            if ($item['id'] === $content_id) {
                // 画像ファイルの削除
                if (!empty($item['thumbnail'])) {
                    $thumb_path = __DIR__ . '/' . $item['thumbnail'];
                    if (file_exists($thumb_path)) { unlink($thumb_path); }
                }
                $deleted = true;
                return false;
            }
            return true;
        });
        
        if ($deleted) {
            $data['contents'] = array_values($data['contents']);
            file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = "コンテンツを削除しました。";
        }
    }

    // --- 中カテゴリ追加 ---
    if (isset($_POST['add_mid_cat'])) {
        $parent_id = $_POST['parent_cat_id'];
        $mid_id = preg_replace('/[^a-z0-9-]/', '', $_POST['mid_id']); // 英数字ハイフンのみ
        $mid_name = htmlspecialchars($_POST['mid_name']);

        if($mid_id && $mid_name) {
            foreach ($data['categories'] as &$cat) {
                if ($cat['id'] === $parent_id) {
                    if (!isset($cat['middle_categories'])) $cat['middle_categories'] = [];
                    $cat['middle_categories'][] = [
                        'id' => $mid_id,
                        'name' => $mid_name,
                        'small_categories' => []
                    ];
                    break;
                }
            }
            file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = "中カテゴリを追加しました。";
        }
    }

    // --- 小カテゴリ追加 ---
    if (isset($_POST['add_small_cat'])) {
        $parent_cat = $_POST['parent_cat'];
        $parent_mid = $_POST['parent_mid'];
        $small_id = preg_replace('/[^a-z0-9-]/', '', $_POST['small_id']);
        $small_name = htmlspecialchars($_POST['small_name']);

        if($small_id && $small_name) {
            foreach ($data['categories'] as &$cat) {
                if ($cat['id'] === $parent_cat && isset($cat['middle_categories'])) {
                    foreach ($cat['middle_categories'] as &$mid) {
                        if ($mid['id'] === $parent_mid) {
                            if (!isset($mid['small_categories'])) $mid['small_categories'] = [];
                            $mid['small_categories'][] = [
                                'id' => $small_id,
                                'name' => $small_name
                            ];
                            break 2;
                        }
                    }
                }
            }
            file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = "小カテゴリを追加しました。";
        }
    }

    // --- カテゴリ削除 ---
    if (isset($_POST['delete_cat'])) {
        $type = $_POST['type'];
        $cat_id = $_POST['cat_id'];
        $mid_id = $_POST['mid_id'] ?? '';
        $small_id = $_POST['small_id'] ?? '';

        if ($type === 'mid') {
            foreach ($data['categories'] as &$cat) {
                if ($cat['id'] === $cat_id && isset($cat['middle_categories'])) {
                    $cat['middle_categories'] = array_filter($cat['middle_categories'], function($m) use ($mid_id) {
                        return $m['id'] !== $mid_id;
                    });
                    $cat['middle_categories'] = array_values($cat['middle_categories']);
                }
            }
        } elseif ($type === 'small') {
            foreach ($data['categories'] as &$cat) {
                if ($cat['id'] === $cat_id && isset($cat['middle_categories'])) {
                    foreach ($cat['middle_categories'] as &$mid) {
                        if ($mid['id'] === $mid_id && isset($mid['small_categories'])) {
                            $mid['small_categories'] = array_filter($mid['small_categories'], function($s) use ($small_id) {
                                return $s['id'] !== $small_id;
                            });
                            $mid['small_categories'] = array_values($mid['small_categories']);
                        }
                    }
                }
            }
        }
        file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = "カテゴリを削除しました。";
    }
}

$categories = $data['categories'] ?? [];
$categories_json = json_encode($categories, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>司令室 - Studio 222 Music</title>
    <style>
        :root {
            --bg: #0a0a0a; --surface: #111111; --border: #333333;
            --text: #f5f5f5; --text-dim: #888888; --accent: #d4af37;
            --error: #ff4444; --success: #44ff44;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: var(--bg); color: var(--text); line-height: 1.6; padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 2rem; font-weight: 300; letter-spacing: 2px; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .card { background: var(--surface); border: 1px solid var(--border); padding: 2rem; margin-bottom: 2rem; border-radius: 4px; }
        
        /* フォーム系 */
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: var(--text-dim); font-size: 0.9rem; letter-spacing: 1px; }
        input[type="text"], input[type="password"], input[type="url"], select {
            width: 100%; padding: 0.8rem; background: var(--bg); border: 1px solid var(--border);
            color: var(--text); font-family: inherit; font-size: 1rem;
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); }
        .btn { background: transparent; border: 1px solid var(--accent); color: var(--accent); padding: 0.8rem 2rem; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s; }
        .btn:hover { background: var(--accent); color: var(--bg); }
        .btn-small { padding: 0.4rem 1rem; font-size: 0.8rem; }
        .btn-danger { border-color: var(--error); color: var(--error); }
        .btn-danger:hover { background: var(--error); color: #fff; }
        
        .message { padding: 1rem; margin-bottom: 1rem; border: 1px solid var(--accent); color: var(--accent); }
        
        /* ナビゲーション・ヘッダー */
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .nav-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border); }
        .nav-tab { padding: 1rem 2rem; cursor: pointer; color: var(--text-dim); border-bottom: 2px solid transparent; text-transform: uppercase; letter-spacing: 1px; }
        .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* ツールグリッド */
        .tools-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .tool-card { border: 1px dashed var(--border); padding: 1.5rem; text-align: center; text-decoration: none; color: var(--text-dim); transition: 0.3s; display: block; }
        .tool-card:hover { border-color: var(--accent); color: var(--accent); }
        
        /* カテゴリリスト */
        .cat-list { list-style: none; margin-bottom: 2rem; }
        .cat-list li { padding: 1rem; border: 1px solid var(--border); margin-bottom: 0.5rem; background: var(--bg); display: flex; justify-content: space-between; align-items: center; }
        .cat-list ul { list-style: none; padding-left: 2rem; margin-top: 1rem; width: 100%; }
        
        .cat-badge { display: inline-block; padding: 0.2rem 0.5rem; background: #222; border-radius: 4px; font-size: 0.8rem; margin-right: 1rem; }
        
        /* TinyMCE Dark mode adjustments */
        .tox-tinymce { border-color: var(--border) !important; }
    </style>
    <!-- TinyMCE CDN (Open Source via cdnjs) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#richtext-editor',
            plugins: 'link lists',
            toolbar: 'undo redo | bold italic | link | bullist numlist',
            menubar: false,
            skin: 'oxide-dark',
            content_css: 'dark',
            setup: function (editor) {
                editor.on('change', function () {
                    editor.save();
                });
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <?php if (!$is_logged_in): ?>
            <div class="card" style="max-width: 400px; margin: 10vh auto;">
                <h1 style="border:none; text-align:center;">AUTHENTICATION</h1>
                <?php if ($error): ?><p style="color:var(--error); margin-bottom:1rem; text-align:center;"><?php echo $error; ?></p><?php endif; ?>
                <form method="post">
                    <div class="form-group"><input type="password" name="password" placeholder="Passcode" required autofocus></div>
                    <button type="submit" name="login" class="btn" style="width: 100%;">ACCESS</button>
                </form>
            </div>
        <?php else: ?>
            <div class="header-actions">
                <h1>COMMAND CENTER</h1>
                <div>
                    <a href="?logout=1" class="btn btn-small btn-danger" style="text-decoration:none;">LOGOUT</a>
                </div>
            </div>

            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <!-- タブナビゲーション -->
            <div class="nav-tabs">
                <div class="nav-tab active" onclick="switchTab('content')">コンテンツ配備</div>
                <div class="nav-tab" onclick="switchTab('list')">コンテンツ一覧・管理</div>
                <div class="nav-tab" onclick="switchTab('import')">一括インポート</div>
                <div class="nav-tab" onclick="switchTab('category')">カテゴリ管理</div>
                <div class="nav-tab" onclick="switchTab('tools')">関連ツール・確認</div>
            </div>

            <!-- 1. コンテンツ登録タブ -->
            <div id="tab-content" class="tab-content active">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">新着コンテンツ配備</h2>
                    <form method="post" enctype="multipart/form-data" id="content-form">
                        <input type="hidden" name="edit_id" id="edit-id" value="">
                        <div class="form-group">
                            <label>タイトル</label>
                            <input type="text" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label>大カテゴリ</label>
                            <select name="category" id="select-main" required onchange="updateMiddleCats()">
                                <option value="">選択してください</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>中カテゴリ</label>
                            <select name="middle_category" id="select-mid" required onchange="updateSmallCats()">
                                <option value="">大カテゴリを選択してください</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>小カテゴリ</label>
                            <select name="small_category" id="select-small" required>
                                <option value="">中カテゴリを選択してください</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>サムネイル画像</label>
                            <input type="file" name="thumbnail" accept="image/*" required id="thumbnail-input">
                            <p id="thumb-hint" style="font-size: 0.8rem; color: var(--text-dim); margin-top: 0.5rem;"></p>
                        </div>

                        <div class="form-group">
                            <label>紹介文 (リッチエディタ)</label>
                            <!-- TinyMCE Editor -->
                            <textarea id="richtext-editor" name="description"></textarea>
                        </div>

                        <div class="form-group">
                            <label>メインURL (任意)</label>
                            <input type="url" name="link_url" placeholder="https://...">
                        </div>

                        <div class="form-group">
                            <label>PDF資料URL (任意)</label>
                            <input type="url" name="pdf_url" placeholder="https://...">
                        </div>

                        <button type="submit" name="add_content" class="btn">データアップロード実行</button>
                    </form>
                </div>
            </div>

            
            <!-- 新設: コンテンツ一覧・管理タブ -->
            <div id="tab-list" class="tab-content">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">登録済みコンテンツ一覧</h2>
                    <?php if(empty($data['contents'])): ?>
                        <p style="color: var(--text-dim);">配備されたコンテンツはまだありません。</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 700px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <th style="padding: 1rem; color: var(--text-dim); width: 100px;">画像</th>
                                        <th style="padding: 1rem; color: var(--text-dim);">タイトル</th>
                                        <th style="padding: 1rem; color: var(--text-dim);">カテゴリ (大/中/小)</th>
                                        <th style="padding: 1rem; color: var(--text-dim); width: 150px;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['contents'] as $item): ?>
                                    <tr style="border-bottom: 1px solid #222;">
                                        <td style="padding: 1rem;">
                                            <?php if(!empty($item['thumbnail'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" style="width: 80px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #333;">
                                            <?php else: ?>
                                                <div style="width: 80px; height: 45px; background: #222; border-radius: 4px; border: 1px solid #333;"></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td style="padding: 1rem; font-size: 0.85rem; color: var(--accent);">
                                            <?php echo htmlspecialchars($item['category'] . ' / ' . $item['middle_category'] . ' / ' . $item['small_category']); ?>
                                        </td>
                                        <td style="padding: 1rem; display: flex; gap: 0.5rem;">
                                            <!-- Edit button -->
                                            <button type="button" class="btn btn-small" onclick='editContent(<?php echo json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>編集</button>
                                            
                                            <!-- Delete button -->
                                            <form method="post" onsubmit="return confirm('本当に削除しますか？\n※画像も完全に削除されます。');" style="margin: 0;">
                                                <input type="hidden" name="content_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                                <button type="submit" name="delete_content" class="btn btn-small btn-danger">削除</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <!-- 新設: 一括インポートタブ -->
            <div id="tab-import" class="tab-content">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">CSV一括インポート</h2>
                    <p style="margin-bottom: 1rem; color: var(--text-dim); line-height: 1.6;">
                        スプレッドシートやExcelから出力したCSVファイルを使って、複数のデータを一括で登録できます。<br>
                        画像のURLも直接指定可能です。
                    </p>
                    
                    <div style="margin-bottom: 2rem;">
                        <a href="?download_template=1" class="btn btn-small" style="text-decoration: none;">📥 テンプレート(CSV)をダウンロード</a>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" style="border: 1px dashed var(--border); padding: 2rem; border-radius: 4px;">
                        <div class="form-group">
                            <label>CSVファイルを選択</label>
                            <input type="file" name="csv_file" accept=".csv" required style="border: none; padding: 0;">
                        </div>
                        <button type="submit" name="import_csv" class="btn">データを一括配備する</button>
                    </form>

                    <div style="margin-top: 2rem; padding: 1rem; background: var(--bg); border-left: 4px solid var(--accent);">
                        <h4 style="margin-bottom: 0.5rem; color: var(--accent);">■ 登録順と表示順について</h4>
                        <p style="font-size: 0.9rem; color: var(--text-dim);">
                            CSVファイルの<strong>1行目のデータがサイトの一番上（最新）</strong>に表示されます。<br>
                            上から順番に新しいデータとして処理されるため、スプレッドシートで見ている通りの順番でサイトに並びます。
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- 2. カテゴリ管理タブ -->
            <div id="tab-category" class="tab-content">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">カテゴリ構造管理</h2>
                    
                    <div style="margin-bottom: 3rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--accent);">■ 新規 中カテゴリ追加</h3>
                        <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
                            <div style="flex:1;">
                                <label>親（大カテゴリ）</label>
                                <select name="parent_cat_id" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex:1;">
                                <label>ID (英数字ハイフン)</label>
                                <input type="text" name="mid_id" placeholder="例: ambient" required>
                            </div>
                            <div style="flex:1;">
                                <label>表示名</label>
                                <input type="text" name="mid_name" placeholder="例: アンビエント" required>
                            </div>
                            <button type="submit" name="add_mid_cat" class="btn btn-small">追加</button>
                        </form>
                    </div>

                    <div style="margin-bottom: 3rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--accent);">■ 新規 小カテゴリ追加</h3>
                        <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                            <div style="flex:1; min-width: 150px;">
                                <label>親（大カテ）</label>
                                <select name="parent_cat" id="add-small-cat" required onchange="updateAddSmallMid()">
                                    <option value="">選択</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex:1; min-width: 150px;">
                                <label>親（中カテ）</label>
                                <select name="parent_mid" id="add-small-mid" required>
                                    <option value="">大カテを選択</option>
                                </select>
                            </div>
                            <div style="flex:1; min-width: 150px;">
                                <label>ID (英数)</label>
                                <input type="text" name="small_id" placeholder="例: deep-focus" required>
                            </div>
                            <div style="flex:1; min-width: 150px;">
                                <label>表示名</label>
                                <input type="text" name="small_name" placeholder="例: Deep Focus" required>
                            </div>
                            <button type="submit" name="add_small_cat" class="btn btn-small" style="height:45px;">追加</button>
                        </form>
                    </div>

                    <h3 style="margin-bottom: 1rem; border-top: 1px solid var(--border); padding-top: 2rem;">■ 登録済みカテゴリ一覧</h3>
                    <ul class="cat-list">
                        <?php foreach ($categories as $cat): ?>
                            <li style="flex-direction:column; align-items:flex-start;">
                                <div style="font-weight:bold; font-size:1.1rem; color:var(--accent);">[大] <?php echo htmlspecialchars($cat['name']); ?> (<?php echo htmlspecialchars($cat['id']); ?>)</div>
                                <?php if (!empty($cat['middle_categories'])): ?>
                                    <ul>
                                    <?php foreach ($cat['middle_categories'] as $mid): ?>
                                        <li style="background:var(--surface);">
                                            <div>
                                                <span class="cat-badge">中</span> <?php echo htmlspecialchars($mid['name']); ?> (<?php echo htmlspecialchars($mid['id']); ?>)
                                            </div>
                                            <form method="post" onsubmit="return confirm('削除しますか？');" style="margin:0;">
                                                <input type="hidden" name="type" value="mid">
                                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                                <input type="hidden" name="mid_id" value="<?php echo $mid['id']; ?>">
                                                <button type="submit" name="delete_cat" class="btn btn-small btn-danger" style="padding:0.2rem 0.5rem; font-size:0.7rem;">削除</button>
                                            </form>
                                        </li>
                                        <?php if (!empty($mid['small_categories'])): ?>
                                            <ul>
                                            <?php foreach ($mid['small_categories'] as $small): ?>
                                                <li style="background:var(--bg);">
                                                    <div>
                                                        <span class="cat-badge" style="background:#444;">小</span> <?php echo htmlspecialchars($small['name']); ?> (<?php echo htmlspecialchars($small['id']); ?>)
                                                    </div>
                                                    <form method="post" onsubmit="return confirm('削除しますか？');" style="margin:0;">
                                                        <input type="hidden" name="type" value="small">
                                                        <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                                        <input type="hidden" name="mid_id" value="<?php echo $mid['id']; ?>">
                                                        <input type="hidden" name="small_id" value="<?php echo $small['id']; ?>">
                                                        <button type="submit" name="delete_cat" class="btn btn-small btn-danger" style="padding:0.2rem 0.5rem; font-size:0.7rem;">削除</button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- 3. 関連ツール・確認タブ -->
            <div id="tab-tools" class="tab-content">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">システム・ツール</h2>
                    <div class="tools-grid">
                        <a href="index.html" target="_blank" class="tool-card" style="border-color:var(--accent);">
                            <div style="font-size:1.2rem; margin-bottom:0.5rem; color:var(--text);">サイトを確認 ↗</div>
                            <small>本番のフロントページを別タブで開きます</small>
                        </a>
                        
                        <a href="youtube-generator/index.html" target="_blank" class="tool-card">
                            <div style="font-size:1.2rem; margin-bottom:0.5rem; color:var(--text);">YouTubeジェネレータ</div>
                            <small>YouTube Description Maker</small>
                        </a>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // タブ切り替えロジック
        function switchTab(tabId) {
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // 3階層カテゴリ連動プルダウンのロジック
        const categoriesData = <?php echo $categories_json; ?>;

        
        // 編集モードへの切り替え
        function editContent(item) {
            switchTab('content');
            
            // フォームに値を流し込む
            document.getElementById('edit-id').value = item.id;
            document.querySelector('input[name="title"]').value = item.title;
            document.querySelector('input[name="link_url"]').value = item.link_url || '';
            document.querySelector('input[name="pdf_url"]').value = item.pdf_url || '';
            
            // カテゴリの連動選択
            document.getElementById('select-main').value = item.category;
            updateMiddleCats();
            document.getElementById('select-mid').value = item.middle_category;
            updateSmallCats();
            document.getElementById('select-small').value = item.small_category;
            
            // TinyMCEへの値の流し込み
            if (tinymce.get('richtext-editor')) {
                tinymce.get('richtext-editor').setContent(item.description || '');
            }
            
            // 画像の必須設定を解除し、メッセージを表示
            document.getElementById('thumbnail-input').removeAttribute('required');
            document.getElementById('thumb-hint').innerText = "※編集モード: 新しい画像を選択しない場合は、元の画像が維持されます。";
            
            // タイトルとボタンのテキストを変更
            document.querySelector('#tab-content h2').innerHTML = "コンテンツ編集モード <span style='font-size:0.8rem; color:var(--text-dim);'>(ID: " + item.id + ")</span> <button type='button' class='btn btn-small' onclick='resetForm()' style='margin-left:1rem;'>新規作成に戻る</button>";
            document.querySelector('button[name="add_content"]').innerText = "データ更新実行";
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('content-form').reset();
            document.getElementById('edit-id').value = '';
            document.getElementById('thumbnail-input').setAttribute('required', 'required');
            document.getElementById('thumb-hint').innerText = '';
            if (tinymce.get('richtext-editor')) {
                tinymce.get('richtext-editor').setContent('');
            }
            document.querySelector('#tab-content h2').innerText = "新着コンテンツ配備";
            document.querySelector('button[name="add_content"]').innerText = "データアップロード実行";
            
            updateMiddleCats();
            updateSmallCats();
        }

        function updateMiddleCats() {
            const mainId = document.getElementById('select-main').value;
            const midSelect = document.getElementById('select-mid');
            const smallSelect = document.getElementById('select-small');
            
            midSelect.innerHTML = '<option value="">選択してください</option>';
            smallSelect.innerHTML = '<option value="">中カテゴリを選択してください</option>';
            
            if(!mainId) return;
            
            const cat = categoriesData.find(c => c.id === mainId);
            if(cat && cat.middle_categories) {
                cat.middle_categories.forEach(m => {
                    midSelect.innerHTML += `<option value="${m.id}">${m.name}</option>`;
                });
            }
        }

        function updateSmallCats() {
            const mainId = document.getElementById('select-main').value;
            const midId = document.getElementById('select-mid').value;
            const smallSelect = document.getElementById('select-small');
            
            smallSelect.innerHTML = '<option value="">選択してください</option>';
            
            if(!mainId || !midId) return;
            
            const cat = categoriesData.find(c => c.id === mainId);
            if(cat && cat.middle_categories) {
                const mid = cat.middle_categories.find(m => m.id === midId);
                if(mid && mid.small_categories) {
                    mid.small_categories.forEach(s => {
                        smallSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
                    });
                }
            }
        }

        function updateAddSmallMid() {
            const mainId = document.getElementById('add-small-cat').value;
            const midSelect = document.getElementById('add-small-mid');
            midSelect.innerHTML = '<option value="">選択</option>';
            if(!mainId) return;
            const cat = categoriesData.find(c => c.id === mainId);
            if(cat && cat.middle_categories) {
                cat.middle_categories.forEach(m => {
                    midSelect.innerHTML += `<option value="${m.id}">${m.name}</option>`;
                });
            }
        }
    </script>
</body>
</html>
