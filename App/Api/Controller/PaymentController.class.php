<?php
// 本类由系统自动生成，仅供测试用途
namespace Api\Controller;
use Think\Controller;
class PaymentController extends PublicController {


	//***************************
	//  会员立即购买获取数据接口
	//***************************
	public function buy_now(){
		$uid = intval($_REQUEST['uid']);
		if (!$uid) {
			echo json_encode(array('status'=>0,'err'=>'系统错误.'));
			exit();
		}
		//单件商品结算
		//地址管理
		$address=M("address");
		$city=M("china_city");
		$add=$address->where('uid='.intval($uid))->select();
		$citys=$city->where('tid=0')->field('id,name')->select();
		$shopping=M('shopping_char');
		$product=M("product");
		//运费
		$post = M('post');
        
        //立即购买数量
        $num=intval($_REQUEST['num']);
        if (!$num) {
        	$num=1;
        }

        //购物车id
        $cart_id = intval($_REQUEST['cart_id']);
        //检测购物车是否有对应数据
		$check_cart = $shopping->where('id='.intval($cart_id).' AND num>='.intval($num))->getField('pid');
		if (!$check_cart) {
			echo json_encode(array('status'=>0,'err'=>'购物车信息错误.'));
			exit();
		}
		//判断基本库存
		$pro_num = $product->where('id='.intval($check_cart))->getField('num');
		if ($num>intval($pro_num)) {
			echo json_encode(array('status'=>0,'err'=>'库存不足.'));
			exit();
		}
        
		$qz=C('DB_PREFIX');//前缀

		$pro=$shopping->where(''.$qz.'shopping_char.uid='.intval($uid).' and '.$qz.'shopping_char.id='.intval($cart_id))->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id=__SHOPPING_CHAR__.pid')->join('LEFT JOIN __SHANGCHANG__ ON __SHANGCHANG__.id=__SHOPPING_CHAR__.shop_id')->field(''.$qz.'product.num as pnum,'.$qz.'shopping_char.id,'.$qz.'shopping_char.pid,'.$qz.'shangchang.name as sname,'.$qz.'product.name,'.$qz.'product.shop_id,'.$qz.'product.photo_x,'.$qz.'product.price_yh,'.$qz.'shopping_char.num,'.$qz.'shopping_char.buff,'.$qz.'shopping_char.price,'.$qz.'shangchang.alipay,'.$qz.'shangchang.alipay_pid,'.$qz.'shangchang.alipay_key')->find();
		//获取运费
		$yunfei = $post->where('pid='.intval($pro['shop_id']))->find();

		if($pro['buff']!=''){
		    $pro['zprice']=$pro['price']*$num;
		}else{
			$pro['price']=$pro['price_yh'];
		    $pro['zprice']=$pro['price']*$num;
		}

		//如果需要运费
		if ($yunfei) {
			if ($yunfei['price_max']>0 && $yunfei['price_max']<=$pro['zprice']) {
				$yunfei['price']=0;
			}
		}

		$buff_text='';
		if($pro['buff']){
			//获取属性名称
			$buff = explode(',',$pro['buff']);
			if(is_array($buff)){
				foreach($buff as $keys => $val){
					$ggid=M("guige")->where('id='.intval($val))->getField('name');
					//$buff_text .= select('name','aaa_cpy_category','id='.$val['id']).':'.select('name','aaa_cpy_category','id='.$val['val']).' ';
					$buff_text .=' '.$ggid.' ';
				}
			}
		}
		$pro['buff']=$buff_text;
		$pro['photo_x']='http://'.$_SERVER['SERVER_NAME'].__UPLOAD__.'/'.$pro['photo_x'];

		echo json_encode(array('status'=>1,'citys'=>$citys,'yun'=>$yunfei,'adds'=>$add,'pro'=>$pro,'num'=>$num,'buff'=>$buff_text));
		exit();
		//$this->assign('citys',$citys);
	}

	//***************************
	//  会员立即购买下单接口
	//***************************
	public function pay_now(){
		$product=M("product");
		//运费
		$post = M('post');
		$order=M("order");
		$order_pro=M("order_product");

		$uid = intval($_REQUEST['uid']);
		if (!$uid) {
			echo json_encode(array('status'=>0,'err'=>'登录状态异常.'));
			exit();
		}

		//下单
			try {	
				$data = array();
				$data['shop_id']=intval($_POST['sid']);
				$data['uid']=intval($uid);
				$data['addtime']=time();
				$data['del']=0; 
				$data['type']=trim($_POST['paytype']);
				//订单状态 10未付款20代发货30确认收货（待收货）40交易关闭50交易完成
				$data['status']=10;//未付款

				//dump($_POST);exit;
				$_POST['yunfei'] ? $yunPrice = $post->where('id='.intval($_POST['yunfei']))->find() : NULL;
				//dump($yunPrice);exit;
				if(!empty($yunPrice)){
	                $data['post'] = $yunPrice['id'];
	                $data['price']=$_POST['price']+$yunPrice['price'];
				}else{
	                $data['post'] = 0;
	                $data['price']=$_POST['price'];
				}

				$adds_id = intval($_POST['aid']);
				if (!$adds_id) {
					echo json_encode(array('status'=>0,'err'=>'请选择收货地址.'.__LINE__));
					exit();
				}

				$adds_info = M('address')->where('id='.intval($adds_id))->find();
				$data['receiver']=$adds_info['name'];
				$data['tel']=$adds_info['tel'];
				$data['address_xq']=$adds_info['address_xq'];
				$data['code']=$adds_info['code'];
				$data['product_num']=intval($_POST['num']);
				$data['remark']=$_POST['remark'];
				/*******解决屠涂同一订单重复支付问题 lisa**********/
				$data['order_sn']=$this->build_order_no();//生成唯一订单号

				if (!$data['product_num'] || !$data['price']) {
					throw new \Exception("System Error !");
				}

				/**************************************************/
				//dump($data);exit;
				$result = $order->add($data);
				if($result){
					$date =array();
					$date['pid']=intval($_POST['pid']);//商品id
					$date['order_id']=$result;//订单id
					$date['name']=$product->where('id='.intval($date['pid']))->getField('name');//商品名字
					$date['price']=$product->where('id='.intval($date['pid']))->getField('price_yh');
					$date['pro_buff']=$_POST['buff'];
					$date['photo_x']=$product->where('id='.intval($date['pid']))->getField('photo_x');
					$date['addtime']=time();
					$date['num']=intval($_POST['num']);
					//$date['pro_guige']=$_REQUEST['guige'];
					$res = $order_pro->add($date);
					if(!$res){
						throw new \Exception("下单 失败！".__LINE__);
					}

	            	//检查产品是否存在，并修改库存
					$check_pro = $product->where('id='.intval($date['pid']).' AND del=0 AND is_down=0')->field('num,shiyong')->find();
					if (!$check_pro) {
						throw new \Exception("商品不存在或已下架！");
					}
					$up = array();
					$up['num'] = intval($check_pro['num'])-intval($date['num']);
					$up['shiyong'] = intval($check_pro['shiyong'])+intval($date['num']);
					$product->where('id='.intval($date['pid']))->save($up);

					$url=$_SERVER['HTTP_REFERER'];
					
				}else{
					throw new \Exception("下单 失败！");
				}
			} catch (Exception $e) {
				echo json_encode(array('status'=>0,'err'=>$e->getMessage()));
				exit();
			}
			//把需要的数据返回
			$arr = array();
			$arr['order_id'] = $result;
			$arr['order_sn'] = $data['order_sn'];
			$arr['pay_type'] = $_POST['paytype'];
			echo json_encode(array('status'=>1,'arr'=>$arr));
			exit();
	}

	//**********************************
    // 购物车结算 获取数据
    //***********************************
	public function buy_cart(){
		$uid = intval($_REQUEST['uid']);
		if (!$uid) {
			echo json_encode(array('status'=>0,'err'=>'登录状态异常.'));
			exit();
		}

		$address=M("address");
		//运费
		$post = M('post');
		$qz=C('DB_PREFIX');
		$add=$address->where('uid='.intval($uid))->order('is_default desc,id desc')->limit(1)->find();
		$product=M("product");
		$shopping=M('shopping_char');
		$cart_id = trim($_REQUEST['cart_id'],',');
		$id=explode(',', $cart_id);
		if (!$cart_id) {
			echo json_encode(array('status'=>0,'err'=>'网络异常.'.__LINE__));
			exit();
		}

		$pro=array();
		$pro1=array();
		$price = 0;
		foreach($id as $k => $v){
			//检测购物车是否有对应数据
			$check_cart = $shopping->where('id='.intval($v))->getField('id');
			if (!$check_cart) {
				echo json_encode(array('status'=>0,'err'=>'数据异常.'.__LINE__));
				exit();
			}

			$pro[$k]=$shopping->where(''.$qz.'shopping_char.uid='.intval($uid).' and '.$qz.'shopping_char.id='.$v)->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id=__SHOPPING_CHAR__.pid')->field(''.$qz.'product.num as pnum,'.$qz.'shopping_char.id,'.$qz.'shopping_char.pid,'.$qz.'product.name,'.$qz.'product.shop_id,'.$qz.'product.photo_x,'.$qz.'product.price_yh,'.$qz.'shopping_char.num,'.$qz.'shopping_char.buff,'.$qz.'shopping_char.price')->find();
		    //获取运费
		    $yunfei = $post->where('pid='.intval($pro[$k]['shop_id']))->find();
		    //判断是否价格是否变动，是否还有库存
		    if ($pro[$k]['buff']!='') {
		    	//获取不同属性价格库存
				$gg = $this->getprice(intval($pro[$k]['pid']),trim($pro[$k]['buff'],','));
				if ($gg['status']>0) {
					if (floatval($pro[$k]['price'])!=floatval($gg['price'])) {
						$pro[$k]['price'] = floatval($gg['price']);
						$shopping->where('id='.intval($pro[$k]['id']))->save(array('price'=>floatval($gg['price'])));
					}

					//判断当前库存
					if (intval($pro[$k]['num'])>intval($gg['stock'])) {
						echo json_encode(array('status'=>0,'err'=>'商品库存不足.'.__LINE__));
						exit();
					}
				}
		    }

		    $zprice = floatval($pro[$k]['price']*$pro[$k]['num']);
		    $pro[$k]['zprice'] = $zprice;
		    //计算总价
		    $price += $zprice;
		    $pro[$k]['photo_x'] = __DATAURL__.$pro[$k]['photo_x'];
		    //获取可用优惠券
		 	$vou = $this->get_voucher($uid,intval($pro[$k]['pid']),$id);
		}

		//如果需要运费
		if ($yunfei) {
			if ($yunfei['price_max']>0 && $yunfei['price_max']<=$price) {
				$yunfei['price']=0;
			}
		}

		if (!$add) {
			$addemt = 1;
		}else{
			$addemt = 0;
		}

        echo json_encode(array('status'=>1,'vou'=>$vou,'price'=>floatval($price),'pro'=>$pro,'adds'=>$add,'addemt'=>$addemt,'yun'=>$yunfei));
		exit();
	}

	//**********************************
    // 购物车结算 下订单
    //***********************************
    public function payment(){
    	$uid = intval($_REQUEST['userId']);
		$userinfo = M('user')->where('id='.intval($uid).' AND del=0')->find();
		if (!$userinfo) {
			echo json_encode(array('status'=>0,'err'=>'用户信息异常！'));
			exit();
		}
		$res = M('user_auth')->where('uid='.$uid)->getField();
		if(!$res){
			$temp['times'] = intval($userinfo['times']) + 1;
			M('user')->where('uid='.$uid)->save($temp);
		}
		$order=M("order");

		//生成订单
		  try {
		  	$qz=C('DB_PREFIX');//前缀		  	
			$data['uid']=intval($uid);
			$data['amount'] = floatval($_REQUEST['amount']);
			$data['addtime']=time();
			$data['del']=0;
			$data['status']=10;
			$data['order_sn']=$this->build_order_no();//生成唯一订单号

			$result = $order->add($data);
		  } catch (Exception $e) {
		  	echo json_encode(array('status'=>0,'err'=>$e->getMessage()));
		  	exit();
		  }
		  
		    //把需要的数据返回
			$arr = array();
			$arr['order_id'] = $result;
			$arr['order_sn'] = $data['order_sn'];
			echo json_encode(array('status'=>1,'arr'=>$arr));
			exit();	
    }

    //****************************
    // 获取可用优惠券
    //****************************
    public function get_voucher($uid,$pid,$cart_id){
    	$qz=C('DB_PREFIX');
    	//计算总价
    	$prices = 0;
	    foreach($cart_id as $ks => $vs){
			$pros=M('shopping_char')->where(''.$qz.'shopping_char.uid='.intval($uid).' AND '.$qz.'shopping_char.id='.$vs)->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id=__SHOPPING_CHAR__.pid')->field(''.$qz.'shopping_char.num,'.$qz.'shopping_char.price,'.$qz.'shopping_char.type')->find();
		    $zprice=$pros['price']*$pros['num'];
			$prices+=$zprice;
		}

    	$condition = array();
    	$condition['uid'] = intval($uid);
    	$condition['status'] = array('eq',1);
    	$condition['start_time'] = array('lt',time());
    	$condition['end_time'] = array('gt',time());
    	$condition['full_money'] = array('elt',floatval($prices));

    	$vou = M('user_voucher')->where($condition)->order('addtime desc')->select();
    	$vouarr = array();
    	foreach ($vou as $k => $v) {
    		$chk_order = M('order')->where('uid='.intval($uid).' AND vid='.intval($v['vid']).' AND status>0')->find();
    		$vou_info = M('voucher')->where('id='.intval($v['vid']))->find();
    		$proid = explode(',', trim($vou_info['proid'],','));
    		if (($vou_info['proid']=='all' || $vou_info['proid']=='' || in_array($pid, $proid)) && !$chk_order) {
    			$arr = array();
    			$arr['vid'] = intval($v['vid']);
    			$arr['full_money'] = floatval($v['full_money']);
    			$arr['amount'] = floatval($v['amount']);
    			$vouarr[] = $arr;
    		}
    	}

    	return $vouarr;
    }

    //**********************************
	//  会员商品 属性价格库存获取
	//**********************************
    public function getprice($pid,$attr){
    	$pid = intval($pid);
    	$info = trim($attr,',');
    	if (!$pid || !$info) {
    		return array('status'=>0);
    	}

    	$attrs = array();
    	$attrs = explode(',', $info);
    	$price = array();$stock = array();
    	foreach ($attrs as $k => $v) {
    		$gg = M('guige')->where('pid='.intval($pid).' AND name="'.$v.'"')->find();
    		$attr_id = M('attribute')->where('id='.intval($gg['attr_id']).' AND pro_id='.intval($pid))->getField('id');
    		if (!$gg || !intval($attr_id)) {
    			return array('status'=>0);
    		}
    		$price[] = floatval($gg['price']);
    		$stock[] = intval($gg['stock']);
    	}

    	rsort($price);
    	sort($stock);
    	$theprice = sprintf("%.2f",$price[0]);
    	$thestock = $stock[0];
    	return array('status'=>1,'price'=>$theprice,'stock'=>intval($thestock));
    	exit();
    }

	public function ceshi(){
		print_r("adads");die();
	}

	/**针对涂屠生成唯一订单号
	*@return int 返回16位的唯一订单号
	*/
	public function build_order_no(){
		return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
	}

	public function pay_now2(){
    	$uid = intval($_REQUEST['userId']);
		$userinfo = M('user')->where('id='.intval($uid).' AND del=0')->find();
		if (!$userinfo) {
			echo json_encode(array('status'=>0,'err'=>'用户信息异常！'));
			exit();
		}
		$order = M('order')->where("id=".intval($order_id)." AND order_sn='".$order_sn."' AND del=0")->find();
		$user_amount = M('user')->where('uid='.$uid)->getField('amount');
		if(floatval($order['amount']) > floatval($user_amount)){
			echo json_encode(array('status'=>0,'err'=>'余额不足，请先充值！'));
			exit();
		}
		$data['amount'] = floatval($order['amount']) - floatval($user_amount);
		M('user')->where('uid='.$uid)->save($data);
		echo json_encode(array('status'=>1,'err'=>'支付成功！'));
		exit();

    }
}