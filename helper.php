<?php


function setLog(string $dirName, string $fileName, $message)
{
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $dirName . DIRECTORY_SEPARATOR . $fileName . '.log';

    $message = date('Y-m-d H:i:s', time()) . ' ' . json_encode($message, JSON_UNESCAPED_UNICODE) . "\n\n";

    error_log($message, 3, $filePath);

}