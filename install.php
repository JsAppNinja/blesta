<?php
// Load the core config
$init = dirname(__FILE__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "init.php";
include $init;

$error = null;
if (defined("HTACCESS") && HTACCESS) {
	// Error, "HTACCESS" is expected, but mod rewrite is not working
	
	$error = "Mod rewrite is not enabled, or htaccess is not supported by this server.
	You must disable pretty URL support in Blesta by removing the .htaccess file.";
}
else {
	header("Location: index.php/install/");
	exit();
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Install Error</title>
		<link href="/app/views/admin/default/css/admin_login/all.css" rel="stylesheet" type="text/css" media="all" />
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	</head>
	<body>
		<div class="login-box">
			<h2>Error</h2>
	
			<div class="box">
				<div class="t"></div>
				<div class="c">
					<?php echo $error;?>
				</div>
				<div class="b"></div>
			</div>
		</div>
	</body>
</html>