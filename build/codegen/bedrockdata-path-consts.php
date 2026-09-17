<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\build\generate_bedrockdata_path_consts;

use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function dirname;
use function fclose;
use function fopen;
use function fwrite;
use function is_dir;
use function is_file;
use function mkdir;
use function scandir;
use function str_replace;
use function strtoupper;
use const PHP_EOL;
use const pocketmine\BEDROCK_DATA_PATH;
use const pocketmine\LOCAL_BEDROCK_DATA_PATH;
use const SCANDIR_SORT_ASCENDING;
use const STDERR;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function constantify(string $permissionName) : string{
	return strtoupper(str_replace([".", "-"], "_", $permissionName));
}

/**
 * @return string[] filename => path constant name to use for that file
 * @phpstan-return array<string, string>
 */
function scanDataDir(string $dir, string $pathConstName) : array{
	$files = scandir($dir, SCANDIR_SORT_ASCENDING);
	if($files === false){
		fwrite(STDERR, "Couldn't find any files in " . $dir . PHP_EOL);
		exit(1);
	}

	$result = [];
	foreach($files as $file){
		if($file === '.' || $file === '..'){
			continue;
		}
		if($file[0] === '.'){
			continue;
		}
		$path = Path::join($dir, $file);
		if(!is_file($path) && !is_dir($path)){
			continue;
		}

		foreach([
			'README.md',
			'LICENSE',
			'composer.json',
			'.github'
		] as $ignored){
			if($file === $ignored){
				continue 2;
			}
		}

		$result[$file] = $pathConstName;
	}
	return $result;
}

//2026-09-17: LOCAL_BEDROCK_DATA_PATH is scanned second so its files win any name collision with
//BEDROCK_DATA_PATH (none expected in practice - see resources/vanilla-1.26.50-data/README.md).
$consts = [
	...scanDataDir(BEDROCK_DATA_PATH, 'BEDROCK_DATA_PATH'),
	...scanDataDir(LOCAL_BEDROCK_DATA_PATH, 'LOCAL_BEDROCK_DATA_PATH'),
];

$path = dirname(__DIR__, 2) . '/generated/data/bedrock/BedrockDataFiles.php';
$dir = dirname($path);
if(!@mkdir($dir, recursive: true) && !is_dir($dir)){
	fwrite(STDERR, "Couldn't create directory: $dir" . PHP_EOL);
	exit(1);
}
$header = Filesystem::fileGetContents(__DIR__ . "/templates/header.php");
$output = fopen($path, 'wb');
if($output === false){
	fwrite(STDERR, "Couldn't open output file: $path" . PHP_EOL);
	exit(1);
}
fwrite($output, $header);
fwrite($output, <<<'HEADER'

namespace pocketmine\data\bedrock;

use const pocketmine\BEDROCK_DATA_PATH;
use const pocketmine\LOCAL_BEDROCK_DATA_PATH;

final class BedrockDataFiles{
	private function __construct(){
		//NOOP
	}


HEADER
);

foreach($consts as $fileName => $pathConstName){
	fwrite($output, "\tpublic const " . constantify($fileName) . " = $pathConstName . '/$fileName';\n");
}

fwrite($output, "}\n");
fclose($output);

echo "Done. Don't forget to run CS fixup after generating code.\n";
