<?php 
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

// ==== AWS CONFIG ====
$awsRegion = "ap-southeast-1"; 
$awsKey    = "YOUR_AWS_ACCESS_KEY";
$awsSecret = "YOUR_AWS_SECRET_KEY";
$bucket    = "sadhabucket1";
$cloudFrontDomain = "https://d1phdi9e99sb96.cloudfront.net";

// ==== RDS CONFIG ====
$dbHost = "database-1.ch2s2qo0652t.ap-southeast-1.rds.amazonaws.com";
$dbPort = 3306;
$dbName = "facebook";
$dbUser = "root";
$dbPass = "Rohan1234";

// ==== FORM DATA ====
$name  = $_POST['name'];
$email = $_POST['email'];
$image = $_FILES['profile_image'];

// ==== UPLOAD IMAGE TO S3 ====
try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $awsRegion,
        'credentials' => [
            'key'    => $awsKey,
            'secret' => $awsSecret,
        ],
    ]);

    $fileKey = "uploads/" . time() . "_" . basename($image['name']);

    $result = $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $fileKey,
        'SourceFile' => $image['tmp_name'],
        'ACL'    => 'private' // 👈 keep private for CloudFront
    ]);

} catch (AwsException $e) {
    die("S3 Upload Error: " . $e->getMessage());
}

// ==== SAVE TO RDS ====
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, image_key) VALUES (:name, :email, :image_key)");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'image_key' => $fileKey
    ]);

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// ==== GENERATE CLOUDFRONT SIGNED URL ====
try {
    $cloudFront = new CloudFrontClient([
        'version' => 'latest',
        'region'  => $awsRegion,
        'credentials' => [
            'key'    => $awsKey,
            'secret' => $awsSecret,
        ],
    ]);

    $expires = time() + 600; // 10 minutes
    $resource = $cloudFrontDomain . "/" . $fileKey;

    $signedUrl = $cloudFront->getSignedUrl([
        'url' => $resource,
        'expires' => $expires,
        'key_pair_id' => "YOUR_CLOUDFRONT_KEY_PAIR_ID", // 👈 must replace
        'private_key' => "/absolute/path/to/private_key.pem" // 👈 must replace
    ]);

} catch (AwsException $e) {
    die("CloudFront Error: " . $e->getMessage());
}

// ==== OUTPUT ====
echo "<h2>Registration Successful!</h2>";
echo "Name: $name <br>";
echo "Email: $email <br>";
echo "Profile Image: <a href='$signedUrl' target='_blank'>View Image</a>";
?>
