<?php
/**
 * 分析HTML文件列表中 HTML 标签 并替换 为 dedecms标签
 * 文件编码限定 UTF-8
 * 注意文件内容相同是根据 HTML 标签的完全匹配 忽略 <script></script> <style></style> <link />
 * 现阶段小BUG：
 * TODO 注意代码中有时有修改代码的情况（手动删减内容）
 * 1、同一级标签数组，有可能第一个是特殊情况（新闻第一条是有图片的，其他没有）
 * 2、多个相同标签 arclist 替换完 剩下的 <a> 标签 全部替换为 type 错误量多。
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);  //不限制 执行时间
date_default_timezone_set('Asia/Shanghai');
header("content-Type: text/javascript; charset=utf-8"); //语言强制
header('Cache-Control:no-cache,must-revalidate');
header('Pragma:no-cache');

//todo 环境检测
//1、PHP版本 默认大于5.3
//2、函数库检测：打开文件夹需要 system 函数

//定义根目录
define('WEB_ROOT', str_replace("\\", '/', dirname(__FILE__)) );
define('INPUT_DIR', WEB_ROOT . '/input/');
define('OUTPUT_DIR', WEB_ROOT . '/output/');
define('VENDOR_DIR', WEB_ROOT . '/vendor/');

//比较HTML字符串中HTML标签的最小开始数量
define('HTML_MIN_SEGMENT_COUNT', 1);
define('LOG_DIFFERENT_HEAD', WEB_ROOT . '/different_head.log');
define('LOG_DIFFERENT_FOOT', WEB_ROOT . '/different_foot.log');

//织梦标签替换类型，pc：电脑版；wap：手机版
define('DEDECMS_TAG_TYPE', 'pc');
//define('DEDECMS_TAG_TYPE', 'wap');

//定义模板常量
define('BR', "\r\n");

//清空输出目录 和 日志
deldir(OUTPUT_DIR);

//======================================================================================================================
//======================================================================================================================

//HTML 单闭合标签
$html_single_tag = array('br', 'hr', 'area', 'base', 'img', 'input', 'link', 'meta', 'basefont', 'param', 'col', 'frame', 'embed');

//存储运行中的变量
$comprehensive = array();

//获取文件列表
$file_list = get_file_list();

//分析HTML标签并提出来存放
$html_body = '';

//遍历 输入文件列表
foreach($file_list as $key=>$item){
    $html_content = get_file_content($item);

    //$html_content 所有内容替换 例如 路径起始变量 Public/
    $html_content = str_replace('Public/', '/Public/', $html_content);

    $html_body = $html_content;
    //截取HTML 的body 标签
    if(false !== stripos($html_body, '<body')){
        $html_body = substr($html_body, stripos($html_body, '<body'));
    }

    if(false !== stripos($html_body, '</body>')){
        $html_body = substr($html_body, 0, stripos($html_body, '</body>') + 7);
    }


    //region HTML_body 内容 预处理 注释、脚本、样式等，暂时保存到变量里边
    // HTML_body 内容 预处理 处理注释 代码 <!-- <div></div> --> 替换为 KEY #!--1--#
    // todo 注意注释里边有 <!--[if IE 6]> 未处理！
    $matches_notes = array();
    preg_match_all('/<!--[\s\S]*?-->/i', $html_body, $matches_notes);
    if(isset($matches_notes[0][0])){
        foreach($matches_notes[0] as $k=>$v){
            $html_body = str_replace($v, '#!--' . $k . '--#', $html_body);
        }
    }


    // HTML_body 内容 预处理 处理脚本 代码 <script[\s|>][\s\S]*?<\/script> 替换为 KEY <script>1</script>
    $matches_script = array();
    preg_match_all('/<script[\s|>][\s\S]*?<\/script>/i', $html_body, $matches_script);
    if(isset($matches_script[0][0])){
        foreach($matches_script[0] as $k=>$v){
            $html_body = str_replace($v, '<script>' . $k . '</script>', $html_body);
        }
    }

    // HTML_body 内容 预处理 处理注释 代码 /<style[\s|>][\s\S]*?<\/style>/i 替换为 KEY <style>1</style>
    $matches_style = array();
    preg_match_all('/<style[\s|>][\s\S]*?<\/style>/i', $html_body, $matches_style);
    if(isset($matches_style[0][0])){
        foreach($matches_style[0] as $k=>$v){
            $html_body = str_replace($v, '<style>' . $k . '</style>', $html_body);
        }
    }

    // HTML_body 内容 预处理 处理外链样式 代码 /<link [\s|>][\s\S]*/i 替换为 KEY <link>1</link>
    $matches_link = array();
    preg_match_all('/<link [\s|>][\s\S]*?>/i', $html_body, $matches_link);
    if(isset($matches_link[0][0])){
        foreach($matches_link[0] as $k=>$v){
            $html_body = str_replace($v, '<link>' . $k . '</link>', $html_body);
        }
    }
    //endregion

    echo $key + 1 . ' - ' . iconv('GB2312', 'UTF-8//IGNORE', $item);
    echo BR;

    $file_name = basename(iconv('GB2312', 'UTF-8//IGNORE', $item));

    //region 处理页面基本路由 start
    if('FOOTER.html' == $file_name){
       echo '处理底部代码' . BR;
       $html_body = handle_foorer($html_body);
    }
    elseif('HEAD.html' == $file_name){
       echo '处理头部代码' . BR;
        $html_body = handle_head($html_body);
    }
    elseif('index.html' == $file_name){
        echo '处理首页代码' . BR;
        $html_content = str_replace('<title>{dede:field.typename /}_{dede:global.cfg_webname/}</title>', '<title>{dede:global.cfg_webname/}</title>', $html_content);
        $html_body = handle_index($html_body);
    }
    elseif(preg_match('/单页/', $file_name)){
        echo '处理单页代码' . BR;
        $html_body = handle_page($html_body);
    }
    elseif(preg_match('/列表/', $file_name)){
        echo '处理列表页代码' . BR;
        $html_body = handle_list($html_body);
    }
    elseif(preg_match('/详情/', $file_name)){
        echo '处理详情页代码' . BR;
        $html_content = str_replace('<title>{dede:field.typename /}_{dede:global.cfg_webname/}</title>', '<title>{dede:field.title /}_{dede:global.cfg_webname/}</title>', $html_content);
        $html_body = handle_show($html_body);
    }
    else{
        echo '处理其他页面代码' . BR;
        $html_body = handle_index($html_body);
    }
    //endregion

    //region HTML_body 内容 预处理 注释、脚本、样式等 还原操作
    // HTML_body 内容处理 处理注释 注释还原
    if(isset($matches_notes[0][0])){
        foreach($matches_notes[0] as $k=>$v){
            $html_body = str_replace('#!--' . $k . '--#', $v, $html_body);
        }
    }

    // HTML_body 内容处理 处理脚本 脚本还原
    if(isset($matches_script[0][0])){
        foreach($matches_script[0] as $k=>$v){
            $html_body = str_replace('<script>' . $k . '</script>', $v, $html_body);
        }
    }

    // HTML_body 内容处理 处理样式 脚本样式
    if(isset($matches_style[0][0])){
        foreach($matches_style[0] as $k=>$v){
            $html_body = str_replace('<style>' . $k . '</style>', $v, $html_body);
        }
    }

    // HTML_body 内容处理 处理外链样式 脚本外链样式
    if(isset($matches_link[0][0])){
        foreach($matches_link[0] as $k=>$v){
            $html_body = str_replace('<link>' . $k . '</link>', $v, $html_body);
        }
    }
    //endregion


    //替换完成 输出 文件
//    echo $html_body;
    // 有body 替换body 没有直接输出
    if(false !== stripos($html_body, '<body')) {
        $html_content = preg_replace('/<body[\s\S]*<\/body>/', $html_body, $html_content);
    }
    else{
        $html_content = $html_body;
    }

    //文件后缀名称替换
    $item = str_ireplace('.html', '.htm', $item);

    put_file_content($item, $html_content);
}

echo BR . BR ."恭喜，处理完成！";


//======================================================================================================================
// 项目所用函数
//======================================================================================================================

/**
 * 替换底部标签
 * @param $html_body
 * @return mixed
 */
function handle_foorer($html_body){
    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);
//    var_dump($html_segments);
//    exit;
    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级 代码下有 相同的地方 暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

//    var_dump($result_segments);

    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){

            if(0 == $k){

                if(preg_match('/首页|主页|Home/i', $v)){
//                    echo $v;
                    $matches_home = array();
                    preg_match('/<a [^h]*href[\s]*=[\s]*[\'"](.*?)[\'"][^>]*>/i', $v, $matches_home);
                    if(isset($matches_home[1])){
                        if('pc' == DEDECMS_TAG_TYPE){
                            $replace_dedecms['home'] = str_replace($matches_home[1], '/index.php', $v);
                        }
                        elseif('wap' == DEDECMS_TAG_TYPE){
                            $replace_dedecms['home'] = str_replace($matches_home[1], '/m/index.php', $v);
                        }
                        else{
                            //todo 预留 暂无用
                        }

                        $replace_segment = '<home>';
//                        $result_body = str_replace($v, $replace_segment, $result_body);
                    }
//                    var_dump($matches_home);
//                    echo $matches_home[1];
//                    exit;
                }

                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('index_channel', $v);;
                    $replace_segment .= '<' . $replace_key . '>';
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('index_channel', $v);;
                    $replace_segment .= '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
                //$replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }
        }
    }

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

//    echo $result_body;
//    echo $html_body;
//    exit;
    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 替换头部标签
 * @param $html_body
 * @return mixed
 */
function handle_head($html_body){
//    echo $html_body;
    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);

//    var_dump($html_segments);
    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级 代码下有 相同的地方 暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

//    var_dump($result_segments);

    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){

            if(0 == $k){

                if(preg_match('/首页|主页|Home/i', $v)){
//                    echo $v;
                    $matches_home = array();
                    preg_match('/<a [^h]*href[\s]*=[\s]*[\'"](.*?)[\'"][^>]*>/i', $v, $matches_home);
                    if(isset($matches_home[1])){
                        if('pc' == DEDECMS_TAG_TYPE){
                            $replace_dedecms['home'] = str_replace($matches_home[1], '/index.php', $v);
                        }
                        elseif('wap' == DEDECMS_TAG_TYPE){
                            $replace_dedecms['home'] = str_replace($matches_home[1], '/m/index.php', $v);
                        }
                        else{
                            //todo 预留 暂无用
                        }

                        $replace_segment = '<home>';
//                        $result_body = str_replace($v, $replace_segment, $result_body);
                    }
//                    var_dump($matches_home);
//                    echo $matches_home[1];
//                    exit;
                }

                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('index_channel', $v);;
                    $replace_segment .= '<' . $replace_key . '>';
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('index_channel', $v);;
                    $replace_segment .= '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
                //$replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }
        }
    }

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

//    echo $result_body;
//    echo $html_body;
//    exit;
    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 处理首页模板标签替换
 * @param $html_body
 * @return mixed
 */
function handle_index($html_body){
    // 替换页面内容新的算法思路
    // 1、先替换最长最多的内容 如超过 3 或 5 的相同代码段 肯定是 二级栏目或者 文档列表 其余替换为空 ！
    // 2、第一步替换完成后，则只剩下，小于3 或 5的代码段，有<a> 标签的话 那90%就是 type （当然也有只有一篇文章的分类，这个先不考虑）

    // 2019年6月18日16:35:51 这次更新思路
    // 1、代码进行分段递归处理
    // 2、整个同级代码段，先去掉第一个（大于1），再进行比较 （解决第一个是头条图片等情况）
    // 3、代码进行精确替换，没有匹配到的暂时不替换。

    // ###问题
    // 切换特效中的代码替换会替换整个切换的代码块

    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);

//    var_dump($html_segments);
//    exit;

    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级代码下有相同的地方。暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){
            if(0 == $k){
                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('index_channel_typeid', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
//                    $replace_segment .= BR . BR . format_pc('arclist', $v);
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('index_channel_typeid', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
//        $replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }

        }

    }


    //替换剩余只是“更多”的 <a> 标签
    $pattern_a = '/<a [\s\S]*?<\/a>/i';
    $matches_a = array();
    preg_match_all($pattern_a, $result_body, $matches_a);
//    var_dump($matches_a);
//    echo $result_body;
    if(isset($matches_a[0][0])){
        foreach($matches_a[0] as $item){
            //截取 a 标签包裹的内容
            $innerText = substr($item, strpos($item, '>') + 1);
            $innerText = substr($innerText, 0, strrpos($innerText, '<'));
            //判断 a 标签包裹的内容
            if(!is_skip_str($innerText)){
                continue;
            }

            if('pc' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_pc('type', $item) . BR, $result_body);
            }
            elseif('wap' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_wap('type', $item) . BR, $result_body);
            }
            else{
                //todo 预留 暂无用
            }
        }
    }


//    echo $result_body;

//    exit;

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 处理单页模板标签替换
 * @param $html_body
 * @return mixed
 */
function handle_page($html_body){
    // 替换页面内容新的算法思路
//    1、先替换最长最多的内容 如超过 3 或 5 的相同代码段 肯定是 二级栏目或者 文档列表 其余替换为空 ！
//    2、第一步替换完成后，则只剩下，小于3 或 5的代码段，有<a> 标签的话 那90%就是 type （当然也有只有一篇文章的分类，这个先不考虑）

    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);
//    var_dump($html_segments);
//    exit;

    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级 代码下有 相同的地方 暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

//    var_dump($result_segments);
    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){
            if(0 == $k){
                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
//                    $replace_segment .= BR . BR . format_pc('arclist', $v);
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
//        $replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }

        }

    }

    //替换剩余只是“更多”的 <a> 标签
    $pattern_a = '/<a [\s\S]*?<\/a>/i';
    $matches_a = array();
    preg_match_all($pattern_a, $result_body, $matches_a);
//    var_dump($matches_a);
//    echo $result_body;
    if(isset($matches_a[0][0])){
        foreach($matches_a[0] as $item){
            //截取 a 标签包裹的内容
            $innerText = substr($item, strpos($item, '>') + 1);
            $innerText = substr($innerText, 0, strrpos($innerText, '<'));
            //判断 a 标签包裹的内容
            if(!is_skip_str($innerText)){
                continue;
            }

            if('pc' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_pc('type', $item) . BR, $result_body);
            }
            elseif('wap' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_wap('type', $item) . BR, $result_body);
            }
            else{
                //todo 预留 暂无用
            }
        }
    }

//    echo $result_body;

//    exit;

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 处理列表页模板标签替换
 * @param $html_body
 * @return mixed
 */
function handle_list($html_body){
    // 替换页面内容新的算法思路
//    1、先替换最长最多的内容 如超过 3 或 5 的相同代码段 肯定是 二级栏目或者 文档列表 其余替换为空 ！
//    2、第一步替换完成后，则只剩下，小于3 或 5的代码段，有<a> 标签的话 那90%就是 type （当然也有只有一篇文章的分类，这个先不考虑）

    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);
//    var_dump($html_segments);
//    exit;

    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级 代码下有 相同的地方 暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

//    var_dump($result_segments);
    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){
            if(0 == $k){
                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'list_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('list', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
//                    $replace_segment .= BR . BR . format_pc('arclist', $v);
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'list_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('list', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
//        $replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }

        }

    }

    //替换剩余只是“更多”的 <a> 标签
    $pattern_a = '/<a [\s\S]*?<\/a>/i';
    $matches_a = array();
    preg_match_all($pattern_a, $result_body, $matches_a);
//    var_dump($matches_a);
//    echo $result_body;
    if(isset($matches_a[0][0])){
        foreach($matches_a[0] as $item){
            //截取 a 标签包裹的内容
            $innerText = substr($item, strpos($item, '>') + 1);
            $innerText = substr($innerText, 0, strrpos($innerText, '<'));
            //判断 a 标签包裹的内容
            if(!is_skip_str($innerText)){
                continue;
            }

            if('pc' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_pc('type', $item) . BR, $result_body);
            }
            elseif('wap' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_wap('type', $item) . BR, $result_body);
            }
            else{
                //todo 预留 暂无用
            }
        }
    }


//    echo $result_body;

//    exit;

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 处理列表页模板标签替换
 * @param $html_body
 * @return mixed
 */
function handle_show($html_body){
    // 替换页面内容新的算法思路
//    1、先替换最长最多的内容 如超过 3 或 5 的相同代码段 肯定是 二级栏目或者 文档列表 其余替换为空 ！
//    2、第一步替换完成后，则只剩下，小于3 或 5的代码段，有<a> 标签的话 那90%就是 type （当然也有只有一篇文章的分类，这个先不考虑）

    $result_body = $html_body;
    $html_segments = get_multilayer_html($html_body);
//    var_dump($html_segments);
//    exit;

    $result_segments = array();

    foreach($html_segments as $index=>$item){
        // todo 同一级 代码下有 相同的地方 暂未处理
        get_son_segments_recursion($item,$result_segments);
    }

    $result_segments = array_values($result_segments);

//    var_dump($result_segments);
    $replace_segment = '';
    $replace_dedecms = array();

    foreach($result_segments as $key=>$value){
//        echo $value[0];
//        echo BR . BR;
        foreach($value as $k=>$v){
            if(0 == $k){
                if('pc' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_pc('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
//                    $replace_segment .= BR . BR . format_pc('arclist', $v);
                }
                elseif('wap' == DEDECMS_TAG_TYPE){
                    $replace_key = 'channel_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('channel', $v);;
                    $replace_segment = '<' . $replace_key . '>';

                    $replace_key = 'arclist_' . $key;
                    $replace_dedecms[$replace_key] = BR . format_wap('arclist', $v);;
                    $replace_segment .= BR . '<' . $replace_key . '>';
                }
                else{
                    //todo 预留 暂无用
                }

                // 添加原来的标签代码
//        $replace_segment .= BR . BR . $value[0];

                $result_body = str_replace($v, $replace_segment, $result_body);
            }
            else{
                $result_body = str_replace($v, '', $result_body);
            }

        }

    }

    //替换剩余只是“更多”的 <a> 标签
    $pattern_a = '/<a [\s\S]*?<\/a>/i';
    $matches_a = array();
    preg_match_all($pattern_a, $result_body, $matches_a);
//    var_dump($matches_a);
//    echo $result_body;
    if(isset($matches_a[0][0])){
        foreach($matches_a[0] as $item){
            //截取 a 标签包裹的内容
            $innerText = substr($item, strpos($item, '>') + 1);
            $innerText = substr($innerText, 0, strrpos($innerText, '<'));
            //判断 a 标签包裹的内容
            if(!is_skip_str($innerText)){
                continue;
            }

            if('pc' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_pc('type', $item) . BR, $result_body);
            }
            elseif('wap' == DEDECMS_TAG_TYPE){
                $result_body = str_replace($item, BR . format_wap('type', $item) . BR, $result_body);
            }
            else{
                //todo 预留 暂无用
            }
        }
    }


//    echo $result_body;

//    exit;

    //统一 替换代码
    foreach($replace_dedecms as $key=>$value){
        $result_body = str_replace('<' . $key . '>', $value, $result_body);
    }

    // 替换上一步中替换的多个空行为一行或为空
    $result_body = preg_replace('/^\s+$/m', '', $result_body);

    return $result_body;
}

/**
 * 获取HTML body 中 第一个多层（>1）同级的HTML片段
 * @param $html_body
 * @return array
 */
function get_multilayer_html($html_body){
    $html_temp_body = $html_body;
    $html_segments = array();
    //匹配到HTML中body里边的 第一级 标签 注意 不匹配 <div> 直接闭合的开始标签 只匹配有空格的
    $matches_head_tags = array();
    preg_match_all('/\n<[a-z1-6]+ /i', $html_body, $matches_head_tags);

    // 得到一级的闭合标签
    if(isset($matches_head_tags[0][0])){
        foreach($matches_head_tags[0] as $key=>$value){

            $html_segment = get_closing_tag_html($value, $html_temp_body);
            $html_segment_length = strlen($html_segment);
            $html_segment_offset = strpos($html_temp_body, $html_segment);

            $html_temp_body = substr($html_temp_body, $html_segment_offset + $html_segment_length);

            $html_segments[] = $html_segment;
        }
    }

    return $html_segments;
}

/**
 * 判断代码数组HTML标签是否相同（即需要替换的部分）
 * TODO 注意暂时 只匹配有<a>标签的代码
 * @param $html_segments
 * @param array $condition TODO 条件参数暂未启用
 * @return bool
 */
function judege_segments($html_segments, $condition=array()){
//    var_dump($html_segments);
    $result = true;
    $html_segment = clear_html_attr_content($html_segments[0]);;

    foreach($html_segments as $item){

        $html_segment_temp = clear_html_attr_content($item);

        // 代码中判断 必须有<a>标签
        if(false === stripos($html_segment_temp, '<a>')){
            $result = false;
            break;
        }

        if(0 !== strcmp($html_segment, $html_segment_temp)){
            $result = false;
            break;
        }
    }

    return $result;
}

/**
 * 得到下级代码段的数组 如：
 * <div>
 *   <h1></h1>
 *   <ul><li></li></ul>
 * </div>
 * 返回：
 * array(
0 => <h1></h1>
1 => <ul><li></li></ul>
 * );
 * @param $html_segment
 * @return array
 */
function get_son_segments($html_segment){
    $son_segments = array();
    $html_total_body = $html_segment;
    $html_start_tag = substr($html_total_body,0, strpos($html_total_body, '>') + 1);
    $html_end_tag = substr($html_total_body, strrpos($html_total_body, '<'));
    // 去掉最外层的HTML标签 和 最外层标签 与 内部第一个标签之间的其它东西。如： 文本
    $html_total_body = substr($html_total_body, strlen($html_start_tag));
    $html_total_body = substr($html_total_body, 0, -strlen($html_end_tag));
    $html_total_body = substr($html_total_body, strpos($html_total_body, '<'));
    $html_total_body = substr($html_total_body, 0, strrpos($html_total_body, '>') + 1);

//    echo 'html_total_body:  ';
//    echo $html_total_body;
//    echo "\r\n\r\n";

    do{

//        echo 'html_total_body:  ';
//        echo $html_total_body;
//        echo "\r\n\r\n";

        $html_head_tag = substr($html_total_body, strpos($html_total_body, '<'));
        $html_head_tag = substr($html_head_tag, 0, strpos($html_head_tag, '>') + 1);

//        echo 'html_head_tag:  ';
//        echo $html_head_tag;
//        echo "\r\n\r\n";
//exit;
        // 此处字符串 判定为 <a> 长度为3 但数组从0开始所以为 2 。
//        echo $html_head_tag[2];

        if(!isset($html_head_tag[2]) || ('/' == $html_head_tag[1])){
            break;
        }

        $html_segment_temp = get_closing_tag_html($html_head_tag, $html_total_body);
//        echo $html_segment_temp;
//        echo "\r\n\r\n";
        $son_segments[] =  $html_segment_temp;
//        $html_total_body = str_replace($html_segment_temp, '', $html_total_body); //TODO 此处可能有bug 即代码相同直接替换完了。
        $html_total_body = trim($html_total_body);
        $html_total_body = substr($html_total_body, strlen($html_segment_temp)); //TODO 此处可能有bug strlen 多字节字符
//        echo "============================================================================";
//        echo $html_total_body;
//        echo "\r\n\r\n";
//        exit;
    }while(true);

    return $son_segments;
}

/**
 * 递归获取 下级代码数组 并判断数组是否是 替换部分
 * @param $html_segment
 * @param $result
 * @param $index
 */
function get_son_segments_recursion($html_segment, &$result){

    $html_segments = get_son_segments($html_segment);

//    var_dump($html_segments);
//    echo "\r\n\r\n";
//exit;
    // todo 此处判断代码段是否为替换部分 此处可以添加其他参数控制脚本代码段。如 $condition
    if(HTML_MIN_SEGMENT_COUNT < count($html_segments) ){
        if(judege_segments($html_segments)){
            $result[] = $html_segments;
            return;
        }
    }
//    var_dump($html_segments);

    foreach($html_segments as $value){
        //递归处理代码段
        get_son_segments_recursion($value, $result);
    }

}


/**
 * 根据 HTML 开始标签 返回该标签的整段闭合HTML代码
 * TODO 注意此函数未处理 注释中的代码 <!-- --> 脚本代码 样式代码
 * !可能有多字节字符问题
 * 不匹配 </div > 闭合标签中有空格问题
 * @param $tag_start
 * @param $html
 * @return bool|string
 */
function get_closing_tag_html($tag_start, $html){
    if(empty($tag_start) || empty($html)){
        exit(__LINE__ . __FUNCTION__ . ' Parameters Error!');
    }

    //HTML 单闭合标签
    $html_single_tag = array('br', 'hr', 'area', 'base', 'img', 'input', 'link', 'meta', 'basefont', 'param', 'col', 'frame', 'embed');

    $html_fragment = ''; //HTML闭合标签整段代码

    //直接付给body 可能用于 body 内部代码段
    $html_body = $html;

    if(false !== stripos($html, '<body')){
        $html_body = substr($html, stripos($html, '<body'));
    }

    if(false !== stripos($html_body, '</body>')){
        $html_body = substr($html_body, 0, stripos($html_body, '</body>') + 7);
    }

    //如果没有找到开始代码段
    if(stripos($html_body, $tag_start) !== false){
        $tag_name_temp = explode(' ', $tag_start);
        $tag_name = substr($tag_name_temp[0], 1);
        $tag_name = str_replace(array('<', '>'), '', $tag_name);


        $html_start = substr($html_body, strpos($html_body, $tag_start));
        if(in_array($tag_name, $html_single_tag)){
            $html_fragment = substr($html_start, 0, strpos($html_start, '>') + 1);
        }
        else{

            $html_tag_end = '</' . $tag_name . '>';
            $html_tag_end_count = substr_count($html_body, $html_tag_end);

            $html_fragment = substr($html_start, 0, strpos($html_start, $html_tag_end) + strlen($html_tag_end));
            $html_fragment_length = strlen($html_fragment);
            $html_tag_start_count = substr_count($html_fragment, '<' . $tag_name . ' ') + substr_count($html_fragment, '<' . $tag_name . '>');
            $end_count = 1; //标签结束标志

            //遍历HTML 闭合标签代码 找到闭合位置
            for($i=1; $i<$html_tag_end_count; $i++){

                if($html_tag_start_count > $end_count){

                    $html_fragment = substr($html_start, $html_fragment_length);
                    $html_fragment = substr($html_fragment, 0, strpos($html_fragment, $html_tag_end) + strlen($html_tag_end));
                    $html_fragment = substr($html_start, 0, $html_fragment_length + strlen($html_fragment));
                    $html_fragment_length = strlen($html_fragment);
                    $html_tag_start_count = substr_count($html_fragment, '<' . $tag_name . ' ') + substr_count($html_fragment, '<' . $tag_name . '>');
                    $end_count++;
                }
                else{
                    break;
                }
            }
        }

    }

    return $html_fragment;
}

/**
 * 清除 HTML中的 属性 和 内容 仅返回包裹的HTML标签 如：
 * <div><ul><li><a></a></li><li><a></a></li><li><a></a></li><li><a></a></li><li><a></a></li></ul></div>
 * @param $html
 * @return mixed|string|string[]|null
 */
function clear_html_attr_content($html){
    if(empty($html)){
        return '';
    }
    $html = str_replace(array("\r", "\n", "\t", "&nbsp;"), '', $html);  //去掉换行
    $html = preg_replace('/<script[\s|>][\s\S]*?<\/script>/i', '', $html); //去掉js
    $html = preg_replace('/<style[\s|>][\s\S]*?<\/style>/i', '', $html); //去掉css
    $html = preg_replace('/<!--[\s\S]*?-->/', '', $html); //去掉HTML注释
    $html = preg_replace('/ {2,}/', ' ', $html); //多个空格替换为一个
    $html = str_replace("> <", '><', $html);  //去掉两个标签中间的空格
    $html = trim($html); // 去掉两边的空白

    $pattern_html_tags = '/<[a-zA-Z1-6]+[\s|>]{1}/i'; //匹配所有标签 (用\s包括回车)
    $matches_html_tags = array();
    preg_match_all($pattern_html_tags, $html, $matches_html_tags);

    $htmlTags = array();
    if(isset($matches_html_tags[0][0])) {
        foreach ($matches_html_tags[0] as $item) {
            $htmlTag = str_replace(array('<', '>', ' '), '', $item);
            $htmlTags[] = $htmlTag;
        }
    }

    $uniqueHtmlTags = array_unique($htmlTags);
    if(isset($uniqueHtmlTags[0])){
        foreach($uniqueHtmlTags as $item){
            // todo xslooi 此处有bug li 会替换 link 、 b 会替换 body 和 br
            $html = preg_replace('/<' . $item . '(?!a|b|c|d|e|f|p|s|u|i|l|m|n|o|r|\/).*?>/i', '<' . $item . '>', $html);
        }
    }

    $pattern_replace = '/>[\s\S]*?</'; //替换中文内容的正则
    $html = preg_replace($pattern_replace, '><', $html);

    return $html;
}

/**
 * 电脑站标签格式化
 * @param $tag_name
 * @param $source_code
 */
function format_pc($tag_name, $source_code){
    $pc_tags = array(
        'arclist' => array(
            'tag_start' => "{dede:arclist  flag='c,p' typeid='15' row='8' col='' titlelen='60' infolen='' imgwidth='' imgheight='' listtype='' orderby='' orderway=''  keyword=''}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'channel' => array(
            'tag_start' => "{dede:channel type='son' row='20' currentstyle=\"<li><a href='~typelink~' class='thisclass'>~typename~</a></li>\"}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'type' => array(
            'tag_start' => "{dede:type  typeid='1'}",
            'tag_end' => "{/dede:type}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:typelitpic /]" alt="[field:typename/]" title="[field:typename/]"',
                    ),
            ),
        ),

        'flink' => array(
            'tag_start' => "{dede:flink row='99'}",
            'tag_end' => "{/dede:flink}",
            'inner_title' => '[field:webname/]',
            'inner_text' => '[field:webname/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:url/]" title="[field:webname/]"',
                )
            ),
        ),

        'list' => array(
            'tag_start' => "{dede:list pagesize='12'  titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:list}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description function=\'cn_substr(@me,300)\'/]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_arclist' => array(
            'tag_start' => "{dede:arclist typeid='32' flag='p' orderby='id' orderway='asc'}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_channel' => array(
            'tag_start' => "{dede:channel type='top' row='10' }",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelink/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'index_channel_typeid' => array(
            'tag_start' => "{dede:channel typeid='1' type='son' row='20'}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'likearticle' => array(
            'tag_start' => "{dede:likearticle mytypeid='22' row='20' col='' titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:likearticle}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'productimagelist' => array(
            'tag_start' => "{dede:productimagelist}",
            'tag_end' => "{/dede:productimagelist}",
            'inner_title' => '[field:text/]',
            'inner_text' => '[field:text/]',
            'inner_img' => '[field:imgsrc/]',
            'inner_tags' => array(
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:imgsrc/]" alt="[field:text/]" title="[field:text/]"',
                    ),
            ),
        ),
    );

    return replace_tags($pc_tags[$tag_name], $source_code);

    if(isset($pc_tags[$tag_name])){
        $response_array = array(
            'state' => 1,
            'msg' => 'succ',
            'data' => '{'.$tag_name.'}' . $source_code . '{/'.$tag_name.'}',
        );

        $response_array['data'] = replace_tags($pc_tags[$tag_name], $source_code);
    }else{
        $response_array = array(
            'state' => -1,
            'msg' => 'error',
            'data' => 'format_pc Not Exists',
        );
    }

    exit(json_encode($response_array));
}

/**
 * 手机站标签格式化
 * @param $tag_name
 * @param $source_code
 */
function format_wap($tag_name, $source_code){
    $wap_tags = array(
        'arclist' => array(
            'tag_start' => "{dede:arclist  flag='c,p' typeid='15' row='8' col='' titlelen='60' infolen='' imgwidth='' imgheight='' listtype='' orderby='' orderway=''  keyword=''}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'channel' => array(
            'tag_start' => "{dede:channel type='son' row='20' currentstyle=\"<li><a href='/m/list.php?tid=~id~' class='thisclass'>~typename~</a></li>\"}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'type' => array(
            'tag_start' => "{dede:type  typeid='1'}",
            'tag_end' => "{/dede:type}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'flink' => array(
            'tag_start' => "{dede:flink row='99'}",
            'tag_end' => "{/dede:flink}",
            'inner_title' => '[field:webname/]',
            'inner_text' => '[field:webname/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:url/]" title="[field:webname/]"',
                )
            ),
        ),

        'list' => array(
            'tag_start' => "{dede:list pagesize='12'  titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:list}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description function=\'cn_substr(@me,300)\'/]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_arclist' => array(
            'tag_start' => "{dede:arclist typeid='32' flag='p' orderby='id' orderway='asc'}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_channel' => array(
            'tag_start' => "{dede:channel type='top' row='10' }",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'index_channel_typeid' => array(
            'tag_start' => "{dede:channel typeid='1' type='son' row='20'}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'likearticle' => array(
            'tag_start' => "{dede:likearticle mytypeid='22' row='20' col='' titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:likearticle}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'productimagelist' => array(
            'tag_start' => "{dede:productimagelist}",
            'tag_end' => "{/dede:productimagelist}",
            'inner_title' => '[field:text/]',
            'inner_text' => '[field:text/]',
            'inner_img' => '[field:imgsrc/]',
            'inner_tags' => array(
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:imgsrc/]" alt="[field:text/]" title="[field:text/]"',
                    ),
            ),
        ),
    );

    if(isset($wap_tags[$tag_name])){
        $response_array = array(
            'state' => 1,
            'msg' => 'succ',
            'data' => '{'.$tag_name.'}' . $source_code . '{/'.$tag_name.'}',
        );

        $response_array['data'] = replace_tags($wap_tags[$tag_name], $source_code);
    }else{
        $response_array = array(
            'state' => -1,
            'msg' => 'error',
            'data' => 'format_wap Not Exists',
        );
    }

    exit(json_encode($response_array));
}

/**
 * 替换织梦标签使用
 * @param $tags
 * @param $source_code
 * @return mixed|string
 */
function replace_tags($tags, $source_code){
//    1、根据标签匹配里边HTML标签
//    2、替换匹配到的HTML标签
//    3、再替换源码中的HTML标签
//    4、组装返回

    //初始化变量
    $result_str = '';
    $old_html_tags = array();
    $matches = array();
    $replace_html_olds = array();
    $replace_html_news = array();

    //源码整理格式化
    $source_code = trim($source_code);
    $source_code = str_replace("'", '"', $source_code);
    $source_code = str_replace('("', "('", $source_code);
    $source_code = str_replace('")', "')", $source_code);

    //匹配源码中要替换的HTML标签
    $replace_html_tags = array_keys($tags['inner_tags']);
    foreach($replace_html_tags as $item){
        $pattern = '/<' . $item . "\s+.*?" . '>/i';
        preg_match_all($pattern, $source_code, $matches);
        if(isset($matches[0][0])){
            $old_html_tags[$item] = $matches[0];
        }
    }

    //处理替换HTML中的标签
    $oi = 0;
    foreach($old_html_tags as $key=>$value){
        $attrs = array();
        if(!empty($tags['inner_tags'][$key]['attrs'])){
            $attrs = explode('|', $tags['inner_tags'][$key]['attrs']);
        }

        foreach($value as $k=>$v){
            $replace_html_olds[$oi] = $v;

            foreach($attrs as $attr){
                $pattern = '/' . $attr . "[\s]*=[\s]*\".*?\"[\s]*" . '/i';
                $v = preg_replace($pattern, '', $v);
            }

            $v = str_ireplace('<' . $key, '<' . $key . $tags['inner_tags'][$key]['replace'], $v);

            $replace_html_news[$oi] = $v;
            $oi++;
        }

    }

    $result_str .= str_ireplace($replace_html_olds, $replace_html_news, $source_code);

    //todo 2018年12月26日14:05:06  更新算法
    //1、先匹配出所有内部内容 即 匹配内容区 (?<=>)[^<>]+(?=<)
    //2、再在内容区数组里边进行其他匹配
    //3、再替换源码中内容 内部内容用 > < 包裹

    $inner_texts = array();
    $pattern = '/(?<=>)[^<>]+(?=<)/';
    preg_match_all($pattern, $result_str, $matches);

    if(isset($matches[0])){
        foreach($matches[0] as $row){
            if(!is_skip_str($row)){  // 跳过不需要替换的内容
                $inner_texts[] = $row;
            }
        }
    }

    //匹配中文字符-替换标题、描述  todo 纯英文标题暂未考虑
    if(isset($tags['inner_title']) && (0 < count($inner_texts))) {
        $chinese_texts = array(); //再次组装是为了判断他们的长短
        $pattern = '/[\sa-zA-z0-9]*[\x{4e00}-\x{9fa5}]+/u';
        foreach ($inner_texts as $key=>$val) {
            if(preg_match($pattern, $val)){
                $chinese_texts[] = $val;
                unset($inner_texts[$key]); //是中文的话 剔除掉 下边时间替换不会错乱
            }
        }

        //todo 此处有BUG 如：第一个标题内容是第二个描述的子串则发生替换错乱 解决方法：添加分割符号
        foreach($chinese_texts as $key=>$value){
            if(isset($chinese_texts[$key-1]) && (strlen(trim($chinese_texts[$key-1])) < strlen(trim($chinese_texts[$key])))){
                $result_str = str_ireplace('>' . $value . '<', '>' . $tags['inner_text'] . '<', $result_str);
            }else{
                $result_str = str_ireplace('>' . $value . '<', '>' . $tags['inner_title'] . '<', $result_str);
            }
        }
    }

    //匹配日期时间并替换
    if(isset($tags['inner_time']) && (0 < count($inner_texts))){
        //替换 年-月-日
        $pattern_year = '/[\s]*\d{2,4}.{2,4}\d{1,2}.{2,4}\d{1,2}[\s]*/';
        //再次替换 年-月
        $pattern_month = '/[\s]*\d{2,4}.{2,4}\d{1,2}[\s]*/';
        //再次替换 日
        $pattern_day = '/[\s]*[0123]{1}\d{1}[\s]*/';

        foreach ($inner_texts as $key=>$val) {
            if(preg_match($pattern_year, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' . $tags['inner_time'] . '<', $result_str);
                continue;
            }

            if(preg_match($pattern_month, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' . str_replace('-d', '', $tags['inner_time']) . '<', $result_str);
                continue;
            }

            if(preg_match($pattern_day, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' .  str_replace('Y-m-', '', $tags['inner_time']) . '<', $result_str);
                continue;
            }
        }
    }

    // 替换background url 里边的图片链接
    if(isset($tags['inner_img'])){
        $pattern = '/url[\s]*\(.*?\)/i';
        preg_match_all($pattern, $result_str, $matches);

        if(isset($matches[0])){
            foreach($matches[0] as $item){
                $result_str = str_ireplace($item, 'url(' . $tags['inner_img'] . ')', $result_str);
            }
        }
    }

    //添加 起始标签、结束标记
    return $tags['tag_start'] . "\r\n" . $result_str . "\r\n" . $tags['tag_end'];
}

/**
 * 是否跳过这个字符串 如：空字符串、中文长度大于6、“更多”关键词
 * @param $string
 * @return bool
 */
function is_skip_str($string){
    $is_skip = false;

    if(preg_match("/^[\s]+$/", $string)){
        $is_skip = true;
        return $is_skip;
    }

    if(6 < mb_strlen($string)){ //文字超过6个直接返回
        return $is_skip;
    }

    $more = array('查看', '详情', '推荐', '详细', '参数', '更多', '全部', '立即', '咨询', 'more');

    foreach($more as $item){
        if(false !== stripos($string, $item)){
            $is_skip = true;
            break;
        }
    }

    return $is_skip;
}

/**
 * 得到某个目录的文件列表
 * @param string $path_pattern
 * @return array|false
 */
function get_file_list($path_pattern=''){
    if(empty($path_pattern)){
        $path_pattern = INPUT_DIR . '*.html';
    }
    return glob($path_pattern);
}

/**
 * 得到文件内容
 * @param $file_path
 * @return false|string
 */
function get_file_content($file_path){
    return file_get_contents($file_path);
}

/**
 * 输出文件内容
 * @param $file_path
 * @param $html_body
 * @return bool|int
 */
function put_file_content($file_path, $html_body, $mode = FILE_APPEND){
    return file_put_contents(str_replace(INPUT_DIR, OUTPUT_DIR, $file_path), $html_body, $mode);
}

/**
 * 递归删除一个目录包含子目录和文件 (不包括自身)
 * @param $path
 */
function deldir($path){
    //如果是目录则继续
    if(is_dir($path)){
        //扫描一个文件夹内的所有文件夹和文件并返回数组
        $p = scandir($path);
        foreach($p as $val){
            //排除目录中的.和..
            if($val !="." && $val !=".."){
                //如果是目录则递归子目录，继续操作
                if(is_dir($path.$val)){
                    //子目录中操作删除文件夹和文件
                    deldir($path.$val.'/');
                    //目录清空后删除空文件夹
                    @rmdir($path.$val.'/');
                }else{
                    //如果是文件直接删除
                    unlink($path.$val);
                }
            }
        }
    }
}