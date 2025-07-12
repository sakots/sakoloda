document.addEventListener('DOMContentLoaded', function() {
  const dropbox = document.querySelector('.dropbox');
  const inputFiles = document.getElementById('input-files');
  const uploadArea = document.querySelector('.upload-area');
  const submitBtn = document.getElementById('submit-btn');
  const filePreviews = document.getElementById('file-previews');
  
  // デバッグ情報
  console.log('要素の確認:', {
    dropbox: !!dropbox,
    inputFiles: !!inputFiles,
    uploadArea: !!uploadArea,
    submitBtn: !!submitBtn,
    filePreviews: !!filePreviews
  });
  
  // filePreviewsが存在しない場合のエラーハンドリング
  if (!filePreviews) {
    console.error('file-previews要素が見つかりません');
    return;
  }

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
    handleFileSelect();
  }

  // クリックでファイル選択
  dropbox.addEventListener('click', () => {
    inputFiles.click();
  });

  // ファイル選択時の処理
  inputFiles.addEventListener('change', handleFileSelect);

  // クリップボード機能
  const clipboardBtn = document.getElementById('clipboard-btn');
  const clipboardPreview = document.getElementById('clipboard-preview');
  const clipboardImage = document.getElementById('clipboard-image');
  const clipboardCancelBtn = document.getElementById('clipboard-cancel-btn');
  
  console.log('クリップボード要素の確認:', {
    clipboardBtn: !!clipboardBtn,
    clipboardPreview: !!clipboardPreview,
    clipboardImage: !!clipboardImage,
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
            
            // クリップボードのBlobをファイル入力フィールドに設定（ファイル名をサニタイズ）
            const sanitizeFileName = (filename) => {
              // 危険な文字を除去（正規表現の特殊文字をエスケープ）
              const dangerousChars = /[<>:"/\\|?*&;=\[\]{}()`~!@#$%^+|]/g;
              let sanitized = filename.replace(dangerousChars, '');
              
              // 制御文字を除去
              sanitized = sanitized.replace(/[\x00-\x1F\x7F]/g, '');
              
              // 連続するドットを除去
              sanitized = sanitized.replace(/\.+/g, '.');
              
              // 先頭・末尾のドットとスペースを除去
              sanitized = sanitized.trim('. ');
              
              // ファイル名が空になった場合のデフォルト名
              if (!sanitized) {
                sanitized = 'clipboard_image';
              }
              
              // ファイル名の長さ制限（拡張子を除いて最大50文字）
              const maxLength = 50;
              if (sanitized.length > maxLength) {
                const lastDotIndex = sanitized.lastIndexOf('.');
                if (lastDotIndex > 0) {
                  const name = sanitized.substring(0, lastDotIndex);
                  const ext = sanitized.substring(lastDotIndex);
                  sanitized = name.substring(0, maxLength - ext.length) + ext;
                } else {
                  sanitized = sanitized.substring(0, maxLength);
                }
              }
              
              return sanitized;
            };
            
            const timestamp = Date.now();
            const safeFileName = sanitizeFileName(`clipboard-${timestamp}.png`);
            const file = new File([blob], safeFileName, { type: type });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            inputFiles.files = dataTransfer.files;
            
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
              // ファイル入力もクリア
              inputFiles.value = '';
              updateSubmitButton();
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
    // ファイル入力もクリア
    inputFiles.value = '';
    updateSubmitButton();
  };

  // ファイル選択時の処理
  function handleFileSelect() {
    console.log('ファイル選択イベントが発生しました');
    updateSubmitButton();
    createFilePreviews();
  }

  // ファイルプレビューを作成する関数
  function createFilePreviews() {
    try {
      // 既存のプレビューをクリア
      filePreviews.innerHTML = '';
      
      const files = inputFiles.files;
      console.log('選択されたファイル数:', files.length);
      
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        createPreviewElement(file, i);
      }
    } catch (error) {
      console.error('ファイルプレビューの作成中にエラーが発生しました:', error);
    }
  }

  // 個別のプレビュー要素を作成する関数
  function createPreviewElement(file, index) {
    try {
      const previewDiv = document.createElement('div');
      previewDiv.className = 'file-preview';
      previewDiv.dataset.index = index;
      
      // キャンセルボタン
      const cancelBtn = document.createElement('button');
      cancelBtn.className = 'cancel-btn';
      cancelBtn.innerHTML = '×';
      cancelBtn.title = 'このファイルを削除';
      cancelBtn.onclick = () => removeFile(index);
      
      // ファイル名
      const fileName = document.createElement('div');
      fileName.className = 'file-name';
      fileName.textContent = file.name.length > 15 ? file.name.substring(0, 12) + '...' : file.name;
      
      // 画像プレビューまたはアイコン
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.className = 'preview-image';
        img.alt = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
        };
        reader.onerror = function(e) {
          console.error('ファイル読み込みエラー:', e);
        };
        reader.readAsDataURL(file);
        
        previewDiv.appendChild(img);
      } else {
        // 画像以外のファイルはアイコンを表示
        const icon = document.createElement('svg');
        icon.className = 'preview-icon';
        icon.innerHTML = '<use href="templates/basic/icons/cloud-upload-alt.svg#cloud-upload"></use>';
        previewDiv.appendChild(icon);
      }
      
      previewDiv.appendChild(cancelBtn);
      previewDiv.appendChild(fileName);
      filePreviews.appendChild(previewDiv);
    } catch (error) {
      console.error('プレビュー要素の作成中にエラーが発生しました:', error);
    }
  }

  // ファイルを削除する関数
  function removeFile(index) {
    try {
      const dt = new DataTransfer();
      const files = inputFiles.files;
      
      for (let i = 0; i < files.length; i++) {
        if (i !== index) {
          dt.items.add(files[i]);
        }
      }
      
      inputFiles.files = dt.files;
      updateSubmitButton();
      createFilePreviews();
    } catch (error) {
      console.error('ファイル削除中にエラーが発生しました:', error);
    }
  }

  // アップロードボタンの有効/無効を更新する関数
  function updateSubmitButton() {
    const hasFiles = inputFiles.files.length > 0;
    console.log('ファイル数:', inputFiles.files.length, 'ボタン有効:', hasFiles);
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


});