<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$title}} - アクセスログ</title>
  <link rel="stylesheet" href="css/base.min.css">
  <style>
    .logs-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
    }
    .stat-number {
      font-size: 2em;
      font-weight: bold;
      color: #333;
    }
    .stat-label {
      color: #666;
      margin-top: 5px;
    }
    .logs-table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .logs-table th,
    .logs-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    .logs-table th {
      background: #f8f9fa;
      font-weight: 600;
    }
    .logs-table tr:hover {
      background: #f5f5f5;
    }
    .status-200 { color: #28a745; }
    .status-400 { color: #dc3545; }
    .status-500 { color: #dc3545; }
    .status-429 { color: #ffc107; }
    .action-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8em;
      font-weight: 500;
    }
    .action-upload { background: #d4edda; color: #155724; }
    .action-view { background: #d1ecf1; color: #0c5460; }
    .action-error { background: #f8d7da; color: #721c24; }
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 20px;
      gap: 10px;
    }
    .pagination a {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      text-decoration: none;
      color: #333;
    }
    .pagination a:hover {
      background: #f5f5f5;
    }
    .pagination .current {
      background: #007bff;
      color: white;
      border-color: #007bff;
    }
    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      padding: 10px 20px;
      background: #6c757d;
      color: white;
      text-decoration: none;
      border-radius: 4px;
    }
    .back-link:hover {
      background: #5a6268;
    }
    .filter-form {
      margin-bottom: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    .filter-form select,
    .filter-form input {
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      margin-right: 10px;
    }
    .filter-form button {
      padding: 8px 16px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .filter-form button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="logs-container">
    <a href="index.php" class="back-link">← メイン画面に戻る</a>
    
    <h1>アクセスログ</h1>
    
    <!-- 統計情報 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number">{{$stats['total_access']}}</div>
        <div class="stat-label">総アクセス数</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{$stats['today_access']}}</div>
        <div class="stat-label">今日のアクセス数</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{$stats['successful_uploads']}}</div>
        <div class="stat-label">成功アップロード数</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{$stats['errors']}}</div>
        <div class="stat-label">エラー数</div>
      </div>
    </div>
    
    <!-- フィルター -->
    <div class="filter-form">
      <form method="GET" action="index.php">
        <input type="hidden" name="mode" value="logs">
        <select name="action">
          <option value="">すべてのアクション</option>
          <option value="upload" {{$filter_action == 'upload' ? 'selected' : ''}}>アップロード</option>
          <option value="view" {{$filter_action == 'view' ? 'selected' : ''}}>表示</option>
          <option value="error" {{$filter_action == 'error' ? 'selected' : ''}}>エラー</option>
        </select>
        <select name="status">
          <option value="">すべてのステータス</option>
          <option value="200" {{$filter_status == '200' ? 'selected' : ''}}>成功 (200)</option>
          <option value="400" {{$filter_status == '400' ? 'selected' : ''}}>エラー (400)</option>
          <option value="500" {{$filter_status == '500' ? 'selected' : ''}}>サーバーエラー (500)</option>
          <option value="429" {{$filter_status == '429' ? 'selected' : ''}}>レート制限 (429)</option>
        </select>
        <input type="text" name="ip" placeholder="IPアドレス" value="{{$filter_ip}}">
        <button type="submit">フィルター</button>
      </form>
    </div>
    
    <!-- ログテーブル -->
    <table class="logs-table">
      <thead>
        <tr>
          <th>日時</th>
          <th>アクション</th>
          <th>IPアドレス</th>
          <th>ステータス</th>
          <th>ファイルサイズ</th>
          <th>ファイル名</th>
          <th>エラーメッセージ</th>
        </tr>
      </thead>
      <tbody>
        @foreach($logs as $log)
        <tr>
          <td>{{$log['created']}}</td>
          <td>
            <span class="action-badge action-{{$log['action']}}">
              {{$log['action']}}
            </span>
          </td>
          <td>{{$log['user_ip']}}</td>
          <td class="status-{{$log['status_code']}}">
            {{$log['status_code']}}
          </td>
          <td>
            @if($log['file_size'] > 0)
              {{number_format($log['file_size'] / 1024, 1)}} KB
            @else
              -
            @endif
          </td>
          <td>{{$log['file_name'] ?: '-'}}</td>
          <td>{{$log['error_message'] ?: '-'}}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    
    <!-- ページネーション -->
    @if($total_pages > 1)
    <div class="pagination">
      @if($current_page > 1)
        <a href="?mode=logs&page={{$current_page-1}}&action={{$filter_action}}&status={{$filter_status}}&ip={{$filter_ip}}">前へ</a>
      @endif
      
      @for($i = max(1, $current_page-2); $i <= min($total_pages, $current_page+2); $i++)
        @if($i == $current_page)
          <span class="current">{{$i}}</span>
        @else
          <a href="?mode=logs&page={{$i}}&action={{$filter_action}}&status={{$filter_status}}&ip={{$filter_ip}}">{{$i}}</a>
        @endif
      @endfor
      
      @if($current_page < $total_pages)
        <a href="?mode=logs&page={{$current_page+1}}&action={{$filter_action}}&status={{$filter_status}}&ip={{$filter_ip}}">次へ</a>
      @endif
    </div>
    @endif
  </div>
</body>
</html> 