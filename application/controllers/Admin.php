<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Admin extends MY_Controller {

	private function _redirect_page($method, $params = array()){
		if (method_exists($this, $method))
			return call_user_func_array(array($this, $method), $params);
		else
			show_404();
	}

	public function _remap($method, $params = array()){
		$this->load->model('user');
		
		$allowed_methods = array('problemset', 'change_problem_status');
		$restricted_methods = array('delete_problem', 'dataconf', 'scan', 'upload', 'change_problem_nosubmit', 'check_file_exist', 'edit_tags');
		$payment_methods = array('items', 'change_item', 'delete_item', 'orders', 'review_order', 'reject_order');

		if ($this->config->item('allow_add_problem'))
			$allowed_methods[] = 'addproblem';
		else
			$restricted_methods[] = 'addproblem';

		if ($this->user->is_logged_in()){
			if ($this->user->is_admin() || in_array($method, $allowed_methods)){
				if (in_array($method, $payment_methods) && !$this->config->item('enable_payment'))
					$this->load->view('error', array('message' => lang('function_turned_off')));
				else
					$this->_redirect_page($method, $params);
			}
			else if (in_array($method, $restricted_methods)){
				$this->load->model('problems');
				if (isset($params[0]) && $this->problems->has_control($params[0]))
					$this->_redirect_page($method, $params);
				else
					$this->load->view('error', array('message' => '<h5 class="alert">Operation not permitted!</h5>'));
			}
			else
				$this->load->view('error', array('message' => '<h5 class="alert">You are not administrators!</h5>'));
		}
		else
			$this->login();
	}
	
	public function index(){
		$this->load->view('admin/index');
	}
	
	public function addproblem($pid = 0){
		$this->load->model('problems');
		$this->load->model('user');
		$this->load->model('text');
		if ($pid > 0 && ! $this->user->is_admin() && $this->problems->uid($pid) != $this->user->uid()) {
			$this->load->view('error', array('message' => 'You are not allowed to edit this problem!'));
			return;
		}

		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '</div>');
		
		$this->form_validation->set_rules('title', 'Title', 'required');
		$this->form_validation->set_rules('problemDescription', 'Problem Description', 'required');
		$this->form_validation->set_rules('inputDescription', 'Input Description', 'required');
		$this->form_validation->set_rules('outputDescription', 'Output Description', 'required');
		$this->form_validation->set_rules('inputSample', 'Sample Input', 'required');
		$this->form_validation->set_rules('outputSample', 'Sample Output', 'required');
		//$this->form_validation->set_rules('dataConstraint', 'Data Constraint', 'required');
		
		if ($this->form_validation->run() == FALSE){
			$data = array();
			if ($pid > 0){
				$data = (array)$this->db->query("SELECT * FROM ProblemSet WHERE pid=?", array($pid))->row();
				$data['pid'] = $pid;

				$path = $this->config->item('problem_path') . $pid;
				$data['problemDescription']   = file_get_contents("$path/problemDescription.html");
				$data['inputDescription']     = file_get_contents("$path/inputDescription.html");
				$data['outputDescription']    = file_get_contents("$path/outputDescription.html");
				$data['inputSample']          = file_get_contents("$path/inputSample.html");
				$data['outputSample']         = file_get_contents("$path/outputSample.html");
				$data['dataConstraint']       = file_get_contents("$path/dataConstraint.html");
				$data['hint']                 = file_get_contents("$path/hint.html");
				if ($this->config->item('show_copyright'))
					$data['copyright'] = ($this->user->load_priviledge($data['uid']) == 'admin' ? 'admin' : 'user');
				else
					$data['copyright'] = null;
			} else
				if ($this->config->item('show_copyright'))
					$data['copyright'] = ($this->user->is_admin() ? 'admin' : 'user');
				else
					$data['copyright'] = null;

			$this->load->view("admin/addproblem", $data);
		}else{
			$data = $this->input->post(NULL, FALSE);
			foreach (array('title', 'problemDescription', 'inputDescription', 'outputDescription', 'inputSample', 'outputSample', 'dataConstraint', 'hint') as $key)
				$data[$key] = $this->text->bb2html($data[$key]);

			//$data['isShowed'] = 0;
			if ($pid == 0){
				$new = TRUE;
				$pid = $this->problems->add($data);
				$this->problems->save_dataconf($pid, '{IOMode:0, cases:[]}', null, null);
			}else{
				$new = FALSE;
				$this->problems->add($data, $pid);
			}
			
			$target_path = $this->config->item('data_path') . '/' . $pid . '/';
			if (! is_dir($target_path)) mkdir($target_path, 0777, true);
			
			if ($new) $this->load->view('information', array('data' => 'success' . $pid));
			else $this->load->view('success');
		}
	}
	
	function delete_problem($pid){
		$this->load->model('problems');
		$this->problems->delete($pid);
	}

	public function approve_review($pid){
		$this->load->model('problems');
		$this->problems->approve_review($pid);
	}

	public function decline_review($pid){
		$msg = $this->input->post('msg');
		if (isset($msg) && $msg){
			$this->load->model('problems');
			$this->problems->decline_review($pid, $msg);
			$this->load->view('admin/decline_msg', array('success' => true));
		} else {
			$this->load->view('admin/decline_msg', array('pid' => $pid));
		}
	}
	
	public function change_problem_status($pid){
		$this->load->model('user');
		$this->load->model('problems');
		if (! $this->user->is_admin() && $this->user->uid() != $this->problems->uid($pid)) exit('forbidden');
		$this->problems->change_status($pid);
	}

	public function change_problem_nosubmit($pid){
		$this->load->model('problems');
		$this->problems->change_nosubmit($pid);
	}
	
	public function problemset($page = 1){
		if ($this->input->get('old_version')===false || $this->input->get('old_version')===null)
		{
			$oj_name = $this->config->item('oj_name');
			$spliter = ($this->input->get('spliter')=='left'?'left':'right');
			header("location: /$oj_name/index.php/main/problemset/$page?spliter=$spliter&reverse_order=1&show_in_control=1");
			return;
		}
		if (! $this->user->is_admin())
		{
			$this->load->view('error', array('message' => '<h5 class="alert">You are not administrators!</h5>'));
			return;
		}

		$problems_per_page = 20;
	
		$this->load->model('problems');
		$query = (object)array(
			'rev' => true,
			'admin' => true,
		);
		$count = $this->problems->count($query);
		if ($count > 0 && ($count + $problems_per_page - 1) / $problems_per_page < $page)
			$page = ($count + $problems_per_page - 1) / $problems_per_page;
		$row_begin = ($page - 1) * $problems_per_page;
		$data = $this->problems->load_problemset($row_begin, $problems_per_page, $query);
		foreach ($data as $row)
		{
			$row->isShowed=($row->isShowed?'<span class="label label-success">Showed</span>':'<span class="label label-important">Hidden</span>');
			$row->noSubmit=($row->noSubmit?'<span class="label label-important">Disallowing</span>':'<span class="label label-success">Allowing</span>');
		}

		$this->load->library('pagination');
		$config['base_url'] = '#admin/problemset/';
		$config['total_rows'] = $count;
		$config['per_page'] = $problems_per_page;
		$config['cur_page'] = $page;
		$config['suffix'] = '?' . http_build_query($this->input->get());
		$config['first_url'] = $config['base_url'] . '1' . $config['suffix'];
		$this->pagination->initialize($config);

		$this->load->view('admin/problemset', array('data' => $data, 'page' => $page));
	}

	public function dataconf($pid){
		$this->load->library('form_validation');
		$this->load->model('problems');
		$this->form_validation->set_error_delimiters('<div class="alert">', '</div>');
		
		$this->form_validation->set_rules('script-init', 'Initialization Part', 'required');
		$this->form_validation->set_rules('script-run', 'Running Part', 'required');
		$this->form_validation->set_rules('group', 'Manual Setting of Data Grouping', 'required');
		
		$datapath = $this->config->item('data_path').$pid;
		
		$post = $this->input->post(NULL, FALSE);
		try
		{
			if ($this->form_validation->run() == FALSE) throw new MyException();
			$confCache = $this->problems->save_script($pid, $post["script-init"], $post["script-run"]);
			$this->problems->mark_update($pid);
			$this->problems->save_dataconf($pid, $post["traditional"], $post["group"], $confCache);
			
			$this->load->view('success');

		} catch (MyException $e)
		{
			$data = $this->problems->load_dataconf($pid);
			if (!$data)
			{
				$this->load->view('error',array('message'=>'No such a problem'));
				return;
			}
			$pass = array();
			$pass['title'] = $data->title;
			$pass['pid'] = $pid;
			$pass['traditional'] = isset($post['traditional'])?$post['traditional']:$data->dataConfiguration;
			$pass['group'] = isset($post['group'])?$post['group']:$data->dataGroup;
			$pass['init'] = isset($post['script-init'])?$post['script-init']:file_exists($datapath.'/init.src')?file_get_contents($datapath.'/init.src'):'';
			$pass['run'] = isset($post['script-run'])?$post['script-run']:file_exists($datapath.'/run.src')?file_get_contents($datapath.'/run.src'):'';
			$pass['errmsg'] = $e->getMessage();

			$this->load->view('admin/dataconf', $pass);
		}
	}

	public function upload($pid){
		if ( !isset($_FILES['files'])) return;
		$count = count($_FILES['files']['tmp_name']);

		$target_path = $this->config->item('data_path') . $pid . '/';
		if (! is_dir($target_path)) mkdir($target_path,0777,true);
		//$cwd = getcwd();
		//chdir($target_path);
		//$makefile = "SPJ :";
		
		for ($i = 0; $i < $count; $i++) {
			$temp_file = $_FILES['files']['tmp_name'][$i];
			$target_file = $target_path . $this->security->sanitize_filename($_FILES['files']['name'][$i]);
			//$file_types = array('c', 'cpp', 'pas', 'dpr');
			//$file_parts = pathinfo($_FILES['files']['name'][$i]);
			//$basename = $file_parts['basename'];
			//$filename = $file_parts['filename'];
			if (isset($file_parts['extension'])) $extension = $file_parts['extension'];
			else $extension = '';
		
		//	if (in_array($file_parts['extension'],$file_types))
			//if ( ! is_executable($temp_file))
			move_uploaded_file($temp_file, $target_file);
			
			//if (in_array($extension, $file_types)){
				/*chdir($target_path);
				if ($extension == 'c')
					exec("gcc $basename -o $filename");
				if ($extension == 'cpp')
					exec("g++ $basename -o $filename");
				if ($extension == 'pas' || $extension == 'dpr')
					exec("fpc $basename -o$filename");*/
				//$makefile .= " $filename";
			//}
			
			if (in_array($extension, array('tar', 'tar.gz', 'zip', 'rar', '7z', 'bz2', 'gz')))
				exec("extract.sh $basename");
		}
		//$handle = fopen("spj.makefile","w");
		//fwrite($handle, $makefile);
		//fclose($handle);
		//chdir($cwd);
		$this->load->model('problems');
		$this->problems->mark_update($pid);
	}
		
	public function wipedata($pid){
		$target_path = $this->config->item('data_path') . $pid . '/';
		echo $target_path;
		exec("rm -f $target_path*");
	}

	public function scan($pid){
		$this->load->model('problems');
		$target_path = $this->config->item('data_path') . '/' . $pid . '/';
		chdir($target_path);
		$dir = (scandir('.'));
		natsort($dir);
		$data = json_decode($this->problems->load_dataconf($pid)->dataConfiguration,true);
		$hash = array();
		$hash['input'] = $hash['output'] = array();
		
		$input_pattern = $this->input->post('input_file');
		$output_pattern = $this->input->post('output_file');
		
		if (isset($data['cases'])) {
			foreach ($data['cases'] as $cid => &$case) {
				foreach ($case['tests'] as $tid => $test){
					if (file_exists($test['input']) && file_exists($test['output'])) {
						$hash['input'][$test['input']] = true;
						$hash['output'][$test['output']] = true;
					} else {
						$case['tests'] = array_diff_key($case['tests'],array($tid=>null)); // do not use unset.
					}
				}
				if (count($data['cases'][$cid]['tests']) == 0)
					$data['cases'] = array_diff_key($data['cases'],array($cid=>null)); // do not use unset.
			}
		}
		
		$name_array = array();
		if ($input_pattern == '' || $output_pattern == '') {
			foreach ($dir as $file){
				if (is_file($file)){
					$info = pathinfo('./' . $file);
					$infile = $info['basename'];
					if (strpos($infile, '.in')===false) continue;
					$outfile1 = str_ireplace('.in', '.out', $infile);
					$outfile2 = str_ireplace('.in', '.ans', $infile);
					$outfile3 = str_ireplace('.in', '.ou', $infile);
					$outfile4 = str_ireplace('.in', '.sol', $infile);
					$outfile5 = str_ireplace('.in', '.std', $infile);
					$outfile = '';
					if (file_exists($outfile = $outfile1) || file_exists($outfile = $outfile2) ||
						file_exists($outfile = $outfile3) || file_exists($outfile = $outfile4) ||
						file_exists($outfile = $outfile5)) {
							if (array_key_exists($infile, $hash['input']) && array_key_exists($outfile, $hash['output'])) continue;
							$name_array[] = $infile;
						}
				}
			}
			
			usort($name_array, "strnatcmp");

			foreach ($name_array as $infile){
					$outfile1 = str_ireplace('.in', '.out', $infile);
					$outfile2 = str_ireplace('.in', '.ans', $infile);
					$outfile3 = str_ireplace('.in', '.ou', $infile);
					$outfile4 = str_ireplace('.in', '.sol', $infile);
					$outfile5 = str_ireplace('.in', '.std', $infile);
					$outfile = '';
					
					if (file_exists($outfile = $outfile1) || file_exists($outfile = $outfile2) ||
						file_exists($outfile = $outfile3) || file_exists($outfile = $outfile4) ||
						file_exists($outfile = $outfile5)){
						if (isset($test)) unset($test);
						if (isset($case)) unset($case);
						$test['input'] = $infile;
						$test['output'] = $outfile;
						$case['tests'][] = $test;
						$data['cases'][] = $case;
					}
			}
		} else {
			$input_pattern = '/' . str_replace('*', "(?P<var>\w+)", $input_pattern) . '/';

			foreach ($dir as $file) {
				if (preg_match($input_pattern, $file, $matches)) {
					$infile = $matches[0];
					$outfile = str_replace("*", $matches['var'], $output_pattern);
					
					if (file_exists($outfile)) {
						if (array_key_exists($infile, $hash['input']) && array_key_exists($outfile, $hash['output'])) continue;
						$name_array[] = $infile;
					}
				}
			}
			
			usort($name_array, "strnatcmp");

			foreach ($name_array as $infile){
					preg_match($input_pattern, $infile, $matches);
					$outfile = str_replace("*", $matches['var'], $output_pattern);
					
					if (file_exists($outfile)) {
						if (isset($test)) unset($test);
						if (isset($case)) unset($case);
						$test['input'] = $infile;
						$test['output'] = $outfile;
						$case['tests'][] = $test;
						$data['cases'][] = $case;
					}
			}
		}
		
		$num = count($data['cases']);
		foreach ($data['cases'] as &$case)
		{
			if (!isset($cases['score'])) $case['score']=100/$num;
			foreach ($case['tests'] as &$test)
			{
				if (!isset($test['timeLimit'])) $test['timeLimit']=1000;
				if (!isset($test['memoryLimit'])) $test['memoryLimit']=262144;
			}
		}

		if (!isset($data["IOMode"])) $data["IOMode"]=0;
		
		echo json_encode($data);
	}
	
	public function contestlist($page = 1){
		$contests_per_page = 20;
	
		$this->load->model('contests');
		$count = $this->contests->count();
		if ($count > 0 && ($count + $contests_per_page - 1) / $contests_per_page < $page)
			$page = ($count + $contests_per_page - 1) / $contests_per_page;
		$row_begin = ($page - 1) * $contests_per_page;
		$data = $this->contests->load_contests_list($row_begin, $contests_per_page);
		foreach ($data as $row){
			$startTime = strtotime($row->startTime);
			$endTime = strtotime($row->endTime);
			$now = strtotime('now');
			if ($now > $endTime) $row->status = '<span class="label label-success">Ended</span>';
			else if ($now < $startTime) $row->status = '<span class="label label-info">Scheduled</span>';
			else{
				$row->status = '<span class="label label-important">Running</span>';
				$row->running = TRUE;
			}
			
			$row->count = $this->contests->load_contest_teams_count($row->cid);
		}

		$this->load->library('pagination');
		$config['base_url'] = '#admin/contestlist/';
		$config['total_rows'] = $count;
		$config['per_page'] = $contests_per_page;
		$config['cur_page'] = $page;
		$config['first_url'] = $config['base_url'] . '1';
		$this->pagination->initialize($config);

		$this->load->view('admin/contestlist', array('data' => $data));
	}

	public function newcontest($cid = 0){
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '</div>');

		$this->form_validation->set_rules('contest_title', 'Title', 'required');
		$this->form_validation->set_rules('start_date', 'Start Date', 'required');
		$this->form_validation->set_rules('start_time', 'Start Time', 'required');
		$this->form_validation->set_rules('submit_date', 'Submit Date', 'required');
		$this->form_validation->set_rules('submit_time', 'Submit Time', 'required');
		$this->form_validation->set_rules('end_date', 'End Date', 'required');
		$this->form_validation->set_rules('end_time', 'End Time', 'required');
		$this->form_validation->set_rules('teamMode', 'Team Mode', 'required');
		$this->form_validation->set_rules('contestMode', 'Contest Mode', 'required');
		$this->form_validation->set_rules('contestType', 'Contest Type', 'required');
		$this->form_validation->set_rules('submitAfter', 'Submit After', 'required');
		$this->form_validation->set_rules('endAfter', 'End After', 'required');

		$this->load->model('contests');
		if ($this->form_validation->run() == FALSE){
			if ($cid > 0) $data = $this->contests->load_contest_configuration($cid);
			else $data = NULL;

			if (isset($data) && $data['isTemplate']) {
				$temp = strtotime($data['endTime']);
				$temp -= strtotime('1970-01-01 ' . $data['endAfter'] . ' +0000');
				$this->load->model('misc');
				$data['endTime'] = $this->misc->format_datetime($temp);
			}

			$this->load->view('admin/newcontest', $data);
		}else{
			$data = $this->input->post(NULL, TRUE);
			if (!isset($data['isTemplate'])) $data['isTemplate'] = '0';
			$data['isShowed'] = 1;

			if ($cid == 0) $this->contests->add($data);
			else $this->contests->add($data, $cid);

			$this->load->view('success');
		}
	}
	
	function delete_contest($cid){
		$this->load->model('contests');
		$this->contests->delete($cid);
	}
	
	function users(){
		$this->load->model('misc');
		$this->load->model('user');

		$newSchool = $this->input->post('newschool');
		if ($newSchool)
			foreach ($newSchool as $uid => $schoolName)
				$this->db->query("UPDATE User SET school=? WHERE uid=?", array($schoolName, $uid));

		$data = $this->user->load_users_list();
		$unused_count = $this->user->count_unused_user();
		$groups = $this->misc->load_groups($this->user->uid());
		foreach ($data as $row){
			$row->groups = $this->user->load_user_groups($row->uid, $groups);
		}

		$keyword = $this->input->get('sort');
		$order = $this->input->get('order');
		if (!isset($keyword))
		{
			$keyword = 'uid';
			$order = 'reverse';
		} else if (!isset($order))
			$order = null;
		switch ($keyword)
		{
		case 'uid':
		case 'name':
		case 'school':
		case 'isEnabled':
		case 'priviledge':
		case 'groups':
		case 'lastIP':
		case 'lastLogin':
		case 'expiration':
			if ($order != 'reverse')
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword > $rhs->$keyword; };
			else
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword < $rhs->$keyword; };
			usort($data, $callback);
		}

		$this->load->view('admin/users', array('data' => $data, 'unused' => $unused_count, 'keyword' => $keyword, 'order' => $order));
	}

	function change_user_status($uid){
		$this->user->change_status($uid);
	}

	function change_user_priviledge($uid, $priviledge)
	{
		$this->user->change_priviledge($uid, $priviledge);
	}

	function change_expiration()
	{
		$names = json_decode($this->input->post('names', TRUE));
		$datetime = $this->input->post('datetime');

		foreach ($names as $name)
			$this->user->set_expiration($this->user->load_uid($name), $datetime);
		$this->load->view('success');
	}
	
	function delete_user($uid){
		$this->user->delete($uid);
	}

	function delete_unused_users(){
		$this->user->delete_unused();
	}

	function setallowing($uid){
		$this->load->model('misc');
		$add = $this->input->get('add');
		$del = $this->input->get('del');
		if (isset($add) && $add)
			$this->misc->add_allowing($uid, $add);
		if (isset($del) && $del)
			$this->misc->del_allowing($del);
		$data = $this->misc->load_allowing($uid);
		$this->load->view('admin/setallowing', array('data' => $data, 'uid' => $uid));
	}

	function setallowings(){
		$this->load->model('misc');
		$this->load->model('user');
		$names = $this->input->post('users');
		$probs = $this->input->post('probs');
		if (!$names) $names = array();
		if (!$probs) $probs = array();
		$alter_user = $this->input->post('alter_user');
		$alter_prob = $this->input->post('alter_prob');
		$all = $this->input->post('all');
		$users = array();
		foreach ($names as $id => $name)
		{
			$uid = $this->user->load_uid($name);
			if ($uid === false || $this->user->load_priviledge($uid)!='restricted')
				unset($names[$id]);
			else
				$users[] = $uid;
		}
		if ($alter_user) $alter_user = $this->user->load_uid($alter_user);
		if ($alter_user && $alter_prob)
			$this->misc->alter_allowing($alter_user, $alter_prob);
		$data = $this->misc->load_allowings($users, $probs);
		foreach ($names as $id => $user)
			foreach ($probs as $prob)
			{
				if ($all == 'add' && !isset($data[$user][$prob]))
					$this->misc->add_allowing($users[$id],$prob);
				if ($all == 'del' && isset($data[$user][$prob]))
					$this->misc->del_allowing($data[$user][$prob]);
			}
		if ($all == 'add' || $all == 'del')
			$data = $this->misc->load_allowings($users, $probs);
		$this->load->view('admin/setallowings', array(
			'data' => $data,
			'users' => $names,
			'probs' => $probs
		));
	}

	function new_task($tid = 0){
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '</div>');
		
		$this->form_validation->set_rules('task_title', 'Title', 'required');
		$this->form_validation->set_rules('description', 'Description', '');
		
		$this->load->model('misc');
		if ($this->form_validation->run() == FALSE){
			if ($tid > 0) $data = $this->misc->load_task($tid);
			else $data = NULL;

			$this->load->view('task/new_task', $data);
		}else{
			$data = $this->input->post(NULL, TRUE);
			$this->misc->add_task($data, $tid);
			
			$this->load->view('success');
		}
	}
	
	function delete_task($tid) {
		$this->load->model('misc');
		$this->misc->delete_task($tid);
	}
	
	function task_list($page = 1){
		$tasks_per_page = 20;
		
		$this->load->model('misc');
		
		$begin = ($page - 1) * $tasks_per_page;
		$count = $this->misc->count_tasks();
		$tasks = $this->misc->load_task_list($begin, $tasks_per_page);
		
		$this->load->library('pagination');
		$config['base_url'] = '#admin/task_list/';
		$config['total_rows'] = $count;
		$config['per_page'] = $tasks_per_page;
		$config['cur_page'] = $page;
		$config['first_url'] = $config['base_url'] . '1';
		$this->pagination->initialize($config);
		
		$this->load->view('admin/task_list', array('tasks' => $tasks));
	}

	function change_submission_status($sid){
		$this->load->model('submission');
		$this->submission->change_status($sid);
	}
	
	function rejudge(){
		$this->load->library('form_validation');
		$this->load->model('problems');
		$this->load->model('submission');
		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '</div>');
		
		$this->form_validation->set_rules('type', 'Type', 'required');
		$this->form_validation->set_rules('id', 'ID', 'required');
		if ($this->form_validation->run() == FALSE)
			$this->load->view('admin/rejudge');
		else{
			$data = $this->input->post(NULL, TRUE);
			if ($data['type'] == 'submission'){
				$idmin = $data['id'];
				$idmax = ($data['idmax']) ? $data['idmax'] : $data['id'];
				for ($i = $idmin; $i <= $idmax; $i++)
					$this->submission->rejudge($i);
			}else{
				$data = $this->problems->load_problem_submission($data['id']);
				foreach ($data as $row)
					$this->submission->rejudge($row->sid);
			}
			$this->load->view('success');
		}
	}

	function always_true() {
		return TRUE;
	}
	
	function statistic() {
		$this->load->library('form_validation');
		$this->load->model('problems');
		$this->load->model('contests');
		$this->load->model('submission');
		
		$this->form_validation->set_rules('user', '', 'callback_always_true');
		
		if ($this->form_validation->run() == FALSE)
			$this->load->view('admin/statistic');
		else{
			$data = $this->input->post(NULL, TRUE);
			
			$uids = $pids = '';
	
			if (isset($data['problem']) && $data['problem'] != '') {
				$pid_array = explode(',', $data['problem']);
				foreach ($pid_array as $pid)
					$pids .= "$pid,";
			}
			
			if (isset($data['contest']) && $data['contest'] != '') {
				$cid_array = explode(',', $data['contest']);
				foreach ($cid_array as $cid) {
					$result = $this->contests->load_contest_problemset($cid);
					foreach ($result as $row)
						$pids .= "$row->pid,";
				}
			}
			
			if (isset($data['task']) && $data['task'] != '') {
				$tid_array = explode(',', $data['task']);
				foreach ($tid_array as $tid) {
					$result = $this->contests->load_task_problems($tid);
					foreach ($result as $row)
						$pids .= "$row->pid,";
				}
			}
			
			$pids = rtrim($pids, ',');
			
			if (isset($data['user']) && $data['user'] != '') {
				$name_array = explode(',', $data['user']);
				foreach ($name_array as $name) {
					$uid = $this->user->load_uid($name);
					$uids .= "$uid,";
				}
			}
			
			if ($uids == '') $uids = FALSE;
			else $uids = rtrim($uids, ',');
			
			if (isset($data['group']) && $data['group'] != '') {
				
			}
			
			$data = $this->contests->load_statistic_OI($pids, $uids);
			$this->load->view('admin/standing', array('data' => $data, 'pids' => $pids));
		}
	}
	
	function contest_to_task($cid) {
		$this->load->model('contests');
		
		$this->contests->contest_to_task($cid);
	}

	function functions_check() {
		$data = $this->input->post(NULL);
		if ($data['name'] != '' && $data['date'] != '' && $data['time'] != '') return TRUE;
		if ($data['reset_pwd_username'] != '' && $data['reset_password'] != '') return TRUE;
		return FALSE;
	}

	function functions() {
		$this->load->library('form_validation');

		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '</div>');

		$this->form_validation->set_rules('name', 'Username', 'callback_functions_check');
		//$this->form_validation->set_rules('date', 'Date', 'required');
		//$this->form_validation->set_rules('time', 'Time', 'required');

		if ($this->form_validation->run() == FALSE) {
			$this->load->view('admin/functions');
		} else {
			$data = $this->input->post(NULL);
			if ($data['name'] != '' && $data['date'] != '' && $data['time'] != '') { 
				$permission = $this->input->post('permission');
				$time = $this->input->post('date') . ' ' . $this->input->post('time');
				$time = strtotime($time);

				$per = array();
				if ($permission != FALSE) {
					foreach ($permission as $row)
						$per[$row] = $time;
				}
				$this->user->set_permission($per, $this->user->load_uid($this->input->post('name')));
			}
			
			if ($data['reset_pwd_username'] != '' && $data['reset_password'] != '') { 
				$uid = $this->user->load_uid($data['reset_pwd_username']);
				$this->user->save_password($uid, md5(md5($data['reset_password']) . $this->config->item('password_suffix')));
			}

			$this->load->view('success');
		}
	}

	function change_contest_pinned($cid) {
		$this->load->model('contests');
		$this->contests->change_pinned($cid);
	}

	function global_settings()
	{
		$this->load->model('misc');
		$data = $this->misc->load_dynamic_config();
		$post = $this->input->post('set', TRUE);
		if ($post)
		{
			$post = json_decode($post);
			$key = $post->key;
			$value = $post->value;
			if ($data[$key]->format->datatype == 'enum' && in_array($value, $data[$key]->format->enum_value, true) ||
				$data[$key]->format->datatype == 'input')
				$this->misc->save_dynamic_config($data[$key]->valuefile, $key, $value);
		} else
			$this->load->view('admin/global_settings', array('data' => $data));
	}

	function edit_tags($pid) // manage tags for a problem
	{
		$this->load->view("admin/edit_tags", array("pid" => $pid));
	}

	function manage_tags() // manage all the tags
	{
		$this->load->view("admin/manage_tags");
	}

	function del_tag($id)
	{
		$this->load->model("problems");
		$this->problems->del_tag($id);
	}

	function add_tag($name, $proto = NULL)
	{
		$name = urldecode($name);
		$this->load->model("problems");
		$properties = $this->input->post('properties');
		if ($this->problems->add_tag($name, $proto, $properties))
			$ret = array("status" => "ok");
		else
			$ret = array("status" => "error", "message" => lang('error_tag_same_name'));
		exit(json_encode($ret));
	}

	function tag_change_proto($id, $proto)
	{
		$this->load->model("problems");
		$this->problems->tag_change_proto($id, $proto == 'null' ? null : $proto);
	}

	function tag_set_properties($id)
	{
		$this->load->model("problems");
		$properties = $this->input->post('properties');
		$this->problems->tag_set_properties($id, $properties);
	}

	function check_file_exist($pid, $file)
	{
		$this->load->model('problems');
		if (
			$pid != $this->security->sanitize_filename($pid) ||
			$file != $this->security->sanitize_filename($file)
		   )
		   exit("<i class='icon-remove'></i>Name disallowed");
		if ($this->problems->file_exist($pid, $file, $this->input->get('require')))
			exit("<i class='icon-ok'></i>OK. File exists");
		else
			exit("<i class='icon-remove'></i>File not exists");
	}

	public function items(){
		$this->load->model('payment');

		$data = $this->payment->get_items_list();

		$keyword = $this->input->get('sort');
		$order = $this->input->get('order');
		if (!isset($keyword))
			$keyword = 'itemid';
		if (!isset($order))
			$order = null;
		switch ($keyword)
		{
		case 'itemid':
		case 'itemDescription':
		case 'price':
		case 'type':
			if ($order != 'reverse')
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword > $rhs->$keyword; };
			else
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword < $rhs->$keyword; };
			usort($data, $callback);
			break;
		case 'timeInt':
			if ($order != 'reverse')
				$callback = function($lhs, $rhs) { return $this->payment->get_expiration($lhs->type, $lhs->timeInt) > $this->payment->get_expiration($rhs->type, $rhs->timeInt); };
			else
				$callback = function($lhs, $rhs) { return $this->payment->get_expiration($lhs->type, $lhs->timeInt) < $this->payment->get_expiration($rhs->type, $rhs->timeInt); };
			usort($data, $callback);
		}

		$this->load->view('admin/pay_items', array('data' => $data, 'keyword' => $keyword, 'order' => $order));
	}
	
	function two_decimal_places($num){
		$num *= 100;
		if ($num - floor($num) != 0){
			$this->form_validation->set_message('two_decimal_places', '%s only allows <=2 decimal places!');
			return false;
		}
		return true;
	}

	public function change_item($itemid){
		$this->load->library('form_validation');
		$this->load->model('payment');

		$this->form_validation->set_error_delimiters('<div class="control-group"><span class="controls add-on alert alert-error">', '</span></div>');
		
		$this->form_validation->set_rules('itemDescription', 'lang:item_description', 'required');
		$this->form_validation->set_rules('price', 'lang:price', 'greater_than_equal_to[0]|callback_two_decimal_places');
		$this->form_validation->set_rules('type', 'lang:type', 'required|is_natural|less_than[2]');
		$this->form_validation->set_rules('timeInt', 'lang:time_int', 'required|is_natural_no_zero');

		$this->form_validation->set_message('required', '%s is required!');
		$this->form_validation->set_message('greater_than_equal_to', '%s must be >=0!');
		$this->form_validation->set_message('is_natural', '%s must be in range!');
		$this->form_validation->set_message('less_than', '%s must be in range!');
		$this->form_validation->set_message('is_natural_no_zero', '%s must be a positive number!');
		
		if ($this->form_validation->run() == FALSE)
			if ($itemid == 0)
				$this->load->view('admin/form_item');
			else
				$this->load->view('admin/form_item', array('item' => $this->payment->get_item($itemid)));
		else {
			$this->payment->set_item($this->input->post('itemid'),
									$this->input->post('itemDescription'),
									$this->input->post('price'),
									$this->input->post('type'),
									$this->input->post('timeInt'));
			$this->load->view('success');
		}
	}

	public function delete_item($itemid){
		$this->load->model('payment');
		$this->payment->delete_item($itemid);
	}

	public function orders(){
		$this->load->model('payment');

		$data = $this->payment->get_orders_list();

		$keyword = $this->input->get('sort');
		$order = $this->input->get('order');
		if (!isset($keyword)){
			$keyword = 'orderid';
			$order = 'reverse';
		}
		else if (!isset($order))
			$order = 'null';
		switch ($keyword)
		{
		case 'orderid':
		case 'payid':
		case 'uid':
		case 'name':
		case 'itemDescription':
		case 'expiration':
		case 'price':
		case 'realPrice':
		case 'method':
		case 'status':
		case 'createTime':
		case 'finishTime':
			if ($order != 'reverse')
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword > $rhs->$keyword; };
			else
				$callback = function($lhs, $rhs) use($keyword) { return $lhs->$keyword < $rhs->$keyword; };
			usort($data, $callback);
		}

		$this->load->view('admin/pay_orders', array('data' => $data, 'keyword' => $keyword, 'order' => $order));
	}

	public function review_order(){
		$orderid_arr = json_decode($this->input->post('orderid'));
		$global_expiration = $this->input->post('datetime');

		$this->load->model('payment');
		foreach ($orderid_arr as $orderid){
			$order = $this->payment->get_order($orderid);
			if (isset($global_expiration))
				$expiration = $global_expiration;
			else
				$expiration = $order->expiration;

			$this->payment->review_order($orderid, $expiration);
			$this->user->set_expiration($order->uid, $expiration);
		}
		$this->load->view('success');
	}

	public function reject_order(){
		$orderid_arr = json_decode($this->input->post('orderid'));

		$this->load->model('payment');
		foreach ($orderid_arr as $orderid)
			$this->payment->reject_order($orderid);
		$this->load->view('success');
	}

	// temp

	/*public function rejudgeall()
	{
		ignore_user_abort(true);
		set_time_limit(0);
		$this->load->model('submission');
		for ($i=1000; $i<=86511; $i++)
		{
			if (!$this->db->query("SELECT COUNT(*) AS cnt FROM Submission WHERE sid=$i")->row()->cnt) continue;
			$this->submission->rejudge($i);
		}
	}

	public function rejudgeerror()
	{
		ignore_user_abort(true);
		set_time_limit(0);
		$this->load->model('submission');
		for ($i=1000; $i<=86511; $i++)
		{
			if (!$this->db->query("SELECT COUNT(*) AS cnt FROM Submission WHERE sid=$i && status=9")->row()->cnt) continue;
			$this->submission->rejudge($i);
		}
	}*/
}

// End of file admin.php
