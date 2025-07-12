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
  case 'logs':
    return show_logs();
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
      
      // アップロードログテーブル
      $sql = "CREATE TABLE uplog (id integer primary key autoincrement, created timestamp, name VARCHAR(1000), sub VARCHAR(1000), com VARCHAR(10000), host TEXT, pwd TEXT, upfile TEXT, age INT, invz VARCHAR(1) )";
      $db->query($sql);
      
      // アクセスログテーブル
      $sql = "CREATE TABLE accesslog (id integer primary key autoincrement, created timestamp, action VARCHAR(50), user_ip TEXT, user_agent TEXT, referer TEXT, request_uri TEXT, method VARCHAR(10), status_code INT, file_size BIGINT, file_name VARCHAR(255), error_message TEXT)";
      $db->query($sql);
      
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

  if($req_method !== "POST") {
    log_access('upload', 400, 0, '', '投稿形式が不正です。');
    error('投稿形式が不正です。'); 
  }

  $user_ip = get_uip();
  
  // セキュリティ: セッションIDの再生成
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }

  // レート制限チェック
  if (!check_rate_limit($user_ip)) {
    log_access('upload', 429, 0, '', 'アップロード制限に達しました。');
    error('アップロード制限に達しました。1分間お待ちください。');
    exit;
  }

  //アップロード処理
  $dest = '';
  $ok_message = '';
  $ng_message = '';
  $total_file_size = 0;
  $successful_files = array();
  $ok_num = 0; // ループ外で初期化
  
  if(count($_FILES['upfile']['name']) < 1) {
    log_access('upload', 400, 0, '', 'ファイルがないです。');
    error('ファイルがないです。');
    exit;
  }
  for ($i = 0; $i < count($_FILES['upfile']['name']); $i++) {
    $origin_file = isset($_FILES['upfile']['name'][$i]) ? sanitize_filename($_FILES['upfile']['name'][$i]) : "";
    $tmp_file = isset($_FILES['upfile']['tmp_name'][$i]) ? $_FILES['upfile']['tmp_name'][$i] : "";
    
    // セキュリティ: ファイルアップロードの基本チェック
    if (!is_uploaded_file($tmp_file)) {
      log_access('upload', 400, 0, $origin_file, '不正なファイルです。');
      $ng_message .= $origin_file.'(不正なファイルです。), ';
      continue;
    }
    
    // セキュリティ: ファイルサイズの基本チェック
    if ($_FILES['upfile']['size'][$i] <= 0) {
      log_access('upload', 400, 0, $origin_file, '空のファイルです。');
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
      log_access('upload', 500, 0, $origin_file, '正常にコピーできませんでした。');
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
          $total_file_size += $final_size;
          $successful_files[] = $upfile;
        } catch (PDOException $e) {
          // セキュリティ: エラーメッセージを一般化
          error_log("DB接続エラー: " . $e->getMessage());
          log_access('upload', 500, $final_size, $upfile, 'データベースエラーが発生しました。');
          echo "データベースエラーが発生しました。";
        }
      } else {
        log_access('upload', 400, $final_size, $origin_file, '規定外の拡張子なので削除');
        $ng_message .= $origin_file.'(規定外の拡張子なので削除), ';
        unlink($dest);
      }
    } else {
      log_access('upload', 413, $_FILES['upfile']['size'][$i], $origin_file, '設定されたファイルサイズをオーバー');
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
  
  // アップロード完了時のログ記録
  if ($ok_num > 0) {
    log_access('upload', 200, $total_file_size, implode(', ', $successful_files), '');
  }
  
  result($ok_num,$ng_message);
}

//削除
function del() {

}

// ログ表示モード
function show_logs() {
  global $blade, $dat;
  
  // フィルターパラメータを取得
  $filter_action = htmlspecialchars(filter_input(INPUT_GET, 'action') ?: '', ENT_QUOTES, 'UTF-8');
  $filter_status = htmlspecialchars(filter_input(INPUT_GET, 'status') ?: '', ENT_QUOTES, 'UTF-8');
  $filter_ip = htmlspecialchars(filter_input(INPUT_GET, 'ip') ?: '', ENT_QUOTES, 'UTF-8');
  $current_page = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1);
  $per_page = 50;
  $offset = ($current_page - 1) * $per_page;
  
  try {
    // フィルター条件を構築
    $where_conditions = array();
    $params = array();
    
    if ($filter_action) {
      $where_conditions[] = "action = :action";
      $params[':action'] = $filter_action;
    }
    
    if ($filter_status) {
      $where_conditions[] = "status_code = :status";
      $params[':status'] = (int)$filter_status;
    }
    
    if ($filter_ip) {
      $where_conditions[] = "user_ip LIKE :ip";
      $params[':ip'] = '%' . $filter_ip . '%';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
      $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // 総件数を取得
    $total_count = execute_db_operation(function($db) use ($where_clause, $params) {
      $sql = "SELECT COUNT(*) as count FROM accesslog " . $where_clause;
      $stmt = $db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();
      $result = $stmt->fetch();
      return $result['count'];
    });
    
    // ログデータを取得
    $logs = execute_db_operation(function($db) use ($where_clause, $params, $per_page, $offset) {
      $sql = "SELECT * FROM accesslog " . $where_clause . " ORDER BY created DESC LIMIT :limit OFFSET :offset";
      $stmt = $db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
      $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      
      $logs = array();
      while ($row = $stmt->fetch()) {
        $logs[] = $row;
      }
      return $logs;
    });
    
    // 統計情報を取得
    $stats = get_access_stats();
    
    // ページネーション情報
    $total_pages = ceil($total_count / $per_page);
    
    // テンプレートにデータを渡す
    $dat['logs'] = $logs;
    $dat['stats'] = $stats;
    $dat['current_page'] = $current_page;
    $dat['total_pages'] = $total_pages;
    $dat['filter_action'] = $filter_action;
    $dat['filter_status'] = $filter_status;
    $dat['filter_ip'] = $filter_ip;
    
    echo $blade->run('logs', $dat);
    
  } catch (Exception $e) {
    error_log("ログ表示エラー: " . $e->getMessage());
    error('ログの表示中にエラーが発生しました。');
  }
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
  
  // アクセスログ記録
  log_access('view', 200);

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

// ファイル名のサニタイズ強化
function sanitize_filename($filename) {
  // 文字コード変換
  $filename = char_convert($filename);
  
  // 危険な文字を除去・置換
  $dangerous_chars = [
    '<' => '', '>' => '', ':' => '', '"' => '',
    '/' => '', '\\' => '', '|' => '', '?' => '',
    '*' => '', '&' => '', ';' => '', '=' => '',
    '[' => '', ']' => '', '{' => '', '}' => '',
    '(' => '', ')' => '', '`' => '', '~' => '',
    '!' => '', '@' => '', '#' => '', '$' => '',
    '%' => '', '^' => '', '+' => '', '|' => ''
  ];
  $filename = strtr($filename, $dangerous_chars);
  
  // 制御文字を除去
  $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
  
  // パストラバーサル攻撃を防ぐ（相対パスを除去）
  $filename = basename($filename);
  
  // 連続するドットを除去（隠しファイル対策）
  $filename = preg_replace('/\.+/', '.', $filename);
  
  // 先頭・末尾のドットとスペースを除去
  $filename = trim($filename, '. ');
  
  // ファイル名が空になった場合のデフォルト名
  if (empty($filename)) {
    $filename = 'uploaded_file';
  }
  
  // ファイル名の長さ制限（拡張子を除いて最大100文字）
  $pathinfo = pathinfo($filename);
  $name = $pathinfo['filename'];
  $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
  
  // ファイル名部分を100文字に制限
  if (mb_strlen($name, 'UTF-8') > 100) {
    $name = mb_substr($name, 0, 100, 'UTF-8');
  }
  
  // 拡張子も含めて最大255文字に制限
  $fullname = $name . $ext;
  if (mb_strlen($fullname, 'UTF-8') > 255) {
    $max_name_length = 255 - mb_strlen($ext, 'UTF-8');
    $name = mb_substr($name, 0, $max_name_length, 'UTF-8');
    $fullname = $name . $ext;
  }
  
  return $fullname;
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

// アクセスログ記録関数
function log_access($action, $status_code = 200, $file_size = 0, $file_name = '', $error_message = '') {
  try {
    $user_ip = get_uip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
    
    execute_db_operation(function($db) use ($action, $user_ip, $user_agent, $referer, $request_uri, $method, $status_code, $file_size, $file_name, $error_message) {
      $stmt = $db->prepare("INSERT INTO accesslog (created, action, user_ip, user_agent, referer, request_uri, method, status_code, file_size, file_name, error_message) VALUES (datetime('now', 'localtime'), :action, :user_ip, :user_agent, :referer, :request_uri, :method, :status_code, :file_size, :file_name, :error_message)");
      $stmt->bindParam(':action', $action, PDO::PARAM_STR);
      $stmt->bindParam(':user_ip', $user_ip, PDO::PARAM_STR);
      $stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
      $stmt->bindParam(':referer', $referer, PDO::PARAM_STR);
      $stmt->bindParam(':request_uri', $request_uri, PDO::PARAM_STR);
      $stmt->bindParam(':method', $method, PDO::PARAM_STR);
      $stmt->bindParam(':status_code', $status_code, PDO::PARAM_INT);
      $stmt->bindParam(':file_size', $file_size, PDO::PARAM_INT);
      $stmt->bindParam(':file_name', $file_name, PDO::PARAM_STR);
      $stmt->bindParam(':error_message', $error_message, PDO::PARAM_STR);
      return $stmt->execute();
    });
  } catch (Exception $e) {
    // ログ記録に失敗しても処理を継続
    error_log("アクセスログ記録エラー: " . $e->getMessage());
  }
}

// アクセスログ取得関数
function get_access_logs($limit = 100, $offset = 0) {
  try {
    return execute_db_operation(function($db) use ($limit, $offset) {
      $stmt = $db->prepare("SELECT * FROM accesslog ORDER BY created DESC LIMIT :limit OFFSET :offset");
      $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      
      $logs = array();
      while ($row = $stmt->fetch()) {
        $logs[] = $row;
      }
      return $logs;
    });
  } catch (Exception $e) {
    error_log("アクセスログ取得エラー: " . $e->getMessage());
    return array();
  }
}

// アクセスログ統計取得関数
function get_access_stats() {
  try {
    return execute_db_operation(function($db) {
      $stats = array();
      
      // 総アクセス数
      $stmt = $db->prepare("SELECT COUNT(*) as total FROM accesslog");
      $stmt->execute();
      $result = $stmt->fetch();
      $stats['total_access'] = $result['total'];
      
      // 今日のアクセス数
      $stmt = $db->prepare("SELECT COUNT(*) as today FROM accesslog WHERE date(created) = date('now', 'localtime')");
      $stmt->execute();
      $result = $stmt->fetch();
      $stats['today_access'] = $result['today'];
      
      // アップロード成功数
      $stmt = $db->prepare("SELECT COUNT(*) as uploads FROM accesslog WHERE action = 'upload' AND status_code = 200");
      $stmt->execute();
      $result = $stmt->fetch();
      $stats['successful_uploads'] = $result['uploads'];
      
      // エラー数
      $stmt = $db->prepare("SELECT COUNT(*) as errors FROM accesslog WHERE status_code >= 400");
      $stmt->execute();
      $result = $stmt->fetch();
      $stats['errors'] = $result['errors'];
      
      // アクション別統計
      $stmt = $db->prepare("SELECT action, COUNT(*) as count FROM accesslog GROUP BY action");
      $stmt->execute();
      $action_stats = array();
      while ($row = $stmt->fetch()) {
        $action_stats[$row['action']] = $row['count'];
      }
      $stats['action_stats'] = $action_stats;
      
      return $stats;
    });
  } catch (Exception $e) {
    error_log("アクセス統計取得エラー: " . $e->getMessage());
    return array();
  }
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
  
  // エラーログ記録
  log_access('error', 500, 0, '', $mes);
  
  $dat['errmes'] = htmlspecialchars($mes, ENT_QUOTES, 'UTF-8');
  $dat['othermode'] = 'err';
  echo $blade->run(OTHER_FILE,$dat);
  exit;
}
