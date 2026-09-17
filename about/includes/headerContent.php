<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="img/about.css" rel="stylesheet">
<title>The Event Phoenix Event Management Software</title>
<?php
	$root = $_SERVER['DOCUMENT_ROOT'];
	include($root."/common_functions.php");
	include($root."/commonStyles.php");
	include($root."/commonJs.php");
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Arbutus+Slab|Copse|Old+Standard+TT|Scheherazade|Suranna|Trocchi&display=swap" rel="stylesheet">
<script>
	$(document).ready(function(){
		let loc = location.pathname.replace('/about/','').replace('.php','');
		$('.navbar-nav').find(`a[href*="${loc}"]`).closest('li').addClass('active');
	});
</script>