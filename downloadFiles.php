<?php
	$filesInput = $_POST['files'] ?? ($_GET['files'] ?? array());
	$files = is_array($filesInput) ? $filesInput : explode(',', $filesInput);
	$zipname = $_POST['zipname'] ?? ($_GET['zipname'] ?? 'logos.zip');
	$zipname = preg_replace('/[^A-Za-z0-9._-]/', '', $zipname);
	if($zipname == '') $zipname = 'logos.zip';
	if(strtolower(substr($zipname, -4)) != '.zip') $zipname .= '.zip';

	$baseDir = realpath(__DIR__);
	$allowedRoots = array();
	foreach(array('img', 'documents') as $dir){
		$root = realpath($baseDir . DIRECTORY_SEPARATOR . $dir);
		if($root) $allowedRoots[] = str_replace('\\', '/', $root);
	}

	$getSafePath = function($file) use ($baseDir, $allowedRoots){
		$file = trim(str_replace("\0", '', $file));
		$file = ltrim($file, "/\\");
		if($file == '' || strpos($file, '..') !== false) return false;

		$path = $baseDir . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file);
		$realPath = realpath($path);
		if(!$realPath || !is_file($realPath)) return false;

		$realPath = str_replace('\\', '/', $realPath);
		foreach($allowedRoots as $root){
			$root = rtrim($root, '/') . '/';
			if(strpos($realPath, $root) === 0) return $realPath;
		}

		return false;
	};

	$zipPath = tempnam(sys_get_temp_dir(), 'erp_download_');
	$zip = new ZipArchive;
	if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
		http_response_code(500);
		echo 'Unable to create download archive.';
		exit;
	}

	$usedNames = array();
	foreach($files as $file){
		$filePath = $getSafePath($file);
		if(!$filePath) continue;

		$pathInfo = pathinfo($filePath);
		$baseName = $pathInfo['filename'] ?? basename($filePath);
		$extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
		$localName = $baseName . $extension;
		$suffix = 2;

		while(isset($usedNames[$localName])){
			$localName = $baseName . '-' . $suffix . $extension;
			$suffix++;
		}

		if($zip->addFile($filePath, $localName)) $usedNames[$localName] = true;
	}

	if($zip->numFiles == 0){
		$zip->close();
		unlink($zipPath);
		http_response_code(400);
		echo 'No files found to download.';
		exit;
	}

	$zip->close();

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . $zipname . '"');
	header('Content-Length: ' . filesize($zipPath));
	readfile($zipPath);
	unlink($zipPath);
?>
