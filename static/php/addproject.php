<?php

header('Content-type: application/json');

$response_array['status'] = 'success';

$file = "addproject_log.txt";

$logfile = "";
$logfile .= print_r($_POST, true);
$logfile .= print_r($_FILES, true);


function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function error($msg, $path) {
    global $file;
    $response_array['status'] = $msg;
    // Write the contents back to the file
    file_put_contents($file, $msg);
    echo json_encode($response_array);
    exit();
}

function error_del($msg, $path) {
    array_map('unlink', glob("$path/*"));
    rmdir($path);
    error($msg, $path);
}

function uploadFile($file, $fullpath, $allowed) {
    global $logfile;

    $target_dir = "$fullpath";
    $target_file = $target_dir . "/" . basename($file["name"]) ;
    $uploadOk = 1;
    $fileType = pathinfo($target_file,PATHINFO_EXTENSION);

    // Check if file already exists
    if (file_exists($target_file)) {
        error_del("Sorry, file $target_file already exists.", $fullpath);
        $uploadOk = 0;
    }

    // Check file size (20Mb maximum)
    if ($file["size"] > 20000000) {
        error_del("Sorry, your file is too large (> 20Mb).", $fullpath);
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($fileType, $allowed)) {
        $msg = "Sorry only ";
        $total = count($allowed);
        $idx = 1;
        foreach ($allowed as $allow) {
           if ($idx == $total && $idx != 1) {
            $msg .= "or " . $allow . " ";
           } else if ($idx == $total && $idx == 1) {
            $msg .= $allow . " ";
           }
           else {
            $msg .= $allow . ", ";
           }
           $idx = $idx + 1;
        }
        if (in_array("jpg", $allowed)) {
            error_del($msg . "files are allowed for Project Thumbnail.", $fullpath);
        } else {
            error_del($msg . "files are allowed for Project File.", $fullpath);
        }
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        error_del("Sorry, your file was not uploaded.", $fullpath);
    // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            $logfile .= "The file ". basename( $file["name"]). " has been uploaded.";
        } else {
            error_del("Sorry, there was an error uploading your file.", $fullpath);
        }
    }
}

$projectname = $creator = $description = $path = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$projectname = test_input($_POST["projectname"]); // required
	$projectname_id = strtolower($projectname);
	$projectname_id = str_replace(' ', '-', $projectname_id);
	$creator = test_input($_POST["creator"]); // required
	$description = test_input($_POST["description"]); // required
	$path = test_input($_POST["path"]); // required
}

// Expect:
// club-website/build/static/php/ as CWD

$fullpath = "../../../content$path$projectname_id";

if (file_exists($fullpath)) {
    error("The project $projectname already exists. Please use a different project name.", $fullpath);
}


if (!mkdir($fullpath, 0777, true)) {
    error("Error creating $fullpath on server.", $fullpath);
}

if (!($content_file = fopen("$fullpath/contents.lr", "w"))) {
    error("Error creating $content_file on server.", $fullpath);
}

$contents_txt = "name: $projectname\n---\nauthor: $creator\n---\ndescription:\n\n$description\n";
if (!fwrite($content_file, $contents_txt)) {
    error("Error writing to $content_file!", $fullpath);
}

fclose($content_file);


uploadFile($_FILES["thumbnail"], $fullpath, array("jpg", "png", "jpeg"));
uploadFile($_FILES["projectfile"], $fullpath, array("sb", "sb2"));

$cmd = "cd ../../../ && PATH=\$PATH:/usr/bin:/usr/local/node/bin lektor build -O build --no-prune";
exec($cmd, $output);
$logfile .= print_r($output, true);

// Write the contents back to the file
file_put_contents($file, $logfile);

echo json_encode($response_array);
?>

