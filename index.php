<?php

include_once __DIR__.'/lib/simple_html_dom.php';
include_once __DIR__.'/lib/database.php';
include_once __DIR__.'/lib/sougou.php';
include_once __DIR__.'/config/config.php';

// -------------------------------

// 词库首页
define('LIST_URL', 'http://pinyin.sogou.com/dict/list.php');
// 词库介绍页面
define('PAGE_URL', 'http://pinyin.sogou.com/dict/cell.php');
// 词库下载地址
define('DOWN_URL', 'http://pinyin.sogou.com/dict/download_txt.php');

// -------------------------------

$sougou = new SouGou(LIST_URL, PAGE_URL, DOWN_URL, SAVE_PATH, LOG_PATH, USE_AGENT,
    DB_HOST, DB_USER, DB_PASS, DB_DBNAME, DB_PORT, DB_TABLE);

// -------------------------------
