<?php
defined('IN_IA') or exit('Access Denied');
class Xuan_mixloan_Poster
{
    public function getPoster($get=[], $conditon=[]) {
        global $_W;
        $wheres = $fields = "";
        if (!empty($get)) {
            $fields = implode(',', $get);
        } else {
            $fields = '*';
        }
        if (!empty($conditon)) {
            foreach ($conditon as $k => $v) {
                $wheres .= " AND `{$k}` = '{$v}'";
            }
        }
        $sql = "SELECT {$fields} FROM ".tablename('xuan_mixloan_poster')." WHERE uniacid={$_W['uniacid']} {$wheres} ";
        $item = pdo_fetch($sql);
        return $item;
    }
     /**
    *   生成海报
    **/
    public function createPoster($config, $params) {
        global $_W;
        $tmplogo = XUAN_MIXLOAN_PATH."data/poster/base.jpg";
        require_once(IA_ROOT.'/framework/library/qrcode/phpqrcode.php');
        QRcode::png($params['url'],$tmplogo,'L',15,2);
        $QR = imagecreatefromstring(file_get_contents($tmplogo));
        $bgpath = IA_ROOT . '/attachment/' . $config['poster_image'];
        $font = XUAN_MIXLOAN_PATH."data/fonts/msyh.ttf";
        $bgpng = imagecreatefrompng($bgpath);
        if ($config['poster_avatar']) {
            //头像
            if (strstr($params['member']['avatar'], 'mix_loan')) {
                $avatar = imagecreatefromstring(file_get_contents($params['member']['avatar']));
            } else {
                $avatar = imagecreatefromstring(curl_file_get_contents($params['member']['avatar']));
            }
            $newa = imagecreatetruecolor(imagesx($bgpng)*0.2,imagesx($bgpng)*0.2);
            imagecopyresized($newa,$avatar,0,0,0,0,imagesx($bgpng)*0.2,imagesx($bgpng)*0.2,imagesx($avatar),imagesy($avatar));
            imagecopymerge($bgpng,$newa,imagesx($bgpng)*0.4,imagesy($bgpng)*0.4,0,0,imagesx($bgpng)*0.2,imagesx($bgpng)*0.2,100);
        }
        $newl = imagecreatetruecolor(imagesx($bgpng)*0.3,imagesx($bgpng)*0.3);
        imagecopyresized($newl,$QR,0,0,0,0,imagesx($bgpng)*0.3,imagesx($bgpng)*0.3,imagesx($QR),imagesy($QR));
        //字体
        $poster_color = hex2rgb($config['poster_color']);
        $color = imagecolorallocatealpha($bgpng,$poster_color['r'],$poster_color['g'],$poster_color['b'],0);
        imagettftext($bgpng,imagesx($bgpng)*0.03,0,imagesx($bgpng)*0.4,imagesy($bgpng)*0.95,$color,$font,$params['member']['nickname']);
        if (!$config['poster_avatar']) {
            $height = 0.72;
        } else {
            $height = 0.72;
        }
        imagecopymerge($bgpng,$newl,imagesx($bgpng)*0.345,imagesy($bgpng)*$height,0,0,imagesx($newl),imagesy($newl),100);
        $res = imagepng($bgpng,$params['out']);
        imagedestroy($QR);
        imagedestroy($bgpng);
        if ($res) {
            $insert = array(
                'uniacid'=>$_W['uniacid'],
                'uid'=>$params['member']['id'],
                'pid'=>$params['pid'],
                'type'=>$params['type'],
                'poster'=>$params['poster_path'],
                'createtime'=>time(),
            );
            pdo_insert('xuan_mixloan_poster',$insert);
            return true;
        } else {
            return false;
        }
    }

    /**
     *   新的生成海报方式
     **/
    public function createNewPoster($params) {
        global $_W;
        $poster_id = intval($params['poster_id']);
        if (empty($poster_id)) {
            return false;
        }
        $ext_info = pdo_fetchcolumn("SELECT ext_info FROM ".tablename('xuan_mixloan_poster_data').' WHERE id=:id', array(':id'=>$poster_id));
        $ext_info = json_decode($ext_info, 1);
        if (empty($ext_info) || empty($ext_info['back'])) {
            return false;
        }
        $bgpath = IA_ROOT . '/attachment/' . $ext_info['back'];
        $bgpng = imagecreatefrompng($bgpath);
        $font = XUAN_MIXLOAN_PATH."data/fonts/msyh.ttf";
        $width_proportion = imagesx($bgpng) / 320;
        if (!empty($ext_info['poster']['qr'])) {
            //二维码
            $tmplogo = XUAN_MIXLOAN_PATH."data/poster/base.jpg";
            require_once(IA_ROOT.'/framework/library/qrcode/phpqrcode.php');
            QRcode::png($params['url'],$tmplogo,'L',15,2);
            $QR = imagecreatefromstring(file_get_contents($tmplogo));
            $width = bcmul(intval($ext_info['poster']['qr']['width']), $width_proportion);
            $height = intval($ext_info['poster']['qr']['height'])* $width_proportion;
            $left = intval($ext_info['poster']['qr']['left'])* $width_proportion;
            $top = intval($ext_info['poster']['qr']['top'])* $width_proportion;
            $newQRcode = imagecreatetruecolor($width, $height);
            imagecopyresized($newQRcode,$QR,0,0,0,0,$width, $height,imagesx($QR),imagesy($QR));
            imagecopymerge($bgpng,$newQRcode,$left,$top,0,0,imagesx($newQRcode),imagesy($newQRcode),100);
        }
        if (!empty($ext_info['poster']['head'])) {
            //头像
            if (strstr($params['member']['avatar'], 'mix_loan')) {
                $avatar = imagecreatefromstring(file_get_contents($params['member']['avatar']));
            } else {
                $avatar = imagecreatefromstring(curl_file_get_contents($params['member']['avatar']));
            }
            $width = bcmul(intval($ext_info['poster']['head']['width']), $width_proportion);
            $height = intval($ext_info['poster']['head']['height'])* $width_proportion;
            $left = intval($ext_info['poster']['head']['left'])* $width_proportion;
            $top = intval($ext_info['poster']['head']['top'])* $width_proportion;
            $newAvatar = imagecreatetruecolor($width, $height);
            imagecopyresized($newAvatar,$avatar,0,0,0,0,$width,$height,imagesx($avatar),imagesy($avatar));
            imagecopymerge($bgpng,$newAvatar,$left,$top,0,0,$width,$height,100);
        }
        if (!empty($ext_info['poster']['nickname'])) {
            //昵称
            $poster_color = hex2rgb($ext_info['poster']['nickname']['color']);
            $left = intval($ext_info['poster']['head']['left'])* $width_proportion;
            $top = intval($ext_info['poster']['head']['top'])* ($width_proportion+0.2);
            $color = imagecolorallocatealpha($bgpng,$poster_color['r'],$poster_color['g'],$poster_color['b'],0);
            imagettftext($bgpng,$ext_info['poster']['nickname']['size']*$width_proportion,0,$left,$top,$color,$font,$params['member']['nickname']);
        }
        $res = imagepng($bgpng,$params['out']);
        @imagedestroy($QR);
        imagedestroy($bgpng);
        if ($res) {
            $insert = array(
                'uniacid'=>$_W['uniacid'],
                'uid'=>$params['member']['id'],
                'pid'=>$params['pid'],
                'type'=>$params['type'],
                'poster'=>$params['poster_path'],
                'createtime'=>time(),
            );
            pdo_insert('xuan_mixloan_poster',$insert);
            return true;
        } else {
            return false;
        }
    }
}