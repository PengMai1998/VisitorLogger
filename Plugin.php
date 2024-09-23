<?php

// if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 访客日志插件，记录来访者的信息，统计来访者情况，本插件基于XQLocation进行开发
 *
 * @package VisitorLogger
 * @author Mike
 * @version 1.2.2
 * @link https://www.maisblog.cn
 */
require_once dirname(__FILE__) . '/ipdata/src/IpLocation.php';
require_once dirname(__FILE__) . '/ipdata/src/ipdbv6.func.php';
use itbdw\Ip\IpLocation;

require_once dirname(__FILE__) . '/ip2region/src/XdbSearcher.php';
use ip2region\XdbSearcher;

class VisitorLogger_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}visitor_log` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL,
            `route` VARCHAR(255) NOT NULL,
            `country` VARCHAR(100),
            `region` VARCHAR(100),
            `city` VARCHAR(100),
            `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        try {
            $db->query($sql);
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception('创建访客日志表或IP地址记录表失败: ' . $e->getMessage());
        }

        Typecho_Plugin::factory('Widget_Archive')->header = array('VisitorLogger_Plugin', 'logVisitorInfo');

        Helper::addPanel(3, 'VisitorLogger/panel.php', '访客日志', '查看访客日志', 'administrator');

        return '插件已激活，访客日志功能已启用。';
    }

    public static function deactivate()
    {
        Helper::removePanel(3, 'VisitorLogger/panel.php');
        return '插件已禁用，访客日志功能已停用。';
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        /* IPV4数据库选择 */
        $ipv4db = new Typecho_Widget_Helper_Form_Element_Radio(
            'ipv4db', 
            array('ip2region' => _t('ip2region数据库'),'cz88'=>_t('纯真数据库')),
            'cz88','IPV4数据库选项','介绍：此项是选择IPV4类型的数据库！本插件基于XQLocation进行开发');
        $form->addInput($ipv4db);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function logVisitorInfo()
    {
        $route = explode('?', $_SERVER['REQUEST_URI'])[0];
        if (strpos($route, "admin") !== false) {
            return;
        }
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $ip = self::getIpAddress();
        $location = self::getIpLocation($ip);

        $db->query($db->insert('table.visitor_log')->rows(array(
            'ip' => $ip,
            'route' => $route,
            'country' => $location['country'] ?? 'Unknown',
            'region' => $location['region'] ?? 'Unknown',
            'city' => $location['city'] ?? 'Unknown'
        )));
    }

    public static function getVisitorLogs($page = 1, $pageSize = 10)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $offset = ($page - 1) * $pageSize;

        $select = $db->select()->from($prefix . 'visitor_log')
            ->order('time', Typecho_Db::SORT_DESC)
            ->offset($offset)
            ->limit($pageSize);

        return $db->fetchAll($select);
    }
    
    public static function getSearchVisitorLogs($page = 1, $pageSize = 10, $ip = '')
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $offset = ($page - 1) * $pageSize;
    
        $select = $db->select()->from($prefix . 'visitor_log')
            ->order('time', Typecho_Db::SORT_DESC)
            ->offset($offset)
            ->limit($pageSize);
    
        if (!empty($ip)) {
            $select->where('ip LIKE ?', '%' . $ip. '%');
        }

    
        return $db->fetchAll($select);
    }


    private static function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    private static function getIpLocation($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (Helper::options()->plugin('VisitorLogger')->ipv4db === "cz88") {
                $ipaddr = IpLocation::getLocation($ip)['area'];
                $location = array();
                $location['country'] = $ipaddr ?? 'Unknown';
            } else {
                $xdb = __DIR__ . DIRECTORY_SEPARATOR . 'ip2region/src/ip2region.xdb';
                $region = XdbSearcher::newWithFileOnly($xdb)->search($ip);
                $region = str_replace("0", "", $region);

                $subStrings = explode(' ', $region);

                $repeatedSubstring = explode('|', $region)[3];
                $newString = '';

                foreach ($subStrings as $subString) {
                    if (strpos($newString, $subString) !== false) {
                        $repeatedSubstring = $subString;
                        break;
                    }
                    $newString .= $subString . ' ';
                }

                if ($repeatedSubstring) {
                    $newString = str_replace($repeatedSubstring, '', $newString);
                    $ipaddr = str_replace("|", "", $newString);
                } else {
                    $ipaddr = str_replace("|", "", $region);
                }
                $location['country'] = $ipaddr ?? 'Unknown';
            }
        } else {
            $ipaddr = self::ipquery($ip);
            $location = $ipaddr;
        }
        return $location;
    }

    private static function ipquery($ip)
    {
        $db6 = new ipdbv6(__DIR__ . DIRECTORY_SEPARATOR . 'ipdata/src/zxipv6wry.db');
        $code = 0;
        try {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $result = $db6->query($ip);
            }
        } catch (Exception $e) {
            $result = array("disp" => $e->getMessage());
            $code = -400;
        }
        $o1 = $result["addr"][0];
        $o2 = $result["addr"][1];
        $o1 = str_replace("\"", "\\\"", $o1);
        $o2 = str_replace("\"", "\\\"", $o2);
        $local = str_replace(["无线基站网络", "公众宽带", "3GNET网络", "CMNET网络", "CTNET网络", "\t"], "", $o1);
        $locals = str_replace(["无线基站网络", "公众宽带", "3GNET网络", "CMNET网络", "CTNET网络", "中国", "\t"], "", $o2);
        return $local . $locals;
    }

    public static function cleanUpOldRecords($days) {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $expiryTime = $days * 24 * 60 * 60;
        $currentTime = time();

        $db->query($db->delete($prefix . 'visitor_log')->where('UNIX_TIMESTAMP(time) < ?', $currentTime - $expiryTime));

        $db->query($db->delete($prefix . 'ip_address')->where('UNIX_TIMESTAMP(last_update) < ?', $currentTime - $expiryTime));
    }
}

?>