<?php
	include_once __DIR__ . '/config/bootstrap.php'; // Secure session; do not copy ?accountid= over a login
	$tepJsonHex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; // Block </script> breakout in JSON
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-FBGZ4T6LJK"></script>
<script>
	if(window.location.href.includes('/easyregpro.com/')){ // Production hostname detector — not display branding
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', 'G-FBGZ4T6LJK');
	}
</script>
<script type="text/javascript">
	window.erCsrfToken = <?= json_encode((string)($_SESSION['csrf_token'] ?? ''), $tepJsonHex) ?>; // HEX flags on token literal
	window.erGetCsrfToken = function(){
		return window.erCsrfToken || (window.erSessionData && window.erSessionData.csrf_token) || '';
	};
	window.erPostRedirect = function(url, data){
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = url;
		const postData = Object.assign({}, data || {}, {csrf_token: window.erGetCsrfToken()});
		Object.keys(postData).forEach(function(key){
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = key;
			input.value = postData[key];
			form.appendChild(input);
		});
		document.body.appendChild(form);
		form.submit();
	};
	window.erLogout = function(redirectPath){
		const data = redirectPath ? {"r": redirectPath} : {};
		window.erPostRedirect('/logout.php', data);
	};
	if(window.location.href.indexOf('/events/') > 0 || window.location.href.indexOf('/account/') > 0 ){
		if(!window.location.href.includes('invoice.php') && <?= json_encode((string)($_SESSION['accountid'] ?? ''), $tepJsonHex) ?> != <?= json_encode((string)($_SESSION['useraccount'] ?? ''), $tepJsonHex) ?>){ // Encoded session ids
			window.erLogout();
		}
	}
	<?php
	$tepSessionClient = $_SESSION; // Copy for the AngularJS bootstrap bag
	unset($tepSessionClient['pass'], $tepSessionClient['password']); // Never ship credential columns
	?>
	let erSessionData = <?= json_encode($tepSessionClient, $tepJsonHex) ?>; // HEX flags so session strings cannot break the script tag
	erSessionData.csrf_token = erSessionData.csrf_token || window.erCsrfToken;
	erSessionData.sponsors_enabled = erSessionData.sponsors_enabled == 'true';
</script>
<script src="/js/jquery.js"></script>
<script src="/js/bootstrap/bootstrap.bundle.min.js"></script>
<!-- <script src="/js/bootstrap.min.js"></script> -->
<script src="/js/jquery-ui.js"></script>
<script src="/js/common_functions.js?_=<?= rand() ?>"></script>
<script src="/js/angular1_7_2.js"></script>
<script src="/js/dataAccess.js?_=<?= rand() ?>"></script>
<script src="/js/easyRegService.js?_=<?= rand() ?>"></script>
<script src="/directives/gridWidget.js?_=<?= rand() ?>"></script>
<script src="/js/dateService.js?_=<?= rand() ?>"></script>
<script src="/js/alertService.js?_=<?= rand() ?>"></script>
<script src="/controllers/navController.js?_=<?= rand() ?>"></script>
