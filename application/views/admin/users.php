<div class="mb_10 pull-left">
	<span class="btn btn-primary" onclick="return change_expirations()"><?=lang('batch_change_expiration')?></span>
	<span class="btn" onclick="return reverse_select()"><?=lang('reverse_select')?></span>
</div>
<div class="mb_10 pull-right">
	<i class="icon-info-sign" style="vertical-align:middle" title="Only users that not enabled and never login will be deleted."></i>
	<button class="btn btn-danger" onclick="delete_unused_users()"><i class="icon-eye-close icon-white"></i> <?=sprintf(lang('delete_unused_user'), "<span id='unused_count'>$unused</span>")?></button>
</div>
<table class="table table-bordered table-condensed table-stripped">
	<thead style="background-color:#89cff0">
		<th><input type="checkbox" id="select_all_users" /></th>
		<?php foreach (array(
			'uid' => 'UID',
			'name' => 'Name',
			'school' => 'School',
			'isEnabled' => 'Status',
			'priviledge' => 'Privilege',
			'groups' => 'Groups',
			'lastIP' => 'Last IP Address',
			'lastLogin' => 'Last Login Time',
			'expiration' => lang('expiration')) as $key => $title): ?>
			<th style='white-space: nowrap'>
				<?php
					$iconType = $keyword!=$key?'icon-resize-vertical':($order!='reverse'?'icon-arrow-up':'icon-arrow-down');
					$iconUrl = ($keyword!=$key||$order=='reverse')?"#admin/users?sort=$key":"#admin/users?sort=$key&order=reverse";
				?>
				<a href='<?=$iconUrl?>'>
					<?=$title?>
					<i class='<?=$iconType?>'></i>
				</a>
				<?php if ($key == 'school'): ?>
					<span class='btn btn-mini pull-right school-display' onclick='edit_school();'>Edit</span>
					<span class='btn btn-mini btn-primary pull-right school-edit' style='display:none' onclick='submit_school();'>Save</span>
				<?php endif; ?>
			</th>
		<?php endforeach; ?>
		<th></th>
	</thead>
	<tbody><?php
		foreach ($data as $row){
			echo "<tr><td><input type='checkbox' class='user_select' /></td>";
			echo "<td>$row->uid</td>";
			echo "<td><a href='#users/$row->name'><span class='label label-info name'>$row->name</span></a></td>";
			echo "<td>
				<span class='school-display'>".htmlspecialchars($row->school)."</span>
				<input type='text' class='school-edit' style='display:none' onchange='newSchool[$row->uid]=$(this).val()' value='".htmlspecialchars($row->school)."' />
			</td>";
			echo "<td><span style='width:55px; text-align:center' onclick=\"user_change_status($row->uid, $(this))\"";
			if ($row->isEnabled) echo 'class="label label-success">Enabled';
			else echo 'class="label label-important">Disabled';
			echo '</span></td><td>';
			echo '<div class="dropdown">';
			$display = "style='display:none'";
			if ($row->priviledge == 'admin')
				echo "<span id='bt$row->uid' class='label label-warning dropdown-toggle' data-toggle='dropdown' role='button'>Administrator</span>";
			else if ($row->priviledge == 'user')
				echo "<span id='bt$row->uid' class='label dropdown-toggle' data-toggle='dropdown' role='button'>User</span>";
			else {
				echo "<span id='bt$row->uid' class='label label-inverse dropdown-toggle' data-toggle='dropdown' role='button'>Restricted</span>";
				$display = '';
			}
			echo "<ul class='dropdown-menu' role='menu' aria-labelledby='bt$row->uid'>";
			echo "<li role='presentation'><a onclick='change_user_priviledge($row->uid,\"admin\",$(\"#bt$row->uid\"),$(\".op$row->uid\"));'>Administrator</a></li>";
			echo "<li role='presentation'><a onclick='change_user_priviledge($row->uid,\"user\",$(\"#bt$row->uid\"),$(\".op$row->uid\"));'>User</a></li>";
			echo "<li role='presentation'><a onclick='change_user_priviledge($row->uid,\"restricted\",$(\"#bt$row->uid\"),$(\".op$row->uid\"));'>Restricted</a></li>";
			echo "<li role='presentation' class='divider op$row->uid' $display></li>";
			echo "<li role='presentation' class='op$row->uid' $display><a href='#admin/setallowing/$row->uid'>Set Allowing</a></li>";
			echo '</ul>';
			echo '</div>';
			echo '</td><td>';
			foreach ($row->groups as $group) echo "<span class=\"label\">$group->name</span> ";
			echo "</td><td>$row->lastIP</td>";
			echo "<td class='lastlogin'>$row->lastLogin</td>";
			echo "<td><span class='expiration' style='margin-right:5px'>$row->expiration</span><a href='javascript:void(0)' onclick='change_one_expiration($(this))'><i class='icon-pencil'></i></a></td>";
			echo "<td><button class='close' onclick=\"delete_user($row->uid, $(this))\">&times;</button>";
			if ($row->isUnused) echo "<span><i class='icon-eye-close'></i></span>";
			echo "</td></tr>";
		}
	?></tbody>
</table>

<div class="modal hide fade" id="modal_confirm">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3>Confirm Action</h3>
	</div>
	<div class="modal-body">
		<p>Are you sure to delete user: </p>
		<h3><div id="info"></div></h3>
	</div>
	<div class="modal-footer">
		<a class="btn" data-dismiss="modal"><?=lang('close')?></a>
		<a class="btn btn-danger" id="delete"><?=lang('delete')?></a>
	</div>
</div>

<div class="modal hide fade" id="modal_confirm_unused">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3>Confirm Action</h3>
	</div>
	<div class="modal-body">
		<h4>Are you sure to delete <span id="modal_unused_count"><?=$unused?></span> unused users?</h4>
	</div>
	<div class="modal-footer">
		<a class="btn" data-dismiss="modal"><?=lang('close')?></a>
		<a class="btn btn-danger" id="delete"><?=lang('delete')?></a>
	</div>
</div>

<div class="modal hide fade" id="modal_expiration">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3><?=lang('change_expiration')?></h3>
	</div>
	<div class="modal-body">
		<p id="username"></p>
		<span>
			<input type="date" id="date" class="input-medium"/>
			<input type="time" id="time" class="input-medium"/>
		</span>
	</div>
	<div class="modal-footer">
		<a class="btn pull-left" data-dismiss="modal"><?=lang('close')?></a>
		<button class="btn" onclick="return submit_expiration(true)"><?=lang('set_no_expiration')?></button>
		<button class="btn btn-success" onclick="return submit_expiration()"><?=lang('ok')?></button>
	</div>
</div>

<div class="modal hide fade" id="modal_confirm_unused">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3>Confirm Action</h3>
	</div>
	<div class="modal-body">
		<h4>Are you sure to delete <span id="modal_unused_count"><?=$unused?></span> unused users?</h4>
	</div>
	<div class="modal-footer">
		<a class="btn" data-dismiss="modal">Close</a>
		<a class="btn btn-danger" id="delete">Delete</a>
	</div>
</div>

<script type="text/javascript">
	
	var names = new Array();
	var datetime = '';

	$('#select_all_users').click(function(){
		$.each($('input:checkbox[class=user_select]'), function(){
			$(this).prop('checked', $('#select_all_users').prop('checked'));
		});
	});

	function reverse_select(){
		$.each($('input:checkbox[class=user_select]'), function(){
			$(this).prop('checked', !$(this).prop('checked'));
		});
	}

	function create_name_tag(name){
		return '<span class="label label-info" style="margin-right:5px;font-size:16px">' + name + '</span>';
	}

	function delete_user(uid, selector){
		$('#modal_confirm #delete').live('click', function(){
			$('#modal_confirm').modal('hide');
			access_page('admin/delete_user/' + uid);
		});
		$('#modal_confirm #info').html(uid + '. ' + selector.parent().parent().find('.name').html());
		$('#modal_confirm').modal({backdrop: 'static'});
	}

	function delete_unused_users(){
		$('#modal_confirm_unused #delete').live('click', function(){
			$('#modal_confirm_unused').modal('hide');
			access_page('admin/delete_unused_users');
		});
		$('#modal_confirm_unused').modal({backdrop: 'static'});
	}
	
	function user_change_status(uid, selector){
		access_page('admin/change_user_status/' + uid, function(){
			unused_count = $("#unused_count").html();
			if (selector.hasClass('label-success')){
				selector.removeClass('label-success');
				selector.addClass('label-important');
				selector.html('Disabled');
				if (selector.parent().parent().find('.lastlogin').html() == "")
					unused_count++;
			} else {
				selector.removeClass('label-important');
				selector.addClass('label-success');
				selector.html('Enabled');
				if (selector.parent().parent().find('.lastlogin').html() == "")
					unused_count--;
			}
			$("#unused_count").html(unused_count);
			$("#modal_unused_count").html(unused_count);
		}, false);
	}

	function change_user_priviledge(uid, priviledge, button, option)
	{
		access_page('admin/change_user_priviledge/' + uid + '/' + priviledge, function(){
			button.removeClass('label-inverse');
			button.removeClass('label-warning');
			option.hide();
			if (priviledge == 'admin')
			{
				button.addClass('label-warning');
				button.html('Administrator');
			} else if (priviledge == 'user')
				button.html('User');
			else
			{
				button.addClass('label-inverse');
				button.html('Restricted');
				option.show();
			}
		}, false);
	}

	function edit_school()
	{
		$('.school-display').hide();
		$('.school-edit').show();
		newSchool = {};
	}

	function submit_school()
	{
		$.post("index.php/admin/users", {'newschool':newSchool}, function(data) {
			newSchool = undefined;
			$("#page_content").html(data);
		});
	}

	function twodigit(num){
		return num < 10 ? '0' + num : num;
	}

	function load_modal_expiration()
	{
		name_str = '';
		$.each(names, function(index, name){name_str += create_name_tag(name);});
		$('#modal_expiration #username').html(name_str);
		if (datetime == '' || new Date() > new Date(Date.parse(datetime)))
		{
			datetime = (new Date().getTime() / 1000) + 24 * 60 * 60;
			var obj = new Date(datetime * 1000);
			var year = obj.getFullYear(),
				month = twodigit(obj.getMonth() + 1),
				day = twodigit(obj.getDate()),
				hour = twodigit(obj.getHours()),
				minute = twodigit(obj.getMinutes());
			datetime = year+'-'+month+'-'+day+' '+hour+':'+minute;
		}
		$('#modal_expiration #date').val(datetime.split(' ')[0]);
		$('#modal_expiration #time').val(datetime.split(' ')[1]);
		$('#modal_expiration').modal({backdrop: 'static'});
	}

	function submit_expiration(is_remove = false){
		var send_data = {
			names: JSON.stringify(names),
			datetime: (is_remove) ? '' : $('#modal_expiration #date').val() + ' ' + $('#modal_expiration #time').val()
		};
		
		$.post('index.php/admin/change_expiration', send_data, function(data){
			$('#modal_expiration').modal('hide');
			if (data == 'success')
				refresh_page();
			else
				$('#page_content').html(data);
		});
		return false;
	}

	function change_one_expiration(user)
	{
		names = new Array(user.parent().parent().find('.name').html());
		datetime = user.parent().parent().find('.expiration').html();
		load_modal_expiration();
	}

	function change_expirations(){
		names = new Array();
		datetime = null;
		$.each($('input:checkbox[class=user_select]:checked'), function(){
			row = $(this).parent().parent();
			names.push(row.find('.name').html());
			if (datetime == null)
				datetime = row.find('.expiration').html();
		});
		load_modal_expiration();
	}

</script>
