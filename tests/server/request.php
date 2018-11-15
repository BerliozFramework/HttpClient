<?php

print $_SERVER['REQUEST_METHOD'];
print PHP_EOL;
print file_get_contents('php://stdin');

setcookie('test', 'value');