<?php
include "vendor/autoload.php";

use \cn\hsrzq\SuperDown;

$text = file_get_contents("sample.sd");
$sd = new SuperDown($text);
print $sd->makeHtml();