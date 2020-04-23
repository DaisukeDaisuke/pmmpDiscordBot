<?php

$file_phar = "pmmpDiscordBot.phar";
if(file_exists($file_phar)){
	echo "Phar file already exists, overwriting...";
	echo PHP_EOL;
	Phar::unlinkArchive($file_phar);
}

$dir = dirname(__FILE__);

$files = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
	if($file->isFile() === false){
		continue;
	}
	$files[str_replace($dir,"",$path)] = $path;
}
//var_dump($files);
echo "圧縮しています...";
echo PHP_EOL;
$phar = new Phar($file_phar, 0);
$phar->startBuffering();
$path = dirname(__FILE__)  . DIRECTORY_SEPARATOR;
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->buildFromIterator(new \ArrayIterator($files));
if(isset($argv[1])&&$argv[1] === "enableCompressAll"){
	$phar->compressFiles(Phar::GZ);
}
$phar->stopBuffering();
//$phar->setStub('<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');
echo "終了";
echo PHP_EOL;
