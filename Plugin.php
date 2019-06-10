<?php
/**
 * 从 Typecho 导出 Markdown 文件
 * 
 * 导出文章、页面为 Markdown 文件，支持完整的 metadata，包括 slug、分类、标签等等
 * 
 * @package Tp2MD
 * @author 熊猫小A
 * @version 1.1
 * @link https://www.imalan.cn
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Tp2MD_Plugin implements Typecho_Plugin_Interface 
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
        // 添加路由
        Helper::addRoute("route_Tp2MD","/Tp2MD","Tp2MD_Action",'action');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 删除路由
        Helper::removeRoute("route_Tp2MD");
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
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
?>
<p>
使用方法：设置好插件后访问 <mark><?php Helper::options()->index('/Tp2MD?action=save&key=[KEY]'); ?></mark> 即可。记住将 [KEY] 替换为插件中的设置的值。<br>
<mark>请保证 插件目录/cache 文件夹可写</mark>
</p>
<?php
        $t = new Typecho_Widget_Helper_Form_Element_Select(
            'postInFolder',
            array('true' => '是','false' => '否'),
            'true',
            '文章按分类保存至文件夹',
            '是否按分类将文章保存至文件夹。'
        );
        $form->addInput($t);

        $t = new Typecho_Widget_Helper_Form_Element_Text(
            'key',
            null,
            self::generateRandomString(),
            '请求 Key',
            '设置好后请勿泄露，以防别人盗取文章。'
        );
        $form->addInput($t);
    }

    /**
     * 生成随机字符串作为 key
     */
    private static function generateRandomString($length = 10) { 
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
        $randomString = ''; 
        for ($i = 0; $i < $length; $i++) { 
            $randomString .= $characters[rand(0, strlen($characters) - 1)]; 
        } 
        return $randomString; 
    }

    /**
     * 执行
     */
    public static function save()
    {
        date_default_timezone_set('Asia/Shanghai');
        $db = Typecho_Db::get();
        $options = Helper::options()->plugin('Tp2MD');
        $folder = $options->postInFolder == 'true';
        
        // 创建路径
        $d = date('Y-m-d-H-i-s');
        $pathBase = __DIR__.'/cache/'.$d;
        mkdir($pathBase);
        $pagePath = $pathBase.'/page/';
        mkdir($pagePath);
        $postPath = $pathBase.'/post/';
        mkdir($postPath);
        if($folder)
        {
            $rows = $db->fetchAll($db->select()->from('table.metas')
                ->where('table.metas.type = ?', 'category'));
            foreach ($rows as $row) {
                mkdir($postPath.$row['name']);
            }
        }

        // 导出页面
        $rows = $db->fetchAll($db->select()->from('table.contents')
            ->where('table.contents.type != ?', 'attachment'));
        foreach ($rows as $row) {
            // 建立 yaml front matter
            $front_matter = array(
                'layout' => $row['type'],
                'cid' => $row['cid'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'date' => date('Y/m/d H:i:s', $row['created']),
                'updated' => date('Y/m/d H:i:s', $row['modified']),
                'status' => $row['status']
            );

            // 作者
            $author = $db->fetchRow($db->select()->from('table.users')
                ->where('table.users.uid = ?', $row['authorId']));
            $front_matter['author'] = $author['screenName'];

            // 分类与标签
            $cates = array();
            $tags = array();
            $relations = $db->fetchAll($db->select()->from('table.relationships')
                ->where('table.relationships.cid = ?',$row['cid']));
            foreach ($relations as $relation) {
                $meta = $db->fetchRow($db->select()->from('table.metas')
                    ->where('table.metas.mid = ?',$relation['mid']));
                if($meta['type'] == 'tag') $tags[] = $meta['name'];
                if($meta['type'] == 'category') $cates[] = $meta['name'];
            }
            $front_matter['categories'] = $cates;
            $front_matter['tags'] = $tags;

            // 自定义字段
            $fields = $db->fetchAll($db->select()->from('table.fields')
                ->where('table.fields.cid = ?',$row['cid']));
            foreach ($fields as $field) {
                $front_matter[$field['name']] = $field[$field['type'].'_value'];
            }

            $output = "---".PHP_EOL;
            foreach ($front_matter as $key => $value) {
                if($key == 'categories' || $key == 'tags'){
                    $output .= $key.': '.PHP_EOL;
                    foreach ($value as $v) {
                        $output .= '  - '.$v.PHP_EOL;
                    }
                }else{
                    $output .= $key.': '.$value.PHP_EOL;
                }
            }
            $output .= "---".PHP_EOL.PHP_EOL.PHP_EOL;
            $output .= str_replace('<!--markdown-->', '', $row['text']);

            $fileName = str_replace(array(' ','/','|'), '_', $row['title']);
            $fileName = date('Y-m-d-', $row['created']).$fileName.'.md';
            $filePath = '';
            if($row['type'] == 'page'){
                $filePath = $pagePath;
            }
            else {
                $filePath = $postPath;
                if($folder) $filePath .= $cates[0].'/';
            }
            $filePath .= $fileName;
            
            if(!file_put_contents($filePath,$output))
            {
                echo '不能写入文件，请检查 cache 文件夹权限！';
                die();
            }
        }
        echo "导出成功！位置：{$pathBase}。";
    }
}
