<?php

class DataBase {

    private $db     = '';  // 数据库连接句柄
    private $tbname = '';  // 表名


    public function __construct ($host, $username, $password, $dbname, $dbport, $tbname) {
        $this->db = new mysqli($host, $username, $password, $dbname, $dbport);
        // 连接错误则退出
        if (mysqli_connect_errno()) {
            exit('Connect failed: '.mysqli_connect_error());
        }

        $this->db->query('set names utf8');  // 设置默认字符集为UTF-8

        $this->tbname = $tbname;
    }


    public function __destruct () {
        // 关闭数据库连接
        $this->db->close();
    }


    /**
     * 写入记录
     * @param $id          词库ID
     * @param $cid         分类ID
     * @param $pid         上一级分类ID
     * @param $name        词库名
     * @param $file        存储文件名
     * @param $last_update 爬取到的词库最后更新时间
     * @param $insert_time 本次插入时间
     */
    public function insert ($id, $cid, $pid, $name = '', $file = '', $last_update = 0, $insert_time = 0) {
        $id          = intval($id);
        $cid         = intval($cid);
        $pid         = intval($pid);
        $last_update = intval($last_update);
        $insert_time = intval($insert_time);

        $sql = "insert into `{$this->tbname}` (`id`, `cid`, `pid`, `name`, `file`, `last_update`, `insert_time`)
            values ({$id}, {$cid}, {$pid}, '{$name}', '{$file}', {$last_update}, {$insert_time})";
        $this->db->query($sql);
    }


    /**
     * 更新记录
     * @param $id          词库ID
     * @param $name        词库名
     * @param $last_update 爬取到的词库最后更新时间
     * @param $update_time 本次更新时间
     */
    public function update ($id, $name = '', $last_update = 0, $update_time = 0) {
        $id          = intval($id);
        $last_update = intval($last_update);
        $update_time = intval($update_time);

        $sql = sprintf("update `%s` set
            `name` = '%s',
            `last_update` = %d,
            `update_time` = %d
            where `id` = %d", $this->tbname, $name, $last_update, $update_time, $id);
        $this->db->query($sql);
    }


    /**
     * 获取最后更新时间
     * @param $id 词库ID
     */
    public function get_last_update_time ($id) {
        $id = intval($id);

        $sql = sprintf("select `last_update` from {$this->tbname} where `id` = %d", $id);
        $res = $this->db->query($sql);
        $res = $res->fetch_assoc();
        $time = intval($res['last_update']);

        return $time;
    }

}
