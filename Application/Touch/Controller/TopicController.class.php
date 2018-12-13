<?php
/**
 * Created by PhpStorm.
 * User: Grannis
 * Date: 2017/10/10
 * Time: 14:44
 */

namespace Touch\Controller;

/**
 * 专题分享
 * m.zhaimiaosh.com/topic/detail?title=标题&desc=简介&uid=123456&ids=123,456,789
 * Class TopicController
 *
 * @package Touch\Controller
 */
class TopicController extends CommonController {

    /**
     * 专题分享
     *
     * @param $title
     * @param $desc
     * @param $uid
     * @param $ids
     * @return array
     */
    public function detail() {
        $title   = I('get.title', '', 'trim,urldecode');
        $desc    = I('get.desc', '', 'trim,urldecode');
        $token   = I('get.token', '', 'trim,urldecode');
        $uid     = I('get.uid', 0, 'int');
        $ids     = I('get.ids', '', 'trim');
        $num_iid = I('get.num_iid', '', 'trim');

        //  解析用户的token
        if (!empty($token)) {
            $user_id = $this->analyzeToken($token);
            if (!empty($user_id)) {
                $uid = $user_id;
            }
        }
        if ($this->openSearchStatus == true) {
            if ($num_iid) {
                $ids   = explode(',', $num_iid);
                $count = count($ids);
                $query = "num_iid:'" . implode("' OR num_iid:'", array_values($ids)) . "'";
            } else {
                $ids   = explode(',', $ids);
                $count = count($ids);
                $query = "id:'" . implode("' OR id:'", array_values($ids)) . "'";
            }
            $data = $this->_getOpenSearchList($query, null, null, 0, $count, 'touch');
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $newStr = preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $val['title']);
                    $count  = mb_strlen($newStr, 'UTF8');
                    $str1   = mb_substr($newStr, 0, 20, 'utf-8');
                    if ($count > 20) {
                        $val['title'] = $str1 . '...';
                    } else {
                        $val['title'] = $val['title'];
                    }
                    $val['item_url'] = U('Item/index', array('id' => $val['id'], 'uid' => $uid));
                }
            }
        } else {
            $field = 'id,title,pic_url,volume,shop_type,price,coupon_price,quan';
            if ($num_iid) {
                $where['num_iid'] = array('in', $ids);
            } else {
                $where['id'] = array('in', $ids);
            }
            $data = M('items')->field($field)->where($where)->select();
            foreach ($data as &$val) {
                $newStr = preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $val['title']);
                $count  = mb_strlen($newStr, 'UTF8');
                $str1   = mb_substr($newStr, 0, 20, 'utf-8');
                if ($count > 20) {
                    $val['title'] = $str1 . '...';
                } else {
                    $val['title'] = $val['title'];
                }
                $val['item_url'] = U('Item/index', array('id' => $val['id'], 'uid' => $uid));
            }
        }

        $newStr = preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $title);
        $count  = mb_strlen($newStr, 'UTF8');
        $str1   = mb_substr($newStr, 0, 20, 'utf-8');
        if ($count > 20) {
            $title = $str1 . '...';
        }

        $items_list = array_filter($data);

        $this->assign('title', $title);
        $this->assign('desc', $desc);
        $this->assign('items_list', $items_list);
        $this->display();
    }

    /**
     * 解析用户的token
     *
     * @param $token
     * @return mixed
     */
    public function analyzeToken($token) {
        $uid = M('user')->where(array('token' => $token))->getField('id');
        if (!$uid) {
            return '';
        } else {
            return $uid;
        }
    }

    /**
     * 获取加盟用户信息
     *
     * @param $user
     * @param $mobile
     * @param $type
     * @return array
     */
    public function getJoinConsumer() {
        $param    = array(
            'user'   => I('get.user', '', 'trim'),
            'mobile' => I('get.mobile', '', 'trim'),
            'type'   => I('get.type', 0, 'int'),
        );
        $jump_url = 'http://www.zhaimiaosh.com/index.html';
        if (empty($param['user']) || empty($param['mobile']) || empty($param['type'])) {
            $this->error('姓名，手机或报名类型不能为空', $jump_url);
            exit;
        }
        $count = M('join_consumer')->where(array('mobile' => $param['mobile']))->count('id');
        if ($count != 0) {
            $this->error('报名失败，该手机号已经注册，请检查手机号', $jump_url);
            exit;
        }
        $param['create_time'] = time();
        $join_status          = M('join_consumer')->add($param);
        if ($join_status != 0) {
            $this->success('报名成功', $jump_url);
            exit;
        } else {
            $this->error('报名失败', $jump_url);
            exit;
        }
    }

}