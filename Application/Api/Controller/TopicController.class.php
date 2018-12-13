<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/4/18
 * Time: 10:48
 */

namespace Api\Controller;

/**
 * 专题
 * Class TopicController
 *
 * @package Api\Controller
 */
class TopicController extends CommonController {

    /**
     * 专题详情
     */
    public function detail() {
        $topic_id = I('get.topic_id', 0, 'int');
        if (empty($topic_id)) {
            $this->output('专题编号不能为空！');
        }

        $this->output('ok', 'success', $this->_getTopicDetail($topic_id));
    }

}