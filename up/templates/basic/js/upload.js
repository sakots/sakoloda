document.addEventListener('DOMContentLoaded', function() {
  const dropbox = document.querySelector('.dropbox');
  const inputFiles = document.getElementById('input-files');
  const uploadArea = document.querySelector('.upload-area');

  // ドラッグオーバー時のイベント
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  // ドラッグオーバー時のスタイル変更
  ['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
  });

  function highlight(e) {
    uploadArea.classList.add('highlight');
  }

  function unhighlight(e) {
    uploadArea.classList.remove('highlight');
  }

  // ファイルドロップ時の処理
  uploadArea.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    inputFiles.files = files;
  }

  // クリックでファイル選択
  dropbox.addEventListener('click', () => {
    inputFiles.click();
  });

  // クリップボード機能
  const clipboardBtn = document.getElementById('clipboard-btn');
  const clipboardPreview = document.getElementById('clipboard-preview');
  const clipboardImage = document.getElementById('clipboard-image');
  const clipboardUploadBtn = document.getElementById('clipboard-upload-btn');
  const clipboardCancelBtn = document.getElementById('clipboard-cancel-btn');

  // クリップボードから画像を読み取り
  clipboardBtn.addEventListener('click', async () => {
    try {
      const clipboardItems = await navigator.clipboard.read();
      for (const clipboardItem of clipboardItems) {
        for (const type of clipboardItem.types) {
          if (type.startsWith('image/')) {
            const blob = await clipboardItem.getType(type);
            const url = URL.createObjectURL(blob);
            clipboardImage.src = url;
            clipboardPreview.style.display = 'block';
            clipboardBtn.style.display = 'none';
            
            // アップロードボタンのイベントリスナーを設定
            clipboardUploadBtn.onclick = () => uploadClipboardImage(blob);
            
            // キャンセルボタンのイベントリスナーを設定
            clipboardCancelBtn.onclick = () => {
              clipboardPreview.style.display = 'none';
              clipboardBtn.style.display = 'block';
              URL.revokeObjectURL(clipboardImage.src);
            };
            return;
          }
        }
      }
      alert('クリップボードに画像が見つかりませんでした。');
    } catch (err) {
      console.error('クリップボードの読み取りに失敗しました:', err);
      alert('クリップボードの読み取りに失敗しました。ブラウザの権限を確認してください。');
    }
  });

  // キャンセルボタンのイベントリスナーを設定（初期化時）
  clipboardCancelBtn.onclick = () => {
    clipboardPreview.style.display = 'none';
    clipboardBtn.style.display = 'block';
    if (clipboardImage.src) {
      URL.revokeObjectURL(clipboardImage.src);
    }
  };

  // クリップボード画像をアップロード
  async function uploadClipboardImage(blob) {
    const formData = new FormData();
    formData.append('upfile[]', blob, 'clipboard-image.png');
    
    // 認証ワードがある場合は追加
    const authInput = document.querySelector('input[name="authword"]');
    if (authInput && authInput.value) {
      formData.append('authword', authInput.value);
    }
    
    // CSRFトークンがある場合は追加
    const tokenInput = document.querySelector('input[name="token"]');
    if (tokenInput && tokenInput.value) {
      formData.append('token', tokenInput.value);
    }

    try {
      const response = await fetch('index.php?mode=upload', {
        method: 'POST',
        body: formData
      });
      
      if (response.ok) {
        window.location.reload();
      } else {
        alert('アップロードに失敗しました。');
      }
    } catch (err) {
      console.error('アップロードエラー:', err);
      alert('アップロードに失敗しました。');
    }
  }
});