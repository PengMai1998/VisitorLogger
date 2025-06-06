<?php
/**
 * 删除IP黑名单记录
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 引入 Typecho 配置
require_once('../../../config.inc.php');

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请使用POST请求']);
    exit;
}

// 这里可以添加权限验证
// if (!isset($_SESSION['user']) || !$_SESSION['user']['admin']) {
//     echo json_encode(['success' => false, 'message' => '权限不足']);
//     exit;
// }

try {
    // 获取并验证ID
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的ID']);
        exit;
    }
    
    // 获取数据库连接
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
    
    // 先检查记录是否存在
    $exists = $db->fetchRow($db->select()
        ->from("{$prefix}visitor_bot_list")
        ->where('id = ?', $id));
    
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => '记录不存在或已被删除']);
        exit;
    }
    
    // 删除记录
    $deleted = $db->query($db->delete("{$prefix}visitor_bot_list")
        ->where('id = ?', $id));
    
    if ($deleted) {
        echo json_encode([
            'success' => true, 
            'message' => 'IP规则删除成功',
            'deleted_ip' => $exists['ip']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '删除失败，请稍后重试']);
    }
    
} catch (Exception $e) {
    // 记录错误日志
    error_log('Delete bot IP error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误，请稍后重试']);
}
?>