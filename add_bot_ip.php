<?php
/**
 * 添加IP到黑名单
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
    // 获取并验证IP地址
    $ip = trim($_POST['ip'] ?? '');
    
    if (empty($ip)) {
        echo json_encode(['success' => false, 'message' => '请输入IP地址']);
        exit;
    }
    
    // 验证IP格式（支持通配符）
    if (!validateIpWithWildcard($ip)) {
        echo json_encode(['success' => false, 'message' => '请输入合法的IP地址（支持*通配符）']);
        exit;
    }
    
    // 获取数据库连接
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
    
    // 检查是否已存在
    $exists = $db->fetchRow($db->select()
        ->from("{$prefix}visitor_bot_list")
        ->where('ip = ?', $ip));
    
    if ($exists) {
        echo json_encode(['success' => false, 'message' => '该IP规则已存在']);
        exit;
    }
    
    // 插入新记录
    $result = $db->query($db->insert("{$prefix}visitor_bot_list")
        ->rows([
            'ip' => $ip,
            'time' => date('Y-m-d H:i:s')
        ]));
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'IP规则添加成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '添加失败，请稍后重试']);
    }
    
} catch (Exception $e) {
    // 记录错误日志
    error_log('Add bot IP error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误，请稍后重试']);
}

/**
 * 验证IP地址（支持通配符）
 * @param string $ip
 * @return bool
 */
function validateIpWithWildcard($ip) {
    // 如果包含通配符，先替换为有效IP进行验证格式
    $testIp = str_replace('*', '1', $ip);
    
    // 检查是否是有效的IPv4或IPv6格式
    if (filter_var($testIp, FILTER_VALIDATE_IP)) {
        return true;
    }
    
    // 额外检查一些常见的通配符模式
    if (preg_match('/^(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$/', $ip)) {
        // IPv4 with wildcards
        $parts = explode('.', $ip);
        foreach ($parts as $part) {
            if ($part !== '*' && ($part < 0 || $part > 255)) {
                return false;
            }
        }
        return true;
    }
    
    // 简单的IPv6通配符检查
    if (strpos($ip, ':') !== false && strpos($ip, '*') !== false) {
        return true;
    }
    
    return false;
}
?>