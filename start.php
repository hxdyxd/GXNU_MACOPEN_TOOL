<?php
//@hxdyxd(hxdyxd@gmail.com)
//仅在广西师范大学校园网使用
//Only support in the Guangxi Normal University campus network
//仅支持linux系统
//only linux is supported.
//你可能需要设置静态路由
//You may need to set up a static route
//请修改以下变量的值:$server_inner_ip, $admin_passwd;
//please config:$server_inner_ip, $admin_passwd;

$server_inner_ip = '172.16.0.0';

use \Workerman\Worker;
require_once './Workerman/Autoloader.php';

$worker = new Worker('http://0.0.0.0:80');
$worker->count = 4;
$worker->name = 'Webpage-Process';
$worker_s = new Worker();
$worker_s->count = 1;
$worker_s->name = 'Send-Process';

//the first create sql table
//$db = new SQLite3('gxnu_macopen_tool.db');
//$create_sql = 'CREATE TABLE MACOPEN (name varchar, mac varchar, isp int, passwd varchar, info varchar)';
//$db->exec($create_sql);

$worker_s->onWorkerStart = function($worker_s){
	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$db = new SQLite3('gxnu_macopen_tool.db');
	while(1){
		$sql = "SELECT name,mac,isp FROM MACOPEN";
		$ret = $db->query($sql);
		while($row = $ret->fetchArray(SQLITE3_ASSOC)){
			echo json_encode($row);
			$msg = mac_pack($row['mac'], $row['isp']);
			echo socket_sendto($sock, $msg, strlen($msg), 0, '202.193.160.123', 20015)."\n";
		}
		sleep(15);
	}
	socket_close($sock);
};

$worker->onMessage = function($connection, $http){
	$admin_passwd = 'adminadmin';
	$err = '0:未知错误，请联系管理员';
	$db = new SQLite3('gxnu_macopen_tool.db');
	if($http['server']['REQUEST_URI'] == '/' || strpos($http['server']['REQUEST_URI'],'index.html')){
		$connection->send(file_get_contents('./index.html'));
	}else if(strpos($http['server']['REQUEST_URI'], 'Select.All')){
		$result = array();
		$sql = "SELECT name FROM MACOPEN";
		$ret = $db->query($sql);
		while($row = $ret->fetchArray(SQLITE3_ASSOC)){
			array_push($result, $row['name']);
		}
		echo "LIST from ".$http['server']['REMOTE_ADDR']."\n";
		$connection->send(json_encode($result));
		return $connection->close();
	}else if(strpos($http['server']['REQUEST_URI'], 'Select') && $http['server']['REQUEST_METHOD'] == 'POST'){
		if(is_data($http['post']['name'])){
			$is_select = false;
			$sql = "SELECT name,SUBSTR(mac,1,8) as mac,isp,info FROM MACOPEN";
			$ret = $db->query($sql);
			while($row = $ret->fetchArray(SQLITE3_ASSOC)){
				if($row['name'] == $http['post']['name']){
					$is_select = true;
					break;
				}
			}
			if( $is_select ){
				echo "READ ". $http['post']['name']. " from ".$http['server']['REMOTE_ADDR']."\n";
				$connection->send(json_encode($row));
				return $connection->close();
			}else{
				$err = '1:没有找到这个名字';
			}
		}else{
			$err = '2:MUST SUBMIT NAME.';
		}
	}else if(strpos($http['server']['REQUEST_URI'], 'Insert') && $http['server']['REQUEST_METHOD'] == 'POST'){
		if(is_data($http['post']['name']) && is_data($http['post']['mac']) && is_data($http['post']['isp']) && is_data($http['post']['passwd'])){
			$is_recover = false;
			$sql = "SELECT name,mac FROM MACOPEN";
			$ret = $db->query($sql);
			while($row = $ret->fetchArray(SQLITE3_ASSOC)){
				if($row['name'] == $http['post']['name']){
					$is_recover = true;
					$err = '3:名字已经存在';
					break;
				}else if($row['mac'] == $http['post']['mac']){
					$is_recover = true;
					$err = '4:物理地址已经存在,如需添加请删除旧的记录';
					break;
				}
			}
			if(!$is_recover){
				$name = $http['post']['name'];
				$mac = $http['post']['mac'];
				$isp = intval($http['post']['isp']);
				$passwd = md5($http['post']['passwd'].$admin_passwd);
				$info = json_decode(@file_get_contents('http://www.imfirewall.com/ip-mac-lookup/get_mac_info.php?mac=' . $mac), true);
				if($info && $info['success'] == true){
					$info = $info['result']['mac_producer'];
				}else{
					$info = 'NO-INFO';
				}
				$sql = "INSERT INTO MACOPEN VALUES ('$name', '$mac', $isp, '$passwd', '$info')";
				if( $db->exec($sql) ){
					echo "ADD ". $http['post']['name']. " from ".$http['server']['REMOTE_ADDR']."\n";
					$connection->send(json_encode(array('success'=>'1')));
					return $connection->close();
				}
			}
		}else{
			$err = '6:只能输入字母和数字还有 :  -  . 哦';
		}
	}else if(strpos($http['server']['REQUEST_URI'], 'Delete') && $http['server']['REQUEST_METHOD'] == 'POST'){
		if(is_data($http['post']['name']) && is_data($http['post']['passwd'])){
			$is_del = false;
			$sql = "SELECT name,passwd FROM MACOPEN";
			$ret = $db->query($sql);
			while($row = $ret->fetchArray(SQLITE3_ASSOC)){
				if($row['name'] == $http['post']['name'] && ($row['passwd'] == md5($http['post']['passwd'].$admin_passwd) || $http['post']['passwd'] == $admin_passwd)){
					$is_del = true;
					break;
				}
			}
			if( $is_del ){
				$name = $http['post']['name'];
				$sql = "DELETE FROM MACOPEN WHERE name = '$name' ";
				if( $db->exec($sql) ){
					echo "DEL ". $http['post']['name']. " from ".$http['server']['REMOTE_ADDR']."\n";
					$connection->send(json_encode(array('success'=>'1')));
					return $connection->close();
				}
			}else{
				$err = '5:删除密匙错误';
			}
		}
	}
	$connection->send(json_encode( array('success'=>'0', 'msg'=> $err)));
	return $connection->close();
};

function convent_to_hex($int){
	return bin2hex(chr($int));
}

/*
 must len<20 && [0-9] [a-z] [A-Z] [- . :]
*/
function is_data($data){
	if( isset($data) && strlen($data)<=20 ){
		for($i=0; $i<strlen($data); $i++){
			if( !(ord($data[$i]) >= 45 && ord($data[$i]) <= 58 && ord($data[$i]) != 47) && !(ord($data[$i]) >= 65 && ord($data[$i]) <= 90) && !(ord($data[$i]) >= 97 && ord($data[$i]) <= 122) ) return false;
		}
		return true;
	}else{
		return false;
	}
}

function mac_pack($mac, $isp){
	global $server_inner_ip;
	$isp = chr($isp);
	//---header---56bytes---
	$msg = str_repeat("\x00", 30);
	$msg .= inet_pton($server_inner_ip);
	$msg .= $mac;
	$msg .= "\x00\x00\x00".$isp."\x00";
	//---header---checksum---
	$ispKey = 0x4e67c6a7;
	$ecx = $ispKey;
	for($i=0;$i<strlen($msg);$i++){
		$esi = $ecx;
		$esi = $esi<<5;
		if($ecx>0){
		        $ebx=$ecx;
			$ebx=$ebx>>2;
		}else{
			$ebx=$ecx;
			$ebx=$ebx>>2;
			$ebx=$ebx|0xC0000000;
		}
		$esi = $esi + ord($msg[$i]);
		$ebx = intval($ebx + $esi);
		$ecx = $ecx^$ebx;
	}
	$ecx = $ecx&0x7FFFFFFF;
	for($i=0;$i<4;$i++){
		$msg .= chr(($ecx>>($i*8))&0x000000FF);
	}
	return $msg;
}

function err($msg){
	echo $msg."\n";
}

Worker::runAll();
