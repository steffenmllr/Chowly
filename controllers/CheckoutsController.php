<?php
namespace chowly\controllers;

use li3_flash_message\extensions\storage\FlashMessage;
use chowly\models\Cart;
use chowly\models\Inventory;
use chowly\models\Venue;
use chowly\models\Offer;
use chowly\models\Purchase;

use chowly\extensions\data\InventoryException;
use \lithium\net\http\Router;
use \lithium\template\View;

use \Swift_MailTransport;
use \Swift_Mailer;
use \Swift_Message;
use \Swift_Attachment;

class CheckoutsController extends \chowly\extensions\action\Controller{
		
	protected function _init(){
		parent::_init();
		/** lets wait before requiring this
		if(!$this->request->is('ssl')){
			$this->redirect(Router::match(
				$this->request->params,
				$this->request,
				array('absolute' => true, 'scheme'=>'https://')
				)
			);
		}*/
	}
	public function cancel(){
		Cart::unlock();
		Cart::unfreeze();
		$this->redirect(array('Offers::index'));
	}
	public function confirm(){
		if(Cart::isEmpty()){
			FlashMessage::set("Empty Cart!");
			$this->redirect("Offers::index");
		}
		
		$conditions = array(
			'_id' => array_keys(Cart::get())
		);
		$offers = Offer::all(compact('conditions'));
		
		$cart = Cart::get();
		return compact('offers', 'cart');
	}
	
	public function checkout(){
		$provinces = Purchase::getProvinces();
		
		Cart::freeze();
		if(Cart::isEmpty()){
			Cart::unfreeze();
			FlashMessage::set("Empty Cart!");
			$this->redirect("Offers::index");
		}
		
		Cart::freeze();
		$cart = Cart::get();
		
		//Secure inventory so it does not expire while in checkout.
		foreach($cart as $offer_id => $attr){
			try{
				Inventory::secure($attr['inventory_id']);
			}catch(InventoryException $e){
				//TODO:Log failure
				//TODO: Do we fail at that point or still sell the item?
			}
		}
		
		//TODO: Credit Card data processing...
		if($this->request->data){
			
			$purchase = Purchase::create();
			$purchase->status = 'new';
			$purchase->set($this->request->data);
			
			if(!$purchase->validates()){
				unset($purchase->cc_number, $purchase->cc_sc);
				return compact('purchase', 'provinces');
			}
			
			Cart::lock();
			//TODO: Send email
			//TODO: Log transaction for history/accounting
			//TODO: HERE BE CC Processing
			
			$purchase->process($this->request->data);
			unset($purchase->cc_number, $purchase->cc_sc);
			
			if(!$purchase->isComplete()){
				FlashMessage::set("Some processing errors occured.");
				return compact('purchase');
			}
			
			
			foreach($cart as $offer_id => $attr){
				try{
					Inventory::purchase($transaction['id'], $attr['inventory_id']);
				}catch(InventoryException $e){
					//TODO: Add logs of a failure...
				}
			}
			
			$path = $this->writePdf($purchase->_id, $pdf);
			if(!$path){
				//TODO: DERRRR.... errors. What to do now?
			}
			
			$to = $purchase->email;
			$transport = Swift_MailTransport::newInstance();
			$mailer = Swift_Mailer::newInstance($transport);
			$message = Swift_Message::newInstance();
			$message->setSubject("Chowly Purchase {:$purchase->_id} confirmation");
			$message->setFrom(array('purchases@chowly.com' => 'Chowly'));
			$message->setTo($to);
			$message->setBody('Thank you for your purchase at Chowly!');
			$message->attach(Swift_Attachment::fromPath($path));
			
			$mailer->send($message);
			
			Cart::unlock();
			Cart::unfreeze();
			Cart::clear();
			$this->_render['template'] = 'success';
			return compact('purchase');
		}
		return compact('provinces');
	}
	private function writePdf($purchaseId, &$pdf){
		$path = LITHIUM_APP_PATH.'/resources/purchases/'. $purchaseId.'.pdf';
		if(file_exists($path)){
			return true;
		}
		$file = fopen($path, 'w');
		if(!file){
			throw new \Exception("Cannot create pdf");
		}
		if(fwrite($file,$pdf)){
			return $path;
		}else{
			return false;
		}
	}
}
?>