<?php

class SouGou {

    private $root_url   = ''; // 起爬地址
    private $page_url   = ''; // 词库页面
    private $down_url   = ''; // 下载地址
    private $save_path  = ''; // 保存路径
    private $log_path   = ''; // 日志路径

    private $user_agent      = 0;       // 是否使用代理
    private $agent_list      = array(); // 代理服务器列表
    private $user_agent_list = array(); // 浏览器用户代理列表

    private $large_class   = array(); // 保存大分类信息
    private $little_class  = array(); // 保存小分类信息
    private $little_class2 = array(); // 保存更小分类信息

    private $db = ''; // 数据库连接句柄

    private $update_time = 0;  // 当前词库页面爬取到的最后更新时间


    public function __construct ($root_url, $page_url, $down_url, $save_path, $log_path, $use_agent,
        $host, $username, $password, $dbname, $dbport, $tbname) {
        $this->root_url  = $root_url;
        $this->page_url  = $page_url;
        $this->down_url  = $down_url;
        $this->save_path = $save_path;
        $this->log_path  = $log_path;
        $this->use_agent = $use_agent;

        // 获取代理服务器列表
        if ($this->use_agent) {
            $this->agent_list = $this->fetch_agent_list();
        }

        // 获取浏览器用户代理列表
        $this->user_agent_list = $this->fetch_user_agent();

        // 初始化数据库
        $this->db = new DataBase($host, $username, $password, $dbname, $dbport, $tbname);

        // -------------------------------
        // For Test

        // 测试 城市信息 > 天津 下面的分类中，子分类数为0的页能否成功获取
        // $this->fetch_little_class2(324, $this->save_path.'/167-324', 1);
        // exit();

        // 测试 自然科学下子分类数为0的页能否成功获取
        // $this->fetch_little_class(2, $this->save_path.'/1');
        // exit();

        // 测试 城市信息 > 国外地名 是否可以成功获取
        // $this->fetch_little_class2(366, $this->save_path.'/167-366', 0);
        // exit();

        // 测试 自然科学 > 生物 的获取是否正常
        // $this->fetch_little_class2(14, $this->save_path.'/1-14', 1);
        // exit();

        // 测试 自然科学 > 化学 的获取是否正常
        // $this->fetch_little_class2(13, $this->save_path.'/1-13', 0);
        // exit();

        // 测试 自然科学 大类是否可以正常获取
        // $this->fetch_little_class(1, $this->save_path.'/1');
        // exit();

        // 测试 社会科学 大类是否可以正常获取
        // $this->fetch_little_class(76, $this->save_path.'/76');
        // exit();

        // 手动开爬指定大类
        // $this->fetch_little_class(403, $this->save_path.'/403');
        // exit();

        // 手动开爬指定小类
        // $this->fetch_little_class2(461, $this->save_path.'/436-461', 1);
        // exit();

        // $this->fetch_last_update_time(11258);
        // exit();

        // -------------------------------

        // 爬取大分类
        $this->fetch_large_class();
        $this->logger('Finished fetch large class');

        foreach ($this->large_class as $id => $text) {
            $path = $this->save_path.'/'.$id;
            // 爬取小分类
            $this->fetch_little_class($id, $path);
            $this->logger("Finished fetch little class {$id}");
        }
    }


    /**
     * 封装simple_html_dom函数
     */
    private function get_html ($url) {
        $curl = curl_init();

        // 使用浏览器用户代理
        $user_agent_list = $this->user_agent_list;
        $rand = array_rand($user_agent_list);
        $user_agent = $user_agent_list[$rand];

        // 使用代理服务器
        if ($this->use_agent) {
            $rand = array_rand($this->agent_list);
            $ip = $this->agent_list[$rand];
            curl_setopt($curl, CURLOPT_PROXY, $ip); // 代理服务器地址，格式：8.8.8.8:8080
        }

        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent); // 模拟用户使用的浏览器
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_HTTPGET, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_TIMEOUT, 1200); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回

        $content = curl_exec ($curl);
        curl_close ($curl);

        // 调用simple_html_dom
        $html= str_get_html($content);

        return $html;
    }


    /**
     * 获取大分类
     */
    private function fetch_large_class () {
        $html = $this->get_html($this->root_url);
        $sidebar = $html->find('div[id=sidebar2]', 0);
        $classes = $sidebar->find('ul.dictcatelist li');
        foreach ($classes as $item) {
            $id = $item->find('a', 0)->href;
            $id = explode('?c=', $id);
            $id = $id[1];

            $text = $item->plaintext;
            if ($id == 132) {  // 该词条乱码，手动修正
                $text = '医学医药';
            }

            // 按ID与名称一一对应存入数组备用
            $this->large_class[$id] = $text;

            // 入库存储
            $this->db->insert(0, $id, 0, $text, '', 0, time());
        }
    }


    /**
     * 获取小分类
     * @param $id   词库ID
     * @param $path 文件存储路径
     */
    private function fetch_little_class ($id, $path) {
        $pid = $id;

        // 清空小分类数组
        $this->little_class = array();

        $url = "{$this->root_url}?c={$id}";
        $html = $this->get_html($url);
        $sidebox = $html->find('div.sidebox5', 0);
        $tds = $sidebox->find('td');

        foreach ($tds as $td) {
            $link = $td->find('a', 0);

            $id = $link->href;
            $id = explode('?c=', $id);
            $id = $id[1];

            $name = $link->innertext;
            $name = explode('(', $name);
            $name = $name[0];
            if ($tmp_name = @iconv('GB2312', 'UTF-8//IGNORE//TRANSLIT', $name)) {
                $name = $tmp_name;
            }

            // 是否存在子分类
            $has_child = 0;
            if (@$td->find('img')) {
                $has_child = 1;
            }

            // 存储小分类
            $this->little_class[$id] = array($name, $has_child);

            // 入库存储
            $this->db->insert(0, $id, $pid, $name, '', 0, time());
        }

        foreach ($this->little_class as $id => $item) {
            $new_path = $path.'-'.$id;
            $this->fetch_little_class2($id, $new_path, $item[1]);
            $this->logger("Finished fetch more little class {$id}");
        }
    }


    /**
     * 获取更小的分类
     * @param $id        词库ID
     * @param $path      文件存储路径
     * @param $has_child 是否存在子分类
     */
    private function fetch_little_class2 ($id, $path, $has_child) {
        $pid = $id;

        // 不存在子分类的第一种情况，例如 自然科学 > 化学
        if (!$has_child) {
            $page_num = $this->get_page_num($pid);
            // 逐页获取
            for ($i = 1; $i <= $page_num; $i++) {
                $this->fetch($pid, $path, 1, $i);
            }
            return ;
        }

        // 清空更小分类数组
        $this->little_class2 = array();

        $url = "{$this->root_url}?c={$id}";
        $html = $this->get_html($url);
        $sidebox = $html->find('div.sidebox5', 0);
        $link = $sidebox->find('a');
        foreach ($link as $item) {
            $id = $item->href;
            $id = explode('?c=', $id);
            $id = $id[1];

            $name = $item->innertext;
            $name = explode('(', $name);

            $count = intval($name[1]);
            $name = $name[0];
            if ($tmp_name = @iconv('GB2312', 'UTF-8//IGNORE//TRANSLIT', $name)) {
                $name = $tmp_name;
            }

            // 存储小分类
            $this->little_class2[$id] = array($name, $count);

            // 入库存储
            $this->db->insert(0, $id, $pid, $name, '', 0, time());
        }

        // 不存在子分类的另外一种情况，例如 城市信息 > 国外地名
        if (count($this->little_class2) == 1) {

            $page_num = $this->get_page_num($pid);
            // 逐页获取
            for ($i = 1; $i <= $page_num; $i++) {
                $this->fetch($pid, $path, 1, $i);
            }

        } else {

                foreach ($this->little_class2 as $id => $item) {
                    // 不存在词库条目则跳过获取流程
                    // 例如 城市信息 > 天津 > 风景名胜 就不存在词库条目
                    if ($item[1] < 1) {
                        continue;  // 不存在则跳过本次循环
                    }

                    $new_path = $path.'-'.$id;
                    $page_num = $this->get_page_num($id);
                    // 逐页获取
                    for ($i = 1; $i <= $page_num; $i++) {
                        $this->fetch($id, $new_path, $item[1], $i);
                    }
                }

        }
    }


    /**
     * 爬虫实际执行部分
     * @param $id    词库ID
     * @param $path  文件存储路径
     * @param $count 条目数
     * @param $page  页数
     */
    private function fetch ($id, $path, $count, $page = 1) {
        if ($count < 1) {  // 不存在条目时不检索
            return ;
        }

        $pid = $id;

        $url = "{$this->root_url}?c={$id}&page={$page}";
        $html = $this->get_html($url);
        $dictlist = $html->find('dl.dictlist', 0);
        $dd = $dictlist->find('dd');
        foreach ($dd as $item) {
            $link = $item->find('a', 0);
            $href = $link->href;

            $id = explode('id=', $href);
            $id = intval($id[1]);

            $name = explode('name=', $href);
            $name = urldecode($name[1]);
            // 编码能转则转
            if ($tmp_name = @iconv('GB2312', 'UTF-8//IGNORE//TRANSLIT', $name)) {
                $name = $tmp_name;
            } else {
                $name = '[乱码]';  // 设定乱码标志，以便手动修正
            }

            // 已知的乱码词条手动修正
            $name = $this->patch_name($id, $name);

            $file_path = "{$path}-{$id}.txt";

            // 获取当前词库网页显示的最后更新时间
            $this->update_time = $this->fetch_last_update_time($id);

            if (!file_exists($file_path)) {  // 词库文件不存在
                $this->save($id, $pid, $name, $file_path, 1);
            } else if ($this->need_update($id)) {  // 词库文件已存在，但本次爬取检测到更新
                $this->save($id, $pid, $name, $file_path, 0);
            } else {
                $this->logger("Passed {$file_path}");
            }
        }
    }


    /**
     * 保存文件
     * @param $id         词库ID
     * @param $pid        上一级词库ID
     * @param $name       文件名
     * @param $file_path  文件存储路径
     * @param $is_new     是否是还未曾存储过的词库
     */
    private function save ($id, $pid, $name, $file_path, $is_new) {
        // 创建目录
        if (!is_dir($this->save_path)) {
            @mkdir($this->save_path, 0777, true);
        }

        $href = "{$this->down_url}?id={$id}";

        // 读取搜狗词库文件，如果出错，重试两次
        $times = 1;
        do {
            if ($content = @file_get_contents($href)) {
                break;
            } else {
                $times++;
                sleep(1);
            }
        } while ($times <= 3);

        // 写入文件
        file_put_contents($file_path, $content);

        $filename = explode('/', $file_path);
        $filename = $filename[count($filename)-1];

        $msg = '';

        // 入库存储
        if ($is_new) {
            $this->db->insert($id, 0, $pid, $name, $filename, $this->update_time, time());
            $msg = "saved --> {$file_path}";
        } else {
            $this->db->update($id, $name, $this->update_time, time());
            $msg = "updated --> {$file_path}";
        }

        // 记录日志
        $full_msg = date('H:i:s ', time()).$msg;
        $this->logger($full_msg);

        $this->update_time = 0;  // 最后更新时间清零
    }


    /**
     * 查询某个词库是否需要更新
     * @param $id 词库ID
     */
    private function need_update ($id) {
        // 数据库存储的更新时间
        $store_time = $this->db->get_last_update_time($id);

        // 比较本次爬到的更新时间与数据库存储的更新时间
        if ($this->update_time > $store_time) {
            return true;
        }

        return false;
    }


    /**
     * 获取某个词库本次爬取搜狗官方最后的更新时间
     * @param $id 词库ID
     */
    private function fetch_last_update_time ($id) {
        $url = "{$this->page_url}?id={$id}";
        $html = $this->get_html($url);

        $dlinfobox = $html->find('div.dlinfobox', 0);
        $update = @$dlinfobox->find('table tr', 2);
        if (!$update) { // 个别词条乱码，无法获取，选择跳过
            return time();
        } else {
            $update = $update->find('td', 0);
        }

        $match = array();
        preg_match('/(\d{4}-\d{2}-\d{2})/', trim($update), $match);
        $date = $match[1];
        $time = strtotime($date);

        return $time;
    }


    /**
     * 不存在子分类的页面获取词库页数
     * @param $id 词库ID
     */
    private function get_page_num ($id) {
        $url = "{$this->root_url}?c={$id}";
        $html = $this->get_html($url);
        $pagebar = $html->find('div.pagebar', 0);
        $pagenum = @$pagebar->find('a.pagenum');

        if ($pagenum) {
            $current = $pagenum[count($pagenum)-1];
            $href = $current->href;
            $href = explode('page=', $href);
            return intval($href[1]);
        }

        return 1;
    }


    /**
     * 手动修正乱码词条
     * @param $id   词库ID
     * @param $name 词库名
     */
    private function patch_name ($id, $name) {
        switch ($id) {
            case 12172:
                $name = 'SH话';
                break;
            case 17982:
                $name = '广东话俚语';
                break;
            case 18217:
                $name = '滘';
                break;
            case 39770:
                $name = '合浦话特色词汇';
                break;
            case 466:
                $name = '闽南话';
                break;
            case 14977:
                $name = '台羅漢羅詞庫';
                break;
            case 35726:
                $name = '我的常用词库';
                break;
            case 14277:
                $name = '宁波联通小区&基站名';
                break;
            case 21311:
                $name = '名称';
                break;
            case 28826:
                $name = '鳑鲏类，鱊类';
                break;
            case 11258:
                $name = '地下城与勇士';
                break;
            case 39148:
                $name = '治疗脑血栓康复的最佳方法';
                break;
            case 23905:
                $name = '<HitmanReborn>';
                break;
            case 9390:
                $name = 'EVE重生常用词库';
                break;
            case 11515:
                $name = '真无双词库';
                break;
            case 15946:
                $name = 'SD敢达OL';
                break;
            case 17484:
                $name = '广东话词库';
                break;
            case 19938:
                $name = '魔兽世界台服词库';
                break;
            case 29343:
                $name = '陆盘剧仕';
                break;
            case 31374:
                $name = '甄嬛传';
                break;
            case 31617:
                $name = '客语词库';
                break;
            case 32066:
                $name = '浤悦贸易';
                break;
            case 32286:
                $name = '屌丝';
                break;
            case 34441:
                $name = '甴曱';
                break;
            case 36829:
                $name = '屌炸天';
                break;
            case 37546:
                $name = '楚喃厷吇';
                break;
            case 39665:
                $name = '氿上网';
                break;
            case 40374:
                $name = '爸爸去哪儿';
                break;
            case 29248:
                $name = '彥琮錄';
                break;
            case 37614:
                $name = '&#17898;';
                break;
            default:
                break;
        }

        return $name;
    }


    /**
     * 获取代理服务器列表
     */
    private function fetch_agent_list () {
        $content = file_get_contents(__DIR__.'/../config/agent_list.txt');
        $content = explode("\n", $content);
        array_pop($content);

        return $content;
    }


    /**
     * 获取浏览器用户代理字符串
     */
    private function fetch_user_agent () {
        $content = file_get_contents(__DIR__.'/../config/user_agent.txt');
        $content = explode("\n", $content);
        array_pop($content);

        return $content;
    }


    /*
     * 打印日志
     * @param $msg 日志内容
     */
    private function logger ($msg) {
        if (!is_dir($this->log_path)) {
            @mkdir($this->log_path, 0777, true);
        }
        file_put_contents($this->log_path.'/'.date('Y-m-d').'.txt', $msg."\r\n", FILE_APPEND);  // 追加存储
        echo $msg."\n";
    }

}
