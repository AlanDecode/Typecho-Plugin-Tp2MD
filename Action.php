<?php 
/**
 * Action.php
 * 
 * 处理请求
 * 
 * @author 熊猫小A
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Tp2MD_Action extends Widget_Abstract_Contents implements Widget_Interface_Do 
{
    /**
     * 返回请求的 JSON
     * 
     * @access public
     */
    public function action()
    {
        $options = Helper::options()->plugin('Tp2MD');
        
        if($_GET['action'] != 'save')
        {
            echo '指令错误！';
            return;
        }
        if($_GET['key'] != $options->key)
        {
            echo '无效的 Key！';
            return;
        }

        Tp2MD_Plugin::save();
    }
}