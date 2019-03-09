<link href="css/iconfont.css" rel="stylesheet">

<div class="mb_10">
	<span class="btn btn-primary" onclick="return review_orders()"><?=lang('batch_review_pass')?></span>
	<span class="btn btn-primary" onclick="return reject_orders()"><?=lang('batch_review_reject')?></span>
	<span class="btn" onclick="return reverse_select()"><?=lang('reverse_select')?></span>
</div>

<table class="table table-bordered table-condensed table-stripped">
	<thead style="background-color:#89cff0">
		<th><input type="checkbox" id="select_all_order" /></th>
		<?php foreach (array(
			'status' => lang('status'),
			'orderid' => lang('order_id'),
			'uid' => 'UID',
			'name' => lang('username'),
			'itemDescription' => lang('item_description'),
			'expiration' => lang('expiration'),
			'price' => lang('price'),
			'realPrice' => lang('realprice'),
			'method' => lang('pay_method'),
			'createTime' => lang('create_time'),
			'finishTime' => lang('finish_time')
			) as $key => $title): ?>
			<th style='white-space: nowrap'>
				<?php
					$iconType = $keyword!=$key?'icon-resize-vertical':($order!='reverse'?'icon-arrow-up':'icon-arrow-down');
					$iconUrl = ($keyword!=$key||$order=='reverse')?"#admin/orders?sort=$key":"#admin/orders?sort=$key&order=reverse";
				?>
				<a href='<?=$iconUrl?>'>
					<?=$title?>
					<i class='<?=$iconType?>'></i>
				</a>
			</th>
		<?php endforeach; ?>
		<th></th>
	</thead>
	<tbody><?php
		foreach ($data as $row){
			if ($row->status == 1){
				echo "<tr style='background-color:#6DEF9D'>";
				$iconType = 'iconfont iconfont-yiwancheng1';
			}
			else if ($row->status == -1){
				echo "<tr style='background-color:#FF8888'>";
				$iconType = 'icon-remove';
			}
			else if ($row->status == 2){
				echo "<tr style='background-color:#89cff0'>";
				$iconType = 'icon-question-sign';
			}
			else {
				echo "<tr>";
				$iconType = 'iconfont iconfont-dengdai';
			}
			echo "<td>";
			if ($row->status == 2)
				echo "<input type='checkbox' class='order_select' />";
			echo "</td>";
			echo "<td><i class='$iconType'></i></td>";
			echo "<td class='orderid'>$row->orderid</td>";
			echo "<td>$row->uid</td>";
			echo "<td><span class='label label-info'><a href='#users/$row->name' class='username'>$row->name</a></span></td>";
			echo "<td>$row->itemDescription</td>";
			echo "<td class='expiration'>$row->expiration</td>";
			echo "<td>￥$row->price</td>";
			if (isset($row->realPrice))
				echo "<td>￥$row->realPrice</td>";
			else echo "<td></td>";
			if ($row->method == 1)
				echo "<td><i class='iconfont iconfont-umidd17'></i></td>";
			elseif ($row->method == 2)
				echo "<td><i class='iconfont iconfont-pay-wechat'></i></td>";
			else
				echo "<td><i class='icon-refresh'></i></td>";
			echo "<td>$row->createTime</td>";
			echo "<td>$row->finishTime</td><td>";
			if ($row->status == 2){
				echo "<a href='javascript:void(0)' onclick='review_one_order($(this))'><i class='icon-ok'></i></a>";
				echo " <a href='javascript:void(0)' onclick='reject_one_order($(this))'><i class='icon-remove'></i></a>";
			}
			echo "</td></tr>";
		}
	?></tbody>
</table>

<div class="modal hide fade" id="modal_review">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3><?=lang('review_order')?></h3>
	</div>
	<div class="modal-body">
		<p id="username"></p>
		<form class="form form-horizontal" method="post">
			<div class="control-group">
				<label class="control-label" style="text-align:left">
					<input type="checkbox" id="is_change_expiration" />
					<i class="icon-time"></i><?=lang('change_expiration')?>
				</label>
				<div class="controls">
					<input type="date" id="date" class="input-medium"/>
					<input type="time" id="time" class="input-small"/>
				</div>
			</div>
		</form>
	</div>
	<div class="modal-footer">
		<a class="btn pull-left" data-dismiss="modal"><?=lang('close')?></a>
		<button class="btn btn-success" onclick="return submit_review()"><?=lang('accept_review')?></button>
	</div>
</div>

<div class="modal hide fade" id="modal_reject">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3><?=lang('reject_order')?></h3>
	</div>
	<div class="modal-body">
		<h3>Are you sure to reject order for:</h3>
		<p id="username"></p>
	</div>
	<div class="modal-footer">
		<a class="btn pull-left" data-dismiss="modal"><?=lang('close')?></a>
		<button class="btn btn-danger" onclick="return submit_reject()"><?=lang('reject_review')?></button>
	</div>
</div>

<script type="text/javascript">

	var orderid = new Array();
	var ordername = new Array();
	var datetime = '';

	$('#select_all_order').click(function(){
		$.each($('input:checkbox[class=order_select]'), function(){
			$(this).prop('checked', $('#select_all_order').prop('checked'));
		});
	});

	function reverse_select(){
		$.each($('input:checkbox[class=order_select]'), function(){
			$(this).prop('checked', !$(this).prop('checked'));
		});
	}

	function create_name_tag(name){
		return '<span class="label label-info" style="margin-right:5px;font-size:16px">' + name + '</span>';
	}

	function load_modal_review(){
		var username_str = "";
		$.each(ordername, function(index, name){
			username_str = username_str + create_name_tag(name);
		});
		$('#modal_review #username').html(username_str);
		$('#modal_review #date').val(datetime.split(' ')[0]);
		$('#modal_review #time').val(datetime.split(' ')[1]);
		$('#modal_review').modal({backdrop: 'static'});
	}

	function submit_review(){
		var send_data = {
			orderid: JSON.stringify(orderid),
			datetime: $('#modal_review #date').val() + ' ' + $('#modal_review #time').val()
		};

		if (!$('#is_change_expiration').prop('checked'))
			send_data = {orderid: JSON.stringify(orderid)};
		
		$.post('index.php/admin/review_order', send_data, function(data){
			$('#modal_review').modal('hide');
			if (data == 'success')
				refresh_page();
			else
				$('#page_content').html(data);
		});
		return false;
	}

	function review_one_order(order){
		order = order.parent().parent();
		ordername = new Array(order.find('.username').html());
		orderid = new Array(order.find('.orderid').html());
		datetime = order.find('.expiration').html();
		load_modal_review();
	}

	function review_orders(){
		ordername = new Array();
		orderid = new Array();
		datetime = null;
		$.each($('input:checkbox[class=order_select]:checked'), function(){
			row = $(this).parent().parent();
			ordername.push(row.find('.username').html());
			orderid.push(row.find('.orderid').html());
			if (datetime == null)
				datetime = row.find('.expiration').html();
		});
		load_modal_review();
	}

	function load_modal_reject(){
		var username_str = "";
		$.each(ordername, function(index, name){
			username_str = username_str + create_name_tag(name);
		});
		$('#modal_reject #username').html(username_str);
		$('#modal_reject').modal({backdrop: 'static'});
	}

	function submit_reject(){
		$.post('index.php/admin/reject_order', {orderid: JSON.stringify(orderid)}, function(data){
			$('#modal_reject').modal('hide');
			if (data == 'success')
				refresh_page();
			else
				$('#page_content').html(data);
		});
		return false;
	}

	function reject_one_order(order){
		order = order.parent().parent();
		ordername = new Array(order.find('.username').html());
		orderid = new Array(order.find('.orderid').html());
		load_modal_reject();
	}

	function reject_orders(){
		ordername = new Array();
		orderid = new Array();
		$.each($('input:checkbox[class=order_select]:checked'), function(){
			row = $(this).parent().parent();
			ordername.push(row.find('.username').html());
			orderid.push(row.find('.orderid').html());
		});
		load_modal_reject();
	}

</script>
