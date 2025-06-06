<?php
/**
 * 获取IP黑名单列表
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 引入 Typecho 配置
require_once('../../../config.inc.php');

// 这里可以添加权限验证
// if (!isset($_SESSION['user']) || !$_SESSION['user']['admin']) {
//     echo json_encode(['success' => false, 'message' => '权限不足']);
//     exit;
// }

try {
    // 获取数据库连接
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
    
    // 获取分页参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50))); // 限制每页最多100条
    $offset = ($page - 1) * $limit;
    
    // 获取搜索参数
    $search = trim($_GET['search'] ?? '');
    
    // 构建查询
    $select = $db->select()->from("{$prefix}visitor_bot_list");
    
    if (!empty($search)) {
        $select->where('ip LIKE ?', '%' . $search . '%');
    }
    
    // 获取总数
    $countSelect = $db->select('COUNT(*) as total')->from("{$prefix}visitor_bot_list");
    if (!empty($search)) {
        $countSelect->where('ip LIKE ?', '%' . $search . '%');
    }
    $totalCount = $db->fetchRow($countSelect)['total'];
    
    // 获取数据
    $rows = $db->fetchAll($select
        ->order('time', Typecho_Db::SORT_DESC)
        ->limit($limit)
        ->offset($offset)
    );
    
    // 格式化数据
    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'id' => intval($row['id']),
            'ip' => htmlspecialchars($row['ip']),
            'time' => htmlspecialchars($row['time'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($totalCount),
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    error_log('List bot IP error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '获取数据失败，请稍后重试']);
}
?>