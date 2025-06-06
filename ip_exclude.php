<?php
/**
 * ip_exclude.php
 * 
 * 把请求里的 IP 加入不记录列表（visitor_bot_list），
 * 并且删除该 IP 在 visitor_log 表里的所有日志。
 * 只输出 JSON。
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config.inc.php'; // 确保能加载 Typecho 环境

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请使用 POST 请求']);
    exit;
}

$ip = trim($_POST['ip'] ?? '');
if ($ip === '') {
    echo json_encode(['success' => false, 'message' => '未提供 IP 参数']);
    exit;
}

// 验证 IP 格式（支持通配符 *）的函数
function validateIpWithWildcard($ip) {
    // 将 '*' 替换为 '1' 再验证一次，看是否符合 IPv4/IPv6
    $test = str_replace('*', '1', $ip);
    if (filter_var($test, FILTER_VALIDATE_IP)) {
        return true;
    }
    // 针对 IPv4 通配符的正则：例如 123.*.*.4
    if (preg_match('/^(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$/', $ip)) {
        foreach (explode('.', $ip) as $part) {
            if ($part !== '*' && ($part < 0 || $part > 255)) {
                return false;
            }
        }
        return true;
    }
    // 简单判断一下包含 ':' 和 '*'，当作 IPv6 通配符
    if (strpos($ip, ':') !== false && strpos($ip, '*') !== false) {
        return true;
    }
    return false;
}

if (!validateIpWithWildcard($ip)) {
    echo json_encode(['success' => false, 'message' => '请输入合法的 IP（支持 * 通配符）']);
    exit;
}

try {
    $db     = Typecho_Db::get();
    $prefix = $db->getPrefix();

    // 先往 visitor_bot_list 插入（如果已存在，不重复插入）
    $exists = $db->fetchRow($db->select()
        ->from("{$prefix}visitor_bot_list")
        ->where('ip = ?', $ip)
    );

    if (!$exists) {
        $db->query($db->insert("{$prefix}visitor_bot_list")->rows([
            'ip'   => $ip,
            'time' => date('Y-m-d H:i:s')
        ]));
    }

    // 再把 visitor_log 里所有匹配这个 IP 的行删除（精准匹配 ip 列）
    $db->query($db->delete("{$prefix}visitor_log")->where('ip = ?', $ip));

    echo json_encode(['success' => true, 'message' => "IP[$ip] 已加入不记录列表，并删除该 IP 的所有历史日志"]);
} catch (Exception $e) {
    error_log('Exclude IP 错误: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误，请稍后重试']);
}
