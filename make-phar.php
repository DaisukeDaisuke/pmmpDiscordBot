<?php

$file_phar = "pmmpDiscordBot.phar";
if(file_exists($file_phar)){
	echo "Phar file already exists, overwriting...";
	echo PHP_EOL;
	Phar::unlinkArchive($file_phar);
}

$dir = dirname(__FILE__).DIRECTORY_SEPARATOR;

$files = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
	if($file->isFile() === false){
		continue;
	}
	$files[str_replace($dir,"",$path)] = $path;
}
echo "Compressing...".PHP_EOL;
$phar = new Phar($file_phar, 0);
$phar->startBuffering();
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->buildFromIterator(new \ArrayIterator($files));
if(isset($argv[1])&&$argv[1] === "enableCompressAll"){
	$phar->compressFiles(Phar::GZ);
}
$phar->stopBuffering();
echo "end".PHP_EOL;
