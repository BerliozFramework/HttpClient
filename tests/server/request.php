<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

ob_start();
print $_SERVER['REQUEST_METHOD'];
print PHP_EOL;
print file_get_contents('php://stdin');
print_r(getallheaders());
array_map(fn($postValue) => print $postValue, $_POST);
$contents = ob_get_clean();

setcookie('test', 'value');

if ($redirect = (int)($_GET['redirect'] ?? 0)) {
    header(
        'Location: /request.php?encoding=' . ($_GET['encoding'] ?? null) . '&redirect=' . ($redirect - 1),
        true,
        $_GET['response_code'] ?? 301
    );
    exit;
}

switch ($_GET['test'] ?? null) {
    case 'encoded_http_reason':
        $reason = 'HTTP/1.1 200 Requête OK';
        header(mb_convert_encoding($reason, 'ISO-8859-1', mb_detect_encoding($reason)));
        break;
}

if ($_GET['encoding'] ?? null) {
    header('Content-Encoding:' . $_GET['encoding']);
}

print match ($_GET['encoding'] ?? null) {
    'gzip' => gzencode($contents),
    'deflate' => gzdeflate($contents),
    default => $contents,
};