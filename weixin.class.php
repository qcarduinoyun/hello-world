﻿<?php
class wechatCallbackapiTest
{
    # This function reads your DATABASE_URL config var and returns a connection
    # string suitable for pg_connect.
    private function pg_conn_string() {
        extract(parse_url($_ENV["DATABASE_URL"]));
        return "host=$host port=$port user=$user password=$pass dbname=" . substr($path, 1); # <- you may want to add sslmode=require there too
        
        //create a connection string from the PG database URL and then use it to connect
        /*
        $url=parse_url(getenv("DATABASE_URL"));
        $host = $url["host"];
        $port = $url["port"];
        $user = $url["user"];
        $password = $url["pass"];
        $dbname = substr($url["path"],1);
        $connect_string = "host='" . $host . "' ";
        $connect_string = $connect_string . "port=" . $port . " ";
        $connect_string = $connect_string . "user='" . $user . "' ";
        $connect_string = $connect_string . "password='" . $password . "' ";
        $connect_string = $connect_string . "dbname='" . $dbname . "' ";
        
        return $connect_string;*/
    }
    
    # Get temperature value from postgresql db.
    private function pg_get_temperature() {
        # connect to postgresql db
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
            $result = pg_query($con, "SELECT * FROM sensor") or die('Query failed: ' . pg_last_error());;
            while($arr = pg_fetch_array($result)){
                if ($arr['id'] == 1) {
                    $tempr = $arr['data'];
                    $retMsg .= "报告大王："."\n"."主人房间的室温为".$tempr."℃，感谢您对主人的关心";
                    break;
                }
            }
        } else {
            $retMsg = "出错了(001)！！！";
        }
        pg_free_result($result);
        pg_close($con);
        return $retMsg;
    }
    
    # Get existing wx access token stored in postgresql and check if expire
    private function pg_get_wx_config_all() {
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
            $result = pg_query($con, "SELECT * FROM wx_config");
            if ($result) {
                while($arr = pg_fetch_array($result)){
                    if ($arr['id'] == 1) {
                        // only id = 1 row is valid record.
                        $ret = $arr;
                        break;
                    }
                }
            } else {
                echo '数据库查询出错';
                exit;
            }
         } else {
            echo '数据库连接出错';
            exit;
        }
        pg_free_result($result);
        pg_close($con);
        return $ret;
    }
    
    public function responseMsg()
    {
        # correct timezone at very beginning
        date_default_timezone_set('prc');

        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $time = time();
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            if($keyword == "?")
            {
                $msgType = "text";
                $contentStr = date("Y-m-d H:i:s: ",time());
                $contentStr .= self::pg_get_temperature();
            } else if ($keyword == "#")
            {
                $msgType = "text";
                $contentStr = date("Y-m-d H:i:s: ",time());
                $arr_config = self::pg_get_wx_config_all();
                $contentStr .= $arr_config['id'];
            }
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            echo $resultStr;
            return;
        }else{
            echo "";
            exit;
        }
    }
}
?>