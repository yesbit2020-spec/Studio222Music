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

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$message = '';
$error = $error ?? '';

// データの読み込み
$data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : ['categories' => [], 'contents' => []];

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- コンテンツ追加 ---
    if (isset($_POST['add_content'])) {
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
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
                <div class="nav-tab" onclick="switchTab('category')">カテゴリ管理</div>
                <div class="nav-tab" onclick="switchTab('tools')">関連ツール・確認</div>
            </div>

            <!-- 1. コンテンツ登録タブ -->
            <div id="tab-content" class="tab-content active">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; font-weight: 300;">新着コンテンツ配備</h2>
                    <form method="post" enctype="multipart/form-data">
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
                            <input type="file" name="thumbnail" accept="image/*" required>
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
                        <a href="../mockups/mockup_portal.html" target="_blank" class="tool-card">
                            <div style="font-size:1.2rem; margin-bottom:0.5rem; color:var(--text);">モックアップ・ポータル</div>
                            <small>Design Archive</small>
                        </a>
                        <a href="#" class="tool-card">
                            <div style="font-size:1.2rem; margin-bottom:0.5rem; color:var(--text);">説明ジェネレータ</div>
                            <small>AI Description Maker</small>
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
