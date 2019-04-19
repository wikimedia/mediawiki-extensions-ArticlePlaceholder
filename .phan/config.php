<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/Scribunto',
		'../../extensions/Wikibase',
	]
);

// Exclude Wikibase stub for Scribunto
$cfg['exclude_file_list'] = array_merge(
	$cfg['exclude_file_list'],
	[
		'../../extensions/Wikibase/tests/phan/stubs/scribunto.php',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Scribunto',
		'../../extensions/Wikibase',
	]
);

return $cfg;
