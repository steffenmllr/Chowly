<?php $total = 0;?>
<div id="ribbon">
	<span>Step 1 of 2: Purchase Summary</span>
</div>
<div id="content-wrapper">
	<div style="margin-left: 20px; margin-right: 20px; margin-top: 30px; margin-bottom: 10px;">		
		<h1>Your Purchase</h1>
		<table id="offers">
			<thead>
				<tr><th style="width: 40px;"></th><th style="width: 120px;">Reserved Until</th><th>Name</th><th>Price</th></tr>
			</thead>
			<?php foreach($offers as $offer):
			$total += $offer->cost;
			$offer_id = $offer->_id;
			$cart_item = $cart_items->first(function($i) use ($offer_id) { return $i->_id == $offer_id; });
			?>
			<tr id="offer_<?=$offer->_id;?>">
				<td><?=$this->html->image('silk/cart_delete.png', array('id'=>"offer_{$offer->_id}_remove"))?></td>
				<td><?php echo date('l H:i:s', $cart_item['expires']);?></td>
				<td style="font-weight: bold;"><?=$offer->name;?></td>
				<td>$<?=$offer->cost;?></td>
			</tr>
			<?php endforeach;?>
		</table>
		<div id="empty_cart" style="display: none; margin-left: auto; margin-right: auto; width: 500px;">
			<p>Your cart is currently empty.</p>
			<p><?=$this->html->link('Take me back to the main page.', '/');?>
		</div>
		<div style="width: 200px; font-weight: bold; margin-left: auto; margin-right:auto; text-align: center; margin-bottom: 10px; margin-top: 10px;">Total $<?=$total;?></div>
		<div style="margin-left: auto; margin-right: auto; width: 260px;">
			<?=$this->html->link($this->html->image('checkoutbutton.png'),  array('Checkouts::checkout'), array('escape'=>false));?>
		</div>
	</div>
</div>

<script type="text/javascript">
<?php foreach($offers as $offer):?>
	$('#offer_<?=$offer->_id;?>_remove').button();
	$('#offer_<?=$offer->_id;?>_remove').bind('click', function(){
		$(this).hide();
		$.ajax({
			  url: "<?=$this->url(array('Carts::remove', 'id'=>$offer->_id));?>",
			  context: document.body,
			  success: function(data){
				  if(data.cleared){
					  $('#offer_' + data.id).remove();
				  }
				  if($('#offers tr').length == 1){
					  $('#CheckoutGo').hide();
					  $('#empty_cart').show();
				  }
			  }
			});
	});
<?php endforeach;?>
</script>