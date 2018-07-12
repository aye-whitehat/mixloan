<?php
defined('IN_IA') or exit('Access Denied');
global $_GPC,$_W;
$config = $this->module['config'];
(!empty($_GPC['op']))?$operation=$_GPC['op']:$operation='index';
$openid = m('user')->getOpenid();
$member = m('member')->getMember($openid);
if($operation=='index'){
    //首页
    $agent = m('member')->checkAgent($member['id']);
    if ($agent['code']!=1) {
        header("location:{$this->createMobileUrl('vip', array('op'=>'buy'))}");
    }
    include $this->template('product/index');
}  else if ($operation == 'getProduct') {
    //得到产品
    $banner = m('product')->getAdvs();
    $new = m('product')->getRecommends();
    $new = m('product')->packupItems($new);
    $card = m('product')->getList([], ['type'=>1, 'is_show'=>1, 'status'=>1], " sort DESC");
    $loan = m('product')->getList([], ['type'=>2, 'is_show'=>1, 'status'=>1], " sort DESC");
    $card = m('product')->packupItems($card);
    $loan = m('product')->packupItems($loan);
    $arr = array(
        'banner'=>$banner,
        'new'=>$new,
        'card'=>$card,
        'loan'=>$loan
    );
    show_json(1, $arr);
} else if ($operation == 'info') {
    //产品详情
    $agent = m('member')->checkAgent($member['id']);
    if ($agent['code']==1) {
        $verify = 1;
    } else {
        $verify = 0;
    }
    $id = intval($_GPC['id']);
    $info = m('product')->getList([],['id'=>$id])[$id];
    if ( empty($info['is_show']) ) {
        message('该代理产品已被下架', '', 'info');
    }
    if ($info['type'] == 1) {
        $poster_short_url = shortUrl($_W['siteroot'] . 'app/' .$this->createMobileUrl('product', array('op'=>'apply', 'id'=>$id, 'inviter'=>$member['id'])));
        $poster_long_url = $_W['siteroot'] . 'app/' .$this->createMobileUrl('product', array('op'=>'apply', 'id'=>$id, 'inviter'=>$member['id']));
    } else {
        $poster_short_url = shortUrl($_W['siteroot'] . 'app/' .$this->createMobileUrl('loan', array('op'=>'apply', 'id'=>$info['relate_id'], 'inviter'=>$member['id'], 'pid'=>$info['id'])));
        $poster_long_url = $_W['siteroot'] . 'app/' .$this->createMobileUrl('loan', array('op'=>'apply', 'id'=>$info['relate_id'], 'inviter'=>$member['id'], 'pid'=>$info['id']));
    }
    $poster_path = getNowHostUrl()."/addons/xuan_mixloan/data/poster/{$id}_{$member['id']}.png";
    $top_list = m('product')->getTopBonus($id);
    include $this->template('product/info');
} else if ($operation == 'allProduct') {
    //全部产品
    $inviter = intval($_GPC['inviter']);
    $credits = m('product')->getList(['id', 'name', 'relate_id', 'ext_info'], ['type'=>1, 'is_show'=>1]);
    foreach ($credits as $credit) {
        $id[] = $credit['relate_id'];
    }
    $cards = m('bank')->getCard(['id', 'ext_info'], ['id'=>$id]);
    foreach ($credits as $key => $credit) {
        $credits[$key]['v_name'] = $cards[$credit['relate_id']]['ext_info']['v_name'];
        $credits[$key]['card_pic'] = tomedia($cards[$credit['relate_id']]['ext_info']['pic']);
        $credits[$key]['tag'] = $cards[$credit['relate_id']]['ext_info']['tag'];
        $credits[$key]['speed'] = $cards[$credit['relate_id']]['ext_info']['speed'];
        $credits[$key]['limit'] = $cards[$credit['relate_id']]['ext_info']['limit'];
        $credits[$key]['intro'] = $cards[$credit['relate_id']]['ext_info']['intro'];
        $credit_thr[] = $credits[$key];
        if (count($credit_thr) > 2 || $key == max(array_keys($credits))) {
            $credit_all[] = $credit_thr;
            $credit_thr = [];
        }
    }
    $speed_loans = m('product')->getSpecialLoan(9);
    foreach ($speed_loans as $key => $loan) {
        $speed_loan_thr[] = $loan;
        if (count($speed_loan_thr) > 2 || $key == max(array_keys($speed_loans))) {
            $speed_loan_all[] = $speed_loan_thr;
            $speed_loan_thr = [];
        }
    }
    $large_loans = m('product')->getSpecialLoan(7);
    foreach ($large_loans as $key => $loan) {
        $large_loan_thr[] = $loan;
        if (count($large_loan_thr) > 2 || $key == max(array_keys($large_loans))) {
            $large_loan_all[] = $large_loan_thr;
            $large_loan_thr = [];
        }
    }
    $credits_blow = array_slice($credits, (int)count($credits)/2, ceil(count($credits)/5));
    $barrage = m('product')->getBarrage($credits, array_merge($speed_loans, $large_loans));
    include $this->template('product/allProduct');
} else if ($operation == 'apply') {
    //申请产品
    $id = intval($_GPC['id']);
    $inviter = intval($_GPC['inviter']);
    $info = m('product')->getList(['id', 'ext_info', 'is_show'],['id'=>$id])[$id];
    if ( empty($info['is_show']) ) {
        message('该代理产品已被下架', '', 'info');
    }
    include $this->template('product/apply');
} else if ($operation == 'apply_submit') {
    //申请产品
    $id = intval($_GPC['id']);
    $inviter_uid = m('member')->getInviter(trim($_GPC['phone']), $member['openid']);
    $inviter = $inviter_uid ? : intval($_GPC['inviter']);
    if (sha1(md5(strtolower($_GPC['cache']))) != $_COOKIE['authcode']) {
        show_json(-1, [], "图形验证码不正确");
    }
    if ($inviter == $member['id']) {
        show_json(-1, [], "您不能自己邀请自己");
    }
    if ($id <= 0) {
        show_json(-1, [], "id为空");
    }
    if(!trim($_GPC['name']) || !trim($_GPC['phone']) || !trim($_GPC['idcard'])) {
        show_json(-1, [], '资料不能为空');
    }
    $record = m('product')->getApplyList(['id'], ['pid'=>$id, 'phone'=>$_GPC['phone']]);
    if ($record) {
        show_json(-1, [], "您已经申请过啦");
    }
    if ($config['jdwx_open'] == 1) {
        // $res = m('jdwx')->jd_credit_three($config['jdwx_key'], trim($_GPC['name']), trim($_GPC['phone']), trim($_GPC['idcard']));
        // if ($res['code'] == -1) {
        // 	show_json($res['code'], [], $res['msg']);
        // }
    }
    $info = m('product')->getList(['id', 'name', 'type', 'relate_id', 'is_show'],['id'=>$id])[$id];
    if ( empty($info['is_show']) ) {
        show_json(-1, [], '该代理产品已被下架');
    }
    if ($info['type'] == 1) {
        $pro = m('bank')->getCard(['id', 'ext_info'], ['id'=>$info['relate_id']])[$info['relate_id']];
    } else {
        show_json(-1, [], '邀请二维码已失效');
        $pro = m('loan')->getList(['id', 'ext_info'], ['id'=>$info['relate_id']])[$info['relate_id']];
    }
    if ($inviter) {
        $inviter_one = pdo_fetch("SELECT openid,nickname FROM ".tablename("xuan_mixloan_member") . " WHERE id=:id", array(':id'=>$inviter));
        // $datam = array(
        //     "first" => array(
        //         "value" => "尊敬的用户您好，有一个用户通过您的邀请申请了{$info['name']}，请及时跟进。",
        //         "color" => "#173177"
        //     ) ,
        //     "keyword1" => array(
        //         'value' => trim($_GPC['name']),
        //         "color" => "#4a5077"
        //     ) ,
        //     "keyword2" => array(
        //         'value' => date('Y-m-d H:i:s', time()),
        //         "color" => "#4a5077"
        //     ) ,
        //     "remark" => array(
        //         "value" => '点击查看详情',
        //         "color" => "#4a5077"
        //     ) ,
        // );
        // $url = $_W['siteroot'] . 'app/' .$this->createMobileUrl('vip', array('op'=>'salary'));
        // $account = WeAccount::create($_W['acid']);
        // $account->sendTplNotice($inviter_one['openid'], $config['tpl_notice1'], $datam, $url);
        $url = $_W['siteroot'] . 'app/' . $this->createMobileUrl('vip', array('op' => 'salary'));
        $ext_info = array('content' => "尊敬的用户您好，" . $_GPC['name'] . "通过您的邀请申请了" . $info['name'] . "，请及时跟进。", 'remark' => "点击查看详情", 'url' => $url);
        $insert = array(
            'is_read'=>0,
            'uid'=>$member['id'],
            'type'=>2,
            'createtime'=>time(),
            'uniacid'=>$_W['uniacid'],
            'to_uid'=>$inviter,
            'ext_info'=>json_encode($ext_info),
        );
        pdo_insert('xuan_mixloan_msg', $insert);
        $status = 0;
    } else {
        $status = -2;
    }
    $insert = array(
        'uniacid' => $_W['uniacid'],
        'uid' => $member['id'],
        'phone' => trim($_GPC['phone']),
        'certno' => trim($_GPC['idcard']),
        'realname' => trim($_GPC['name']),
        'pid' => $id,
        'inviter' => $inviter,
        're_bonus'=>0,
        'done_bonus'=>0,
        'extra_bonus'=>0,
        'status'=>$status,
        'createtime'=>time()
    );
    pdo_insert('xuan_mixloan_product_apply', $insert);
    //二级
    $inviter_info = m('member')->getInviterInfo($inviter);
    $second_inviter = m('member')->getInviter($inviter_info['phone'], $inviter_info['openid']);
    if ($second_inviter) {
        $insert['inviter'] = $second_inviter;
        $insert['degree'] = 2;
        pdo_insert('xuan_mixloan_product_apply', $insert);
        // $inviter_two = pdo_fetch("SELECT openid,nickname FROM ".tablename("xuan_mixloan_member") . " WHERE id=:id", array(':id'=>$second_inviter));
        // $datam = array(
        //     "first" => array(
        //         "value" => "尊敬的用户您好，有一个用户通过您下级{$inviter_one['nickname']}的邀请申请了{$info['name']}，请及时跟进。",
        //         "color" => "#173177"
        //     ) ,
        //     "keyword1" => array(
        //         'value' => trim($_GPC['name']),
        //         "color" => "#4a5077"
        //     ) ,
        //     "keyword2" => array(
        //         'value' => date('Y-m-d H:i:s', time()),
        //         "color" => "#4a5077"
        //     ) ,
        //     "remark" => array(
        //         "value" => '点击查看详情',
        //         "color" => "#4a5077"
        //     ) ,
        // );
        // $account->sendTplNotice($inviter_two['openid'], $config['tpl_notice1'], $datam, $url);
        $url = $_W['siteroot'] . 'app/' . $this->createMobileUrl('vip', array('op' => 'salary'));
        $ext_info = array('content' => "尊敬的用户您好，" . $_GPC['name'] . "通过您下级 " . $inviter_info['nickname'] . " 的邀请申请了" . $info['name'] . "，请及时跟进。", 'remark' => "点击查看详情", 'url' => $url);
        $insert = array(
            'is_read'=>0,
            'uid'=>$member['id'],
            'type'=>2,
            'createtime'=>time(),
            'uniacid'=>$_W['uniacid'],
            'to_uid'=>$second_inviter,
            'ext_info'=>json_encode($ext_info),
        );
        pdo_insert('xuan_mixloan_msg', $insert);
    }
    show_json(1, $pro['ext_info']['url']);
} else if ($operation == 'customer') {
    //客户列表
    include $this->template('product/customer');
} else if ($operation == 'customer_list') {
    //客户列表接口
    $month = (int)$_GPC['month'];
    $year = (int)$_GPC['year'];
    $params['begin'] = "{$year}-{$month}-01";
    $params['inviter'] = $member['id'];
    $days_list = m('product')->getList(['id', 'name', 'ext_info'],['count_time'=>1]);
    $weeks_list = m('product')->getList(['id', 'name', 'type'],['count_time'=>7]);
    $months_list = m('product')->getList(['id', 'name', 'type'],['count_time'=>30]);
    $invite_list = m('product')->getInviteList($params);
    $days_ids = m('product')->getIds($days_list);
    $weeks_ids = m('product')->getIds($weeks_list);
    $months_ids = m('product')->getIds($months_list);
    $applys = m('product')->getApplys($params);
    $days_count_list = m('product')->getNums($days_ids, $params, 1);
    $weeks_count_list = m('product')->getNums($weeks_ids, $params, 1);
    $months_count_list = m('product')->getNums($months_ids, $params, 1);
    $weeks_succ_list = m('product')->getNums($weeks_ids, $params, 2);
    $months_succ_list = m('product')->getNums($months_ids, $params, 2);
    $weeks_bonus_list = m('product')->getNums($weeks_ids, $params, 3);
    $months_bonus_list = m('product')->getNums($months_ids, $params, 3);
    foreach ($days_list as &$row) {
        $row['count_num'] = $days_count_list[$row['id']]['count'] ? : 0;
    }
    unset($row);
    foreach ($weeks_list as &$row) {
        $row['count_num'] = $weeks_count_list[$row['id']]['count'] ? : 0;
        if ($row['type'] == 1) {
            $row['succ'] = $weeks_succ_list[$row['id']]['count'] ? $weeks_succ_list[$row['id']]['count'].'位' : '0'.'位';
        } else {
            $row['succ'] = $weeks_succ_list[$row['id']]['relate_money'] ? $weeks_succ_list[$row['id']]['relate_money'].'元' : '0'.'元';
        }
        $row['count_bonus'] = $weeks_bonus_list[$row['id']]['bonus'] ? : 0;
    }
    unset($row);
    foreach ($months_list as &$row) {
        $row['count_num'] = $months_count_list[$row['id']]['count'] ? : 0;
        if ($row['type'] == 1) {
            $row['succ'] = $months_succ_list[$row['id']]['count'] ? $months_succ_list[$row['id']]['count'].'位' : '0'.'位';
        } else {
            $row['succ'] = $months_succ_list[$row['id']]['relate_money'] ? $months_succ_list[$row['id']]['relate_money'].'元' : '0'.'元';
        }
        $row['count_bonus'] = $months_bonus_list[$row['id']]['bonus'] ? : 0;
    }
    unset($row);
    $arr = ['days_list'=>array_values($days_list), 'months_list'=>array_values($months_list), 'weeks_list'=>array_values($weeks_list), 'invite_list'=>array_values($invite_list), 'applys'=>$applys];
    show_json(1, $arr);
} else if ($operation == 'customer_detail') {
    //详情
    $pid = intval($_GPC['pid']);
    $inviter = intval($_GPC['inviter']);
    $degree = intval($_GPC['degree']) ? : 1;
    $type = $_GPC['type'] ? : 1;
    if (empty($pid) || empty($inviter)) {
        message('查询出错', '', 'error');
    }
    $arr = array(':pid'=>$pid, ':inviter'=>$inviter);
    if ($type == 1) {
        $condition = ' WHERE inviter=:inviter AND pid=:pid';
    } else if ($type == 2) {
        $condition = ' WHERE inviter=:inviter AND pid=:pid AND status>0';
    } else if ($type == 3) {
        $condition = ' WHERE inviter=:inviter AND pid=:pid AND status=-1';
    }
    $condition .= " and degree={$degree}";
    $count_num = pdo_fetchcolumn('SELECT count(*) FROM ' . tablename('xuan_mixloan_product_apply') . "
        WHERE inviter=:inviter AND pid=:pid and degree={$degree}", $arr) ? : 0;
    $count_succ_num = pdo_fetchcolumn('SELECT count(*) FROM ' . tablename('xuan_mixloan_product_apply') . "
        WHERE inviter=:inviter AND pid=:pid AND status>0 and degree={$degree}", $arr) ? : 0;
    $count_succ_bonus = pdo_fetchcolumn('SELECT SUM(re_bonus+done_bonus+extra_bonus) FROM ' . tablename('xuan_mixloan_product_apply') . "
        WHERE inviter=:inviter AND pid=:pid and degree={$degree}", $arr) ? : 0;
    $sql = 'SELECT id,re_bonus,done_bonus,extra_bonus,pid,status,phone,createtime,degree FROM ' . tablename('xuan_mixloan_product_apply'). $condition;
    $list = pdo_fetchall($sql, $arr);
    if (!empty($list)) {
        foreach ($list as &$row) {
            $row['product'] = m('product')->getList(['id','ext_info','name'],['id'=>$row['pid']])[$row['pid']];
            $row['createtime'] = date('Y-m-d H:i:s', $row['createtime']);
            $row['bonus'] = $row['re_bonus'] + $row['done_bonus'] + $row['extra_bonus'];
            if ($row['status'] == 1) {
                $row['state'] = '已注册';
            } else if ($row['status'] == -1) {
                $row['state'] = '已失效';
            } else if ($row['status'] == 0) {
                $row['state'] = '申请中';
            } else if ($row['status'] == 2) {
                $row['state'] = '已成功';
            }
            if ($row['degree'] == 1) {
                $row['degree'] = '直推';
            } else if ($row['degree'] == 2) {
                $row['degree'] = '二级';
            }
            $row['phone'] = substr($row['phone'], 0, 4) . '****' . substr($row['phone'], -3);
        }
        unset($row);
    }
    include $this->template('product/customer_detail');
}