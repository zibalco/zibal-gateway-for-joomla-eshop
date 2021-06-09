<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_eshop pay zibal plugins
 * @copyright   zibal => https://zibal.ir
 * @copyright   Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die();
require_once JPATH_SITE . '/components/com_eshop/plugins/payment/os_zibal_inputcheck.php';

class os_zibal extends os_payment
{

	public function __construct($params) {

        $config = array(
            'type' => 0,
            'show_card_type' => false,
            'show_card_holder_name' => false
        );
        $this->setData('merchant_id',$params->get('merchant_id'));
        
        parent::__construct($params, $config);
	}

	public function processPayment($data) {
		

		$app	= JFactory::getApplication();
		// $Amount = $data['total']/10; // Toman 
		$Amount = $data['total'];
		$Description = 'خرید محصول از فروشگاه   '. EshopHelper::getConfigValue('store_owner'); 
		$Email = $data['email'];
		$Mobile = $data['telephone']; 
		$CallbackURL = JURI::root().'index.php?option=com_eshop&task=checkout.verifyPayment&payment_method=os_zibal&id='.$data['order_id']; 

		
		$data = array(
			'merchant' => $this->data['merchant_id'],
			'amount' => $Amount,
			'description' => $Description,
			'mobile' => $data['telephone'],
			'callbackUrl' => $CallbackURL
		);

		$result = $this->postToZibal('request', $data);

		if ($result->result == 100) {
			Header('Location: https://gateway.zibal.ir/start/'.$result->trackId); 
		} else {
			echo'ERR: '. $this->resultCodes($result->result);
		}
		
	}

	protected function validate($id) {
		$app	= JFactory::getApplication();		
		$allData = EshopHelper::getOrder(intval($id)); //get all data
		//$mobile = $allData['telephone'];
		$jinput = JFactory::getApplication()->input;
		// $Authority = $jinput->get->get('trackId', '0', 'INT');
		$trackId = $jinput->get->get('trackId', '0', 'INT');
		$status = $jinput->get->get('status', '', 'STRING');
		$success = $jinput->get->get('success', '', 'STRING');
		
		$this->logGatewayData(' success: ' . $id . 'trackId:' . $trackId . 'status:'.$status. 'OrderTime:'.time() );
		
		if (checkHack::checkString($status)){

			if (isset($success) && $success == '1' && isset($status) && $status == '2') {
				$data = array(
					'merchant' => $this->data['merchant_id'],
					'trackId' => $trackId,
				);

				$result = $this->postToZibal('verify', $data);
				if ($result->result == 100) {
					$this->onPaymentSuccess($id, $result->RefID); 
					$msg= $this->resultCodes($result->result); 
					$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=complete',false);
					$app->redirect($link, '<h2>'.$msg.'</h2>'.'<h3>'. $trackId .'شماره پیگری ' .'</h3>' , $msgType='Message'); 
					return true;
				} 
				else {
					$msg= $this->resultCodes($result->result); 
					$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					return false;
				}

			} else {
				$msg = $this->statusCodes($status);
				$app	= JFactory::getApplication();
				$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				return false;	
			}
		
		}
		else {
			// $msg= $this->getGateMsg('hck2'); 
			$msg = $this->statusCodes($status);
			$app	= JFactory::getApplication();
			$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			return false;	
		}
	
	}

	public function verifyPayment() {
		$jinput = JFactory::getApplication()->input;
		$id = $jinput->get->get('id', '0', 'INT');
		$row = JTable::getInstance('Eshop', 'Order');
		$row->load($id);
		if ($row->order_status_id == EshopHelper::getConfigValue('complete_status_id'))
				return false;
				
		$this->validate($id);
	}

	/**
	 * connects to zibal's rest api
	 * @param $path
	 * @param $parameters
	 * @return stdClass
	 */
	protected function postToZibal($path, $parameters)
	{
		$url = 'https://gateway.zibal.ir/v1/'.$path;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		curl_close($ch);
		return json_decode($response);
	}

	/**
	 * returns a string message based on result parameter from curl response
	 * @param $code
	 * @return String
	 */
	protected function resultCodes($code)
	{
		switch ($code) 
		{
			case 100:
				return "با موفقیت تایید شد";
			
			case 102:
				return "merchant یافت نشد";

			case 103:
				return "merchant غیرفعال";

			case 104:
				return "merchant نامعتبر";

			case 201:
				return "قبلا تایید شده";
			
			case 105:
				return "amount بایستی بزرگتر از 1,000 ریال باشد";

			case 106:
				return "callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)";

			case 113:
				return "amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.";

			case 201:
				return "قبلا تایید شده";
			
			case 202:
				return "سفارش پرداخت نشده یا ناموفق بوده است";

			case 203:
				return "trackId نامعتبر می‌باشد";

			default:
				return "وضعیت مشخص شده معتبر نیست";
		}
	}

	/**
	 * returns a string message based on status parameter from $_GET
	 * @param $code
	 * @return String
	 */
	protected function statusCodes($code)
	{
		switch ($code) 
		{
			case -1:
				return "در انتظار پردخت";
			
			case -2:
				return "خطای داخلی";

			case 1:
				return "پرداخت شده - تاییدشده";

			case 2:
				return "پرداخت شده - تاییدنشده";

			case 3:
				return "لغوشده توسط کاربر";
			
			case 4:
				return "‌شماره کارت نامعتبر می‌باشد";

			case 5:
				return "‌موجودی حساب کافی نمی‌باشد";

			case 6:
				return "رمز واردشده اشتباه می‌باشد";

			case 7:
				return "‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد";
			
			case 8:
				return "‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

			case 9:
				return "مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

			case 10:
				return "‌صادرکننده‌ی کارت نامعتبر می‌باشد";
			
			case 11:
				return "خطای سوییچ";

			case 12:
				return "کارت قابل دسترسی نمی‌باشد";

			default:
				return "وضعیت مشخص شده معتبر نیست";
		}
	}
}

