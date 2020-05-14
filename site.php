<?php

require_once IA_ROOT . '/addons/beta_car/aliyun-dypls-php-sdk/api_sdk/vendor/autoload.php';
use Aliyun\Core\Config;
use Aliyun\Core\Exception\ClientException;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Dypls\Request\V20170525\BindAxbRequest;
use Aliyun\Api\Dypls\Request\V20170525\BindAxnRequest;
use Aliyun\Api\Dypls\Request\V20170525\UnbindSubscriptionRequest;
use Aliyun\Api\Dypls\Request\V20170525\UpdateSubscriptionRequest;
use Aliyun\Api\Dypls\Request\V20170525\QueryRecordFileDownloadUrlRequest;
use Aliyun\Api\Dypls\Request\V20170525\QuerySubscriptionDetailRequest;
use Aliyun\Api\Dypls\Request\V20170525\BindAxnExtensionRequest;
Config::load();
defined('IN_IA') or exit('Access Denied');
define('table', 'beta_car_');
define('uniacid', $_W['uniacid']);
define('openid', $_W['fans']['openid']);
class Beta_carModuleSite extends WeModuleSite
{
	static $acsClient = null;
	public function __construct()
	{
		global $_GPC, $_W;
		if ($_GPC['do'] == 'qrcode' and $_GPC['op'] == 'scan') {
			$sql = 'SELECT a.openid,b.id FROM ' . tablename(table . 'car') . 'AS a left join ' . tablename(table . 'user') . 'AS b on a.uniacid=b.uniacid and a.openid=b.openid where a.sn=' . '\'' . $_GPC['sign'] . '\'';
			$user = pdo_fetch($sql);
			$indata = array("reid" => $user['id'], "uniacid" => uniacid, "openid" => openid, "nickname" => $_W['fans']['nickname'], "headimg" => $_W['fans']['headimgurl']);
		} else {
			$indata = array("uniacid" => uniacid, "openid" => openid, "nickname" => $_W['fans']['nickname'], "headimg" => $_W['fans']['headimgurl']);
		}
		if (empty($_W['fans']['nickname'])) {
			mc_oauth_userinfo();
		} else {
			$user = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
			if (empty($user)) {
				pdo_insert(table . 'user', $indata);
			}
		}
	}
	public function doMobilezengzhi()
	{
		$title = '增值服务';
		$setting = json_decode(pdo_get(table . 'zengzhi', array("uniacid" => uniacid), 'weizhang')['weizhang'], true);
		if ($setting['fw_set'] == '0') {
			message('服务未开启');
		}
		if ($setting['shop'] <= '0') {
			message('未设置价格');
		}
		include $this->template('zengzhi');
	}
	public function payResult($params)
	{
		if ($params['result'] == 'success' && $params['from'] == 'notify') {
			$log = pdo_get(table . 'pay', array("order_id" => $params['tid']));
			if ($log['type'] == 'order') {
				$result = pdo_update(table . 'order', array("status" => "1"), array("order_id" => $params['tid']));
				$cash = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
				if ($cash['fw_set'] == '1') {
					if (!empty($result)) {
						$orderinfo = pdo_get(table . 'order', array("order_id" => $params['tid']));
						$userinfo = pdo_get(table . 'user', array("id" => $orderinfo['userid']));
						if (!empty($userinfo['reid'])) {
							if (empty($cash)) {
								$u_c = '50';
							} else {
								$u_c = $cash['cash'];
							}
							$shop = pdo_get(table . 'setting', array("uniacid" => uniacid));
							$cash_r = $shop['shop'] * ($u_c / 100);
							$res = pdo_update(table . 'user', array("cash +=" => $cash_r), array("id" => $userinfo['reid']));
							if (!empty($res)) {
								$data = array("userid" => $userinfo['reid'], "uniacid" => uniacid, "money" => $cash_r, "note" => '推荐用户【' . $userinfo['nickname'] . '】下单分成', "time" => time());
								pdo_insert(table . 'user_log', $data);
							}
						}
					}
				}
			} else {
				if ($log['type'] == 'weizhang') {
					$res = pdo_get(table . 'wzts', array("uniacid" => uniacid, "sn" => $log['sn']));
					if (!empty($res)) {
						if ($res['status'] == '2') {
							$data = array("endtime" => strtotime('+1 month', time()), "status" => "1", "next_time" => time());
						} else {
							$data = array("endtime" => strtotime('+1 month', $res['endtime']), "status" => "1", "next_time" => time());
						}
						$result = pdo_update(table . 'wzts', $data, array("uniacid" => uniacid, "sn" => $log['sn']));
					} else {
						$data = array("order_id" => $log['order_id'], "uniacid" => uniacid, "user_id" => $log['user_id'], "paytime" => time(), "sn" => $log['sn'], "endtime" => strtotime('+1 month'), "status" => "1", "next_time" => time());
						$result = pdo_insert(table . 'wzts', $data);
					}
				}
			}
		}
	}
	public function doMobilewzcode()
	{
		global $_W, $_GPC;
		$jhm = pdo_get(table . 'wzcode', array("uniacid" => uniacid, "code" => $_GPC['jhm']));
		if (!empty($jhm)) {
			if ($jhm['status'] == '1') {
				message('激活码已被使用', null, 'error');
			} else {
				$res = pdo_get(table . 'wzts', array("uniacid" => uniacid, "sn" => $_GPC['sn']));
				if (!empty($res)) {
					$data = array("endtime" => strtotime('+1 month', $res['endtime']), "status" => "1", "next_time" => time());
					$result = pdo_update(table . 'wzts', $data, array("uniacid" => uniacid, "sn" => $_GPC['sn']));
				} else {
					$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
					$data = array("order_id" => $_GPC['jhm'], "uniacid" => uniacid, "user_id" => $u['id'], "paytime" => time(), "sn" => $_GPC['sn'], "endtime" => strtotime('+1 month'), "status" => "1", "next_time" => time());
					$result = pdo_insert(table . 'wzts', $data);
				}
				pdo_update(table . 'wzcode', array("status" => "1"), array("code" => $_GPC['jhm']));
				message('激活成功', $this->createMobileUrl('weizhang', array("action" => "carlist")), 'success');
			}
		} else {
			message('激活码有误', null, 'error');
		}
	}
	public function doMobileweizhang_log()
	{
		global $_GPC, $_W;
		$title = '违章记录';
		$list = pdo_getall(table . 'wzlog', array("uniacid" => uniacid, "sn" => $_GPC['sign']));
		return include $this->template('weizhang_log');
	}
	public function ok($msg = "更新成功")
	{
		message($msg, 'referer', 'success');
	}
	public function view($name)
	{
		return include $this->template($name);
	}
	public function doMobilewzts()
	{
		error_reporting(0);
		set_time_limit(0);
		global $_GPC, $_W;
		if (date('G', time()) > '9') {
			$setting = json_decode(pdo_get(table . 'zengzhi', array("uniacid" => uniacid), 'weizhang')['weizhang'], true);
			if ($setting['fw_set'] == '1') {
				$strat = strtotime(date('Y-m-d H:i:s', strtotime(date('Y-m-d', time()))));
				$end = strtotime(date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime('+1 day')))));
				$sql = 'select a.*,b.car,b.openid,b.engineno,b.classno,b.city from ' . tablename(table . 'wzts') . '  as a left join ' . tablename(table . 'car') . ' as b on a.sn=b.sn where a.status=1 and a.next_time>=' . $strat . ' and a.next_time<' . $end . ' limit 200';
				$jiegou = pdo_fetchall($sql);
				if (!empty($jiegou)) {
					$url = 'http://v.juhe.cn/wz/query';
					$post_data = array("key" => $setting['appkey']);
					$count = 0;
					foreach ($jiegou as $row) {
						if (time() > $row['endtime']) {
							pdo_update(table . 'wzts', array("status" => "2"), array("uniacid" => uniacid, "sn" => $row['sn']));
						}
						if ($setting['ts_day'] <= '0') {
							$time = array("next_time" => strtotime('+1 day', $row['next_time']));
						} else {
							$time = array("next_time" => strtotime('+' . $setting['ts_day'] . ' day', $row['next_time']));
						}
						pdo_update(table . 'wzts', $time, array("uniacid" => uniacid, "sn" => $row['sn']));
						$post_data['city'] = $row['city'];
						$post_data['hphm'] = $row['car'];
						$post_data['engineno'] = $row['engineno'];
						$post_data['classno'] = $row['classno'];
						$post = json_decode(ihttp_post($url, $post_data)['content'], true)['result']['lists'];
						foreach ($post as $us) {
							$wzjs = json_encode($us);
							$wztime = strtotime($us['date']);
							$pdo = pdo_fetch('select * from ' . tablename(table . 'wzlog') . ' where sn=' . "'{$row['sn']}' and wztime=" . "'{$wztime}'" . ' or \'data\'=' . "'{$wzjs}'");
							if (empty($pdo)) {
								$result = pdo_insert(table . 'wzlog', array("uniacid" => uniacid, "sn" => $row['sn'], "data" => $wzjs, "archiveno" => $us[archiveno], "wztime" => $wztime));
								$count += pdo_count(table . 'wzlog', array("uniacid" => uniacid, "sn" => $row['sn'], "status" => "0"));
								pdo_update(table . 'wzlog', array("status" => "1"), array("uniacid" => uniacid, "sn" => $row['sn']));
							}
						}
						if ($count == '0') {
							if ($setting['ts_set'] != '1') {
								$tpl_url = murl('entry//weizhang_log', ["m" => "beta_car", "sign" => $row['sn']], 1, true);
								$data = array("first" => array("value" => "您好，您的爱车暂无新的违章记录！", "color" => "#ff0000"), "keyword1" => array("value" => $row['car'], "color" => "#ff510"), "keyword2" => array("value" => date('Y-m-d', time()), "color" => "#ff510"), "keyword3" => array("value" => $count . '条', "color" => "#ff510"), "remark" => array("value" => "点击查看历史违章", "color" => "#ff510"));
								$account_api = WeAccount::create();
								$result = $account_api->sendTplNotice($row['openid'], $setting['template_id1'], $data, $tpl_url);
								echo $row['car'] . '推送成功' . PHP_EOL;
							}
						} else {
							$tpl_url = murl('entry//weizhang_log', ["m" => "beta_car", "sign" => $row['sn']], 1, true);
							$data = array("first" => array("value" => "您的爱车有新的违章记录！", "color" => "#ff0000"), "keyword1" => array("value" => $row['car'], "color" => "#ff510"), "keyword2" => array("value" => $count . '条', "color" => "#ff510"), "remark" => array("value" => "点击查看详情", "color" => "#ff510"));
							$account_api = WeAccount::create();
							$account_api->sendTplNotice($row['openid'], $setting['template_id'], $data, $tpl_url);
						}
					}
				} else {
					echo '暂无需推送用户';
				}
			}
		}
	}
	public function doMobileweizhang()
	{
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		global $_GPC, $_W;
		$res = json_decode(pdo_get(table . 'zengzhi', array("uniacid" => uniacid), 'weizhang')['weizhang'], true);
		if ($res['fw_set'] != '1') {
			message('服务未启用，请联系管理员');
		}
		if ($_GPC['action'] == 'carlist') {
			$title = '选择车辆';
			$sql = 'SELECT a.*,b.status,b.endtime FROM ' . tablename(table . 'car') . ' AS a left join' . tablename(table . 'wzts') . ' AS b on a.sn=b.sn where a.openid=' . '\'' . openid . '\' and a.uniacid=' . '\'' . uniacid . '\' order by status desc,id desc';
			$car_list = pdo_fetchall($sql);
			return include $this->template('carlist');
		} else {
			$title = '违章推送';
			$carinfo = pdo_get(table . 'car', array("openid" => openid, "uniacid" => uniacid, "sn" => $_GPC['sign']));
			if (!empty($carinfo)) {
				$post = json_decode(file_get_contents('http://v.juhe.cn/wz/carPre?key=' . $res['appkey'] . '&hphm=' . $carinfo['car']), true);
				if ($post['error_code'] == '0') {
					if ($_W['isajax']) {
						if ($_GPC['action'] == 'order') {
							$order_id = date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
							pdo_insert(table . 'pay', array("order_id" => $order_id, "uniacid" => uniacid, "type" => "weizhang", "user_id" => $u['id'], "sn" => $carinfo['sn']));
							$payinfo = array("order_id" => $order_id, "free" => $res['shop']);
							echo json_encode($payinfo);
						} else {
							if ($_GPC['action'] == 'bind') {
								$jieguo = file_get_contents('http://v.juhe.cn/wz/query?key=' . $res['appkey'] . '&city=' . $post['result']['city_code'] . '&hphm=' . urlencode($carinfo['car']) . '&engineno=' . $_GPC['fdj'] . '&classno=' . $_GPC['cj']);
								$jieguo_arr = json_decode($jieguo, true);
								if ($jieguo_arr['error_code'] == '0') {
									pdo_update(table . 'car', array("engineno" => $_GPC['fdj'], "classno" => $_GPC['cj'], "city" => $post['result']['city_code']), array("sn" => $carinfo['sn']));
								}
								echo $jieguo;
							}
						}
					} else {
						return include $this->template('weizhang');
					}
				} else {
					message($post['reason'] . '，请联系管理员');
				}
			} else {
				message('车辆信息有误，请联系管理员');
			}
		}
	}
	public function doMobileweizhang_order()
	{
	}
	public function doMobileteamlist()
	{
		global $_GPC, $_W;
		$title = '团队列表';
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		$list = pdo_getall(table . 'user', array("reid" => $u['id']));
		include $this->template('teamlist');
	}
	public function doMobileuser_log()
	{
		global $_GPC, $_W;
		$title = '收支记录';
		$uni_setting = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
		if ($uni_setting['fw_set'] != '1') {
			message('服务未开启');
		}
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		$list = pdo_getall(table . 'user_log', array("userid" => $u['id']));
		include $this->template('user_log');
	}
	public function doMobileorderlist()
	{
		global $_GPC, $_W;
		$title = '我的订单';
		$shop = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		$list = pdo_getall(table . 'order', array("userid" => $u['id']), '', '', 'addtime DESC');
		include $this->template('orderlist');
	}
	public function dowebdashboard()
	{
		global $_GPC, $_W;
		include $this->template('web/dashboard');
	}
	public function menu()
	{
		global $_GPC, $_W;
		$data = array("url" => array("title" => "应用入口", "url" => $this->createWebUrl('url'), "icon" => "la-chain"), "setting" => array("title" => "系统设置", "url" => $this->createWebUrl('setting'), "icon" => "la-cogs"), "unisetting" => array("title" => "用户分佣设置", "url" => $this->createWebUrl('unisetting'), "icon" => "la-diamond"), "uni_tixian" => array("title" => "用户提现", "url" => $this->createWebUrl('uni_tixian'), "icon" => "la-rotate-right"), "order" => array("title" => "订单管理", "url" => $this->createWebUrl('order'), "icon" => "la-area-chart"), "car_list" => array("title" => "车辆列表", "url" => $this->createWebUrl('car_list'), "icon" => "la-automobile"), "zengzhi" => array("title" => "增值服务", "url" => $this->createWebUrl('zengzhi'), "icon" => "la-money"), "qudao" => array("title" => "渠道管理", "url" => $this->createWebUrl('qudao'), "icon" => "la-pinterest-square"), "ad" => array("title" => "广告管理", "url" => $this->createWebUrl('ad'), "icon" => "la la-image"), "nullqrcode" => array("title" => "空码管理", "url" => $this->createWebUrl('nullqrcode'), "icon" => "la-qrcode"), "wzcode" => array("title" => "违章激活码", "url" => $this->createWebUrl('wzcode'), "icon" => "la-code-fork"));
		foreach ($data as $k => $row) {
			if ($_GPC['do'] == $k) {
				$data[$k]['no'] = 'no';
			}
		}
		return $data;
	}
	public function gongzhonghao($mname, $type)
	{
		global $_GPC, $_W;
		$module_name = trim($mname);
		if (empty($module_name)) {
			exit;
		}
		$accounts_list = module_link_uniacid_fetch($_W['uid'], $module_name);
		if (empty($accounts_list)) {
			exit;
		}
		$selected_account = array();
		foreach ($accounts_list as $account) {
			if (empty($account['uniacid']) || $account['uniacid'] != $_W['uniacid']) {
				continue;
			}
			if (in_array($_W['account']['type'], array(ACCOUNT_TYPE_OFFCIAL_NORMAL, ACCOUNT_TYPE_OFFCIAL_AUTH))) {
				if (!empty($account['version_id'])) {
					$version_info = miniapp_version($account['version_id']);
					$account['version_info'] = $version_info;
				}
				$selected_account = $account;
				break;
			} else {
				if (in_array($_W['account']['type'], array(ACCOUNT_TYPE_APP_NORMAL, ACCOUNT_TYPE_ALIAPP_NORMAL))) {
					$version_info = miniapp_version($account['version_id']);
					$account['version_info'] = $version_info;
					$selected_account = $account;
					break;
				}
			}
		}
		foreach ($accounts_list as $key => $account) {
			$url = url('module/display/switch', array("uniacid" => $account['uniacid'], "module_name" => $module_name));
			if (!empty($account['version_id'])) {
				$url .= '&version_id=' . $account['version_id'];
			}
			$accounts_list[$key]['url'] = $url;
		}
		if ($type == '1') {
			return $selected_account;
		} else {
			return $accounts_list;
		}
		exit;
	}
	public function doMobileOrder()
	{
		global $_GPC, $_W;
		$order_id = date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		$data = array("order_id" => $order_id, "userid" => $u['id'], "uniacid" => uniacid, "userName" => $_GPC['userName'], "telNumber" => $_GPC['telNumber'], "address" => $_GPC['address'], "addtime" => time());
		$rest = pdo_insert(table . 'order', $data);
		pdo_insert(table . 'pay', array("order_id" => $order_id, "uniacid" => uniacid, "type" => "order"));
		if ($rest) {
			$shop = pdo_get(table . 'setting', array("uniacid" => uniacid));
			$payinfo = array("order_id" => $data['order_id'], "free" => $shop['shop']);
			echo json_encode($payinfo);
		}
	}
	public function doWebOrder()
	{
		global $_GPC, $_W;
		$sql = 'SELECT a.*,b.nickname,b.headimg FROM ' . tablename(table . 'order') . ' AS a left join' . tablename(table . 'user') . ' AS b on a.userid=b.id';
		$pindex = max(1, intval($_GPC['page']));
		$type = ' where uniacid=' . uniacid;
		switch (intval($_GPC['type'])) {
			case '0':
				$sql .= ' where a.uniacid=' . uniacid;
				$type .= ' and status>0';
				break;
			case '1':
				$sql .= ' where a.status=0 and a.uniacid=' . uniacid;
				$type .= ' and status=0';
				break;
			case '2':
				$sql .= ' where a.status=1 and a.uniacid=' . uniacid;
				$type .= ' and status=1';
				break;
			case '3':
				$sql .= ' where a.status=2 and a.uniacid=' . uniacid;
				$type .= ' and status=2';
				break;
			case '4':
				$order = pdo_get(table . 'order', array("id" => intval($_GPC['id'])));
				if ($order['status'] == '2') {
					$msg = array("msg" => "订单已处理，勿反复操作", "error" => "1");
				} else {
					if ($order['status'] == '1') {
						if (!empty($order)) {
							$res = pdo_update(table . 'order', array("status" => "2", "expresn" => $_GPC['yd'], "exprename" => $_GPC['kd']), array("id" => intval($_GPC['id'])));
							if (!empty($res)) {
								$msg = array("msg" => "操作成功", "error" => "0");
							}
						} else {
							$msg = array("msg" => "订单有误", "error" => "1");
						}
					} else {
						$msg = array("msg" => "订单状态异常", "error" => "0");
					}
				}
				exit(json_encode($msg));
				break;
			case '5':
				$cash = pdo_get(table . 'cash_log', array("id" => intval($_GPC['id'])));
				if ($cash['type'] == '2') {
					message('订单已处理，勿反复操作', '', 'error');
				} else {
					if ($cash['type'] == '0') {
						if (!empty($cash)) {
							$res = pdo_update(table . 'uni', array("re_cash -=" => $cash['cash'], "cash +=" => $cash['cash']));
							if (!empty($res)) {
								$cash_log = pdo_update(table . 'cash_log', array("type" => "2", "note" => $_GPC['note']), array("id" => intval($_GPC['id'])));
								if (!empty($cash_log)) {
									pdo_insert(table . 'uni_log', array("uniacid" => uniacid, "type" => "0", "cash" => $cash['cash'], "note" => '提现被拒绝，原因：' . $_GPC['note'], "time" => time()));
								}
							}
							$this->ok('操作成功');
						} else {
							message('订单有误', '', 'error');
						}
					} else {
						message('订单状态异常', '', 'error');
					}
				}
				break;
		}
		$psize = 10;
		$sql .= ' order by addtime desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$data = pdo_fetchall($sql);
		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'order') . $type);
		$pager = pagination($total, $pindex, $psize);
		include $this->template('web/order');
	}
	public function doMobileshop()
	{
		global $_GPC, $_W;
		$shop = pdo_get(table . 'setting', array("uniacid" => uniacid));
		if ($shop['shop'] <= 0) {
			message('管理员未设置挪车码销售价格');
		}
		include $this->template('shop');
	}
	public function doMobileu_setting()
	{
		global $_GPC, $_W;
		$title = '收款码设置';
		load()->func('tpl');
		$uni_setting = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
		if ($uni_setting['fw_set'] != '1') {
			message('服务未开启');
		}
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		include $this->template('u_setting');
	}
	public function doWebshopsetting()
	{
		global $_GPC, $_W;
		include $this->template('web/shopsetting');
	}
	public function dowebzengzhi()
	{
		global $_GPC, $_W;
		$res = pdo_get(table . 'zengzhi', array("uniacid" => uniacid));
		$sql = 'select a.paytime,a.endtime,a.status,b.car,b.openid,c.nickname,c.headimg from ' . tablename(table . 'wzts') . '  as a left join ' . tablename(table . 'car') . ' as b on a.sn=b.sn left join ' . tablename(table . 'user') . ' as c on a.user_id=c.id  where  a.uniacid=' . uniacid;
		$pindex = max(1, intval($_GPC['page']));
		$psize = 5;
		$sql .= ' order by time desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$user_car = pdo_fetchall($sql);
		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'wzts') . ' WHERE uniacid=' . uniacid);
		$pager = pagination($total, $pindex, $psize);
		if ($_GPC['action'] == 'weizhang') {
			if ($_W['ispost']) {
				if (!empty($res)) {
					pdo_update(table . 'zengzhi', array("weizhang" => json_encode($_GPC['data'])), array("uniacid" => uniacid));
				} else {
					pdo_insert(table . 'zengzhi', array("uniacid" => uniacid, "weizhang" => json_encode($_GPC['data'])));
				}
				$this->ok();
			}
		}
		$weizhang = json_decode($res['weizhang'], true);
		include $this->template('web/zengzhi');
	}
	public function dowebprint()
	{
		global $_GPC, $_W;
		$i = 1;
		if (!$_GPC['count']) {
			message('请选择数量进行生成');
		}
		if ($_GPC['count'] > 1000) {
			message('请选择1000一下的数量进行生成');
		}
		$tid = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
		pdo_insert(table . 'nullqrcode_log', array("uniacid" => uniacid, "tid" => $tid, "count" => $_GPC['count'], "time" => time(), "q_id" => $_GPC['qid']));
		$i;
		while ($i <= $_GPC['count']) {
			pdo_insert(table . 'nullqrcode', array("uniacid" => uniacid, "tid" => $tid, "q_id" => $_GPC['qid']));
			$id = pdo_insertid();
			$ids[] = $id;
			$sn = md5(time() . $id);
			$sns[] = $sn;
			$url = murl('entry//bind', ["m" => "beta_car", "op" => "scan", "sign" => $sn], 1, true);
			$urls[] = $url;
			pdo_update(table . 'nullqrcode', array("sn" => $sn, "url" => $url), array("id" => $id));
			$i++;
		}
		$this->ok($_GPC[count] . '条空码生成成功');
	}
	public function doWebwzcode()
	{
		global $_GPC, $_W;
		if ($_W['ispost']) {
			if ($_GPC['action'] == 'add') {
				if ($_GPC['count'] > 1000) {
					itoast('生成数量不要超过1000张');
				} else {
					$i = 1;
					$tid = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
					pdo_insert(table . 'wzcode_log', array("uniacid" => uniacid, "tid" => $tid, "count" => $_GPC['count'], "time" => time()));
					$i;
					while ($i <= $_GPC['count']) {
						pdo_insert(table . 'wzcode', array("uniacid" => uniacid, "tid" => $tid));
						$id = pdo_insertid();
						$sn = md5(time() . $id * time() + $tid);
						pdo_update(table . 'wzcode', array("code" => $sn), array("id" => $id));
						$i++;
					}
					itoast($_GPC[count] . '条空码生成成功', null, 'success');
				}
			}
		} else {
			$data = pdo_getall(table . 'wzcode_log', array("uniacid" => uniacid));
			include $this->template('web/wzcode');
		}
	}
	public function dowebnullqrcode()
	{
		global $_GPC, $_W;
		if ($_GPC['action'] == 'edit') {
			if ($_W['ispost']) {
				pdo_update(table . 'nullqrcode', array("q_id" => $_GPC['data']['q_id']), array("tid" => $_GPC['tid']));
				pdo_update(table . 'nullqrcode_log', array("q_id" => $_GPC['data']['q_id']), array("tid" => $_GPC['tid']));
				message('更新成功', $this->createWebUrl('nullqrcode'), 'success');
			} else {
				$qudao = pdo_getall(table . 'qudao', array("uniacid" => uniacid));
				$null = pdo_get(table . 'nullqrcode_log', array("tid" => $_GPC['tid']));
				include $this->template('web/nullqrcode');
			}
		} else {
			$sql = 'SELECT a.*,b.name FROM ' . tablename(table . 'nullqrcode_log') . 'AS a left join ' . tablename(table . 'qudao') . 'AS b on a.q_id=b.id  where a.uniacid=' . uniacid . ' order by a.time desc';
			$data = pdo_fetchall($sql);
			$qudao = pdo_getall(table . 'qudao', array("uniacid" => uniacid));
			include $this->template('web/nullqrcode');
		}
	}
	public function dowebdownexcel()
	{
		global $_GPC, $_W;
		if (!$_GPC['tid']) {
			message('参数有误');
		}
		$sql = 'SELECT a.id,a.sn,a.url,a.status,b.mobile,b.car,b.time FROM ' . tablename(table . 'nullqrcode') . ' AS a left join' . tablename(table . 'car') . ' AS b on a.sn=b.sn where  a.tid=' . '\'' . $_GPC['tid'] . '\'';
		$data = pdo_fetchall($sql);
		if (empty($data)) {
			message('参数有误');
		}
		load()->library('phpexcel/PHPExcel');
		$phpexcel = new PHPExcel();
		$phpexcel->getActiveSheet()->setTitle(count($data) . '条挪车码');
		$phpexcel->getActiveSheet()->setCellValue('A1', '空码id')->setCellValue('B1', '二维码链接')->setCellValue('C1', '绑定状态')->setCellValue('D1', '绑定车辆信息')->setCellValue('E1', '绑定时间');
		$i = 2;
		foreach ($data as $row) {
			if ($row['status'] == 1) {
				$status = '已绑定';
				$car = $row['car'] . ' , ' . $row['mobile'];
				$time = date('Y-m-d H:i:s', $row['time']);
			} else {
				$status = '未绑定';
				$car = '';
				$time = '';
			}
			$phpexcel->getActiveSheet()->setCellValue('A' . $i, $row['id'])->setCellValue('B' . $i, $row['url'])->setCellValue('C' . $i, $status)->setCellValue('D' . $i, $car)->setCellValue('E' . $i, $time);
			$i++;
		}
		$obj_Writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel5');
		$filename = count($data) . '条挪车码' . date('Y-m-d') . '.xls';
		header('Content-Type: application/force-download');
		header('Content-Type: application/octet-stream');
		header('Content-Type: application/download');
		header('Content-Disposition:inline;filename="' . $filename . '"');
		header('Content-Transfer-Encoding: binary');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: no-cache');
		$obj_Writer->save('php://output');
		die;
	}
	public function dowebdownwzcode()
	{
		global $_GPC, $_W;
		if (!$_GPC['tid']) {
			message('参数有误');
		}
		$data = pdo_getall(table . 'wzcode', array("tid" => $_GPC['tid'], "uniacid" => uniacid));
		if (empty($data)) {
			message('参数有误');
		}
		load()->library('phpexcel/PHPExcel');
		$phpexcel = new PHPExcel();
		$phpexcel->getActiveSheet()->setTitle(count($data) . '条激活码');
		$phpexcel->getActiveSheet()->setCellValue('A1', '激活码id')->setCellValue('B1', '激活码')->setCellValue('C1', '状态');
		$i = 2;
		foreach ($data as $row) {
			if ($row['status'] == 1) {
				$status = '已绑定';
			} else {
				$status = '未绑定';
			}
			$phpexcel->getActiveSheet()->setCellValue('A' . $i, $row['id'])->setCellValue('B' . $i, $row['code'])->setCellValue('C' . $i, $status);
			$i++;
		}
		$obj_Writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel5');
		$filename = count($data) . '条激活码' . date('Y-m-d') . '.xls';
		header('Content-Type: application/force-download');
		header('Content-Type: application/octet-stream');
		header('Content-Type: application/download');
		header('Content-Disposition:inline;filename="' . $filename . '"');
		header('Content-Transfer-Encoding: binary');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: no-cache');
		$obj_Writer->save('php://output');
		die;
	}
	public function doMobileuser_setting()
	{
		global $_GPC, $_W;
		if ($_W[isajax]) {
			$user_info = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
			if ($_GPC['action'] == 'phone_set') {
				if ($_GPC['value'] == '0') {
					if ($user_info['wx_set'] == '0') {
						$msg = array("errno" => 0, "msg" => "关闭失败，必须保留一种通知方式");
					} else {
						$res = pdo_update(table . 'user', array("phone_set" => $_GPC['value']), array("uniacid" => uniacid, "openid" => openid));
						if (!empty($res)) {
							$msg = array("errno" => 1);
						} else {
							$msg = array("errno" => 0);
						}
					}
				} else {
					$res = pdo_update(table . 'user', array("phone_set" => $_GPC['value']), array("uniacid" => uniacid, "openid" => openid));
					if (!empty($res)) {
						$msg = array("errno" => 1);
					} else {
						$msg = array("errno" => 0);
					}
				}
			} else {
				if ($_GPC['action'] == 'wx_set') {
					if ($_GPC['value'] == '0') {
						if ($user_info['phone_set'] == '0') {
							$msg = array("errno" => 0, "msg" => "关闭失败，必须保留一种通知方式");
						} else {
							$res = pdo_update(table . 'user', array("wx_set" => $_GPC['value']), array("uniacid" => uniacid, "openid" => openid));
							if (!empty($res)) {
								$msg = array("errno" => 1);
							} else {
								$msg = array("errno" => 0);
							}
						}
					} else {
						$res = pdo_update(table . 'user', array("wx_set" => $_GPC['value']), array("uniacid" => uniacid, "openid" => openid));
						if (!empty($res)) {
							$msg = array("errno" => 1);
						} else {
							$msg = array("errno" => 0);
						}
					}
				}
			}
			echo json_encode($msg);
		}
	}
	public function doMobileMyteam()
	{
		global $_GPC, $_W;
		$title = '财富中心';
		load()->func('tpl');
		$uni_setting = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
		if ($uni_setting['fw_set'] != '1') {
			message('服务未开启');
		}
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		if ($_W['isajax']) {
			if ($_GPC['action'] == 'tixian') {
				if ($u['cash'] < $uni_setting['tixian']) {
					$msg = array("error" => "0", "msg" => '不满足提现条件，满' . $uni_setting['tixian'] . '才能提现');
				} else {
					if ($u['cash'] <= '0') {
						$msg = array("error" => "0", "msg" => "余额不足");
					} else {
						if (!empty($u['wx_img'])) {
							$res = pdo_update(table . 'user', array("cash -=" => $u['cash'], "re_cash +=" => $u['cash']), array("id" => $u['id']));
							if (!empty($res)) {
								$tid = date('YmdHis') . str_pad(mt_rand(1, 99999999), 5, '0', STR_PAD_LEFT);
								$log_res = pdo_insert(table . 'cash_log', array("tid" => $tid, "userid" => $u['id'], "uniacid" => uniacid, "type" => "0", "cash" => $u['cash'], "note" => "申请提现", "time" => time()));
								if (!empty($log_res)) {
									pdo_insert(table . 'user_log', array("userid" => $u['id'], "uniacid" => uniacid, "type" => "1", "money" => $u['cash'], "note" => '提现号：' . $tid, "time" => time()));
									$msg = array("msg" => "申请成功", "error" => "1");
								}
							}
						} else {
							$msg = array("error" => "0", "msg" => "请先设置收款码", "url" => $this->createMobileUrl('u_setting'));
						}
					}
				}
				echo json_encode($msg);
			}
		} else {
			include $this->template('myteam');
		}
	}
	public function doMobileupload()
	{
		global $_GPC, $_W;
		load()->func('file');
		$u = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => openid));
		$res = file_upload($_FILES['imgup'], 'image', '', true);
		if (!empty($res['success'])) {
			pdo_update(table . 'user', array("wx_img" => $res['path']), array("id" => $u['id']));
		}
		echo json_encode($res);
	}
	public function doMobilegetcode()
	{
		global $_GPC, $_W;
		if ($_W['isajax']) {
			$_SESSION['getcodecount'] = $_SESSION['getcodecount'] + 1;
			if ($_SESSION['getcodecount'] > 3) {
				echo json_encode(array("code" => 444, "msg" => "请勿频繁请求"));
			} else {
				$_SESSION['getcode'] = rand('1000', '9999');
				$_SESSION['getcodeendtime'] = time() + 180;
				$setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
				require_once IA_ROOT . '/addons/beta_car/plugin/sendsms/lib/Ucpaas.class.php';
				$options['accountsid'] = $setting['sms_sid'];
				$options['token'] = $setting['sms_token'];
				$ucpass = new Ucpaas($options);
				$appid = $setting['sms_appid'];
				$templateid = $setting['sms_templateid'];
				$param = $_SESSION['getcode'];
				$mobile = $_GPC['tel'];
				$uid = '';
				echo $ucpass->SendSms($appid, $templateid, $param, $mobile, $uid);
			}
		}
	}
	public function doMobilebind()
	{
		global $_GPC, $_W;
		$setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$title = '绑定爱车';
		$res = pdo_get(table . 'nullqrcode', array("sn" => $_GPC['sign']));
		if ($_W['isajax']) {
			if ($res['status'] == '0') {
				if ($_GPC['sign'] == $_GPC['sn']) {
					if ($setting['sms_set'] == 1) {
						if ($_GPC['code'] != $_SESSION['getcode']) {
							$msg = array("ext" => 0, "msg" => "短信验证码有误");
						} else {
							if (time() > $_SESSION['getcodeendtime']) {
								$msg = array("ext" => 0, "msg" => "短信验证码已过期请重新获取");
							} else {
								$_SESSION['getcode'] = null;
								$inst = pdo_insert(table . 'car', array("uniacid" => uniacid, "openid" => openid, "sn" => $_GPC['sn'], "car" => $_GPC['car'], "mobile" => $_GPC['mobile'], "time" => time()));
								if (!empty($inst)) {
									$up = pdo_update(table . 'nullqrcode', array("status" => "1"), array("id" => $res['id']));
									if (!empty($up)) {
										$msg = array("ext" => 1, "msg" => "绑定成功");
									} else {
										$msg = array("ext" => 0, "msg" => "error");
									}
								}
							}
						}
					} else {
						$inst = pdo_insert(table . 'car', array("uniacid" => uniacid, "openid" => openid, "sn" => $_GPC['sn'], "car" => $_GPC['car'], "mobile" => $_GPC['mobile'], "time" => time()));
						if (!empty($inst)) {
							$up = pdo_update(table . 'nullqrcode', array("status" => "1"), array("id" => $res['id']));
							if (!empty($up)) {
								$msg = array("ext" => 1, "msg" => "绑定成功");
							} else {
								$msg = array("ext" => 0, "msg" => "error");
							}
						}
					}
				} else {
					$msg = array("ext" => 0, "msg" => "数据校检失败");
				}
			} else {
				$msg = array("ext" => 0, "msg" => "此挪车码已被绑定");
			}
			echo json_encode($msg);
		} else {
			if ($res['status'] == '0') {
				$account_api = WeAccount::create();
				$fans_info = $account_api->fansQueryInfo($_W['fans']['openid']);
				include $this->template('bind');
			} else {
				if ($res['status'] == '1') {
					header('location: ' . murl('entry//qrcode', ["m" => "beta_car", "op" => "scan", "sign" => $_GPC['sign']], 1, true));
				} else {
					message('挪车码有误');
				}
			}
		}
	}
	public function doMobileaddcar()
	{
		global $_GPC, $_W;
		$setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$title = '绑定爱车';
		if ($_W[isajax]) {
			if (!empty(openid)) {
				$_GPC['data']['uniacid'] = uniacid;
				$_GPC['data']['openid'] = openid;
				$_GPC['data']['time'] = time();
				if ($setting['sms_set'] == 1) {
					if ($_GPC['code'] != $_SESSION['getcode']) {
						$msg = array("ext" => 0, "msg" => "短信验证码有误");
					} else {
						if (time() > $_SESSION['getcodeendtime']) {
							$msg = array("ext" => 0, "msg" => "短信验证码已过期请重新获取");
						} else {
							$_SESSION['getcode'] = null;
							$count = pdo_count(table . 'car', array("car" => $_GPC['data']['car'], "uniacid" => uniacid, "openid" => openid));
							if ($count == '0') {
								pdo_begin();
								$request = $this->instert('car', $_GPC['data']);
								if ($request) {
									$id = pdo_insertid();
									$code = str_pad($id, 11, 0, STR_PAD_LEFT);
									$sn = md5(time() . $id);
									$dir = md5($id . openid . uniacid);
									$url = murl('entry//qrcode', ["m" => "beta_car", "op" => "scan", "sign" => $sn], 1, true);
									$de = $this->qrcode($url, $dir, $code);
									$request = $this->update('car', array("sn" => $sn, "qrcode" => $de), array("id" => $id));
									if ($request) {
										pdo_commit();
										$msg = array("ext" => 1, "sign" => $sn);
									} else {
										pdo_rollback();
										$msg = array("ext" => 0, "msg" => "系统错误，生成失败");
									}
								}
							} else {
								$msg = array("ext" => 0, "msg" => "挪车码已生成，请勿重复提交");
							}
						}
					}
				} else {
					$_SESSION['getcode'] = null;
					$count = pdo_count(table . 'car', array("car" => $_GPC['data']['car'], "uniacid" => uniacid, "openid" => openid));
					if ($count == '0') {
						pdo_begin();
						$request = $this->instert('car', $_GPC['data']);
						if ($request) {
							$id = pdo_insertid();
							$code = str_pad($id, 11, 0, STR_PAD_LEFT);
							$sn = md5(time() . $id);
							$dir = md5($id . openid . uniacid);
							$url = murl('entry//qrcode', ["m" => "beta_car", "op" => "scan", "sign" => $sn], 1, true);
							$de = $this->qrcode($url, $dir, $code);
							$request = $this->update('car', array("sn" => $sn, "qrcode" => $de), array("id" => $id));
							if ($request) {
								pdo_commit();
								$msg = array("ext" => 1, "sign" => $sn);
							} else {
								pdo_rollback();
								$msg = array("ext" => 0, "msg" => "系统错误，生成失败");
							}
						}
					} else {
						$msg = array("ext" => 0, "msg" => "挪车码已生成，请勿重复提交");
					}
				}
			} else {
				$msg = array("ext" => 0, "msg" => "请用微信申请");
			}
			echo json_encode($msg);
		} else {
			include $this->template('addcar');
		}
	}
	public function doMobiledel_car()
	{
		global $_GPC, $_W;
		if ($_W[isajax]) {
			$sn = $_GPC['sn'];
			$res = pdo_delete(table . 'car', array("sn" => $sn, "openid" => openid));
			if (!empty($res)) {
				$msg = array("ext" => 0);
			} else {
				$msg = array("ext" => 1);
			}
			echo json_encode($msg);
		}
	}
	public function doWebdel_car()
	{
		global $_GPC, $_W;
		$sn = $_GPC['sign'];
		$res = pdo_delete(table . 'car', array("sn" => $sn));
		if (!empty($res)) {
			$this->ok('删除成功');
		} else {
			message('删除失败');
		}
	}
	public function doMobileindex()
	{
		global $_GPC, $_W;
		$title = '首页';
		$account_api = WeAccount::create();
		$fans_info = $account_api->fansQueryInfo($_W['fans']['openid']);
		$setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$user_setting = pdo_get(table . 'user', array("uniacid" => uniacid, "openid" => $_W['fans']['openid']));
		$u_set = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
		include $this->template('index');
	}
	public function doMobilemyqrcodelist()
	{
		global $_GPC, $_W;
		$title = '我的挪车码';
		$list = pdo_getall(table . 'car', array("openid" => openid, "uniacid" => uniacid));
		$account_api = WeAccount::create();
		$fans = $account_api->fansQueryInfo(openid);
		$car_set = pdo_get(table . 'setting', array("uniacid" => uniacid), array("car_set", "img"));
		include $this->template('qrlist');
	}
	public function doMobileshow()
	{
		global $_GPC, $_W;
		$sn = $_GPC['sign'];
		if (!empty($sn)) {
			$data = pdo_get(table . 'car', array("sn" => $_GPC['sign']));
			$title = $data['car'];
			if ($data) {
				if ($data['uniacid'] != uniacid) {
					message('非法操作');
				}
			} else {
				message('非法操作');
			}
		}
		include $this->template('show');
	}
	public function doMobileguanzhu()
	{
		global $_GPC, $_W;
		$account_api = WeAccount::create();
		$fans_info = $account_api->fansQueryInfo($_W['fans']['openid']);
		echo json_encode($fans_info);
	}
	public function doMobileweixin()
	{
		global $_GPC, $_W;
		error_reporting(0);
		$template = pdo_get(table . 'setting', array("uniacid" => uniacid), array("template_id"));
		$account_api = WeAccount::create();
		$vlidata = cache_load(openid);
		$wx = pdo_get(table . 'setting', array("uniacid" => uniacid), array("wx_header", "wx_footer", "wx_time"));
		if ($template['template_id'] == '') {
			echo json_encode(array("errno" => "-1", "message" => "未设置模板消息ID，请联系管理员"));
		} else {
			if ($_W['ispost']) {
				$data = array("first" => array("value" => $wx['wx_header'], "color" => "#ff510"), "keyword1" => array("value" => "挪车请求", "color" => "#ff510"), "keyword2" => array("value" => $_GPC['car_id'], "color" => "#ff510"), "keyword3" => array("value" => "麻烦挪一下车，谢谢", "color" => "#ff510"), "keyword4" => array("value" => date('Y-m-d H:i:s', time()), "color" => "#ff510"), "remark" => array("value" => $wx['wx_footer'], "color" => "#ff510"));
				if (time() - $vlidata['time'] < $wx['wx_time'] * 60 and $vlidata['car_id'] == $_GPC['car_id']) {
					echo json_encode(array("errno" => "1", "message" => "你已经通知过车主了，如果车主长时间未到达，请尝试电话联系"));
				} else {
					$result = $account_api->sendTplNotice($_GPC['openid'], $template['template_id'], $data);
					if (!empty($result)) {
						if ($result == '1') {
							cache_write(openid, array("time" => time(), "car_id" => $_GPC['car_id']));
							echo json_encode(array("errno" => "1", "message" => "通知成功"));
						} else {
							echo json_encode($result);
						}
					}
				}
			} else {
				echo '0';
			}
		}
	}
	public function doWebad()
	{
		global $_GPC, $_W;
		$uniacid = intval($_W['uniacid']);
		if ($_GET['action'] == 'del') {
			$result = pdo_delete(table . 'ad', array("id" => $_GET['id'], "uniacid" => $uniacid));
			if (!empty($result)) {
				$msg = array("error" => "0");
			} else {
				$msg = array("error" => "1");
			}
			echo json_encode($msg);
		} else {
			if ($_GET['action'] == 'edit') {
				if ($_W['ispost']) {
					$result = pdo_update(table . 'ad', $_GPC['data'], array("id" => $_GET['id']));
					message('更新成功', $this->createWebUrl('ad'), 'success');
				} else {
					$sql = 'SELECT a.*,b.name FROM ' . tablename(table . 'ad') . 'AS a left join ' . tablename(table . 'qudao') . 'AS b on a.q_id=b.id  where a.uniacid=' . uniacid . ' and a.id=' . $_GET['id'];
					$ad = pdo_fetch($sql);
					$qudao = pdo_getall(table . 'qudao', array("uniacid" => uniacid));
					include $this->template('/web/ad');
				}
			} else {
				if ($_W['ispost']) {
					if (checksubmit()) {
						$_GPC['data']['uniacid'] = $uniacid;
						$result = pdo_insert(table . 'ad', $_GPC['data']);
						if (!empty($result)) {
							$this->ok();
						}
					} else {
						message('token', 'referer', 'error');
					}
				} else {
					$sql = 'SELECT a.*,b.name FROM ' . tablename(table . 'ad') . 'AS a left join ' . tablename(table . 'qudao') . 'AS b on a.q_id=b.id  where a.uniacid=' . uniacid;
					$ad = pdo_fetchall($sql);
					$qudao = pdo_getall(table . 'qudao', array("uniacid" => uniacid));
					include $this->template('/web/ad');
				}
			}
		}
	}
	public function doWebqudao()
	{
		global $_GPC, $_W;
		$uniacid = intval($_W['uniacid']);
		if ($_GET['action'] == 'del') {
			$result = pdo_delete(table . 'qudao', array("id" => $_GET['id'], "uniacid" => $uniacid));
			if (!empty($result)) {
				$msg = array("error" => "0");
			} else {
				$msg = array("error" => "1");
			}
			echo json_encode($msg);
		} else {
			if ($_GET['action'] == 'edit') {
				if ($_W['ispost']) {
					pdo_update(table . 'qudao', $_GPC['data'], array("id" => $_GPC['id']));
					message('更新成功', $this->createWebUrl('qudao'), 'success');
				} else {
					$qudao = pdo_get(table . 'qudao', array("id" => $_GPC['id']));
					include $this->template('/web/qudao');
				}
			} else {
				if ($_W['ispost']) {
					if (checksubmit()) {
						$_GPC['data']['uniacid'] = $uniacid;
						$result = pdo_insert(table . 'qudao', $_GPC['data']);
						if (!empty($result)) {
							$this->ok();
						}
					} else {
						message('token', 'referer', 'error');
					}
				} else {
					$ad = pdo_getall(table . 'qudao', array("uniacid" => $uniacid));
					include $this->template('/web/qudao');
				}
			}
		}
	}
	public function doWebcar_list()
	{
		global $_GPC, $_W;
		$sql = 'SELECT a.*,b.headimg,b.nickname FROM ' . tablename(table . 'car') . 'AS a left join ' . tablename(table . 'user') . 'AS b on a.uniacid=b.uniacid and a.openid=b.openid where a.uniacid=' . uniacid;
		$pindex = max(1, intval($_GPC['page']));
		$psize = 10;
		$sql .= ' order by time desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$user_car = pdo_fetchall($sql);
		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'car') . ' WHERE uniacid=' . uniacid);
		$pager = pagination($total, $pindex, $psize);
		include $this->template('/web/car_list');
	}
	public function doWebcash()
	{
		global $_GPC, $_W;
		$setting = pdo_get(table . 'unisetting', array("type" => "setting"));
		$user_info = pdo_get(table . 'uni', array("uniacid" => uniacid));
		if ($_W['ispost']) {
			if ($_GPC['action'] == 'wximg') {
				if (checksubmit()) {
					$res = pdo_update(table . 'uni', array("wximg" => $_GPC['data']['img']), array("uniacid" => uniacid));
					if (!empty($res)) {
						$this->ok('设置成功');
					}
				}
			} else {
				if ($_GPC['action'] == 'tixian') {
					if ($user_info['cash'] < $setting['tixian']) {
						message('不满足提现条件，满' . $setting['tixian'] . '才能提现');
					} else {
						if ($user_info['cash'] <= '0') {
							message('余额不足');
						} else {
							if (!empty($user_info['wximg'])) {
								$res = pdo_update(table . 'uni', array("cash -=" => $user_info['cash'], "re_cash +=" => $user_info['cash']), array("uniacid" => uniacid));
								if (!empty($res)) {
									$tid = date('YmdHis') . str_pad(mt_rand(1, 99999999), 5, '0', STR_PAD_LEFT);
									$log_res = pdo_insert(table . 'cash_log', array("tid" => $tid, "uniacid" => uniacid, "type" => "0", "cash" => $user_info['cash'], "note" => "申请提现", "time" => time()));
									if (!empty($log_res)) {
										pdo_insert(table . 'uni_log', array("uniacid" => uniacid, "type" => "1", "cash" => $user_info['cash'], "note" => '提现号：' . $tid, "time" => time()));
										$this->ok('申请成功');
									}
								}
							} else {
								message('请先设置收款码');
							}
						}
					}
				}
			}
		} else {
			$sql = 'SELECT * FROM ' . tablename(table . 'uni_log') . ' WHERE uniacid=' . uniacid;
			$pindex = max(1, intval($_GPC['page']));
			$type = 'where uniacid=' . uniacid;
			switch (intval($_GPC['type'])) {
				case '0':
					$sql .= '';
					break;
				case '1':
					$sql .= ' and type=0';
					$type .= ' and type=0';
					break;
				case '2':
					$sql .= ' and type=1';
					$type .= ' and type=1';
					break;
			}
			$psize = 10;
			$sql .= ' order by time desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
			$user_log = pdo_fetchall($sql);
			$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'uni_log') . $type);
			$pager = pagination($total, $pindex, $psize);
			include $this->template('web/cash');
		}
	}
	public function doWebsetting()
	{
		global $_W, $_GPC;
		$uniacid = intval($_W['uniacid']);
		$setting = pdo_get(table . 'setting', array("uniacid" => $uniacid));
		if ($_W['ispost']) {
			if (checksubmit()) {
				$_GPC['data']['uniacid'] = $uniacid;
				if (empty($setting)) {
					$result = pdo_insert(table . 'setting', $_GPC['data']);
				} else {
					$result = pdo_update(table . 'setting', $_GPC['data'], array("uniacid" => uniacid));
				}
				if (!empty($result)) {
					message('更新成功', 'referer', 'success');
				} else {
					message('更新失败', 'referer', 'error');
				}
			}
		} else {
			$config = pdo_get(table . 'setting', array("uniacid" => $uniacid));
			include $this->template('/web/setting');
		}
	}
	public function doWebsite()
	{
		global $_W;
		if ($_W['isfounder'] != 1) {
			message('error');
		} else {
			$res = pdo_get(table . 'site', array("type" => "siteinfo"));
			if (!$res) {
				$token = md5($_W['siteroot'] . time() . $_W['user']['uid'] . date('HisY', time()));
				pdo_insert(table . 'site', array("type" => "siteinfo", "url" => $_W['siteroot'], "token" => $tokoken));
				$res = array("url" => $_W['siteroot'], "token" => $token);
			}
			$data = ihttp_post('https://weixin.betanet.top/app/index.php?i=2&c=entry&do=api&m=beta_movecarpay', array("url" => $res['url'], "token" => $res['token'], "action" => "siteinfo"));
			if ($data['code'] == '200') {
				$json = json_decode($data['content'], true);
				if ($json['status'] == 'ok') {
					$status = '1';
				} else {
					$status = '0';
				}
			}
			include $this->template('web/site');
		}
	}
	public function doWebuni_tixian()
	{
		global $_GPC, $_W;
		$sql = 'SELECT a.*,b.nickname,b.wx_img FROM ' . tablename(table . 'cash_log') . ' AS a left join' . tablename(table . 'user') . ' AS b on a.userid=b.id where a.uniacid=' . uniacid;
		$pindex = max(1, intval($_GPC['page']));
		$type = 'where uniacid=' . uniacid;
		switch (intval($_GPC['type'])) {
			case '0':
				$sql .= '';
				break;
			case '1':
				$sql .= '  and a.type=0';
				$type .= ' and type=0';
				break;
			case '2':
				$sql .= '  and a.type=1';
				$type .= ' and type=1';
				break;
			case '3':
				$sql .= '  and a.type=2';
				$type .= ' and type=2';
				break;
			case '4':
				$cash = pdo_get(table . 'cash_log', array("id" => intval($_GPC['id'])));
				if ($cash['type'] == '1') {
					message('订单已处理，勿反复操作', '', 'error');
				} else {
					if ($cash['type'] == '0') {
						if (!empty($cash)) {
							$res = pdo_update(table . 'user', array("re_cash -=" => $cash['cash']));
							if (!empty($res)) {
								pdo_update(table . 'cash_log', array("type" => "1"), array("id" => intval($_GPC['id'])));
								$this->ok('操作成功');
							}
						} else {
							message('订单有误', '', 'error');
						}
					} else {
						message('订单状态异常', '', 'error');
					}
				}
				break;
			case '5':
				$cash = pdo_get(table . 'cash_log', array("id" => intval($_GPC['id'])));
				if ($cash['type'] == '2') {
					message('订单已处理，勿反复操作', '', 'error');
				} else {
					if ($cash['type'] == '0') {
						if (!empty($cash)) {
							$res = pdo_update(table . 'user', array("re_cash -=" => $cash['cash'], "cash +=" => $cash['cash']), array("id" => $cash['userid']));
							if (!empty($res)) {
								$cash_log = pdo_update(table . 'cash_log', array("type" => "2", "note" => $_GPC['note']), array("id" => intval($_GPC['id'])));
								if (!empty($cash_log)) {
									pdo_insert(table . 'user_log', array("userid" => $cash['userid'], "uniacid" => uniacid, "type" => "0", "money" => $cash['cash'], "note" => '提现被拒绝，原因：' . $_GPC['note'], "time" => time()));
								}
							}
							$this->ok('操作成功');
						} else {
							message('订单有误', '', 'error');
						}
					} else {
						message('订单状态异常', '', 'error');
					}
				}
				break;
		}
		$psize = 10;
		$sql .= ' order by time desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$data = pdo_fetchall($sql);
		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'cash_log') . $type);
		$pager = pagination($total, $pindex, $psize);
		include $this->template('web/uni_tixian');
	}
	public function doWebnullqrcodeinfo()
	{
		global $_GPC, $_W;
		$sql = 'SELECT a.sn,a.id,a.status,a.url,b.mobile,b.car,b.time FROM ' . tablename(table . 'nullqrcode') . ' AS a left join' . tablename(table . 'car') . ' AS b on a.sn=b.sn where  a.tid=' . '\'' . $_GPC['tid'] . '\'';
		$pindex = max(1, intval($_GPC['page']));
		$type = 'where tid=' . '\'' . $_GPC['tid'] . '\'';
		switch (intval($_GPC['type'])) {
			case '0':
				$sql .= '';
				break;
			case '1':
				$sql .= '  and a.status=0';
				$type .= ' and status=0 ';
				break;
			case '2':
				$sql .= '  and a.status=1';
				$type .= ' and status=1 ';
				break;
			case '3':
				$sql .= '  and a.status=2';
				$type .= ' and status=2 ';
				break;
		}
		$psize = 10;
		$sql .= ' order by status desc limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$data = pdo_fetchall($sql);
		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename(table . 'nullqrcode') . $type);
		$pager = pagination($total, $pindex, $psize);
		$bangding = pdo_count(table . 'nullqrcode', array("tid" => $_GPC['tid'], "status" => "1"));
		$weibangding = pdo_count(table . 'nullqrcode', array("tid" => $_GPC['tid'], "status" => "0"));
		include $this->template('web/nullqrcodeinfo');
	}
	public function doWebunisetting()
	{
		global $_W, $_GPC;
		$res = pdo_get(table . 'unisetting', array("uniacid" => uniacid));
		if ($_W['ispost']) {
			if (intval($_GPC['cash']) > '100') {
				message('分佣比例不能大于100');
			} else {
				if (intval($_GPC['cash']) <= '0') {
					message('为打造分佣生态平衡，分佣比例不得小于0');
				}
				if (!empty($res)) {
					$ext = pdo_update(table . 'unisetting', array("fw_set" => $_GPC['fw_set'], "cash" => $_GPC['cash'], "tixian" => $_GPC['tixian'], "time" => time()), array("uniacid" => uniacid));
					if (!empty($ext)) {
						$this->ok('设置成功');
					}
				} else {
					$ext = pdo_insert(table . 'unisetting', array("uniacid" => uniacid, "fw_set" => $_GPC['fw_set'], "cash" => $_GPC['cash'], "tixian" => $_GPC['tixian'], "time" => time()));
					if (!empty($ext)) {
						$this->ok('设置成功');
					}
				}
			}
		}
		include $this->template('web/unisetting');
	}
	public function doWeburl()
	{
		global $_GPC, $_W;
		$user = pdo_count(table . 'user', array("uniacid" => uniacid));
		$car = pdo_count(table . 'car', array("uniacid" => uniacid, "qrcode !=" => "NULL"));
		$car1 = pdo_count(table . 'car', array("uniacid" => uniacid, "qrcode =" => "NULL"));
		$strat = strtotime(date('Y-m-d H:i:s', strtotime(date('Y-m-d', time()))));
		$end = strtotime(date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime('+1 day')))));
		$sql = 'select count(*) from ' . tablename(table . 'order') . ' where status=1 and uniacid=' . uniacid . ' and addtime<' . $end . ' and addtime>' . $strat;
		$order = pdo_fetchcolumn($sql);
		$c = array("0" => array("title" => "首页", "url" => "index"));
		include $this->template('web/url');
	}
	public function doMobileqrcode()
	{
		global $_GPC, $_W;
		$title = '联系车主';
		$sn = $_GPC['sign'];
		if ($_GPC['op'] == 'scan') {
			if (!empty($sn)) {
				$data = pdo_get(table . 'car', array("sn" => $sn));
				$template = pdo_get(table . 'setting', array("uniacid" => uniacid), array("template_id"));
				$account_api = WeAccount::create();
				$fans = $account_api->fansQueryInfo($data['openid']);
				$user_setting = pdo_get(table . 'user', array("uniacid" => $data['uniacid'], "openid" => $data['openid']));
				if ($data) {
					if ($data['uniacid'] != uniacid) {
						message('非法操作');
					}
				} else {
					message('非法操作');
				}
			}
			$fans_user = $account_api->fansQueryInfo(openid);
			$user_set = pdo_get(table . 'setting', array("uniacid" => uniacid), array("user_set", "img"));
			$qudao = pdo_get(table . 'nullqrcode', array("sn" => $data['sn']), array("q_id"));
			if (!empty($qudao)) {
				$ad = pdo_getall(table . 'ad', array("uniacid" => uniacid, "q_id" => $qudao['q_id']));
			} else {
				$ad = pdo_getall(table . 'ad', array("uniacid" => uniacid, "q_id" => "0"));
			}
			include $this->template('qr');
		} else {
			message('非法操作');
		}
	}
	public static function getAcsClient()
	{
		$web_setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$product = 'Dyplsapi';
		$domain = 'dyplsapi.aliyuncs.com';
		$accessKeyId = $web_setting['accessKey_id'];
		$accessKeySecret = $web_setting['accessKey_secret'];
		$region = 'cn-hangzhou';
		$endPointName = 'cn-hangzhou';
		if (static::$acsClient == null) {
			$profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
			DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);
			static::$acsClient = new DefaultAcsClient($profile);
		}
		return static::$acsClient;
	}
	public static function bindAxn($time, $num, $fck)
	{
		$request = new BindAxnRequest();
		$request->setPoolKey($fck);
		$request->setPhoneNoA($num);
		$request->setNoType('NO_95');
		$request->setExpiration(date('Y-m-d H:i:s', $time));
		$response = static::getAcsClient()->getAcsResponse($request);
		return $response;
	}
	public function doMobiletel()
	{
		global $_GPC, $_W;
		$status = array("OK" => "请求成功", "isp.RAM_PERMISSION_DENY" => "RAM权限DENY", "isv.OUT_OF_SERVICE" => "业务停机", "isv.PRODUCT_UN_SUBSCRIPT" => "未开通云通信产品的阿里云客户", "isv.PRODUCT_UNSUBSCRIBE" => "产品未开通", "isv.ACCOUNT_NOT_EXISTS" => "账户不存在", "isv.ACCOUNT_ABNORMAL" => "账户异常", "isp.SYSTEM_ERROR" => "系统错误", "isp.UNKNOWN_ERR_CODE" => "运营商未知错误", "isv.PARTNER_NOT_EXIST" => "未知合作伙伴", "isv.NO_NOT_EXIST" => "号码不存在", "isv.ILLEGAL_ARGUMENT" => "参数非法", "isp.DAO_EXCEPTION" => "数据库异常", "isv.NO_AVAILABLE_NUMBER" => "无可用号码", "isp.VENDOR_UNAVAILABLE" => "运营商降级", "isv.FLOW_LIMIT" => "业务流控", "isv.PARTNER_IS_CLOSED" => "partner被关停", "isv.FORBIDDEN_ACTION" => "无权操作", "isv.NO_USED_BY_OTHERS" => "号码被其他业务方占用", "isv.VENDOR_BIND_FAILED" => "运营商绑定失败", "isv.EXPIRE_DATE_ILLEGAL" => "过期时间非法", "isv.MOBILE_NUMBER_ILLEGAL" => "号码格式非法", "isv.BIND_CONFLICT" => "绑定冲突");
		$car = pdo_get(table . 'car', array("sn" => $_GPC['sn']));
		$user_info = pdo_get(table . 'user', array("openid" => $car['openid']));
		$web_setting = pdo_get(table . 'setting', array("uniacid" => uniacid));
		$vlidata = cache_load(openid . 'tel');
		if (!empty($car)) {
			if (time() - $vlidata['time'] < $web_setting['tel_time'] * 60 and $vlidata['car_id'] == $_GPC['sn']) {
				$msg = array("error" => "1", "msg" => "你已经通知过车主了，请耐心等待一下");
			} else {
				cache_write(openid . 'tel', array("time" => time(), "car_id" => $_GPC['sn']));
				if ($web_setting['yinhao_set'] == '1') {
					if (time() - $user_info['bindtime'] > '180') {
						$time = time() + 180;
						$num = $car['mobile'];
						$res = $this->bindAxn($time, $num, $web_setting['fckey']);
						$axnSubsId = $res->SecretBindDTO ? $res->SecretBindDTO->SubsId : null;
						$axnSecretNo = $res->SecretBindDTO ? $res->SecretBindDTO->SecretNo : null;
						if ($res->Code == 'OK') {
							$msg = array("error" => "0", "num" => $axnSecretNo);
							pdo_update(table . 'user', array("bindtime" => time(), "bindnum" => $axnSecretNo), array("id" => $user_info['id']));
						} else {
							cache_delete(openid . 'tel');
							$msg = array("error" => "1", "msg" => $status[$res->Code]);
						}
					} else {
						$msg = array("error" => "0", "num" => $user_info['bindnum']);
					}
				} else {
					$msg = array("error" => "0", "num" => $car['mobile']);
				}
			}
		} else {
			$msg = array("error" => "1", "msg" => "车辆信息有误");
		}
		echo json_encode($msg);
	}
	public function doWebqrcode()
	{
		global $_GPC, $_W;
		if (!empty($_GPC['url'])) {
			load()->library('qrcode/phpqrcode');
			$errorCorrectionLevel = 'L';
			$matrixPointSize = 6;
			QRcode::png(urldecode($_GPC['url']), false, $errorCorrectionLevel, $matrixPointSize, 2);
		} else {
			echo '参数传递有误';
		}
	}
	public function qrcode($text, $name, $sn)
	{
		require MODULE_ROOT . '/phpqrcode/qrlib.php';
		$path = MODULE_ROOT . '/data/qrcode/';
		$path .= date('Ymd', time());
		if (!file_exists($path)) {
			mkdir($path);
		}
		$QRDir = MODULE_ROOT . '/data/qrcode/' . date('Ymd', time()) . '/' . $name . '.png';
		$errorCorrectionLevel = 'H';
		$matrixPointSize = 50;
		$margin = 0;
		$qrCode = new QRcode();
		$qrCode->png($text, $QRDir, $errorCorrectionLevel, $matrixPointSize, $margin);
		$this->resizejpg($QRDir, 560, 560);
		$path_1 = MODULE_ROOT . '/data/qrcode/car.png';
		$path_2 = $QRDir;
		$image_1 = imagecreatefrompng($path_1);
		$image_2 = imagecreatefrompng($path_2);
		$image_3 = imageCreatetruecolor(imagesx($image_1), imagesy($image_1));
		imagecopyresampled($image_3, $image_1, 0, 0, 0, 0, imagesx($image_1), imagesy($image_1), imagesx($image_1), imagesy($image_1));
		imagecopymerge($image_3, $image_2, 315, 410, 0, 0, imagesx($image_2), imagesy($image_2), 100);
		$black = imagecolorallocate($image_3, 0, 0, 0);
		imagefttext($image_3, 30, 0, 421, 1140, $black, MODULE_ROOT . '/data/qrcode/font.ttf', 'No.' . $sn);
		imagepng($image_3, $QRDir);
		imagedestroy($image_3);
		return date('Ymd', time()) . '/' . $name . '.png';
	}
	function resizejpg($imgsrc, $imgwidth, $imgheight)
	{
		$dir = $imgsrc;
		$arr = getimagesize($imgsrc);
		$imgWidth = $imgwidth;
		$imgHeight = $imgheight;
		$imgsrc = imagecreatefrompng($imgsrc);
		$image = imagecreatetruecolor($imgWidth, $imgHeight);
		imagecopyresampled($image, $imgsrc, 0, 0, 0, 0, $imgWidth, $imgHeight, $arr[0], $arr[1]);
		imagepng($image, $dir);
		imagedestroy($image);
	}
	public function instert($table, $data)
	{
		return pdo_insert(table . $table, $data);
	}
	public function update($table, $data, $zd)
	{
		return pdo_update(table . $table, $data, $zd);
	}
	public function find($table, $conditino, $fields)
	{
		return pdo_get(table . $table, $conditino, $fields);
	}
	public function select($table, $conditino, $fields)
	{
		return pdo_getall(table . $table, $conditino, $fields);
	}
}