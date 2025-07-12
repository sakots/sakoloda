<?php
//--------------------------------------------------
//  sakoloda v0.1.0～
//  by sakots >> https://sakots.red/
//
//  sakoloda の設定ファイルです。
//
//--------------------------------------------------

/* ---------- 最低限設定する項目 ---------- */

//管理者パスワード
//必ず変更してください ! admin_pass のままではプログラムは動作しません !
$admin_pass = 'admin_pass';

//以上です

/*----------絶対に設定が必要な項目はここまでです。ここから下は必要に応じて。----------*/

//アップローダーのタイトル
define('UP_TITLE', 'ろだ');

//初期設定のままの場合、up.dbとなります。
//拡張子は.dbで固定です。
define('DB_NAME', 'up');

//テーマのディレクトリ名
//見た目が変わります。いまはこれしかないです。
define('THEME_DIR', 'basic');

//最大ファイル保持数
//古いファイルから順番に消えます
define('LOG_MAX', '75');

//アップロードファイル保存ディレクトリ名
define('UP_DIR', 'img');

//一時ファイル保存ディレクトリ名
define('TEMP_DIR', 'tmp');

//アップロードできるファイルの最大サイズ(MB)
define('UP_MAX_MB', '4');

//WebP圧縮の閾値(MB) - このサイズを超えるとWebPに圧縮
define('UP_THRESHOLD_MB_WEBP', '2');

//WebP圧縮の品質(0-100)
define('WEBP_QUALITY', '80');

//アップロード可能なファイルのmimetype。', 'で区切ってください。
define('ACCEPT_FILETYPE', 'image/jpeg, image/png, image/gif, image/webp');

//アップロード可能なファイルの拡張子。'|'（パイプ）で区切ってください。
define('ACCEPT_FILE_EXT', 'jpg|jpeg|png|gif|webp');

// タイムゾーン
define('DEFAULT_TIMEZONE','Asia/Tokyo');

// 言語設定
define('LANG', 'Japanese');

//ここまで

/* ------------- トラブルシューティング 問題なく動作している時は変更しない。 ------------- */

//アップロードされたファイルのパーミッション。
define('PERMISSION_FOR_DEST', 0606);//初期値 0606
//ブラウザから直接呼び出さないログファイルのパーミッション
define('PERMISSION_FOR_LOG', 0600);//初期値 0600
//アップロードされたファイルを保存するディレクトリのパーミッション
define('PERMISSION_FOR_DIR', 0707);//初期値 0707

//csrfトークンを使って不正な投稿を拒絶する する:1 しない:0
//する:1 にすると外部サイトからの不正な投稿を拒絶することができます
define('CHECK_CSRF_TOKEN', '1');

/* ------------- できれば変更してほしくないところ ------------- */
//スクリプト名
define('PHP_SELF', 'index.php');

/* ------------- コンフィグ互換性管理 ------------- */

define('CONF_VER', 1);
