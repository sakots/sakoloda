<?php
//--------------------------------------------------
//  sakoloda v0.1.0
//  by sakots https://sakots.red/
//--------------------------------------------------

//スクリプトのバージョン
define('UP_VER','v0.0.0'); //lot.250712.0

//設定の読み込み
require_once (__DIR__.'/config.php');
require_once (__DIR__.'/templates/'.THEME_DIR.'/theme.ini.php');

//タイムゾーン設定
date_default_timezone_set(DEFAULT_TIMEZONE);

//phpのバージョンが古い場合動作させない
if (($php_ver = phpversion()) < "7.4.0") {
  die("PHP version 7.4.0 or higher is required for this program to work. <br>\n(Current PHP version:{$php_ver})");
}
//コンフィグのバージョンが古くて互換性がない場合動作させない
if (CONF_VER < 1 || !defined('CONF_VER')) {
  die("コンフィグファイルに互換性がないようです。再設定をお願いします。<br>\n The configuration file is incompatible. Please reconfigure it.");
}
//管理パスが初期値(admin_pass)の場合は動作させない
if ($admin_pass === 'admin_pass') {
  die("管理パスが初期設定値のままです！危険なので動かせません。<br>\n The admin pass is still at its default value! This program can't run it until you fix it.");
}

//BladeOne v4.18
include (__DIR__.'/BladeOne/lib/BladeOne.php');
use eftec\bladeone\BladeOne;

$views = __DIR__.'/templates/'.THEME_DIR; // テンプレートフォルダ
$cache = __DIR__.'/cache'; // キャッシュフォルダ
$blade = new BladeOne($views,$cache,BladeOne::MODE_AUTO); // MODE_DEBUGだと開発モード MODE_AUTOが速い。
$blade->pipeEnable = true; // パイプのフィルターを使えるようにする

$dat = array(); // bladeに格納する変数

//絶対パス取得
$up_path = realpath("./").UP_DIR.'/';
$temp_path = realpath("./").TEMP_DIR.'/';

$dat['path'] = UP_DIR;
$dat['ver'] = UP_VER;
$dat['title'] = UP_TITLE;
$dat['theme_dir'] = THEME_DIR;

define('UP_MAX_SIZE', UP_MAX_MB*1024*1024);
$dat['up_max_size'] = UP_MAX_SIZE;

$dat['type'] = ACCEPT_FILETYPE;

$dat['up_max_mb'] = UP_MAX_MB;

$dat['t_name'] = THEME_NAME;
$dat['t_ver'] = THEME_VER;

$dat['up_threshold_mb_webp'] = UP_THRESHOLD_MB_WEBP;
$dat['webp_quality'] = WEBP_QUALITY;

//データベース接続PDO
define('DB_PDO', 'sqlite:'.DB_NAME.'.db');
define('DB_TIMEOUT', 5000); // タイムアウト時間（ミリ秒）
define('DB_RETRY_COUNT', 3); // リトライ回数
define('DB_RETRY_DELAY', 100000); // リトライ間隔（マイクロ秒）

//初期設定
init();
del_temp();

// キャッシュディレクトリの作成
if (!is_dir($cache)) {
  mkdir($cache, PERMISSION_FOR_DIR);
  chmod($cache, PERMISSION_FOR_DIR);
}

$req_method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"]: "";
//INPUT_SERVER が動作しないサーバがあるので$_SERVERを使う。

/*----------- mode -------------*/

//INPUT_POSTから変数を取得
$mode = filter_input(INPUT_POST, 'mode');
$mode = $mode ? $mode : filter_input(INPUT_GET, 'mode');

switch($mode) {
  case 'upload':
    return upload();
  case 'del':
    return del();
  default:
    return def();
}
exit;

/* ----------- main ------------- */

//初期作業
function init() {
  if(!is_writable(realpath("./"))) error("カレントディレクトリに書けません<br>");
  $err='';
  try {
    if (!is_file(DB_NAME.'.db')) {
      // はじめての実行なら、テーブルを作成
      $db = new PDO(DB_PDO);
      $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $sql = "CREATE TABLE uplog (id integer primary key autoincrement, created timestamp, name VARCHAR(1000), sub VARCHAR(1000), com VARCHAR(10000), host TEXT, pwd TEXT, upfile TEXT, age INT, invz VARCHAR(1) )";
      $db = $db->query($sql);
      $db = null; //db切断
    }
  } catch (PDOException $e) {
    // セキュリティ: エラーメッセージを一般化
    error_log("DB接続エラー: " . $e->getMessage());
    echo "データベースエラーが発生しました。";
  }
  if (!is_dir(UP_DIR)) {
    mkdir(UP_DIR, PERMISSION_FOR_DIR);
    chmod(UP_DIR, PERMISSION_FOR_DIR);
  }
  if(!is_dir(UP_DIR)) $err.= UP_DIR."がありません<br>";
  if(!is_writable(UP_DIR)) $err.= UP_DIR."を書けません<br>";
  if(!is_readable(UP_DIR)) $err.= UP_DIR."を読めません<br>";

  if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, PERMISSION_FOR_DIR);
    chmod(TEMP_DIR, PERMISSION_FOR_DIR);
  }
  if(!is_dir(TEMP_DIR)) $err.= TEMP_DIR."がありません<br>";
  if(!is_writable(TEMP_DIR)) $err.= TEMP_DIR."を書けません<br>";
  if(!is_readable(TEMP_DIR)) $err.= TEMP_DIR."を読めません<br>";
  if($err) error($err);
}

//ユーザーip
function get_uip() {
  if ($user_ip = getenv("HTTP_CLIENT_IP")) {
    return $user_ip;
  } elseif ($user_ip = getenv("HTTP_X_FORWARDED_FOR")) {
    return $user_ip;
  } elseif ($user_ip = getenv("REMOTE_ADDR")) {
    return $user_ip;
  } else {
    return $user_ip;
  }
}
//csrfトークンを作成
function get_csrf_token() {
  if(!isset($_SESSION)) {
    ini_set('session.use_strict_mode', 1);
    session_start();
    header('Expires:');
    header('Cache-Control:');
    header('Pragma:');
  }
  return hash('sha256', session_id(), false);
}
//csrfトークンをチェック
function check_csrf_token() {
  session_start();
  $token=filter_input(INPUT_POST,'token');
  $session_token=isset($_SESSION['token']) ? $_SESSION['token'] : '';
  if(!$session_token||$token!==$session_token){
    error('無効なアクセスです');
  }
}

// データベース接続を取得する関数
function get_db_connection() {
  try {
    $db = new PDO(DB_PDO);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, DB_TIMEOUT);
    return $db;
  } catch (PDOException $e) {
    throw $e;
  }
}

// データベース操作を実行する関数
function execute_db_operation($operation) {
  $retry_count = 0;
  $last_error = null;

  while ($retry_count < DB_RETRY_COUNT) {
    try {
      $db = get_db_connection();
      $result = $operation($db);
      $db = null; // 明示的に接続を閉じる
      return $result;
    } catch (PDOException $e) {
      $last_error = $e;
      if (strpos($e->getMessage(), 'database is locked') !== false) {
        $retry_count++;
        if ($retry_count < DB_RETRY_COUNT) {
          usleep(DB_RETRY_DELAY); // 少し待機してからリトライ
          continue;
        }
      }
      throw $e; // ロック以外のエラー、またはリトライ上限に達した場合は例外を投げる
    }
  }
  throw $last_error;
}

//アップロードしてデータベースへ保存する
function upload() {
  global $req_method;
  global $admin_pass, $up_path;

  //CSRFトークンをチェック
  if(CHECK_CSRF_TOKEN){
    check_csrf_token();
  }
  $upfile  = '';
  $invz = '0';

  if($req_method !== "POST") {error('投稿形式が不正です。'); }

  $user_ip = get_uip();
  
  // セキュリティ: セッションIDの再生成
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }

  // レート制限チェック
  if (!check_rate_limit($user_ip)) {
    error('アップロード制限に達しました。1分間お待ちください。');
    exit;
  }

  //アップロード処理
  $dest = '';
  $ok_message = '';
  $ng_message = '';
  if(count($_FILES['upfile']['name']) < 1) {
    error('ファイルがないです。');
    exit;
  }
  for ($i = 0; $i < count($_FILES['upfile']['name']); $i++) {
    $origin_file = isset($_FILES['upfile']['name'][$i]) ? basename($_FILES['upfile']['name'][$i]) : "";
    $tmp_file = isset($_FILES['upfile']['tmp_name'][$i]) ? $_FILES['upfile']['tmp_name'][$i] : "";
    $ok_num = 0;
    
    // セキュリティ: ファイルアップロードの基本チェック
    if (!is_uploaded_file($tmp_file)) {
      $ng_message .= $origin_file.'(不正なファイルです。), ';
      continue;
    }
    
    // セキュリティ: ファイルサイズの基本チェック
    if ($_FILES['upfile']['size'][$i] <= 0) {
      $ng_message .= $origin_file.'(空のファイルです。), ';
      continue;
    }
    
    // まずファイルを一時的に保存
    $extension = pathinfo($origin_file, PATHINFO_EXTENSION);
    if (empty($extension) || $extension === 'blob') {
      // クリップボードからのBlobオブジェクトの場合の処理
      if (!empty($tmp_file) && is_file($tmp_file)) {
        // MIMEタイプから拡張子を判定
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_file);
        finfo_close($finfo);
      } else {
        // クリップボードからのBlobの場合、デフォルトでPNGとして扱う
        $mime_type = 'image/png';
      }
      
      switch ($mime_type) {
        case 'image/jpeg':
          $extension = 'jpg';
          break;
        case 'image/png':
          $extension = 'png';
          break;
        case 'image/gif':
          $extension = 'gif';
          break;
        case 'image/webp':
          $extension = 'webp';
          break;
        default:
          $extension = 'png'; // デフォルト
          break;
      }
    }
    
    $upfile = date("Ymd_His").mt_rand(1000,9999).'.'.$extension;
    $dest = UP_DIR.'/'.$upfile;
    move_uploaded_file($tmp_file, $dest);
    chmod($dest, PERMISSION_FOR_DEST);
    if(!is_file($dest)) {
      $ng_message .= $origin_file.'(正常にコピーできませんでした。), ';
      continue;
    }
    
    // WebP圧縮処理（ファイルサイズチェックの前）
    $file_size_mb = $_FILES['upfile']['size'][$i] / (1024 * 1024);
    if ($file_size_mb > UP_THRESHOLD_MB_WEBP && in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
      $webp_result = convert_to_webp($dest, $extension);
      if ($webp_result) {
        // 元ファイルを削除してWebPファイルに置き換え
        unlink($dest);
        $upfile = $webp_result;
        $dest = UP_DIR.'/'.$upfile;
      }
    }
    
    // 圧縮後のファイルサイズでチェック
    $final_size = filesize($dest);
    if($final_size < UP_MAX_SIZE) {
      // セキュリティ: より厳密な拡張子チェック
      if(preg_match('/\A('.ACCEPT_FILE_EXT.')\z/i', $extension) && validate_file_type($dest, $extension)) {
        try {
          execute_db_operation(function($db) use ($user_ip, $upfile, $invz) {
            $stmt = $db->prepare("INSERT INTO uplog (created, host, upfile, invz) VALUES (datetime('now', 'localtime'), :host, :upfile, :invz)");
            $stmt->bindParam(':host', $user_ip, PDO::PARAM_STR);
            $stmt->bindParam(':upfile', $upfile, PDO::PARAM_STR);
            $stmt->bindParam(':invz', $invz, PDO::PARAM_STR);
            return $stmt->execute();
          });
          $ok_num++;
        } catch (PDOException $e) {
          // セキュリティ: エラーメッセージを一般化
          error_log("DB接続エラー: " . $e->getMessage());
          echo "データベースエラーが発生しました。";
        }
      } else {
        $ng_message .= $origin_file.'(規定外の拡張子なので削除), ';
        unlink($dest);
      }
    } else {
      $ng_message .= $origin_file.'(設定されたファイルサイズをオーバー), ';
    }
  }
  //ログ行数オーバー処理
  try {
    $th_count = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog");
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });
    
    if($th_count > LOG_MAX) {
      log_del();
    }
  } catch (PDOException $e) {
    // セキュリティ: エラーメッセージを一般化
    error_log("DB接続エラー: " . $e->getMessage());
    echo "データベースエラーが発生しました。";
  }
  result($ok_num,$ng_message);
}

//削除
function del() {

}

//通常表示モード
function def() {
  global $dat,$blade;

  //csrfトークンをセット
  $dat['token']='';
  if(CHECK_CSRF_TOKEN){
    $token = get_csrf_token();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  // ユーザーIPを取得
  $user_ip = get_uip();

  //ファイル数カウント
  try {
    $th_count = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog");
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });

    //ファイル数が圧倒的に多いときは通常表示の時にも消す
    if($th_count > LOG_MAX) {
      log_del();
    }

    //ファイル一覧を取得
    $file_list = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT * FROM uplog WHERE invz = :invz ORDER BY id DESC");
      $invz = '0';
      $stmt->bindParam(':invz', $invz, PDO::PARAM_STR);
      $stmt->execute();

      $files = array();
      while ($row = $stmt->fetch()) {
        $files[] = $row;
      }
      return $files;
    });

    $dat['file_list'] = $file_list;
    
    // 合計サイズを計算
    $total_size = 0;
    foreach ($file_list as $file) {
      $file_path = UP_DIR.'/'.$file['upfile'];
      if (is_file($file_path)) {
        $total_size += filesize($file_path);
      }
    }
    $dat['total_size'] = $total_size;
    $dat['total_size_mb'] = round($total_size / (1024 * 1024), 2);
    
    // レート制限情報を取得
    $rate_limit_info = get_rate_limit_info($user_ip);
    $dat['rate_limit_info'] = $rate_limit_info;
    $dat['rate_limit_enabled'] = defined('ENABLE_RATE_LIMIT') && ENABLE_RATE_LIMIT == '1';
    
    echo $blade->run(MAIN_FILE,$dat);
  } catch (PDOException $e) {
    // セキュリティ: エラーメッセージを一般化
    error_log("DB接続エラー: " . $e->getMessage());
    echo "データベースエラーが発生しました。";
  }
}

/* ---------- 細かい関数 ---------- */

/* テンポラリ内のゴミ除去 */
function del_temp() {
  $handle = opendir(TEMP_DIR);
  while ($file = readdir($handle)) {
    if(!is_dir($file)) {
      $lapse = time() - filemtime(TEMP_DIR.'/'.$file);
      if($lapse > (24*3600)){
        unlink(TEMP_DIR.'/'.$file);
      }
    }
  }
  closedir($handle);
}

//ログの行数が最大値を超えていたら削除
function log_del() {
  try {
    execute_db_operation(function($db) {
      // 最も古いレコードのIDを取得
      $stmt = $db->prepare("SELECT id FROM uplog ORDER BY id LIMIT 1");
      $stmt->execute();
      $result = $stmt->fetch();

      if ($result) {
        $dt_id = (int)$result["id"];
        
        // 該当IDのレコード数をカウント
        $stmt = $db->prepare("SELECT COUNT(*) as cnti FROM uplog WHERE id = :id");
        $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
        $stmt->execute();
        $count_result = $stmt->fetch();
        $log_count = $count_result["cnti"];
        
        // レコードが存在する場合のみ削除
        if($log_count !== 0) {
          $stmt = $db->prepare("DELETE FROM uplog WHERE id = :id");
          $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
          $stmt->execute();
        }
      }
    });
  } catch (PDOException $e) {
    // セキュリティ: エラーメッセージを一般化
    error_log("DB接続エラー: " . $e->getMessage());
    echo "データベースエラーが発生しました。";
  }
}

//文字コード変換
function char_convert($str) {
    mb_language(LANG);
    return mb_convert_encoding($str, "UTF-8", "auto");
}

// セキュリティ: ファイルタイプ検証関数
function validate_file_type($file_path, $extension) {
  // ファイルの実際のMIMEタイプを取得
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $actual_mime = finfo_file($finfo, $file_path);
  finfo_close($finfo);
  
  // 許可されたMIMEタイプのリスト
  $allowed_mimes = [
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp']
  ];
  
  // 拡張子に対応するMIMEタイプをチェック
  if (isset($allowed_mimes[$extension])) {
    return in_array($actual_mime, $allowed_mimes[$extension]);
  }
  
  return false;
}

// レート制限機能
function check_rate_limit($user_ip) {
  if (!defined('ENABLE_RATE_LIMIT') || ENABLE_RATE_LIMIT != '1') {
      return true; // レート制限が無効な場合は常に許可
  }
  
  try {
    $current_time = time();
    $time_window = defined('RATE_LIMIT_TIME_WINDOW') ? RATE_LIMIT_TIME_WINDOW : 60;
    $max_uploads = defined('RATE_LIMIT_MAX_UPLOADS') ? RATE_LIMIT_MAX_UPLOADS : 5;
    
    // 過去の時間枠内のアップロード回数を取得
    $upload_count = execute_db_operation(function($db) use ($user_ip, $current_time, $time_window) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog WHERE host = :host AND created >= datetime(:current_time - :time_window, 'unixepoch')");
      $stmt->bindParam(':host', $user_ip, PDO::PARAM_STR);
      $stmt->bindParam(':current_time', $current_time, PDO::PARAM_INT);
      $stmt->bindParam(':time_window', $time_window, PDO::PARAM_INT);
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });
    
    return $upload_count < $max_uploads;
  } catch (Exception $e) {
    error_log("レート制限チェックエラー: " . $e->getMessage());
    return true; // エラーの場合は許可（安全側）
  }
}

function get_rate_limit_info($user_ip) {
  if (!defined('ENABLE_RATE_LIMIT') || ENABLE_RATE_LIMIT != '1') {
    return ['remaining' => 999, 'reset_time' => 0];
  }
  
  try {
    $current_time = time();
    $time_window = defined('RATE_LIMIT_TIME_WINDOW') ? RATE_LIMIT_TIME_WINDOW : 60;
    $max_uploads = defined('RATE_LIMIT_MAX_UPLOADS') ? RATE_LIMIT_MAX_UPLOADS : 5;
    
    // 過去の時間枠内のアップロード回数を取得
    $upload_count = execute_db_operation(function($db) use ($user_ip, $current_time, $time_window) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog WHERE host = :host AND created >= datetime(:current_time - :time_window, 'unixepoch')");
      $stmt->bindParam(':host', $user_ip, PDO::PARAM_STR);
      $stmt->bindParam(':current_time', $current_time, PDO::PARAM_INT);
      $stmt->bindParam(':time_window', $time_window, PDO::PARAM_INT);
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });
      
    $remaining = max(0, $max_uploads - $upload_count);
    $reset_time = $current_time + $time_window;
      
    return [
      'remaining' => $remaining,
      'reset_time' => $reset_time,
      'limit' => $max_uploads,
      'used' => $upload_count
    ];
  } catch (Exception $e) {
    error_log("レート制限情報取得エラー: " . $e->getMessage());
    return ['remaining' => 999, 'reset_time' => 0];
  }
}

//WebP変換関数
function convert_to_webp($source_path, $original_extension) {
  // GDライブラリが利用可能かチェック
  if (!extension_loaded('gd')) {
    return false;
  }
    
  // 元画像を読み込み
  $image = null;
  switch (strtolower($original_extension)) {
    case 'jpg':
    case 'jpeg':
      $image = imagecreatefromjpeg($source_path);
      break;
    case 'png':
      $image = imagecreatefrompng($source_path);
      // PNGの透明度を保持
      imagepalettetotruecolor($image);
      imagealphablending($image, true);
      imagesavealpha($image, true);
      break;
    case 'gif':
      $image = imagecreatefromgif($source_path);
      break;
    default:
      return false;
  }
  
  if (!$image) {
    return false;
  }
  
  // WebPファイル名を生成
  $webp_filename = pathinfo($source_path, PATHINFO_FILENAME) . '.webp';
  $webp_path = UP_DIR.'/'.$webp_filename;
  
  // WebPとして保存
  $webp_quality = defined('WEBP_QUALITY') ? WEBP_QUALITY : 80;
  $result = imagewebp($image, $webp_path, $webp_quality);
  
  // メモリを解放
  imagedestroy($image);
  
  if ($result) {
    chmod($webp_path, PERMISSION_FOR_DEST);
    return $webp_filename;
  }
  
  return false;
}

//リザルト画面
function result($ok,$err) {
  global $blade,$dat;
  $dat['oknum'] = $ok;
  $dat['errmes'] = htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
  $dat['othermode'] = 'result';
  echo $blade->run(OTHER_FILE,$dat);
}

//OK画面
function ok($mes) {
  global $blade,$dat;
  $dat['okmes'] = $mes;
  $dat['othermode'] = 'ok';
  echo $blade->run(OTHER_FILE,$dat);
}
//エラー画面
function error($mes) {
  global $db;
  global $blade,$dat;
  $db = null; //db切断
  $dat['errmes'] = htmlspecialchars($mes, ENT_QUOTES, 'UTF-8');
  $dat['othermode'] = 'err';
  echo $blade->run(OTHER_FILE,$dat);
  exit;
}
