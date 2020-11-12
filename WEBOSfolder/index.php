<?php

error_reporting( error_reporting() & ~E_NOTICE );

//Security options
$allow_delete = true; // Set to false to disable delete button and delete POST request.
$allow_upload = true; // Set to true to allow upload files
$allow_create_folder = true; // Set to false to disable folder creation
$allow_direct_link = true; // Set to false to only allow downloads and not direct link
$allow_show_folders = true; // Set to false to hide all subdirectories
$allow_create_file = true;//set to true to allow create file
$allow_copy_file = true; //set to true to allow copyfile
$disallowed_extensions = ['php','bin'];  // must be an array. Extensions disallowed to be uploaded
$hidden_extensions = ['php','bin']; // must be an array of lowercase file extensions. Extensions hidden in directory index

$PASSWORD = '';  // Set the password, to access the file manager... (optional)

if($PASSWORD) {

	session_start();
	if(!$_SESSION['_sfm_allowed']) {
		// sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
		$t = bin2hex(openssl_random_pseudo_bytes(10));
		if($_POST['p'] && sha1($t.$_POST['p']) === sha1($t.$PASSWORD)) {
			$_SESSION['_sfm_allowed'] = true;
			header('Location: ?');
		}
		echo '<html><body><form action=? method=post>PASSWORD:<input type=password name=p autofocus/></form></body></html>';
		exit;
	}
}

// must be in UTF-8 or `basename` doesn't work
setlocale(LC_ALL,'en_US.UTF-8');

$tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
if(DIRECTORY_SEPARATOR==='\\') $tmp_dir = str_replace('/',DIRECTORY_SEPARATOR,$tmp_dir);
$tmp = get_absolute_path($tmp_dir . '/' .$_REQUEST['file']);

if($tmp === false)
	err(404,'File or Directory Not Found');
if(substr($tmp, 0,strlen($tmp_dir)) !== $tmp_dir)
	err(403,"Forbidden");
if(strpos($_REQUEST['file'], DIRECTORY_SEPARATOR) === 0)
	err(403,"Forbidden");


if(!$_COOKIE['_sfm_xsrf'])
	setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));
if($_POST) {
	if($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
		err(403,"XSRF Failure");
}
$file = $_REQUEST['file'] ?: '.';
if($_GET['do'] == 'list') {
	if (is_dir($file)) {
		$directory = $file;
		$result = [];
		$files = array_diff(scandir($directory), ['.','..']);
		foreach ($files as $entry) if (!is_entry_ignored($entry, $allow_show_folders, $hidden_extensions)) {
		$i = $directory . '/' . $entry;
		$stat = stat($i);
	        $result[] = [
	        	'mtime' => $stat['mtime'],
	        	'size' => $stat['size'],
	        	'name' => basename($i),
	        	'path' => preg_replace('@^\./@', '', $i),
	        	'is_dir' => is_dir($i),
	        	'is_deleteable' => $allow_delete && ((!is_dir($i) && is_writable($directory)) ||
                                                           (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i))),
	        	'is_readable' => is_readable($i),
	        	'is_writable' => is_writable($i),
	        	'is_executable' => is_executable($i),
	        ];
	    }
	} else {
		err(412,"Not a Directory");
	}
	echo json_encode(['success' => true, 'is_writable' => is_writable($file), 'results' =>$result]);
	exit;
} elseif ($_POST['do'] == 'delete') {
	if($allow_delete) {
		rmrf($file);
	}
	exit;
} elseif ($_POST['do'] == 'fopen' && $allow_create_file){
	//error_log("file");
	//error_log($_POST['name']);
	//error_log($_POST['fdata']);
	$dir = $_POST['name'];
	$dir = str_replace('/', '',$dir);
	if(substr($dir, 0, 2) === '..')
		exit;
	chdir($file);
	@file_put_contents($_POST['name'],$_POST['fdata']);
	exit;
} elseif ($_POST['do'] == 'mkdir' && $allow_create_folder) {
	//error_log("folder");
	// error_log("fuck_this shit");
	$dir = $_POST['name'];
	$dir = str_replace('/', '', $dir);
	if(substr($dir, 0, 2) === '..')
	    exit;
	chdir($file);
	@mkdir($_POST['name']);
	exit;
} elseif ($_POST['do'] == 'copy' && $allow_copy_file){
	$dir = $_POST['dir'];
	$array = json_decode($_POST['array']);
	$array_f = json_decode($_POST['array_f']);
	$array_f_n = json_decode($_POST['array_f_n']);

	$array_2 = json_decode($_POST['array_2']);
	$array_f_2 = json_decode($_POST['array_f_2']);
	$array_f_n_2 = json_decode($_POST['array_f_n_2']);

	$dir_process= str_replace("%2F","/",$dir);

	$folder_count = count($array_f);
	$folder_count_2 = count($array_f_2);
	// copy stuff
	for ($x = 0; $x < $folder_count; $x++) {
		$value_src = $array_f[$x];
		$value_na = $array_f_n[$x];
		$value_process = str_replace("%2F","/",$value_src);
		$temp.=$dir_process;
		$temp.="/";
		$temp.=$value_na;
		if (substr_count($temp,"/") == 1 && substr_count($temp[0],"/") != 0 ) {
			$final_dir .= substr($temp,1);
		}
		else{
			$final_dir .= $temp;
		}

		recurse_copy($value_process,$final_dir);
		error_log("------------------broke again 2");
		error_log($value_process);
		error_log($final_dir);
		$final_dir = "";
		$final_value ="";
		$temp ="";
	}
	foreach ($array as $value){
		$final_value ="";
		$final_dir="";
		$value_process = str_replace("%2F","/",$value);
		//error_log($value_process);
		$final_value.=substr($value_process,1);
		
		$find ='/';
		$n = strripos($value_process,$find);
		if ($n == false)
		{
			$n =0;
		}
		$name = substr($value_process, $n);
		
		$final_dir.=$dir_process;
		$temp= $final_dir;
		$final_dir.=$name;
		$stop = 0;
		if (strlen($final_dir) == strlen($name))
		{
			$top = 1;	
		}
		error_log("------------------");
		error_log($final_value);
		error_log($final_dir);
		if ($top == 0){
			@copy($final_value,$final_dir);
		}else{
			@copy($final_value,substr($final_dir,1));
		}
		$final_dir = $temp;
		//ex:  @copy('mang_may_tinh.txt','5/mang_may_tinh.txt');
	}
	
	//cut stuff
	for ($x = 0; $x < $folder_count_2; $x++) {
		$value_src = $array_f_2[$x];
		$value_process = str_replace("%2F","/",$value_src);
		rmrf($value_process);
		error_log("shit work");
		$final_value ="";
	}
	foreach ($array_2 as $value){
		$value_process = str_replace("%2F","/",$value);
		rmrf($value_process);
		//error_log($value_process);
	}
	
	exit;
} elseif ($_POST['do'] == 'upload' && $allow_upload) {
	foreach($disallowed_extensions as $ext)
		if(preg_match(sprintf('/\.%s$/',preg_quote($ext)), $_FILES['file_data']['name']))
			err(403,"Files of this type are not allowed.");

	$res = move_uploaded_file($_FILES['file_data']['tmp_name'], $file.'/'.$_FILES['file_data']['name']);
	exit;
} elseif ($_GET['do'] == 'download') {
	$filename = basename($file);
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	header('Content-Type: ' . finfo_file($finfo, $file));
	header('Content-Length: '. filesize($file));
	header(sprintf('Content-Disposition: attachment; filename=%s',
		strpos('MSIE',$_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\"" ));
	ob_flush();
	readfile($file);
	exit;
}

function is_entry_ignored($entry, $allow_show_folders, $hidden_extensions) {
	if ($entry === basename(__FILE__)) {
		return true;
	}

	if (is_dir($entry) && !$allow_show_folders) {
		return true;
	}

	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if (in_array($ext, $hidden_extensions)) {
		return true;
	}

	return false;
}

function rmrf($dir) {
	if(is_dir($dir)) {
		$files = array_diff(scandir($dir), ['.','..']);
		foreach ($files as $file)
			rmrf("$dir/$file");
		rmdir($dir);
	} else {
		unlink($dir);
	}
}
function is_recursively_deleteable($d) {
	$stack = [$d];
	while($dir = array_pop($stack)) {
		if(!is_readable($dir) || !is_writable($dir))
			return false;
		$files = array_diff(scandir($dir), ['.','..']);
		foreach($files as $file) if(is_dir($file)) {
			$stack[] = "$dir/$file";
		}
	}
	return true;
}
function recurse_copy($src,$dst) { 
    $dir = @opendir($src); 
    @mkdir($dst); 
    while(false !== ( $file = @readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else { 
                @copy($src . '/' . $file,$dst . '/' . $file); 
            } 
        } 
    } 
    closedir($dir); 
} 
// from: http://php.net/manual/en/function.realpath.php#84012
function get_absolute_path($path) {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }

function err($code,$msg) {
	http_response_code($code);
	echo json_encode(['error' => ['code'=>intval($code), 'msg' => $msg]]);
	exit;
}

function asBytes($ini_v) {
	$ini_v = trim($ini_v);
	$s = ['g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10];
	return intval($ini_v) * ($s[strtolower(substr($ini_v,-1))] ?: 1);
}
$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<style>
	.is_dir .size {color:transparent;font-size:0;}
	.is_dir .size:before {content: "--"; font-size:14px;color:#333;}
	#all{
		width: 100%;
		display: grid;
		grid-template-rows: 35vh 60vh;
		grid-row-gap: 2.5vh;
	}
	#title{
		margin-bottom: 0.5vw;
		margin-left: 0.5vw;
		margin-right: 0.5vw;
		width: 99%;
		background-color:white;
		border-collapse: separate;
		border-spacing: 5px 10px;
		position:sticky;
		top:0vh;
		z-index:2;
	}
	#title > tr{
		display: grid;
		grid-template-columns: auto auto auto auto auto auto;

	}
	#top{
		display: grid;
		grid-template-columns: 35vw 50vw;	
		margin-top: 1.5vh;	
	}
	#top_left{
		border-radius: 1rem;
		border:1px solid black;
		margin-left: 1vw;
	}
	#top_right{
		border-radius: 1rem;
		border:1px solid black;
		margin-left: 1vw;
		width: 59.75vw;
		padding: 1vw;
	}
	#bottom{
		padding-top:1vh;
		display: grid;
		grid-template-columns: 15vw 82vw;
		justify-content: center;
		width: 100%;	
	}
	#bottom > div{
		align-items: center;
		justify-items: center;
		margin-bottom: 1vh;
	}
	#breadcrumb{
		width:100%;
	}
	#breadcrumb > div {
		margin-top: 3vh;
		display: grid;
		grid-template-columns: auto;
		justify-content: center;
		justify-items: center;
  		align-items: center;
	}
	#breadcrumb_a{
		border: 1px solid rgb(60, 60, 60);
		border-radius: 0.25rem;
		padding: 0.25vw;
	}
	.right::-webkit-scrollbar {
    display: none;
	}
	.right::-ms-scrollbar {
    display: none;
	}
	.left::-webkit-scrollbar {
    display: none;
	}
	.left::-ms-scrollbar {
    display: none;
	}
	th{
		background-color: rgb(230, 230, 230);
		border-radius: 0.8rem;
		padding-top:0.5rem;
		padding-bottom:0.5rem;
	}
	.left{
		border-radius: 1rem;
		border: 1px solid rgb(60, 60, 60);
		margin-right: 1vw;
		overflow-y: scroll;
	}
	.right{
		border-radius: 1rem;
		border: 1px solid rgb(60, 60, 60);
		overflow-y: scroll;
	}
	#table{
		margin: 0.5vw;
		width: 99%;
		border-collapse: separate;
		border-spacing: 5px 10px;
	}
	th:nth-child(1){
		width:25%;
	}
	th:nth-child(2){
		width:10%;
	}
	th:nth-child(3){
		width:15%;
	}
	th:nth-child(4){
		width:15%;
	}
	th:nth-child(5){
		width:27%;
	}
	.first{
		width: 25%;
	}
	.second{
		width: 10%;
		text-align: center;
	}
	.third{
		width:15%;
		text-align: center;
	}
	.forth{
		width:15%;
		text-align: center;
	}
	.fifth{
	
  		text-align: center;
  		justify-content: center;
  		align-items: center;
		z-index: 0;
	}
	.fifth:hover > button{
		opacity: 1;
	}
	.delete{
		background-color: red;
		color: white;
	}
	.delete_btn{
		background-color: red;
		color: white;
	}
	.name{
		background: url(file.png);
		background-repeat: no-repeat;
		background-size: contain;
		padding-left: 2.25rem;
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
		border-radius: 0;
	}
	.is_dir .name{
		background: url(folder.png);
		background-repeat: no-repeat;
		background-size: contain;
		padding-left: 2.25rem;
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
		border-radius: 0;
	}
	.no_btn:hover{
		background-color: lightgreen;
	}
	.fifth > button{
		margin-left: 0.5vw;
		margin-right: 0.5vw;
		border: none;
		border-radius: 1.5rem;
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
		padding-left: 1.25rem;
		padding-right: 1.25rem;
		opacity: 0.5;
    	transition: 0.3s;
	}
	.sixth{
		text-align: center;
	}
	.sixth > a{
		padding-left: 0.5rem;
		padding-right: 0.5rem;
		border-radius: 1rem;
	}
	.copy-active {
		background-color: rgb(43, 43, 43);
		transition: 0.3;
		color: white;
		border: none;
	}
	.cut-active {
	   background-color: rgb(43, 43, 43);
	   transition: 0.3;
	   border: none;
	   color: white;
	}
	.hidden{
		visibility: hidden;
		opacity: 0;
		transition: 0.3s;
	}
	.show{
		display: none;
		opacity: 0;
		transition: 0.3s;
	}
	#list{
		padding: 10px;
	}
	#list_tr > td{
		border-radius: 0.8rem;
		padding: 3px;
	}
	#list_tr:hover > td{
		transition: 0.3s;
		background-color: rgb(230, 230, 230);
	}
	a{
		border-radius: 0.5rem;
		padding-left: 5px;
		padding-right: 5px;
		color: black;
		text-decoration: none;
	}
	#mod_bar{
		display: flex;
		justify-content: center;
		align-content: center;
		align-items:center ;
	}
	.btnCopy:hover{
		background-color: rgb(80, 80, 80);
		transition: 0.3s;
		color: white;
	}
	.btnCopy_f:hover{
		background-color: rgb(80, 80, 80);
		transition: 0.3s;
		color: white;
	}
	.btnCut:hover{
		background-color: rgb(80, 80, 80);
		transition: 0.3s;
		color: white;
	}
	.btnCut_f:hover{
		background-color: rgb(80, 80, 80);
		transition: 0.3s;
		color: white;
	}
	#selecting_files{
		padding-top: 2vh;
		padding-bottom: 2vh;
		display:grid;
		grid-template-columns: 15vw 15vw;
		position: relative;
		width:94%;
		justify-content: center;
		align-items: center;
		grid-template-rows: auto auto;
		grid-column-gap: 1vw;
	}
	#selecting_files >div:nth-child(2)> *{
		border: none;
		width: 8vw;
		margin-left: 6vw;
		border-radius: 1.5rem;
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
		padding-left: 1.25rem;
		padding-right: 1.25rem;
    	transition: 0.3s;
		position: relative;
		margin-top: 1.75vw;
		grid-row: 1/ span 2;
		grid-column: 2/ span3;
	}
	#selecting_files >div:nth-child(2)> *:hover{
		background-color: rgb(60, 60, 60);
		color:white;
	}
	#selecting_files > div:nth-child(1){
		display:grid;
		grid-template-columns: 10vw 15vw;
		justify-items: left;
  		align-items: center;
	}
	#selecting_files > div:nth-child(1)>input{
		border-radius: 1rem;
		padding-left: 0.25rem;
		border: 1px solid black;
	}
	#selecting_files > div:nth-child(3)>input{
		border-radius: 1rem;
		padding-left: 0.25rem;
		border: 1px solid black;
	}
	#selecting_files > div:nth-child(3){
		display:grid;
		border-radius: 1rem;
		grid-template-columns: 10vw 15vw;
		justify-items: left;
  		align-items: center;
	}
	#selecting_files > div >input{
		margin: left;
		width:10vw;
	}
	#upload_bar{
		border-radius: 1rem;
		border: 1px solid black;
		margin-left: 1vw;
		margin-top: 2.5vh;
		align-items:center ;
		display:grid;
		width:93%;
		height: 13vh;
		grid-template-columns: auto;
		grid-template-rows: 30% 70% ;
	}
	#upload_bar >div:nth-child(1)>*{
		margin-top: 2vh;
		width: 40%;
		border-radius: 1rem;
		display: flex;
		justify-content: center;
		align-content: center;
		align-items:center ;
		margin-left: 9.5vw;
		border: 1px solid black;
	}
	#upload_bar >div:nth-child(1)> label{
		background-color:rgb(240, 240, 240);
		padding-top: 0.15rem;
		padding-bottom: 0.15rem;
		transition: 0.3s;
		border: none;
	}
	#upload_bar >div:nth-child(1)> label:hover{
		background-color:rgb(43, 43, 43);
		color:white;
	}
	#upload_bar >div:nth-child(2){
		display: flex;
		justify-content: center;
		align-content: center;
		align-items:center ;
	}
	#upload_btn{
		opacity: 0;
   		position: absolute;
   		z-index: -1;
	}
	#create_bar {
		display: grid;
		grid-template-columns: 10vw 15vw ;
		grid-column-gap: 1vw;
		grid-row-gap: 0px;
		grid-template-rows: auto;
		border-radius: 1rem;
		margin-left: 1vw;
		display: flex;
		padding-left: 0.25vw;
		align-content: center;
		width:93%;
		padding-top: 1vh;
	}
	#create_bar > div:nth-child(1){
		display:grid;
		grid-template-columns: 10vw 10vw;
		justify-items: left;
  		align-items: center;
	} 
	#create_bar > div:nth-child(1)>input{
		margin-left: 0.15vw;
		width:10vw;
		padding-left: 0.25rem;
		border: 1px solid black;
		border-radius: 1rem;
	}
	#create_bar > div:nth-child(2)>input{
		width: 8vw;
		border-radius: 1rem;
		border:none;
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
		margin-left: 1.15vw;
		transition: 0.3s;
	}
	#create_bar > div:nth-child(2)>input:hover{
		background-color: rgb(43, 43, 43);
		color: white;
	}
	#create_file > div:nth-child(1){
		margin-left: 13vw;
	}
	#create_file > div:nth-child(1) > input:nth-child(2){
		padding-left: 0.5vw;
		margin-left: 2vw;
		border-radius: 1rem;
		width:14vw;
		border: 1px solid black;
	}
	#create_file_btn{
		margin-left: 2vw;
		border-radius: 1rem;
		padding: 0.25rem;
		width: 8vw;
		border:none;
		transition: 0.3s;
	}
	#create_file_btn:hover{
		background-color: rgb(43, 43, 43);
		color: white;
	}
	#create_file> div:nth-child(2)>*{
		border-top: 1px solid black;
		border-left: 1px solid black;
		border-right: 1px solid black;
		border-top-right-radius: 1rem;
		border-top-left-radius: 1rem;
		padding-top: 1.25vh;
		padding-bottom: 1.75vh;
		padding-left: 2vh;
		padding-right: 2vh;
	}
	#create_file> div:nth-child(3)>*{
		border-bottom-left-radius: 1rem;
		border-bottom-right-radius: 1rem;
		border-top-right-radius: 1rem;
		border: 1px solid black;
		margin-top: 1vh;
		width: 59.5vw;
		height: 22vh;
	}
</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script>
	
let copiedBtn = [];
let copiedBtn_f = [];
let copiedBtn_f_na =[];
let cutedBtn = [];
let cutedBtn_f = [];
let cutedBtn_f_na =[];
function onCopyBtnClick() {
	const arr_cp = Array.from(document.querySelectorAll('.btnCopy'));
	copiedBtn = [];
	for(let i = 0; i < arr_cp.length; i++) {
		const btn = arr_cp[i];
		if (btn.classList.contains('copy-active')) {
			copiedBtn.push(btn.getAttribute('value').substr(1));
		}
	}
	document.getElementById("selected_files").setAttribute('value',copiedBtn.length );
	localStorage.setItem('copy', JSON.stringify(copiedBtn));

	const raw = localStorage.getItem('copy');
	const array_test=JSON.parse(raw);
	//console.log(array_test);
}
function onCopy_f_BtnClick(){

	const arr_cp_f =Array.from(document.querySelectorAll('.btnCopy_f'));
	copiedBtn_f=[];
	copiedBtn_f_na=[];
	
	for(let i = 0; i < arr_cp_f.length; i++) {
		const btn = arr_cp_f[i];
		if (btn.classList.contains('copy-active')) {
			var $dir_temp = btn.getAttribute('value');
			var $folder_name_pos = $dir_temp.lastIndexOf("%2F");
			if ($folder_name_pos == -1) {
				$folder_name_pos = 1;
			}
			else{
				$folder_name_pos = $folder_name_pos + 3;
			}
			var $folder_name = $dir_temp.substr($folder_name_pos);
			copiedBtn_f.push(btn.getAttribute('value').substr(1));
			copiedBtn_f_na.push($folder_name);
		}
	}
	document.getElementById("selected_folders").setAttribute('value',copiedBtn_f.length);
	localStorage.setItem('copy_f', JSON.stringify(copiedBtn_f));
	localStorage.setItem('copy_f_na', JSON.stringify(copiedBtn_f_na));
}
function onCutBtnClick() {
	const arr_cp = Array.from(document.querySelectorAll('.btnCopy'));
	copiedBtn = [];
	for(let i = 0; i < arr_cp.length; i++) {
		const btn = arr_cp[i];
		if (btn.classList.contains('copy-active')) {
			copiedBtn.push(btn.getAttribute('value').substr(1));
		}
	}
	document.getElementById("selected_files").setAttribute('value',copiedBtn.length);
	localStorage.setItem('copy', JSON.stringify(copiedBtn));

	const arr_ct = Array.from(document.querySelectorAll('.btnCut'));
	cutedBtn = [];
	for(let i = 0; i < arr_ct.length; i++) {
		const btn = arr_ct[i];
		if (btn.classList.contains('cut-active')) {
			cutedBtn.push(btn.getAttribute('value'));
		}
	}
	localStorage.setItem('cut', JSON.stringify(cutedBtn))

	const raw = localStorage.getItem('cut');
	const array_2=JSON.parse(raw);
	//console.log(array_2);
}
function onCut_f_BtnClick(){
	const arr_cp_f =Array.from(document.querySelectorAll('.btnCopy_f'));
	copiedBtn_f=[];
	copiedBtn_f_na=[];
	
	for(let i = 0; i < arr_cp_f.length; i++) {
		const btn = arr_cp_f[i];
		if (btn.classList.contains('copy-active')) {
			var $dir_temp = btn.getAttribute('value');
			var $folder_name_pos = $dir_temp.lastIndexOf("%2F");
			if ($folder_name_pos == -1) {
				$folder_name_pos = 1;
			}
			else{
				$folder_name_pos = $folder_name_pos + 3;
			}
			var $folder_name = $dir_temp.substr($folder_name_pos);
			copiedBtn_f.push(btn.getAttribute('value').substr(1));
			copiedBtn_f_na.push($folder_name);
		}
	}
	document.getElementById("selected_folders").setAttribute('value',copiedBtn_f.length );
	localStorage.setItem('copy_f', JSON.stringify(copiedBtn_f));
	localStorage.setItem('copy_f_na', JSON.stringify(copiedBtn_f_na));

	const arr_ct_f =Array.from(document.querySelectorAll('.btnCut_f'));
	cutedBtn_f = [];
	cutedBtn_f_na =[];
	for(let i = 0; i < arr_ct_f.length; i++) {
		const btn = arr_ct_f[i];
		if (btn.classList.contains('cut-active')) {
			var $dir_temp = btn.getAttribute('value');
			var $folder_name_pos = $dir_temp.lastIndexOf("%2F");
			if ($folder_name_pos == -1) {
				$folder_name_pos = 1;
			}
			else{
				$folder_name_pos = $folder_name_pos + 3;
			}
			var $folder_name = $dir_temp.substr($folder_name_pos);
			cutedBtn_f.push(btn.getAttribute('value').substr(1));
			cutedBtn_f_na.push($folder_name);
		}
	}
	localStorage.setItem('cut_f', JSON.stringify(cutedBtn_f));
	localStorage.setItem('cut_f_na', JSON.stringify(cutedBtn_f_na));
}
(function($){
	$.fn.tablesorter = function() {
		var $table = this;
		this.find('th').click(function() {
			var idx = $(this).index();
			var direction = $(this).hasClass('sort_asc');
			$table.tablesortby(idx,direction);
		});
		return this;
	};
	$.fn.tablesortby = function(idx,direction) {
		var $rows = this.find('tbody tr');
		function elementToVal(a) {
			var $a_elem = $(a).find('td:nth-child('+(idx+1)+')');
			var a_val = $a_elem.attr('data-sort') || $a_elem.text();
			return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
		}
		$rows.sort(function(a,b){
			var a_val = elementToVal(a), b_val = elementToVal(b);
			return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
		})
		this.find('th').removeClass('sort_asc sort_desc');
		$(this).find('thead th:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
		for(var i =0;i<$rows.length;i++)
			this.append($rows[i]);
		this.settablesortmarkers();
		return this;
	}
	$.fn.retablesort = function() {
		var $e = this.find('thead th.sort_asc, thead th.sort_desc');
		if($e.length)
			this.tablesortby($e.index(), $e.hasClass('sort_desc') );

		return this;
	}
	$.fn.settablesortmarkers = function() {
		this.find('thead th span.indicator').remove();
		this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
		this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
		return this;
	}
})(jQuery);
	$(function(){
	var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)')||0)[2];
	var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;
	var $tbody = $('#list');
	$(window).on('hashchange',list).trigger('hashchange');
	$('#table').tablesorter();

	$('#table').on('click','.delete',function(data) {
		$.post("",{'do':'delete',file:$(this).attr('data-file'),xsrf:XSRF},function(response){
			list();
		},'json');
		return false;
	});                                                     

	$('#mkdir').submit(function(e) {
		var hashval = decodeURIComponent(window.location.hash.substr(1)),
			$dir = $(this).find('[name=name]');
		e.preventDefault();
		$dir.val().length && $.post('?',{'do':'mkdir',name:$dir.val(),xsrf:XSRF,file:hashval},function(data){
			list();
		},'json');
		$dir.val('');
		return false;
	});
	$('#create_file').submit(function(e){
		var hashval = decodeURIComponent(window.location.hash.substr(1)),
			$dir = $(this).find('[name=name2]');
			$f_data =$(this).find('[name=data]');
		e.preventDefault();
		$.post('?',{'do':'fopen',name:$dir.val(),xsrf:XSRF,file:hashval,fdata:$f_data.val()},function(data){
			list();
		},'json');
		$dir.val('');
		$f_data.val('');                              
		return false;
	});
	$('#selecting_files').submit(function(e){
		var hashval = decodeURIComponent(window.location.hash.substr(1));
		$array_list = JSON.stringify(copiedBtn);
		$array_list_f= JSON.stringify(copiedBtn_f);
		$array_list_f_n=JSON.stringify(copiedBtn_f_na);

		$array_list_2 = JSON.stringify(cutedBtn);
		$array_list_f_2= JSON.stringify(cutedBtn_f);
		$array_list_f_n_2=JSON.stringify(cutedBtn_f_na);

		e.preventDefault();
		$.post('?',{'do':'copy',xsrf:XSRF, dir:hashval, array:$array_list, array_f:$array_list_f, array_f_n:$array_list_f_n, array_2:$array_list_2, array_f_2:$array_list_f_2, array_f_n_2:$array_list_f_n_2},function(data){
			list();
		},'json');
		return false;
	});
<?php if($allow_upload): ?>
	// file upload stuff
	$('#file_drop_target').on('dragover',function(){
		$(this).addClass('drag_over');
		return false;
	}).on('dragend',function(){
		$(this).removeClass('drag_over');
		return false;
	}).on('drop',function(e){
		e.preventDefault();
		var files = e.originalEvent.dataTransfer.files;
		$.each(files,function(k,file) {
			uploadFile(file);
		});
		$(this).removeClass('drag_over');
	});
	$('input[type=file]').change(function(e) {
		e.preventDefault();
		$.each(this.files,function(k,file) {
			uploadFile(file);
		});
	});


	function uploadFile(file) {
		var folder = decodeURIComponent(window.location.hash.substr(1));

		if(file.size > MAX_UPLOAD_SIZE) {
			var $error_row = renderFileSizeErrorRow(file,folder);
			$('#upload_progress').append($error_row);
			window.setTimeout(function(){$error_row.fadeOut();},5000);
			return false;
		}

		var $row = renderFileUploadRow(file,folder);
		$('#upload_progress').append($row);
		var fd = new FormData();
		fd.append('file_data',file);
		fd.append('file',folder);
		fd.append('xsrf',XSRF);
		fd.append('do','upload');
		var xhr = new XMLHttpRequest();
		xhr.open('POST', '?');
		xhr.onload = function() {
			$row.remove();
    		list();
  		};
		xhr.upload.onprogress = function(e){
			if(e.lengthComputable) {
				$row.find('.progress').css('width',(e.loaded/e.total*100 | 0)+'%' );
			}
		};
	    xhr.send(fd);
	}
	function renderFileUploadRow(file,folder) {
		return $row = $('<div/>')
			.append( $('<span class="fileuploadname" />').text( (folder ? folder+'/':'')+file.name))
			.append( $('<div class="progress_track"><div class="progress"></div></div>')  )
			.append( $('<span class="size" />').text(formatFileSize(file.size)) )
	};
	function renderFileSizeErrorRow(file,folder) {
		return $row = $('<div class="error" />')
			.append( $('<span class="fileuploadname" />').text( 'Error: ' + (folder ? folder+'/':'')+file.name))
			.append( $('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>'
				+' exceeds max upload size of <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>')  );
	}
<?php endif; ?>
	function list() {
		var hashval = window.location.hash.substr(1);
		$.get('?do=list&file='+ hashval,function(data) {
			$tbody.empty();
			$('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
			if(data.success) {
				$.each(data.results,function(k,v){
					$tbody.append(renderFileRow(v));
				});
				!data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td></tr>')
				data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
			} else {
				console.warn(data.error.msg);
			}
			$('#table').retablesort();
		},'json');
	}

	

	function renderFileRow(data) {
		var $dir_temp =  data.is_dir ? '#' + encodeURIComponent(data.path) : './'+ encodeURIComponent(data.path).replaceAll("%2F","/");
		var $link = $('<a class="name" />')
			.attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './'+ encodeURIComponent(data.path).replaceAll("%2F","/"))
			.text(data.name);
		var allow_direct_link = <?php echo $allow_direct_link?'true':'false'; ?>;
        	if (!data.is_dir && !allow_direct_link)  $link.css('pointer-events','none');
		var $dl_link = $('<a/>').attr('href','?do=download&file='+ encodeURIComponent(data.path))
			.addClass('download').text('Download');
		var $delete_link = $('<button href="#" />').attr('data-file',data.path).addClass('show').addClass('delete').text('Cofirm');
		var $copy_file = $(`<button class="btnCopy" value = "${$dir_temp}">Copy</ button>`);
		var $copy_folder = $(`<button class="btnCopy_f" value = "${$dir_temp}">Copy</ button>`);
		var $cut_file = $(`<button class="btnCut" value = "${$dir_temp}">Cut</ button>`);
		var $cut_folder = $(`<button class="btnCut_f" value = "${$dir_temp}">Cut</ button>`);
		var $delete_btn = $('<button href="#" />').addClass('delete_btn').text('Delete');
		var $no_btn= $('<button href="#" />').addClass('no_btn').addClass('show').text('Cancel');
		$no_btn.on('click',function(){
			$delete_btn.toggleClass('show');
			$no_btn.toggleClass('show');
			$delete_link.toggleClass('show');
			$cut_file.toggleClass('show');
			$cut_folder.toggleClass('show');
			$copy_file.toggleClass('show');
			$copy_folder.toggleClass('show');
		});
		$delete_btn.on('click', function() {
			$delete_btn.toggleClass('show');
			$no_btn.toggleClass('show');
			$delete_link.toggleClass('show');
			$cut_file.toggleClass('show');
			$cut_folder.toggleClass('show');
			$copy_file.toggleClass('show');
			$copy_folder.toggleClass('show');
		});
		$copy_file.on('click', function() {
			console.log($dir_temp);
			$copy_file.toggleClass('copy-active');
			$cut_file.toggleClass('hidden');
			$delete_btn.toggleClass('hidden');
			onCopyBtnClick();
		});
		$copy_folder.on('click', function() {
			console.log($dir_temp);
			$copy_folder.toggleClass('copy-active');
			$cut_folder.toggleClass('hidden');
			$delete_btn.toggleClass('hidden');
			onCopy_f_BtnClick();
		});
		$cut_file.on('click', function() {
			console.log($dir_temp);
			$cut_file.toggleClass('cut-active');
			$copy_file.toggleClass('copy-active');
			$copy_file.toggleClass('hidden');
			$delete_btn.toggleClass('hidden');
			onCutBtnClick();
		});
		$cut_folder.on('click', function() {
			console.log($dir_temp);
			$cut_folder.toggleClass('cut-active');
			$copy_folder.toggleClass('copy-active');
			$copy_folder.toggleClass('hidden');
			$delete_btn.toggleClass('hidden');
			onCut_f_BtnClick();
		});
		
		
		var perms = [];
		if(data.is_readable) perms.push('read');
		if(data.is_writable) perms.push('write');
		if(data.is_executable) perms.push('exec');
		if (data.is_dir){
			var $html = $('<tr  id ="list_tr"/>')
			.addClass(data.is_dir ? 'is_dir' : '')
			.append( $('<td class="first" />').append($link) )
			.append( $('<td class ="second"/>').attr('data-sort',data.is_dir ? -1 : data.size)
				.html($('<span class="size" />').text(formatFileSize(data.size))) )
			.append( $('<td class ="third"/>').attr('data-sort',data.mtime).text(formatTimestamp(data.mtime)) )
			.append( $('<td class ="forth"/>').text(perms.join(' & ')) )
			.append( $('<td class ="fifth"/>').append($no_btn).append($delete_btn).append( data.is_deleteable ? $delete_link : '').append($copy_folder).append($cut_folder) )
			.append( $('<td class ="sixth"/>').append($dl_link.attr('style','visibility: hidden;')) )
			return $html;
		}
		else{
			var $html = $('<tr  id ="list_tr"/>')
			.addClass(data.is_dir ? 'is_dir' : '')
			.append( $('<td class="first" />').append($link) )
			.append( $('<td class ="second"/>').attr('data-sort',data.is_dir ? -1 : data.size)
				.html($('<span class="size" />').text(formatFileSize(data.size))) )
			.append( $('<td class ="third"/>').attr('data-sort',data.mtime).text(formatTimestamp(data.mtime)) )
			.append( $('<td class ="forth"/>').text(perms.join(' & ')) )
			.append( $('<td class ="fifth"/>').append($no_btn).append($delete_btn).append( data.is_deleteable ? $delete_link : '').append($copy_file).append($cut_file) )
			.append( $('<td class ="sixth"/>').append($dl_link))
			return $html;
		}
	}
	function renderBreadcrumbs(path) {
		var base = "",
			$html = $('<div/>').append( $('<a href="#" >Home</a></div>') );
		$.each(path.split('%2F'),function(k,v){
			if(v) {
				var v_as_text = decodeURIComponent(v);
				// ▸
				$html.append( $('<span/>').text(' ⇣ ') )
					.append($('<div id="breadcrumb_a"/>').append( $('<a/>').attr('href','#'+base+v).text(v_as_text) ));
				base += v + '%2F';
			}
		});
		return $html;
	}
	function formatTimestamp(unix_timestamp) {
		var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		var d = new Date(unix_timestamp*1000);
		return [m[d.getMonth()],' ',d.getDate(),', ',d.getFullYear()," ",
			(d.getHours() % 12 || 12),":",(d.getMinutes() < 10 ? '0' : '')+d.getMinutes(),
			" ",d.getHours() >= 12 ? 'PM' : 'AM'].join('');
	}
	function formatFileSize(bytes) {
		var s = ['bytes', 'KB','MB','GB','TB','PB','EB'];
		for(var pos = 0;bytes >= 1000; pos++,bytes /= 1024);
		var d = Math.round(bytes*10);
		return pos ? [parseInt(d/10),".",d%10," ",s[pos]].join('') : bytes + ' bytes';
	}
})

</script>
</head>
<body>
<div id="all">
	<div id="top">
		<div id="top_left">
			<?php if($allow_upload): ?>
			<div id="upload_bar">
				<div>
					<label for="upload_btn">Click to Browse</label>
					<input type="file" multiple id="upload_btn"/>
				</div>
				<div id="file_drop_target">
					Drag Files Here To Upload
				</div>
				<div id="upload_progress" style="display:none;"></div>
			</div>
			<?php endif; ?>
			<?php if($allow_create_folder): ?>
					<form action="?" method="post" id="mkdir" >
						<div id="create_bar">
							<div>
								<label for=dirname>Create New Folder</label>
								<input id=dirname type=text name=name value="" />
							</div>
							<div>
								<input type="submit" value="Create" />
							</div>
						</div>
					</form>
			<?php endif; ?>
			<div id="mod_bar">
				<form action = "?" method="post" id="selecting_files" >
						<div>
							<label for="copy">Selected files</label>
							<input type="text" id="selected_files" cols="1" rows="1" ></input>
						</div>
						<div>
							<input type="submit" value="Paste" />
						</div>
						<div>
							<label for="copy">Selected folders</label>
							<input type="text" id="selected_folders" cols="1" rows="1" ></input>
						</div>	
				</form>
			</div>
		</div>
		<div id="top_right">
			<?php if ($allow_create_file): ?>
				<form action = "?" method="post" id="create_file" >
					<div>
						<label for="fopen">Create New File</label>
						<input id=fopen_name type=text name=name2 value="" />
						<input type="submit" id ="create_file_btn" value="Create" />
					</div>
					<div>
					<label for="fopen">File's Input Data</label>
					</div>
					<div>
						<textarea name=data id=fopen_data cols="30" rows="5"></textarea>
					</div>
				</form>
			<?php endif; ?>	
		</div>	
	</div>
	
	<div id="bottom">
		<div class="left">
			<div id="breadcrumb">&nbsp;</div>
		</div>
		<div class="right">
			<table id="title">
			<tr >
				<th>Name</th>
				<th>Size</th>
				<th>Modified</th>
				<th>Permissions</th>
				<th>Actions</th>
				<th>Links</th>
			</tr>
			</table>
			<table id="table"><thead></thead><tbody id="list">
			</tbody></table>
		</div>
	</div>
</div>
	
</body>
</html>
