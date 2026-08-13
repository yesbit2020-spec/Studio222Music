document.addEventListener('DOMContentLoaded', () => {
    let currentEditingId = null;
    // --- Elements ---
    const inputs = {
        songTitle: document.getElementById('songTitle'),
        catchphrase: document.getElementById('catchphrase'),
        lyrics: document.getElementById('lyrics'),
        conceptJa: document.getElementById('conceptJa'),
        conceptEn: document.getElementById('conceptEn'),
        tagsInput: document.getElementById('tagsInput'),
        footerText: document.getElementById('footerText'),
        geminiApiKey: document.getElementById('geminiApiKey'),
        gasUrl: document.getElementById('gasUrl')
    };

    const outputs = {
        outTitle: document.getElementById('outTitle'),
        outDescription: document.getElementById('outDescription'),
        outTags: document.getElementById('outTags')
    };

    const buttons = {
        btnGenerateJa: document.getElementById('btnGenerateJa'),
        btnGenerateEn: document.getElementById('btnGenerateEn'),
        btnGenerateTags: document.getElementById('btnGenerateTags'),
        btnUpdatePreview: document.getElementById('btnUpdatePreview'),
        btnSaveToGas: document.getElementById('btnSaveToGas'),
        btnReloadHistory: document.getElementById('btnReloadHistory')
    };

    const apiStatus = document.getElementById('apiStatus');
    const copyButtons = document.querySelectorAll('.btn-copy');
    const toast = document.getElementById('toast');
    const modelSelectionGroup = document.getElementById('modelSelectionGroup');
    const modelSelect = document.getElementById('modelSelect');

    // Tab Elements
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const historyTableBody = document.getElementById('historyTableBody');

    // --- Load Saved Keys ---
    const savedKey = localStorage.getItem('geminiApiKey');
    if (savedKey) {
        inputs.geminiApiKey.value = savedKey;
        updateApiStatus(true);
    }

    inputs.geminiApiKey.addEventListener('input', (e) => {
        const key = e.target.value.trim();
        if (key) {
            localStorage.setItem('geminiApiKey', key);
            updateApiStatus(true);
        } else {
            localStorage.removeItem('geminiApiKey');
            updateApiStatus(false);
        }
    });

    const savedGasUrl = localStorage.getItem('gasUrl');
    if (savedGasUrl) {
        inputs.gasUrl.value = savedGasUrl;
    }

    inputs.gasUrl.addEventListener('input', (e) => {
        const url = e.target.value.trim();
        if (url) {
            localStorage.setItem('gasUrl', url);
        } else {
            localStorage.removeItem('gasUrl');
        }
    });

    // --- Tab Switching Logic ---
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            const targetId = btn.getAttribute('data-tab');
            document.getElementById(targetId).classList.add('active');
            
            if (targetId === 'history-tab') {
                loadHistoryFromGas();
            }
        });
    });

    function updateApiStatus(hasKey) {
        if (hasKey) {
            apiStatus.textContent = 'API: 準備完了 (Ready)';
            apiStatus.classList.add('connected');
            fetchAvailableModels(); // キーがある場合はモデル一覧を取得
        } else {
            apiStatus.textContent = 'API: 未設定 (ローカルモード)';
            apiStatus.classList.remove('connected');
            modelSelectionGroup.style.display = 'none';
        }
    }

    async function fetchAvailableModels() {
        const apiKey = inputs.geminiApiKey.value.trim();
        if (!apiKey) return;

        try {
            modelSelectionGroup.style.display = 'block';
            modelSelect.innerHTML = '<option value="">利用可能なモデルを検索中...</option>';
            
            const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${apiKey}`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error?.message || 'モデル取得失敗');
            }

            // generateContent をサポートしているモデルのみを抽出
            const availableModels = data.models.filter(m => 
                m.supportedGenerationMethods && m.supportedGenerationMethods.includes('generateContent')
            );

            if (availableModels.length === 0) {
                modelSelect.innerHTML = '<option value="">利用可能なモデルがありません</option>';
                return;
            }

            modelSelect.innerHTML = '';
            // flashモデルを優先的に選択するためのフラグ
            let hasFlash = false;

            availableModels.forEach(model => {
                const option = document.createElement('option');
                // model.name は "models/gemini-1.5-flash" のような形式
                option.value = model.name;
                option.textContent = model.displayName || model.name.replace('models/', '');
                
                // デフォルト選択ロジック (flashを優先)
                if (model.name.includes('flash') && !hasFlash) {
                    option.selected = true;
                    hasFlash = true;
                }
                
                modelSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Model fetch error:', error);
            modelSelect.innerHTML = `<option value="">モデル取得エラー: ${error.message}</option>`;
        }
    }

    // --- Preview Generation Logic ---
    function generatePreview() {
        const title = inputs.songTitle.value.trim();
        const catchphrase = inputs.catchphrase.value.trim();
        const conceptJa = inputs.conceptJa.value.trim();
        const conceptEn = inputs.conceptEn.value.trim();
        const lyrics = inputs.lyrics.value.trim();
        const tags = inputs.tagsInput.value.trim();
        const footer = inputs.footerText.value.trim();

        // 1. Title Generation
        let generatedTitle = '';
        if (title) {
            generatedTitle = title;
            if (catchphrase) {
                generatedTitle += ` - 【${catchphrase}】`;
            }
            generatedTitle += ' Studio 222 Music';
        }
        outputs.outTitle.value = generatedTitle;

        // 2. Description Generation
        let descriptionParts = [];
        
        if (conceptJa) descriptionParts.push(conceptJa);
        if (conceptEn) descriptionParts.push(conceptEn);
        
        if (lyrics) {
            descriptionParts.push('【Lyrics】\n' + lyrics);
        }

        if (footer) {
            descriptionParts.push('----------------------------------------\n' + footer);
        }

        outputs.outDescription.value = descriptionParts.join('\n\n');

        // 3. Tags Generation
        if (tags) {
            const tagArray = tags.split(/[,、\n]/)
                                 .map(t => t.trim())
                                 .filter(t => t.length > 0)
                                 .map(t => t.startsWith('#') ? t : `#${t}`);
            outputs.outTags.value = tagArray.join(', ');
        } else {
            outputs.outTags.value = '';
        }
    }

    buttons.btnUpdatePreview.addEventListener('click', generatePreview);

    // Auto-update preview on input changes
    const inputElements = [
        inputs.songTitle, 
        inputs.catchphrase, 
        inputs.conceptJa, 
        inputs.conceptEn, 
        inputs.lyrics, 
        inputs.tagsInput, 
        inputs.footerText
    ];
    inputElements.forEach(el => {
        el.addEventListener('input', generatePreview);
    });

    // --- AI Generation Logic (Gemini API) ---
    async function callGeminiAPI(prompt, systemInstruction = "") {
        const apiKey = inputs.geminiApiKey.value.trim();
        if (!apiKey) {
            showToast('エラー: Gemini APIキーが設定されていません', true);
            return null;
        }

        const selectedModel = document.getElementById('modelSelect').value;
        if (!selectedModel) {
            showToast('エラー: 有効なAIモデルが選択されていません。キーを確認してください。', true);
            return null;
        }

        // 動的に取得したモデル名をURLに組み込む
        const url = `https://generativelanguage.googleapis.com/v1beta/${selectedModel}:generateContent?key=${apiKey}`;
        
        const fullPrompt = systemInstruction ? `[System Instruction: ${systemInstruction}]\n\n${prompt}` : prompt;

        const payload = {
            contents: [{ parts: [{ text: fullPrompt }] }],
            generationConfig: {
                temperature: 0.7,
                maxOutputTokens: 2000
            }
        };

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error?.message || 'API通信エラー');
            }

            return data.candidates[0].content.parts[0].text;
        } catch (error) {
            console.error(error);
            showToast(`エラー: ${error.message}`, true);
            return null;
        }
    }

    // AI Button: Generate Japanese Concept
    buttons.btnGenerateJa.addEventListener('click', async () => {
        const conceptNote = inputs.conceptJa.value.trim();
        const lyricsText = inputs.lyrics.value.trim();
        
        if (!conceptNote && !lyricsText) {
            showToast('エラー: コンセプトメモか歌詞を入力してください', true);
            return;
        }

        setLoadingState(buttons.btnGenerateJa, true);

        try {
            const prompt = `以下の楽曲情報を元に、YouTubeの概要欄に記載する「キャッチーな日本語の紹介文」を作成してください。
絶対に歌詞のストーリーをそのままなぞらないでください。
楽曲の根底にあるテーマ（孤独、別れ、受容など）を独自の視点で深く解釈し、最後に視聴者へ楽曲を強くプッシュ（おすすめ）する言葉で締めくくってください。
文字数は【200文字以内】で、テンポ良くプロフェッショナルなトーンでまとめてください。また、スマホでも読みやすいように適度に改行を入れてください。

【重要】
紹介文のテキスト「のみ」を出力してください。「承知いたしました」や「紹介文を作成しました」などの前置きや会話文は絶対に含めないでください。

【入力されたコンセプト・メモ】
${conceptNote || '(特になし)'}

【歌詞】
${lyricsText || '(特になし)'}`;

            const result = await callGeminiAPI(prompt, "あなたは優秀な音楽プロモーター・ライターです。");
            
            if (result) {
                inputs.conceptJa.value = result.trim();
                generatePreview();
                showToast('紹介文を生成しました');
            }
        } catch (e) {
            console.error(e);
            showToast('システムエラー: ' + e.message, true);
        } finally {
            setLoadingState(buttons.btnGenerateJa, false, '✨ AIで自動生成 (コンセプト+歌詞から)');
        }
    });

    // AI Button: Generate English Translation
    buttons.btnGenerateEn.addEventListener('click', async () => {
        const conceptJa = inputs.conceptJa.value.trim();
        
        if (!conceptJa) {
            showToast('エラー: 翻訳元の日本語紹介文がありません', true);
            return;
        }

        setLoadingState(buttons.btnGenerateEn, true);

        try {
            const prompt = `以下の日本語の楽曲紹介文を、海外のリスナーにも魅力が伝わるように、自然でエモーショナルな英語に翻訳してください。直訳ではなく、音楽の雰囲気に合った表現にしてください。

【重要】
翻訳された英語のテキスト「のみ」を出力してください。「はい、承知いたしました」や「翻訳文は以下の通りです」などの前置きや会話文は絶対に含めないでください。

【日本語紹介文】
${conceptJa}`;

            const result = await callGeminiAPI(prompt, "あなたはバイリンガルの優秀な音楽ライターです。");
            
            if (result) {
                inputs.conceptEn.value = result.trim();
                generatePreview();
                showToast('英語翻訳が完了しました');
            }
        } catch (e) {
            console.error(e);
            showToast('システムエラー: ' + e.message, true);
        } finally {
            setLoadingState(buttons.btnGenerateEn, false, '✨ AIで英訳');
        }
    });

    // AI Button: Generate Tags
    buttons.btnGenerateTags.addEventListener('click', async () => {
        const title = inputs.songTitle.value.trim();
        const conceptJa = inputs.conceptJa.value.trim();
        const lyricsText = inputs.lyrics.value.trim();
        
        if (!title && !conceptJa && !lyricsText) {
            showToast('エラー: タグ抽出のヒントになる情報（曲名や紹介文など）を入力してください', true);
            return;
        }

        setLoadingState(buttons.btnGenerateTags, true);

        try {
            const prompt = `以下の楽曲情報から、YouTubeの検索に最適化されたハッシュタグを生成してください。
ジャンル、雰囲気、テーマなど多角的に抽出し、日本語のタグを10個〜15個、さらに海外リスナー向けに英語のタグを10個〜15個抽出してください。

【重要】
結果はすべてのタグをカンマ区切りにしたテキスト「のみ」を出力してください。前置きや説明は絶対に含めないでください。

【曲名】${title || '(不明)'}
【紹介文】${conceptJa || '(なし)'}
【歌詞一部】${lyricsText.substring(0, 300) || '(なし)'}`;

            const result = await callGeminiAPI(prompt);
            
            if (result) {
                // Remove hashtags if API included them, logic will add them back
                let cleanedResult = result.replace(/#/g, '').replace(/\n/g, '').trim();
                inputs.tagsInput.value = cleanedResult;
                generatePreview();
                showToast('タグを抽出しました');
            }
        } catch (e) {
            console.error(e);
            showToast('システムエラー: ' + e.message, true);
        } finally {
            setLoadingState(buttons.btnGenerateTags, false, '✨ AIでタグ抽出');
        }
    });

    function setLoadingState(button, isLoading, originalText = '') {
        if (isLoading) {
            button.disabled = true;
            button.innerHTML = '<span style="display:inline-block; animation: spin 1s linear infinite;">⏳</span> 生成中...';
        } else {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    // --- Copy functionality ---
    copyButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const targetId = e.target.getAttribute('data-target');
            const textarea = document.getElementById(targetId);
            
            textarea.select();
            document.execCommand('copy');
            
            showToast('コピーしました');
        });
    });

    function showToast(message, isError = false) {
        toast.textContent = message;
        if (isError) {
            toast.classList.add('error');
        } else {
            toast.classList.remove('error');
        }
        
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // --- GAS Integration Logic ---
    buttons.btnSaveToGas.addEventListener('click', async () => {
        const url = inputs.gasUrl.value.trim();
        if (!url) {
            showToast('エラー: GAS ウェブアプリURLが設定されていません', true);
            return;
        }

        setLoadingState(buttons.btnSaveToGas, true);

        const payload = {
            parentId: currentEditingId,
            title: inputs.songTitle.value.trim(),
            catchphrase: inputs.catchphrase.value.trim(),
            conceptJa: inputs.conceptJa.value.trim(),
            conceptEn: inputs.conceptEn.value.trim(),
            tags: inputs.tagsInput.value.trim(),
            lyrics: inputs.lyrics.value.trim()
        };

        if (!payload.title && !payload.conceptJa) {
            showToast('エラー: 保存するデータがありません', true);
            setLoadingState(buttons.btnSaveToGas, false, '💾 スプレッドシートに保存');
            return;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'text/plain;charset=utf-8',
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.status === 'success') {
                showToast('スプレッドシートに保存しました！');
                if (data.newId) {
                    currentEditingId = data.newId;
                }
            } else {
                throw new Error(data.message || '保存に失敗しました');
            }
        } catch (error) {
            console.error(error);
            showToast(`エラー: ${error.message}`, true);
        } finally {
            setLoadingState(buttons.btnSaveToGas, false, '💾 スプレッドシートに保存');
        }
    });

    buttons.btnReloadHistory.addEventListener('click', () => {
        loadHistoryFromGas();
    });

    async function loadHistoryFromGas() {
        const url = inputs.gasUrl.value.trim();
        if (!url) {
            historyTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">URLが設定されていません</td></tr>';
            return;
        }

        historyTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">⏳ 読み込み中...</td></tr>';
        
        try {
            const response = await fetch(`${url}?t=${new Date().getTime()}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                renderHistoryTable(data.data);
            } else {
                throw new Error(data.message || 'データ取得失敗');
            }
        } catch (error) {
            console.error(error);
            historyTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: red;">エラー: ${error.message}</td></tr>`;
        }
    }

    function renderHistoryTable(records) {
        if (!records || records.length === 0) {
            historyTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">データがありません</td></tr>';
            return;
        }

        historyTableBody.innerHTML = '';
        records.forEach((record, index) => {
            const tr = document.createElement('tr');
            
            const dateObj = new Date(record.timestamp);
            const dateStr = !isNaN(dateObj) ? `${dateObj.getMonth()+1}/${dateObj.getDate()} ${dateObj.getHours()}:${String(dateObj.getMinutes()).padStart(2, '0')}` : '';

            tr.innerHTML = `
                <td>${escapeHtml(record.id || '')}</td>
                <td>${dateStr}<br><small style="color: var(--accent-color);">${escapeHtml(record.flag || '新規')}</small></td>
                <td style="white-space: nowrap;">${escapeHtml(record.title)}<br><small style="color: var(--text-secondary);">${escapeHtml(record.catchphrase)}</small></td>
                <td><div class="text-truncate">${escapeHtml(record.conceptJa)}</div></td>
                <td><div class="text-truncate">${escapeHtml(record.conceptEn)}</div></td>
                <td><div class="text-truncate" style="-webkit-line-clamp: 2;">${escapeHtml(record.tags)}</div></td>
                <td><button class="btn btn-secondary btn-restore" data-index="${index}" style="padding: 5px 10px; font-size: 0.8rem;">復元</button></td>
            `;
            
            tr.dataset.record = JSON.stringify(record);
            historyTableBody.appendChild(tr);
        });

        document.querySelectorAll('.btn-restore').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tr = e.target.closest('tr');
                const record = JSON.parse(tr.dataset.record);
                
                inputs.songTitle.value = record.title || '';
                inputs.catchphrase.value = record.catchphrase || '';
                inputs.conceptJa.value = record.conceptJa || '';
                inputs.conceptEn.value = record.conceptEn || '';
                inputs.tagsInput.value = record.tags || '';
                inputs.lyrics.value = record.lyrics || '';
                
                currentEditingId = record.id || null;

                generatePreview();
                
                document.querySelector('.tab-btn[data-tab="generator-tab"]').click();
                showToast(`ID: ${record.id || '不明'} を復元しました`);
                window.scrollTo(0, 0);
            });
        });
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    // Add CSS for spinner dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes spin { 100% { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);

    // Initial generate (empty)
    generatePreview();
});
