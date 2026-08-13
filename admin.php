<?php
session_start();

// 簡易的なパスワード保護（初期パスワード：studio222）
$password = 'studio222';

if (isset($_POST['login'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "パスワードが違います。";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$message = '';

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    $title = htmlspecialchars($_POST['title'] ?? '');
    $category = htmlspecialchars($_POST['category'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');
    $link_url = htmlspecialchars($_POST['link_url'] ?? '');
    $pdf_url = htmlspecialchars($_POST['pdf_url'] ?? '');
    
    $thumbnail_path = '';

    // 画像アップロード処理
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $tmp_name = $_FILES['thumbnail']['tmp_name'];
        $name = basename($_FILES['thumbnail']['name']);
        
        // ファイル名の衝突を避けるためにタイムスタンプを付与
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $new_name = time() . '_' . uniqid() . '.' . $ext;
        $destination = $upload_dir . $new_name;
        
        if (move_uploaded_file($tmp_name, $destination)) {
            $thumbnail_path = 'uploads/' . $new_name;
        } else {
            $message = "画像のアップロードに失敗しました。";
        }
    }

    if (empty($message)) {
        // data.json の更新
        $json_file = __DIR__ . '/data.json';
        $current_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : ['categories' => [], 'contents' => []];
        
        $new_content = [
            'id' => uniqid('c_'),
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'link_url' => $link_url,
            'pdf_url' => $pdf_url,
            'thumbnail' => $thumbnail_path,
            'created_at' => date('c')
        ];
        
        // 先頭に追加
        array_unshift($current_data['contents'], $new_content);
        
        if (file_put_contents($json_file, json_encode($current_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $message = "コンテンツを正常に配備しました。";
        } else {
            $message = "data.jsonの書き込みに失敗しました。パーミッションを確認してください。";
        }
    }
}

// カテゴリ一覧の取得
$categories = [];
$json_file = __DIR__ . '/data.json';
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    if (isset($data['categories'])) {
        $categories = $data['categories'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>司令室 - Studio 222 Music</title>
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #111111;
            --border: #333333;
            --text: #f5f5f5;
            --text-dim: #888888;
            --accent: #d4af37;
            --error: #ff4444;
            --success: #44ff44;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 2rem;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 2rem; font-weight: 300; letter-spacing: 2px; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .card { background: var(--surface); border: 1px solid var(--border); padding: 2rem; margin-bottom: 2rem; border-radius: 4px; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: var(--text-dim); font-size: 0.9rem; letter-spacing: 1px; }
        input[type="text"], input[type="password"], input[type="url"], textarea, select {
            width: 100%; padding: 0.8rem; background: var(--bg); border: 1px solid var(--border);
            color: var(--text); font-family: inherit; font-size: 1rem;
        }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--accent); }
        textarea { resize: vertical; min-height: 100px; }
        input[type="file"] { background: transparent; border: none; padding: 0; }
        .btn {
            background: transparent; border: 1px solid var(--accent); color: var(--accent);
            padding: 0.8rem 2rem; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn:hover { background: var(--accent); color: var(--bg); }
        .btn-small { padding: 0.4rem 1rem; font-size: 0.8rem; }
        .message { padding: 1rem; margin-bottom: 1rem; border: 1px solid var(--accent); color: var(--accent); }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        
        .tools-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .tool-card { border: 1px dashed var(--border); padding: 1rem; text-align: center; text-decoration: none; color: var(--text-dim); transition: 0.3s; }
        .tool-card:hover { border-color: var(--accent); color: var(--accent); }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_logged_in): ?>
            <div class="card" style="max-width: 400px; margin: 10vh auto;">
                <h1 style="border:none; text-align:center;">AUTHENTICATION</h1>
                <?php if (isset($error)): ?><p style="color:var(--error); margin-bottom:1rem; text-align:center;"><?php echo $error; ?></p><?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Passcode" required autofocus>
                    </div>
                    <button type="submit" name="login" class="btn" style="width: 100%;">ACCESS</button>
                </form>
            </div>
        <?php else: ?>
            <div class="header-actions">
                <h1>COMMAND CENTER</h1>
                <a href="?logout=1" class="btn btn-small">LOGOUT</a>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="card">
                <h2 style="margin-bottom: 1.5rem; font-weight: 300; font-size: 1.5rem;">新着コンテンツ配備</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>タイトル</label>
                        <input type="text" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>カテゴリ</label>
                        <select name="category" required>
                            <option value="">選択してください</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>サムネイル画像</label>
                        <input type="file" name="thumbnail" accept="image/*" required>
                    </div>

                    <div class="form-group">
                        <label>紹介文</label>
                        <textarea name="description" required></textarea>
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

            <div class="card">
                <h2 style="margin-bottom: 1.5rem; font-weight: 300; font-size: 1.5rem;">関連ツール</h2>
                <div class="tools-grid">
                    <a href="#" class="tool-card">
                        <div>説明ジェネレータ</div>
                        <small>AI Description Maker</small>
                    </a>
                    <a href="../mockups/mockup_portal.html" class="tool-card">
                        <div>モックアップ・ポータル</div>
                        <small>Design Archive</small>
                    </a>
                </div>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
