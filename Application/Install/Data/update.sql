ALTER TABLE `onethink_category` ADD COLUMN `groups` varchar(255) NOT NULL DEFAULT '' COMMENT '分组定义';
ALTER TABLE `onethink_document` ADD COLUMN `group_id` smallint(3) unsigned NOT NULL COMMENT '所属分组' AFTER `category_id`;
ALTER TABLE `onethink_model` ADD COLUMN `attribute_alias` varchar(255) NOT NULL DEFAULT '' COMMENT '属性别名定义' AFTER `attribute_list`;
ALTER TABLE `onethink_category` CHANGE COLUMN `model` `model` varchar(100) NOT NULL DEFAULT '' COMMENT '列表绑定模型';
UPDATE `onethink_attribute` SET `extra` = '[DOCUMENT_POSITION]' WHERE `id`=10;
ALTER TABLE `onethink_hooks` ADD COLUMN `status` tinyint(1) unsigned NOT NULL DEFAULT '1';
ALTER TABLE `onethink_category` ADD COLUMN `model_sub` varchar(100) NOT NULL DEFAULT '' COMMENT '子文档绑定模型' AFTER `model`;
UPDATE `onethink_category` SET `model` = '2,3', `model_sub` = '2' WHERE `id` = 1;
UPDATE `onethink_category` SET `model` = '2,3', `model_sub` = '2', `icon` = 0 WHERE `id` = 2;
ALTER TABLE `onethink_file` ADD COLUMN `url` varchar(255) NOT NULL DEFAULT '' COMMENT '远程地址' AFTER `location`;
ALTER TABLE `onethink_menu` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态' AFTER `is_dev`;
ALTER TABLE `onethink_menu` ADD INDEX `status` (`status`);
UPDATE `onethink_menu` SET `status` = '1' ;
UPDATE `onethink_menu` SET `url` = 'article/index' WHERE `id` =2;
INSERT INTO `onethink_menu` (`title`,`pid`,`sort`,`url`,`hide`,`tip`,`group`,`is_dev`,`status`) VALUES ('审核列表', '3', '0', 'Article/examine', '1', '', '', '0','1');
UPDATE `onethink_model` SET `list_grid` = 'id:编号\r\ntitle:标题:[EDIT]\r\ntype:类型\r\nupdate_time:最后更新\r\nstatus:状态\r\nview:浏览\r\nid:操作:[EDIT]|编辑,[DELETE]|删除' WHERE `id` = 1;
UPDATE `onethink_model` set `list_grid` = '' WHERE `id` > 2;
-- 商品评价表增加pid字段，用于回复
ALTER TABLE `winshop_order_comment` ADD COLUMN `pid` int(11)  DEFAULT '0' COMMENT '回复id';
-- 商品评价菜单
INSERT INTO `winshop_menu` VALUES (235, '商品评价', 2, 4, 'ItemComment/index', 0, '商品管理', '商品管理', 0, 1);
-- 行为日志支持批量id记录
ALTER TABLE  `winshop_action_log` ALTER COLUMN   `record_id` varchar(255);
TRUNCATE TABLE `winshop_action`；
-- ----------------------------
--  Records of `winshop_action`
-- ----------------------------
BEGIN;
INSERT INTO `winshop_action` VALUES ('1', 'user_login', '用户登录', '积分+10，每天一次', 'table:member|field:score|condition:uid={$self} AND status>-1|rule:score+10|cycle:24|max:1;', '[user|get_nickname]在[time|time_format]登录了后台', '1', '1', '1387181220'), ('2', 'add_article', '发布文章', '积分+5，每天上限5次', 'table:member|field:score|condition:uid={$self}|rule:score+5|cycle:24|max:5', '', '2', '0', '1380173180'), ('3', 'review', '评论', '评论积分+1，无限制', 'table:member|field:score|condition:uid={$self}|rule:score+1', '', '2', '1', '1383285646'), ('4', 'add_document', '发表文档', '积分+10，每天上限5次', 'table:member|field:score|condition:uid={$self}|rule:score+10|cycle:24|max:5', '[user|get_nickname]在[time|time_format]发表了一篇文章。\r\n表[model]，记录编号[record]。', '2', '0', '1386139726'), ('5', 'add_document_topic', '发表讨论', '积分+5，每天上限10次', 'table:member|field:score|condition:uid={$self}|rule:score+5|cycle:24|max:10', '', '2', '0', '1383285551'), ('6', 'update_config', '更新配置', '新增或修改或删除配置', '', '', '1', '1', '1383294988'), ('7', 'update_model', '更新模型', '新增或修改模型', '', '', '1', '1', '1383295057'), ('8', 'update_attribute', '更新属性', '新增或更新或删除属性', '', '', '1', '1', '1383295963'), ('9', 'update_channel', '更新导航', '新增或修改或删除导航', '', '', '1', '1', '1383296301'), ('10', 'update_menu', '更新菜单', '新增或修改或删除菜单', '', '', '1', '1', '1383296392'), ('11', 'update_category', '更新分类', '新增或修改或删除分类', '', '', '1', '1', '1383296765'), ('12', 'update_item', '更新商品', '新增，修改，删除，上架，下架商品', '', '', '1', '1', '1431058373'), ('13', 'update_item_category', '更新产品分类', '更新产品分类', '', '', '1', '1', '1431058423'), ('14', 'update_item_attribute', '更新商品属性', '更新商品属性', '', '', '1', '1', '1431058465'), ('15', 'update_item_specification', '更新商品规格', '更新商品规格', '', '', '1', '1', '1431058517'), ('16', 'clear_item_recycle', '清空商品回收站', '清空商品回收站', '', '', '1', '1', '1431058557'), ('17', 'permit_item_recycle', '还原商品回收站的商品', '还原商品回收站的商品', '', '', '1', '1', '1431065809'), ('18', 'create_item_qrcode', '批量生成商品二维码', '批量生成商品二维码', '', '', '1', '1', '1431065829'), ('19', 'update_order', '更新订单', '修改订单价格', '', '', '1', '1', '1431065851'), ('20', 'cancel_order_admin', '后台管理员取消订单', '后台管理员取消订单', '', '', '1', '1', '1431065866'), ('21', 'add_order_ship', '订单发货', '订单发货', '', '', '1', '1', '1431065882'), ('22', 'del_order_recycle', '删除回收站订单', '彻底删除回收站中的订单', '', '', '1', '1', '1431065900'), ('23', 'refund_order_alipay', '支付宝批量退款', '支付宝批量退款', '', '', '1', '1', '1431065916'), ('24', 'refund_order', '单笔退款', '单笔退款', '', '', '1', '1', '1431065929'), ('25', 'update_delivery', '更新运费模板', '添加、编辑、删除运费模板', '', '', '1', '1', '1431065950'), ('26', 'update_redpacket', '更新红包', '添加、编辑、删除红包', '', '', '1', '1', '1431065967'), ('27', 'update_coupon', '更新优惠券', '添加、编辑、删除优惠券', '', '', '1', '1', '1431065985'), ('28', 'update_card', '更新礼品卡', '添加、编辑、删除礼品卡', '', '', '1', '1', '1431066004'), ('29', 'update_activity', '更新专场', '添加、编辑、删除专场', '', '', '1', '1', '1431066026'), ('30', 'update_advertise', '更新广告', '添加、编辑、删除广告', '', '', '1', '1', '1431066044'), ('31', 'update_finance', '更新账户余额', '修改用户账户余额', '', '', '1', '1', '1431066062'), ('32', 'update_score', '更新积分', '修改用户积分', '', '', '1', '1', '1431066079'), ('33', 'update_user', '更新用户信息', '修改用户信息', '', '', '1', '1', '1431066096'), ('34', 'update_auth_manger', '用户组授权', '修改用户组授权', '', '', '1', '1', '1431066115'), (null, 'user_reg', '用户注册', '用户注册送5元余额', 'table:member|field:finance|condition:uid={$self}|rule:5|cycle:24|max:1;', '', '1', '-1', '1432777748');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;


INSERT INTO `winshop_config` (`id`, `name`, `type`, `title`, `group`, `extra`, `remark`, `create_time`, `update_time`, `status`, `value`, `sort`, `is_dev`) VALUES
(67, 'MAX_CONFIRM_RECEIPT_DAY', 0, '自动确认收货时限', 5, '', '超出该天数，系统自动确认收货', 1423553262, 1423553262, 1, '7', 0, 0),
(68, 'IS_SHOW_BUYNUM', 4, '是否显示商品购买数量', 5, '0:关闭,1:开启', '是否显示商品购买数量', 1426664944, 1426664960, 1, '1', 1, 0);

INSERT INTO `winshop_hooks` (`id`, `name`, `description`, `type`, `update_time`, `addons`, `status`)
VALUES
	(18, 'userSel', '用户选择器', 1, 1434353448, 'UserSel', 1);
INSERT INTO `winshop_addons` (`id`, `name`, `title`, `description`, `status`, `config`, `author`, `version`, `create_time`, `has_adminlist`)
VALUES
	(17, 'UserSel', '用户选择器', '通用型用户选择器', 1, 'null', 'Justin', '0.1', 1434353460, 0);



