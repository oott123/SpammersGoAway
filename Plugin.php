<?php
/**
 * Spammers Go Away ! 用高大上的方法屏蔽垃圾评论
 * 
 * @package SpammersGoAway 
 * @author oott123
 * @version 0.0.1
 * @link http://oott123.com/
 */
//error_reporting(E_ALL);
class SpammersGoAway_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->comment = array(__CLASS__, 'postcomment');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'render');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('hardlevel', NULL, '1', '挑战难度等级', '请在1~4之间取值。难度等级越低，spammer越容易制造垃圾信息。难度等级越高，用户体验越差（提交速度越慢）。'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('timeout', NULL, '600', '挑战超时', '前端浏览器计算挑战码的间隔。请控制在适当的时间内（600~1200最佳）。以秒为单位。'));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插入尾部js
     * 
     * @access public
     * @return void
     */
    public static function render($t)
    {
        if($t->is('single') && $t->allow('comment') && !Typecho_Widget::widget('Widget_User')->hasLogin()){
            $key1 = time();
            $hardlevel = Typecho_Widget::widget('Widget_Options')->plugin('SpammersGoAway')->hardlevel;
            $timeout = Typecho_Widget::widget('Widget_Options')->plugin('SpammersGoAway')->timeout;
            echo <<<JS
<script>
    var hardlevel = $hardlevel;
    var timeout = $timeout;
    var timeoffset = {$key1}*1000 - (new Date().getTime());
</script>

JS;
            $dirinfo = explode('plugins'.DIRECTORY_SEPARATOR, dirname(__FILE__));
            echo '<script src="';
            Typecho_Widget::widget('Widget_Options')->siteUrl('usr/plugins/'.$dirinfo[1].'/footer.js');
            echo '"></script>';
        }
    }

    /**
     * 评论时的判断
     * @return $comment
     * @access public
     */
    public static function postcomment($comment, $post){
        if(Typecho_Widget::widget('Widget_User')->hasLogin()){
            //不判断登录用户的评论
            return $comment;
        }
        if($err = self::postcode()){
            throw new Typecho_Widget_Exception('本网站被 Spammers Go Away 保护，您暂时无法发表评论。提交错误：'.$err);
        }
        return $comment;
    }
    /**
     * 检查评论发表提交的code
     * @return int 通过/0|不通过/N
     */
    private static function postcode(){
        error_reporting(E_ALL);
        $hardlevel = Typecho_Widget::widget('Widget_Options')->plugin('SpammersGoAway')->hardlevel;
        $timeout = Typecho_Widget::widget('Widget_Options')->plugin('SpammersGoAway')->timeout;
        $hashfirst = str_repeat('3', $hardlevel);
        if(!isset($_POST['SpammersGoAwayCode'])) return 1;    //判断是否提交了code
        list($key1,$key2,$code) = explode(',', $_POST['SpammersGoAwayCode']);
        if(substr($code, 0, $hardlevel)!=$hashfirst) return 2;   //判断code的前N位是否正确
        if(abs(time() - $key1) >= $timeout) return 3;  //key1是否超时
        if(hash('ripemd160', $key1.$key2) != $code) return 4; //判断提交的code是否正确
        return 0;
    }
}
