<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$date = (new DateTime('now'))->format('Y-m-dTH:i');

include '.env.local';

$s3 = new S3Client([
		'version' => $_ENV['VERSION'],
		'region' => $_ENV['REGION'],
		'credentials' => [
			'key' => $_ENV['ACCESS_KEY_ID'],
			'secret' => $_ENV['SECRET_ACCESS_KEY'],
		],
	]);

if (!is_dir($_ENV['ZIPPED_PATH'])) {
	mkdir($_ENV['ZIPPED_PATH']);
}

foreach ($_ENV['BACKUPS'] as $backup)
{
	if (!is_dir($backup['path'])) {
		continue;
	}

	shell_exec('zip -r ' . $_ENV['ZIPPED_PATH'] . '/' . $backup['name'] . '.zip ' . $backup['path']);

	$result = $s3->putObject([
			'Bucket' => $_ENV['BUCKET'],
			'Key' => $backup['name'] . '-' . $date . '.zip',
			'ACL' => $_ENV['ACL'],
			'SourceFile' => $_ENV['ZIPPED_PATH'] . '/' . $backup['name'] . '.zip',
		]);

	shell_exec('rm ' . $_ENV['ZIPPED_PATH'] . '/' . $backup['name'] . '.zip');
}

foreach ($_ENV['DATABASES'] as $database) {
	shell_exec('mariadb-dump --user=root --password=root --lock-tables ' . $database . ' > ' . $database .'.sql');
	shell_exec('zip ' . $_ENV['ZIPPED_PATH'] . '/' . $database . '.sql.zip ' . $database . '.sql');
	shell_exec('rm ' . $database . '.sql');

	$result = $s3->putObject([
			'Bucket' => $_ENV['BUCKET'],
			'Key' => $database . '-' . $date . '.sql.zip',
			'ACL' => $_ENV['ACL'],
			'SourceFile' => $_ENV['ZIPPED_PATH'] . '/' . $database . '.sql.zip',
		]);

	shell_exec('rm ' . $_ENV['ZIPPED_PATH'] . '/' . $database . '.sql.zip');
}

rmdir($_ENV['ZIPPED_PATH']);
