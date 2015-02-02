<?php

require_once ('src/BaseAnalyzer.php');
require_once ('src/Analyzer.php');
require_once ('src/LastAnalyzer.php');

$analyzer = new LastAnalyzer();

$analyzer->reset();

echo "DONE\n";