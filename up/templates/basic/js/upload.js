document.addEventListener('DOMContentLoaded', function() {
  const dropbox = document.querySelector('.dropbox');
  const inputFiles = document.getElementById('input-files');
  const uploadArea = document.querySelector('.upload-area');
  const submitBtn = document.getElementById('submit-btn');

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
    updateSubmitButton();
  }

  // クリックでファイル選択
  dropbox.addEventListener('click', () => {
    inputFiles.click();
  });

  // ファイル選択時の処理
  inputFiles.addEventListener('change', updateSubmitButton);

  // クリップボード機能
  const clipboardBtn = document.getElementById('clipboard-btn');
  const clipboardPreview = document.getElementById('clipboard-preview');
  const clipboardImage = document.getElementById('clipboard-image');
  const clipboardUploadBtn = document.getElementById('clipboard-upload-btn');
  const clipboardCancelBtn = document.getElementById('clipboard-cancel-btn');
  
  console.log('クリップボード要素の確認:', {
    clipboardBtn: !!clipboardBtn,
    clipboardPreview: !!clipboardPreview,
    clipboardImage: !!clipboardImage,
    clipboardUploadBtn: !!clipboardUploadBtn,
    clipboardCancelBtn: !!clipboardCancelBtn
  });

  // クリップボードから画像を読み取り
  clipboardBtn.addEventListener('click', async () => {
    console.log('クリップボードボタンがクリックされました');
    try {
      const clipboardItems = await navigator.clipboard.read();
      console.log('クリップボードアイテム数:', clipboardItems.length);
      for (const clipboardItem of clipboardItems) {
        console.log('クリップボードアイテムのタイプ:', clipboardItem.types);
        for (const type of clipboardItem.types) {
          if (type.startsWith('image/')) {
            console.log('画像タイプを発見:', type);
            const blob = await clipboardItem.getType(type);
            const url = URL.createObjectURL(blob);
            clipboardImage.src = url;
            clipboardPreview.style.display = 'block';
            clipboardBtn.style.display = 'none';
            
            // クリップボードのBlobをファイル入力フィールドに設定
            const file = new File([blob], `clipboard-${Date.now()}.png`, { type: type });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            inputFiles.files = dataTransfer.files;
            
            // アップロードボタンのイベントリスナーを設定
            clipboardUploadBtn.addEventListener('click', () => {
              console.log('クリップボードアップロードボタンがクリックされました');
              uploadClipboardImage(blob);
            }, { once: true });
            
            // クリップボード画像が読み込まれたらアップロードボタンを有効化
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            
            // キャンセルボタンのイベントリスナーを設定
            clipboardCancelBtn.onclick = () => {
              clipboardPreview.style.display = 'none';
              clipboardBtn.style.display = 'block';
              URL.revokeObjectURL(clipboardImage.src);
              // キャンセル時にアップロードボタンを無効化
              submitBtn.disabled = true;
              submitBtn.style.opacity = '0.6';
              submitBtn.style.cursor = 'not-allowed';
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

  // アップロードボタンの有効/無効を更新する関数
  function updateSubmitButton() {
    const hasFiles = inputFiles.files.length > 0;
    submitBtn.disabled = !hasFiles;
    
    if (hasFiles) {
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    } else {
      submitBtn.style.opacity = '0.6';
      submitBtn.style.cursor = 'not-allowed';
    }
  }

  // 初期状態でボタンを無効化
  updateSubmitButton();

  // クリップボード画像をアップロード
  async function uploadClipboardImage(blob) {
    console.log('クリップボードアップロード開始');
    
    // 通常のフォーム送信をトリガー
    const form = document.querySelector('form');
    if (form) {
      console.log('フォーム送信をトリガー');
      form.submit();
    } else {
      console.error('フォームが見つかりません');
      alert('フォームが見つかりません。');
    }
  }
});