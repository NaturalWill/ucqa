<?php
/*
	[UCenter Home] (C) 2007-2008 Comsenz Inc.
	$Id: space_bwzt.php 13208 2009-08-20 06:31:35Z liguode $
*/

if(!defined('IN_UCHOME')) {
	exit('Access Denied');
}

$minhot = $_SCONFIG['feedhotmin']<1?3:$_SCONFIG['feedhotmin'];

$page = empty($_GET['page'])?1:intval($_GET['page']);
if($page<1) $page=1;
$id = empty($_GET['id'])?0:intval($_GET['id']);
$bwztclassid = empty($_GET['bwztclassid'])?0:intval($_GET['bwztclassid']);
$bwztdivisionid = empty($_GET['bwztdivisionid'])?0:intval($_GET['bwztdivisionid']);

//��̬����
@include_once(S_ROOT.'./data/data_click.php');
$clicks = empty($_SGLOBAL['click']['bwztid'])?array():$_SGLOBAL['click']['bwztid'];

if($id) {
	//��ȡ��־
	$query = $_SGLOBAL['db']->query("SELECT bf.*, b.* FROM ".tname('bwzt')." b LEFT JOIN ".tname('bwztfield')." bf ON bf.bwztid=b.bwztid WHERE b.bwztid='$id' AND b.uid='$space[uid]'");
	$bwzt = $_SGLOBAL['db']->fetch_array($query);
	//��־������
	if(empty($bwzt)) {
		capi_showmessage_by_data('view_to_info_did_not_exist');
	}
	//������Ȩ��
	if(!ckfriend($bwzt['uid'], $bwzt['friend'], $bwzt['target_ids'])) {
		//û��Ȩ��
		include template('space_privacy');
		exit();
	} elseif(!$space['self'] && $bwzt['friend'] == 4) {
		//������������
		$cookiename = "view_pwd_bwzt_$bwzt[bwztid]";
		$cookievalue = empty($_SCOOKIE[$cookiename])?'':$_SCOOKIE[$cookiename];
		if($cookievalue != md5(md5($bwzt['password']))) {
			$invalue = $bwzt;
			include template('do_inputpwd');
			exit();
		}
	}

	//����
	$bwzt['tag'] = empty($bwzt['tag'])?array():unserialize($bwzt['tag']);

	//������Ƶ��ǩ
	include_once(S_ROOT.'./source/function_bwzt.php');
	$bwzt['message'] = bwzt_bbcode($bwzt['message']);

	$otherlist = $newlist = array();

	//��Ч��
	if($_SCONFIG['uc_tagrelatedtime'] && ($_SGLOBAL['timestamp'] - $bwzt['relatedtime'] > $_SCONFIG['uc_tagrelatedtime'])) {
		$bwzt['related'] = array();
	}
	if($bwzt['tag'] && empty($bwzt['related'])) {
		@include_once(S_ROOT.'./data/data_tagtpl.php');

		$b_tagids = $b_tags = $bwzt['related'] = array();
		$tag_count = -1;
		foreach ($bwzt['tag'] as $key => $value) {
			$b_tags[] = $value;
			$b_tagids[] = $key;
			$tag_count++;
		}
		if(!empty($_SCONFIG['uc_tagrelated']) && $_SCONFIG['uc_status']) {
			if(!empty($_SGLOBAL['tagtpl']['limit'])) {
				include_once(S_ROOT.'./uc_client/client.php');
				$tag_index = mt_rand(0, $tag_count);
				$bwzt['related'] = uc_tag_get($b_tags[$tag_index], $_SGLOBAL['tagtpl']['limit']);
			}
		} else {
			//����TAG
			$tag_bwztids = array();
			$query = $_SGLOBAL['db']->query("SELECT DISTINCT bwztid FROM ".tname('tagbwzt')." WHERE tagid IN (".simplode($b_tagids).") AND bwztid<>'$bwzt[bwztid]' ORDER BY bwztid DESC LIMIT 0,10");
			while ($value = $_SGLOBAL['db']->fetch_array($query)) {
				$tag_bwztids[] = $value['bwztid'];
			}
			if($tag_bwztids) {
				$query = $_SGLOBAL['db']->query("SELECT uid,username,subject,bwztid FROM ".tname('bwzt')." WHERE bwztid IN (".simplode($tag_bwztids).")");
				while ($value = $_SGLOBAL['db']->fetch_array($query)) {
					realname_set($value['uid'], $value['username']);//ʵ��
					$value['url'] = "space.php?uid=$value[uid]&do=bwzt&id=$value[bwztid]";
					$bwzt['related'][UC_APPID]['data'][] = $value;
				}
				$bwzt['related'][UC_APPID]['type'] = 'UCHOME';
			}
		}
		if(!empty($bwzt['related']) && is_array($bwzt['related'])) {
			foreach ($bwzt['related'] as $appid => $values) {
				if(!empty($values['data']) && $_SGLOBAL['tagtpl']['data'][$appid]['template']) {
					foreach ($values['data'] as $itemkey => $itemvalue) {
						if(!empty($itemvalue) && is_array($itemvalue)) {
							$searchs = $replaces = array();
							foreach (array_keys($itemvalue) as $key) {
								$searchs[] = '{'.$key.'}';
								$replaces[] = $itemvalue[$key];
							}
							$bwzt['related'][$appid]['data'][$itemkey]['html'] = stripslashes(str_replace($searchs, $replaces, $_SGLOBAL['tagtpl']['data'][$appid]['template']));
						} else {
							unset($bwzt['related'][$appid]['data'][$itemkey]);
						}
					}
				} else {
					$bwzt['related'][$appid]['data'] = '';
				}
				if(empty($bwzt['related'][$appid]['data'])) {
					unset($bwzt['related'][$appid]);
				}
			}
		}
		updatetable('bwztfield', array('related'=>addslashes(serialize(sstripslashes($bwzt['related']))), 'relatedtime'=>$_SGLOBAL['timestamp']), array('bwztid'=>$bwzt['bwztid']));//����
	} else {
		$bwzt['related'] = empty($bwzt['related'])?array():unserialize($bwzt['related']);
	}

	//���ߵ�����������־
	$otherlist = array();
	$query = $_SGLOBAL['db']->query("SELECT * FROM ".tname('bwzt')." WHERE uid='$space[uid]' ORDER BY dateline DESC LIMIT 0,6");
	while ($value = $_SGLOBAL['db']->fetch_array($query)) {
		if($value['bwztid'] != $bwzt['bwztid'] && empty($value['friend'])) {
			$otherlist[] = $value;
		}
	}

	//���µ���־
	$newlist = array();
	$query = $_SGLOBAL['db']->query("SELECT * FROM ".tname('bwzt')." WHERE hot>=3 ORDER BY dateline DESC LIMIT 0,6");
	while ($value = $_SGLOBAL['db']->fetch_array($query)) {
		if($value['bwztid'] != $bwzt['bwztid'] && empty($value['friend'])) {
			realname_set($value['uid'], $value['username']);
			$newlist[] = $value;
		}
	}

	//����
	$perpage = 30;
	$perpage = mob_perpage($perpage);
	
	$start = ($page-1)*$perpage;

	//��鿪ʼ��
	ckstart($start, $perpage);

	$count = $bwzt['replynum'];

	$list = array();
	if($count) {
		$cid = empty($_GET['cid'])?0:intval($_GET['cid']);
		$csql = $cid?"cid='$cid' AND":'';

		$query = $_SGLOBAL['db']->query("SELECT * FROM ".tname('comment')." WHERE $csql id='$id' AND idtype='bwztid' ORDER BY dateline LIMIT $start,$perpage");
		while ($value = $_SGLOBAL['db']->fetch_array($query)) {
			realname_set($value['authorid'], $value['author']);//ʵ��
			$list[] = $value;
		}
	}

	//��ҳ
	$multi = multi($count, $perpage, $page, "space.php?uid=$bwzt[uid]&do=$do&id=$id", '', 'content');

	//����ͳ��
	if(!$space['self'] && $_SCOOKIE['view_bwztid'] != $bwzt['bwztid']) {
		$_SGLOBAL['db']->query("UPDATE ".tname('bwzt')." SET viewnum=viewnum+1 WHERE bwztid='$bwzt[bwztid]'");
		inserttable('log', array('id'=>$space['uid'], 'idtype'=>'uid'));//�ӳٸ���
		ssetcookie('view_bwztid', $bwzt['bwztid']);
	}

	//��̬
	$hash = md5($bwzt['uid']."\t".$bwzt['dateline']);
	$id = $bwzt['bwztid'];
	$idtype = 'bwztid';

	foreach ($clicks as $key => $value) {
		$value['clicknum'] = $bwzt["click_$key"];
		$value['bwztclassid'] = mt_rand(1, 4);
		$value['bwztdivisionid'] = mt_rand(1, 4);
		if($value['clicknum'] > $maxclicknum) $maxclicknum = $value['clicknum'];
		$clicks[$key] = $value;
	}

	//����
	$clickuserlist = array();
	$query = $_SGLOBAL['db']->query("SELECT * FROM ".tname('clickuser')."
		WHERE id='$id' AND idtype='$idtype'
		ORDER BY dateline DESC
		LIMIT 0,18");
	while ($value = $_SGLOBAL['db']->fetch_array($query)) {
		realname_set($value['uid'], $value['username']);//ʵ��
		$value['clickname'] = $clicks[$value['clickid']]['name'];
		$clickuserlist[] = $value;
	}

	//�ȵ�
	$topic = topic_get($bwzt['topicid']);

	//ʵ��
	realname_get();

	$_TPL['css'] = 'bwzt';
	//include_once template("space_bwzt_view");
	capi_showmessage_by_data("space_bwzt_view",0, array('bwzt'=>$bwzt));
} else {
	//��ҳ
	$perpage = 10;
	$perpage = mob_perpage($perpage);
	
	$start = ($page-1)*$perpage;

	//��鿪ʼ��
	ckstart($start, $perpage);

	//ժҪ��ȡ
	$summarylen = 300;

	$bwztclassarr = array();
	$bwztdivisionarr = array();
	$list = array();
	$userlist = array();
	$count = $pricount = 0;

	$ordersql = 'b.dateline';

	if(empty($_GET['view']) && ($space['friendnum']<$_SCONFIG['showallfriendnum'])) {
		$_GET['view'] = 'all';//Ĭ����ʾ
	}

	//������ѯ
	$f_index = '';
	if($_GET['view'] == 'click') {
		//�ȹ�����־
		$theurl = "space.php?uid=$space[uid]&do=$do&view=click";
		$actives = array('click'=>' class="active"');

		$clickid = intval($_GET['clickid']);
		if($clickid) {
			$theurl .= "&clickid=$clickid";
			$wheresql = " AND c.clickid='$clickid'";
			$click_actives = array($clickid => ' class="current"');
		} else {
			$wheresql = '';
			$click_actives = array('all' => ' class="current"');
		}

		$count = $_SGLOBAL['db']->result($_SGLOBAL['db']->query("SELECT COUNT(*) FROM ".tname('clickuser')." c WHERE c.uid='$space[uid]' AND c.idtype='bwztid' $wheresql"),0);
		if($count) {
			$query = $_SGLOBAL['db']->query("SELECT b.*, bf.message, bf.target_ids, bf.magiccolor FROM ".tname('clickuser')." c
				LEFT JOIN ".tname('bwzt')." b ON b.bwztid=c.id
				LEFT JOIN ".tname('bwztfield')." bf ON bf.bwztid=c.id
				WHERE c.uid='$space[uid]' AND c.idtype='bwztid' $wheresql
				ORDER BY c.dateline DESC LIMIT $start,$perpage");
		}
	} else {
		
		if($_GET['view'] == 'all') {
			//��ҵ���־
			$wheresql = '1';

			$actives = array('all'=>' class="active"');

			//����
			$orderarr = array('dateline','replynum','viewnum','hot');
			foreach ($clicks as $value) {
				$orderarr[] = "click_$value[clickid]";
			}
			if(!in_array($_GET['orderby'], $orderarr)) $_GET['orderby'] = '';

			//ʱ��
			$_GET['day'] = intval($_GET['day']);
			$_GET['hotday'] = 7;

			if($_GET['orderby']) {
				$ordersql = 'b.'.$_GET['orderby'];

				$theurl = "space.php?uid=$space[uid]&do=bwzt&view=all&orderby=$_GET[orderby]";
				$all_actives = array($_GET['orderby']=>' class="current"');

				if($_GET['day']) {
					$_GET['hotday'] = $_GET['day'];
					$daytime = $_SGLOBAL['timestamp'] - $_GET['day']*3600*24;
					$wheresql .= " AND b.dateline>='$daytime'";

					$theurl .= "&day=$_GET[day]";
					$day_actives = array($_GET['day']=>' class="active"');
				} else {
					$day_actives = array(0=>' class="active"');
				}
			} else {

				$theurl = "space.php?uid=$space[uid]&do=$do&view=all";

				$wheresql .= " AND b.hot>='$minhot'";
				$all_actives = array('all'=>' class="current"');
				$day_actives = array();
			}


		} else {
			
			if(empty($space['feedfriend']) || $bwztclassid) $_GET['view'] = 'me';
			
			if($_GET['view'] == 'me') {
				//�鿴���˵�
				$wheresql = "b.uid='$space[uid]'";
				$theurl = "space.php?uid=$space[uid]&do=$do&view=me";
				$actives = array('me'=>' class="active"');
				//֢״����
				$query = $_SGLOBAL['db']->query("SELECT bwztclassid, bwztclassname FROM ".tname('bwztclass')." WHERE uid='$space[uid]'");
				while ($value = $_SGLOBAL['db']->fetch_array($query)) {
					$bwztclassarr[$value['bwztclassid']] = $value['bwztclassname'];
				}
				//���ҷ���
				$query = $_SGLOBAL['db']->query("SELECT bwztdivisionid, bwztdivisionname FROM ".tname('bwztdivision')." WHERE uid='$space[uid]'");
				while ($value = $_SGLOBAL['db']->fetch_array($query)) {
					$bwztdivisionarr[$value['bwztdivisionid']] = $value['bwztdivisionname'];
				}
			} else {
				$wheresql = "b.uid IN ($space[feedfriend])";
				$theurl = "space.php?uid=$space[uid]&do=$do&view=we";
				$f_index = 'USE INDEX(dateline)';
	
				$fuid_actives = array();
	
				//�鿴ָ�����ѵ�
				$fusername = trim($_GET['fusername']);
				$fuid = intval($_GET['fuid']);
				if($fusername) {
					$fuid = getuid($fusername);
				}
				if($fuid && in_array($fuid, $space['friends'])) {
					$wheresql = "b.uid = '$fuid'";
					$theurl = "space.php?uid=$space[uid]&do=$do&view=we&fuid=$fuid";
					$f_index = '';
					$fuid_actives = array($fuid=>' selected');
				}
	
				$actives = array('we'=>' class="active"');
	
				//�����б�
				$query = $_SGLOBAL['db']->query("SELECT * FROM ".tname('friend')." WHERE uid='$space[uid]' AND status='1' ORDER BY num DESC, dateline DESC LIMIT 0,500");
				while ($value = $_SGLOBAL['db']->fetch_array($query)) {
					realname_set($value['fuid'], $value['fusername']);
					$userlist[] = $value;
				}
			}
		}

		//����
		if($bwztclassid) {
			$wheresql .= " AND b.bwztclassid='$bwztclassid'";
			$theurl .= "&bwztclassid=$bwztclassid";
		}

		//����
		if($bwztdivisionid) {
			$wheresql .= " AND b.bwztdivisionid='$bwztdivisionid'";
			$theurl .= "&bwztdivisionid=$bwztdivisionid";
		}
		
		//����Ȩ��
		$_GET['friend'] = intval($_GET['friend']);
		if($_GET['friend']) {
			$wheresql .= " AND b.friend='$_GET[friend]'";
			$theurl .= "&friend=$_GET[friend]";
		}

		//����
		if($searchkey = stripsearchkey($_GET['searchkey'])) {
			$wheresql .= " AND b.subject LIKE '%$searchkey%'";
			$theurl .= "&searchkey=$_GET[searchkey]";
			cksearch($theurl);
		}

		$count = $_SGLOBAL['db']->result($_SGLOBAL['db']->query("SELECT COUNT(*) FROM ".tname('bwzt')." b WHERE $wheresql"),0);
		//����ͳ��
		if($wheresql == "b.uid='$space[uid]'" && $space['bwztnum'] != $count) {
			updatetable('space', array('bwztnum' => $count), array('uid'=>$space['uid']));
		}
		if($count) {
			$query = $_SGLOBAL['db']->query("SELECT bf.message, bf.target_ids, bf.magiccolor, b.* FROM ".tname('bwzt')." b $f_index
				LEFT JOIN ".tname('bwztfield')." bf ON bf.bwztid=b.bwztid
				WHERE $wheresql
				ORDER BY $ordersql DESC LIMIT $start,$perpage");
		}
	}

	if($count) {
		while ($value = $_SGLOBAL['db']->fetch_array($query)) {
			if(ckfriend($value['uid'], $value['friend'], $value['target_ids'])) {
				realname_set($value['uid'], $value['username']);
				if($value['friend'] == 4) {
					$value['message'] = $value['pic'] = '';
				} else {
					$value['message'] = getstr($value['message'], $summarylen, 0, 0, 0, 0, -1);
				}
				if($value['pic']) $value['pic'] = pic_cover_get($value['pic'], $value['picflag']);
				$list[] = $value;
			} else {
				$pricount++;
			}
		}
	}

	//��ҳ
	$multi = multi($count, $perpage, $page, $theurl);

	//ʵ��
	realname_get();

	$_TPL['css'] = 'bwzt';
	//include_once template("space_bwzt_list");

	capi_showmessage_by_data("space_bwzt_list", 0,array('list'=>$list, 'count'=>count($count)));
}

?>