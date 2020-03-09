<?php
/**
 * php 写日志 高并发处理方式 利用随机分散并发写的可能，在fwrite用锁并设置超时时间，理论上单机性能上phpfpm不会超过4096所以并发可以有效减少锁的情况
 * 
 * 日期
 *   |—— 随机数 (1-4096)
 *          |---- tag.log 
 */
header("Content-Type: text/html;charset=utf-8");
error_reporting(E_ALL ^ E_NOTICE);

function get_date(){

   return date("Ymd",time());
}

function randomid() {

    return rand(1, 4096);
}


function mkdir_date_dir($path){
    
    //判断目录存在否，存在给出提示，不存在则创建目录
    if (is_dir($path)){  
        return true;
    }else{
        //第三个参数是“true”表示能创建多级目录，iconv防止中文目录乱码
        $res=mkdir(iconv("UTF-8", "GBK", $path),0777,true); 
        if ($res){
            return true;
        }else{
            return false;
        }
    }
}

function get_tag($post_data){

        $data = $post_data['data'];
        // json -> array
        $info = json_decode($data,true);
    
        // 检查json里面有必要的KEY
        if(array_key_exists("tag", $info)){

            return $info['tag'];
        }
        else{
            return "unknown";
        }

}


function log_write($filepath, $content) {
    if ($fp = fopen($filepath, 'a')) {
        $startTime = microtime();
        // 对文件进行加锁时，设置一个超时时间为1ms，如果这里时间内没有获得锁，就反复获得，直接获得到对文件操作权为止，当然。如果超时限制已到，就必需马上退出，让出锁让其它进程来进行操作。
        do {
            $canWrite = flock($fp, LOCK_EX);
            if (!$canWrite) {
                usleep(round(rand(0, 100) * 1000));
            }
        } while ((!$canWrite) && ((microtime() - $startTime) < 3000));
        if ($canWrite) {
            fwrite($fp, $content);
        }
        fclose($fp);
    }
}


function run($post_data){

    $date = get_date();
    $rand_id = randomid();
    $tag = get_tag($post_data);
    
    $path = "./".$date."/".$rand_id."/";
    $flag = mkdir_date_dir($path);
    if($flag){
        $file = "./".$date."/".$rand_id."/".$tag.".log";
        log_write($file,$post_data['data']);
    }

}


// $str ='{"msgid":"416db23f-f51e-42ed-8d75-7584761904a7","appid":"100001","fpid":"123456","deviceid":"0xdef782c6","tsms":1567742596475,"tag":"exception","payload":"type: System.Exception\u000Amsg: this is test exception\u000Atarget: \u000Asource: \u000Astack: \u000A\u000Adetail info: exception message"}';
// $POST = array();
// $POST['data'] = $str;


// 检查post里面有必要的KEY
if(array_key_exists("data", $_POST)){

    run($_POST);
}else{
    print_r($_POST);
    echo "post数据格式错误";
}

