/*
Navicat MySQL Data Transfer

Source Server         : 本地MySQL
Source Server Version : 50536
Source Host           : localhost:3306
Source Database       : sougou_words

Target Server Type    : MYSQL
Target Server Version : 50536
File Encoding         : 65001

Date: 2014-04-08 13:57:08
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for words
-- ----------------------------
DROP TABLE IF EXISTS `words`;
CREATE TABLE `words` (
  `id` int(10) unsigned NOT NULL COMMENT '词库ID',
  `cid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上一级分类ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '词库名',
  `file` varchar(255) NOT NULL DEFAULT '' COMMENT '文件名',
  `last_update` int(10) unsigned DEFAULT '0' COMMENT '搜狗官方词库最后更新时间',
  `insert_time` int(10) unsigned DEFAULT '0' COMMENT '本地爬取插入时间',
  `update_time` int(10) unsigned DEFAULT '0' COMMENT '本地爬取更新时间',
  PRIMARY KEY (`id`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
