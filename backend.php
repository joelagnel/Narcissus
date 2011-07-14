<?
/* Narcissus - Online image builder for the angstrom distribution
 * Koen Kooi (c) 2008-2010 - all rights reserved 
 *
 */


$base_pkg_set = " task-base angstrom-version ";
$debug_log = "out.log";
$web_log = "web.log";

/* Some wrappers to send command output and server output to a log file (useful for debug) */

function echo_msg($msg) {
	global $web_log;
	print $msg;
	$fh = fopen($web_log, 'a');
	fwrite($fh, "--------------------------\n");
	fwrite($fh, $msg."\n");
	fwrite($fh, "--------------------------\n");
	fclose($fh);
}

function passthro($cmd, &$retval) {
	global $debug_log;
	exec($cmd, $out, $retval);
	$out_str = join($out, '');
	print($out_str);
	$fh = fopen($debug_log, 'a');
	fwrite($fh, "--------------------------\n");
	fwrite($fh, $cmd."\n");
	fwrite($fh, "--------------------------\n");
	fwrite($fh, join($out,"\n")."\n");
	fwrite($fh, "--------------------------\n");
	fclose($fh);
}

if (isset($_POST["action"]) && $_POST["action"] != "") {
	$action = $_POST["action"];
} else {
	echo_msg("Invalid action: $action");
	exit;
}

if (isset($_POST["machine"])) {
	$machine = escapeshellcmd(basename($_POST["machine"]));
} else {
	echo_msg("Invalid machine");
	exit;
}

if (isset($_POST["name"]) && $_POST["name"] != "") {
	$name = escapeshellcmd(basename($_POST["name"]));
} else {
	$name = "unnamed";
}

if (isset($_POST["pkgs"]) && $_POST["pkgs"] != "") {
	$pkg = $_POST["pkgs"];
} else {
	$pkg = "task-boot";
}

if (isset($_POST["release"]) && $_POST["release"] != "") {
	$release = $_POST["release"];
} else {
	$release = "stable";
}

if (isset($_POST["imagetype"]) && $_POST["imagetype"] != "") {
	$imagetype = $_POST["imagetype"];
} else {
	$imagetype = "tgz";
	$imagesuffix = "tar.gz";
}

if (isset($_POST["manifest"]) && $_POST["manifest"] != "") {
	$manifest = $_POST["manifest"];
} else {
	$manifest = "no";
}

if (isset($_POST["sdk"]) && $_POST["sdk"] != "") {
	$sdk = $_POST["sdk"];
} else {
	$sdk = "no";
}

if (isset($_POST["sdkarch"]) && $_POST["sdkarch"] != "") {
	$sdkarch = $_POST["sdkarch"];
} else {
	$sdkarch = "none";
}

switch($imagetype) {
	case "tgz":
		$imagesuffix = "tar.gz";
		break;
	case "sdimg":
		$imagesuffix = "tar.gz";
		break;
	case "ubifs":
		$imagesuffix = "ubi";
		break;
	default:
		$imagesuffix = $imagetype;
}

switch($action) {
	case "assemble_image":
		echo_msg("assembling\n");
		assemble_image($machine, $name, $imagetype, $manifest, $sdk, $sdkarch);
		break;
	case "configure_image":
		echo_msg("configuring\n");
		configure_image($machine, $name, $release);
		break;
	case "show_image_link":
		show_image_link($machine, $name, $imagesuffix, $manifest, $sdk, $sdkarch);
		break;
	case "install_package":
		echo_msg("installing $pkg\n");
		install_package($machine, $name, $pkg);
		break;
}

function show_image_link($machine, $name, $imagesuffix, $manifest, $sdk, $sdkarch) {
	$foundimage = 0;
	$foundsdimage = 0;
	$foundsdk = 0;
	$foundsources = 0;
	$printedcacheinfo = 0;
	$printstring = "";

	switch($sdkarch) {
			case "intel32":
				$sdkarchname = "i686";
				break;
			case "intel64":
				$sdkarchname = "x86_64";
				break;
			default:
				$sdkarchname = "i686";
				break;
	}
	
	$randomname = substr(md5(time()), 0, 6);
	$deploydir = "deploy/$machine/$randomname";
	mkdir($deploydir,0777,TRUE);
	
	$imagefiles = scandir("work/$machine");
	echo_msg("<br/>");
	foreach($imagefiles as $value) {
		$location = "work/$machine/$value";
		// The !== operator must be used.  Using != would not work as expected
		// because the position of 'a' is 0. The statement (0 != false) evaluates 
		// to false.
		if(strpos($value, "$name-image-$machine-sd") !== false) {
			rename($location, "$deploydir/$value");
			$imgsize = round(filesize("$deploydir/$value") / (1024 * 1024),2);
			$printstring .= "<a href='$deploydir/$value'>$value</a> [$imgsize MiB]<br/> "; 
			$foundsdimage = 1;
			continue;
		}
		if(strpos($value, "$name-image-$machine.$imagesuffix") !== false) {
			rename($location, "$deploydir/$value");
			$imgsize = round(filesize("$deploydir/$value") / (1024 * 1024),2);
			$imagestring .= "<br/><a href='$deploydir/$value'>$value</a> [$imgsize MiB]: This is the rootfs '$name' for $machine you just built. This will get automatically deleted after 2 days.<br/>";

			rename("work/$machine/$name-image.bb", "$deploydir/$name-image.bb");
			rename("work/$machine/$name-image.txt", "$deploydir/$name-image.txt");

			if($manifest == "yes") {
				rename("work/$machine/$name-image-manifest.html", "$deploydir/$name-image-manifest.html");
				$imagestring .= "You can also have a look at the software <a href='$deploydir/$name-image-manifest.html' target='manifest'>manifest</a> for this rootfs<br/>";
			}
			$foundimage = 1;
		}
		//Angstrom-2010.05-narcissus-hawkboard-i686-random-d4ddcec6-image-toolchain.tar.gz
		if(strpos($value, "narcissus-$machine-$sdkarchname-$name-image-$sdk") !== false) {
			rename($location, "$deploydir/$value");
			$sdksize = round(filesize("$deploydir/$value") / (1024 * 1024),2);
			$imagestring .= "<br/><a href='$deploydir/$value'>$value</a> [$sdksize MiB]: $sdk for the generated rootfs.<br/>";
			$foundsdk = 1;
		}
		//random-ed560fe9-image-sources/
		if(strpos($value, "$name-image-sources") !== false) {
			rename($location, "$deploydir/sources");
			$foundsources = 1;
		}
}	
	
	if ($foundimage == 0) {
		echo_msg("Image not found, something went wrong :/");
	} else {
		echo_msg("$imagestring");
	}
	
	if($foundsdimage == 1) {
		echo_msg(" <br/><br/> The raw SD card image(s) below have the intended size for the SD card is encoded in the file name, e.g. 1GiB for a one gigabyte card.<br/><br/> $echo_msgstring");
	}
	
}


function configure_image($machine, $name, $release) {
	echo_msg("Machine: $machine, name: $name\n");
	passthro ("scripts/configure-image.sh $machine $name-image $release && exit");
}

function install_package($machine, $name, $pkg) {
	echo_msg("Machine: $machine, name: $name, pkg: $pkg\n");
	passthro ("scripts/install-package.sh $machine $name-image $pkg && exit", $installretval);
	echo_msg("<div id=\"retval\">$installretval</div>");
}

function assemble_image($machine, $name, $imagetype, $manifest, $sdk, $sdkarch) {
	echo_msg("Machine: $machine, name: $name, type: $imagetype\n");
	passthro ("scripts/assemble-image.sh $machine $name-image $imagetype $manifest $sdk $sdkarch && exit", $installretval);
	echo_msg("<div id=\"retval-image\">$installretval</div>");
}



?>
