<?php  
defined('IN_IA') or exit('Access Denied');
global $_GPC,$_W;
$config = $this->module['config'];
(!empty($_GPC['op']))?$operation=$_GPC['op']:$operation='login';
$openid = m('user')->getOpenid();
$member = m('member')->getMember($openid);
if($operation=='register'){
	//注册
	include $this->template('index/register');
} elseif ($operation == 'register_contract') {
	//注册协议
	include $this->template('index/register_contract');
} else if ($operation == 'register_ajax') {
	//注册提交
	$phone = $_GPC['phone'];
	$pwd = $_GPC['pwd'] ? : '';
	$smsCode = $_GPC['smsCode'];
	if (md5($phone.$smsCode) != $_COOKIE['cache_code']) {
		show_json(-1, null, "验证码不符或验证码已失效");
	}
	if (!empty($member['phone'])) {
		show_json(-1, null, "您的手机已绑定");
	}
	$res = pdo_fetchcolumn("SELECT COUNT(1) FROM ".tablename("xuan_mixloan_member")." WHERE phone=:phone AND uniacid=:uniacid", array(':phone'=>$phone, ':uniacid'=>$_W['uniacid']));
	if ($res) {
		show_json(-1, null, "手机已绑定");
	}
	$insert = array(
        'uniacid' => $_W['uniacid'],
        'phone' => $phone,
        'pass' => $pwd,
        'createtime' => time(),
    );
    pdo_insert('xuan_mixloan_member', $insert);
    show_json(1, ['url'=>$this->createMobileUrl('index', array('op'=>'login'))], "注册成功");
} else if ($operation == 'login') {
    //登陆
    if (isset($_COOKIE['user_id'])) {
        header("location:{$this->createMobileUrl('loan')}");
    }
    include $this->template('index/login');
} else if ($operation == 'login_ajax') {
    $phone = intval($_GPC['phone']);
    $pass = trim($_GPC['pwd']);
    if (empty($phone)) {
        show_json(-1, [], '请填写手机');
    }
    if (empty($pass)) {
        show_json(-1, [], '请填写密码');
    }
    $member = pdo_fetch("SELECT id,pass FROM ".tablename('xuan_mixloan_member').'
        WHERE phone=:phone and uniacid=:uniacid', array(':phone'=>$phone, ':uniacid'=>$_W['uniacid']));
    if (empty($member)) {
        show_json(-1, [], '手机号不存在');
    }
    if ($member['pass'] != $pass) {
        show_json(-1, [], '密码不正确');
    }
    setcookie('user_id', $member['id'], time()+86400);
    show_json(1, ['url'=>$this->createMobileUrl('loan')], '登陆成功');
} else if ($operation == 'loginout') {
    setcookie('user_id', false, time()-86400);
    header("location:{$this->createMobileUrl('index', ['op'=>'login'])}");
}else if ($operation == 'wechat_login_app') {
    //通过app登陆
    include $this->template('index/wechat_login_app');
} else if ($operation == 'wechat_app') {
    //app登陆
    $unionid = trim($_GPC['unionid']);
    if (empty($unionid)) {
        show_json(-1, [], '获取信息出错');
    }
    $id = pdo_fetchcolumn('select id from ' .tablename('xuan_mixloan_member'). '
		where unionid=:unionid', array(':unionid'=>$unionid));
    if (empty($id)) {
        show_json(-1, [], "请先公众号打开{$config['title']}一次再打开APP");
        $insert = array(
            'uniacid'=>$_W['uniacid'],
            'openid'=>$_GPC['openid'],
            'unionid'=>$unionid,
            'avatar'=>$_GPC['headimgurl'],
            'nickname'=>$_GPC['nickname'],
            'country'=>$_GPC['country'],
            'province'=>$_GPC['province'],
            'city'=>$_GPC['city'],
            'sex'=>$_GPC['sex'],
            'createtime'=>time(),
        );
        pdo_insert('xuan_mixloan_member', $insert);
        $id = pdo_insertid();
    }
    setcookie('user_id', $id, time()+86400);
    show_json(1, ['url'=>$this->createMobileUrl('user')]);
} else if ($operation == 'find_pass') {
    //找回密码
    include $this->template('index/find_pass');
} else if ($operation == 'find_pass_ajax') {
    $phone = $_GPC['phone'];
    $pwd = $_GPC['pwd'] ? : '';
    $smsCode = $_GPC['smsCode'];
    if (md5($phone.$smsCode) != $_COOKIE['cache_code']) {
        show_json(-1, null, "验证码不符或验证码已失效");
    }
    $res = pdo_fetchcolumn("SELECT id FROM ".tablename("xuan_mixloan_member")."
        WHERE phone=:phone AND uniacid=:uniacid", array(':phone'=>$phone, ':uniacid'=>$_W['uniacid']));
    if (empty($res)) {
        show_json(-1, null, "未查到此手机记录");
    }
    pdo_update('xuan_mixloan_member', array('pass'=>$pwd), array('id'=>$res));
    if (is_weixin()) {
        show_json(1, ['url'=>$this->createMobileUrl('user', array('op'=>''))], "更改密码成功，请牢记您的密码");
    } else {
        show_json(1, ['url'=>$this->createMobileUrl('index', array('op'=>'login'))], "更改密码成功，请牢记您的密码");
    }
}