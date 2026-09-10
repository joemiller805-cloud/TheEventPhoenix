<?php
	ini_set('upload_max_filesize', '900M');
   	ini_set('post_max_size', '900M');
   	ini_set('max_input_time', 1000);
   	ini_set('max_execution_time', 1000);
	session_start();
	if (isset($_SESSION['accountid'])){
		$path = $_POST['location'];
		// make sure a folder exists for the selected account
		if (!file_exists($path)) mkdir($path, 0777, true);
		if(isset($_POST['fileName'])){
			$path = $path . '/' . basename($_POST['fileName']);
		}else{
			$path = $path . '/' . basename($_FILES["document"]["name"]);
		}
		
		if(is_uploaded_file($_FILES['document']['tmp_name'])){
			move_uploaded_file($_FILES['document']['tmp_name'], $path);
			echo "success";
		}else{
			echo 'error';
		}
	}else{
		echo 'error';
	}
?>