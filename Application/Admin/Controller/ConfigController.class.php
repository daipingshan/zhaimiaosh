<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/23
 * Time: 9:00
 */

namespace Admin\Controller;

use Common\Org\AliYunOss as OSS;

/**
 * Class ConfigController
 *
 * @package Admin\Controller
 */
class ConfigController extends CommonController {
    /**
     * 读取系统配置信息
     */
    public function index() {
        $content = M('config')->getFieldById(1, 'content');
        $content = unserialize($content);
        $this->assign('content', $content);
        $this->display();
    }

    /**
     * 编辑系统设置
     */
    public function edit() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $content = M('config')->getFieldById(1, 'content');
        $content = unserialize($content);
        $data    = I('post.', '', 'trim');

        //APP分享图
        if (isset($data['SHARE_POSTER']['img_data']) && !empty($data['SHARE_POSTER']['img_data'])) {
            $data['SHARE_POSTER']['img_data'] = array_values($data['SHARE_POSTER']['img_data']);
        }

        //app首页4个模块图
        if (isset($data['INDEX_4_MODULE_PICS']) && !empty($data['INDEX_4_MODULE_PICS'])) {
            $data['INDEX_4_MODULE_PICS'] = array_values($data['INDEX_4_MODULE_PICS']);
        }

        if ($content) {
            $data = array_merge($content, $data);
        }
        $post_content = serialize($data);
        $res          = M('config')->where(array('id' => 1))->save(array('content' => $post_content));
        if ($res !== false) {
            S('config', null);

            //删除旧的启动页图片
            if (isset($data['APP_INIT']['img_url']) && !empty($data['APP_INIT']['img_url'])) {
                if ($data['APP_INIT']['img_url'] != $content['APP_INIT']['img_url'] && !empty($content['APP_INIT']['img_url'])) {
                    $path_info = parse_url($content['APP_INIT']['img_url']);
                    $oss = new OSS();
                    $oss->deleteObject(substr($path_info['path'], 1));
                }
            }

            //删除不用的APP分享图
            if (isset($data['SHARE_POSTER']['img_data']) && !empty($data['SHARE_POSTER']['img_data'])) {
                $pics = array_diff($content['SHARE_POSTER']['img_data'], $data['SHARE_POSTER']['img_data']);
                foreach ($pics as $key => $pic) {
                    $path_info = parse_url($pic);
                    $oss = new OSS();
                    $oss->deleteObject(substr($path_info['path'], 1));
                }
            }

            //删除不用的app首页4个模块图
            if (isset($data['INDEX_4_MODULE_PICS']) && !empty($data['INDEX_4_MODULE_PICS'])) {
                $pics = array_diff($content['INDEX_4_MODULE_PICS'], $data['INDEX_4_MODULE_PICS']);
                foreach ($pics as $key => $pic) {
                    $path_info = parse_url($pic);
                    $oss = new OSS();
                    $oss->deleteObject(substr($path_info['path'], 1));
                }
            }

            //删除旧的小程序图片
            if (isset($data['MINI_PROGRAM_SHARE']['pic']) && !empty($data['MINI_PROGRAM_SHARE']['pic'])) {
                if ($data['MINI_PROGRAM_SHARE']['pic'] != $content['MINI_PROGRAM_SHARE']['pic'] && !empty($content['MINI_PROGRAM_SHARE']['pic'])) {
                    $path_info = parse_url($content['MINI_PROGRAM_SHARE']['pic']);
                    $oss = new OSS();
                    $oss->deleteObject(substr($path_info['path'], 1));
                }
            }

            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }
}