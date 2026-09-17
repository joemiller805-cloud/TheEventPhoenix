<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<link type="text/css" rel="stylesheet" 
		href="https://source.zoom.us/1.9.1/css/bootstrap.css" />
	<link type="text/css" rel="stylesheet" 
		href="https://source.zoom.us/1.9.1/css/react-select.css" />
</head>

<body>
		<script src="/js/jquery.js"></script>
		<!-- import ZoomMtg dependencies -->
	    <script src="https://source.zoom.us/1.9.1/lib/vendor/react.min.js"></script>
	    <script src="https://source.zoom.us/1.9.1/lib/vendor/react-dom.min.js"></script>
	    <script src="https://source.zoom.us/1.9.1/lib/vendor/redux.min.js"></script>
	    <script src="https://source.zoom.us/1.9.1/lib/vendor/redux-thunk.min.js"></script>
	    <script src="https://source.zoom.us/1.9.1/lib/vendor/lodash.min.js"></script>
	    <!-- import ZoomMtg -->
	    <script src="https://source.zoom.us/zoom-meeting-1.9.1.min.js"></script>
</body>

<script type="text/javascript"> 
	ZoomMtg.setZoomJSLib('https://dmogdx0jrul3u.cloudfront.net/1.9.1/lib', '/av');
	ZoomMtg.preLoadWasm();
	ZoomMtg.prepareJssdk();

	var mtgid = new URL(location).searchParams.get('mtgid');
	var mtgpass = new URL(location).searchParams.get('mtgpass');
	var username = new URL(location).searchParams.get('name');
	var email = new URL(location).searchParams.get('email');

	const meetConfig = {
		"apiKey": 'Yp7WjcuWSA69gjQj-dEcuA',
		"meeting_number": mtgid,
		"leaveUrl": 'https://www.easyregpro.com', // Production leave URL hostname — not display branding
		"userName": username,
		"userEmail": email,
		"passWord": mtgpass,
		"role": 0 // 1 for host; 0 for attendee
	};

	$.post("/zoomSignature.php", meetConfig, function(signature){
		ZoomMtg.init({
			"leaveUrl": meetConfig.leaveUrl,
			"isSupportAV": true,
			"success": function() {
				ZoomMtg.join({
					"signature": signature,
					"apiKey": meetConfig.apiKey,
					"meetingNumber": meetConfig.meeting_number,
					"userName": meetConfig.userName,
					"userEmail": meetConfig.userEmail,
					"passWord": meetConfig.passWord, 
					"error": function(res) { 
						console.log(res);
					}
				});		
			}
		});
	});
</script>
</html>