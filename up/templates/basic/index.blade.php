<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$title}}</title>
  <link rel="stylesheet" href="templates/{{$theme_dir}}/css/base.min.css">
  <script src="templates/{{$theme_dir}}/js/upload.js"></script>
</head>
<body>
<header>
  <div>
    <h1>{{$title}}</h1>
  </div>
</header>
<main>
  <section id="up">
    <h2>ろだ</h2>
    <p>最大アップロードサイズ: {{$up_max_mb}}MB</p>
    <p>このサイズを超えるとWebPに圧縮します: {{$up_threshold_mb_webp}}MB</p>
    <p>複数ファイルのドラッグアンドドロップに対応しています</p>
    @if ($rate_limit_enabled)
    <div class="rate-limit-info">
      <p>アップロード制限: {{$rate_limit_info['remaining']}}/{{$rate_limit_info['limit']}} 残り</p>
      @if ($rate_limit_info['remaining'] == 0)
      <p class="rate-limit-warning">⚠️ 制限に達しました。{{date('H:i', $rate_limit_info['reset_time'])}}までお待ちください。</p>
      @endif
    </div>
    @endif
    <form action="index.php?mode=upload" method="POST" enctype="multipart/form-data">
      <div class="upload-area">
        <div class="dropbox">
          <svg viewBox="0 0 640 512"><use href="templates/{{$theme_dir}}/icons/cloud-upload-alt.svg#cloud-upload"></svg>
        </div>
        <p>Drag and drop file(s) or click</p>
        <input type="file" name="upfile[]" id="input-files" accept="{{$type}}" multiple>
        <div id="file-previews" class="file-previews"></div>
      </div>
      <div class="clipboard-upload">
        <button type="button" id="clipboard-btn" class="clipboard-btn">
          📋 クリップボードから画像をアップロード
        </button>
        <div id="clipboard-preview" class="clipboard-preview" style="display: none;">
          <div class="clipboard-image-container">
            <img id="clipboard-image" alt="クリップボード画像プレビュー">
            <button type="button" id="clipboard-cancel-btn" class="clipboard-cancel-btn">×</button>
          </div>
        </div>
      </div>
      <div>
        <input type="submit" id="submit-btn" value=" うp " disabled>
        @if ($token != null)
        <input type="hidden" name="token" value="{{$token}}">
        @else
        <input type="hidden" name="token" value="">
        @endif
      </div>
    </form>
  </section>
  <section id="file_section">
    <h2>ファイル</h2>
    <div class="file-info">
      <p class="file-count">ファイル数: {{count($file_list)}}個</p>
      <p class="total-size">合計サイズ: {{$total_size_mb}}MB ({{number_format($total_size)}}バイト)</p>
    </div>
    <div>
      <div class="files">
        <div class="contain">
          <ul class="file-grid">
            @foreach ($file_list as $files)
            <li class="file-card">
              <a href="{{$path}}/{{$files['upfile']}}" target="_top" rel="noopener noreferrer" class="thumb-link">
                @if ($files['thumb_url'])
                <img src="{{$files['thumb_url']}}" alt="{{$files['upfile']}}のサムネイル" loading="lazy" decoding="async">
                @else
                <div class="thumb-fallback">
                  {{$files['upfile']}}
                </div>
                @endif
              </a>
              <p class="file-name"><a href="{{$path}}/{{$files['upfile']}}" target="_top" rel="noopener noreferrer">{{$files['upfile']}}</a></p>
            </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </section>
</main>
<footer>
  <!-- 著作権表示 -->
  <div class="copy">
    <p>
      sakoloda {{$ver}} &copy; 2025 sakots
      <a href="https://github.com/sakots/sakoloda" class="github"><svg viewBox="0 0 496 512"><use href="templates/{{$theme_dir}}/icons/github.svg#github"></svg>github</a>
    </p>
    <p>
      theme - {{$t_name}} {{$t_ver}} by sakots
    </p>
    <p>
      used function -
      <a href="https://github.com/EFTEC/BladeOne" target="_top" rel="noopener noreferrer">BladeOne</a>
    </p>
  </div>
</footer>
</body>
</html>