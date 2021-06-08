<?php
// Script config
$dbname = 'robosou_database';
$username = 'root';
$passwd = 'admiN123';
$host = 'localhost';
$namespace = 'api_robosou.Models';

function toCamelCase ($string) {
    $camelCaseAttrName = ucfirst(strtolower($string));
    $underscorePositions = [];
    $len = strlen($camelCaseAttrName);
    for ($i = 0; $i < $len; $i++) {
        if ($camelCaseAttrName[$i] === '_') {
            $underscorePositions[] = $i;
        }
    }
    foreach ($underscorePositions as $underscorePos) {
        $camelCaseAttrName[$underscorePos + 1] = strtoupper($camelCaseAttrName[$underscorePos + 1]);
    }
    $camelCaseAttrName = str_replace('_', '', $camelCaseAttrName);
    return $camelCaseAttrName;
}

$version = date('Y_m_d.h_I_s');

// Connects to database in order to get tables pattern
$charset = 'utf8';
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$descriptionColumnArr = [];
try {
    $connection = new PDO($dsn, $username, $passwd);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $connection->query('SHOW TABLES');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_map(fn ($obj) => $obj["Tables_in_$dbname"], $columns);
    foreach ($columns as $column) {
        $stmt = $connection->query("DESCRIBE $column");
        $descriptionColumn = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $descriptionColumnArr[$column] = $descriptionColumn;
    }
} catch (PDOException $PDOException) {
    echo $PDOException->getMessage();
    exit;
}
$connection = null;

// Creates entities folder
$folder = "entities_$version";
mkdir($folder);
$namefile = "$folder/description_columns.json";
$content = json_encode($descriptionColumnArr);
$file = fopen($namefile, "w");
if (!$descriptionColumnArr || !$file || !fwrite($file, $content)) {
    die ('Error on writing description_columns.json');
}
fclose($file);

// Sets the mysql types mapper to php types
$types = [
    '/int/' => 'int',
    '/varchar/' => 'string',
    '/decimal/' => 'float',
    '/float/' => 'float',
    '/char/' => 'float',
    '/bool/' => 'bool',
    '/blob/' => 'string',
    '/text/' => 'string',
    '/date/' => 'string',
    '/time/' => 'string',
    '/year/' => 'string',
    '/double/' => 'float',
];

$tab = chr(9);
// Creates class content
foreach ($descriptionColumnArr as $classname => $description) {
    $camelCaseClassName = toCamelCase($classname);
    $classContent = 'using System;' . PHP_EOL . PHP_EOL;
    $classContent .= "namespace $namespace" . PHP_EOL . '{';
    $classContent .= PHP_EOL . $tab . "public class $camelCaseClassName" . PHP_EOL . $tab . '{';

    foreach ($description as $field) {
        foreach ($types as $regex => $type) {
            if (preg_match($regex, $field['Type'])) {
                $field = ucfirst(toCamelCase($field['Field']));
                $classContent .= PHP_EOL . $tab . $tab . "public $type $field { get; set; }";
            }
        }
    }
    $classContent .= PHP_EOL . $tab. '}' . PHP_EOL . '}' . PHP_EOL;
    $file = fopen("$folder/$camelCaseClassName.cs", 'w');
    if ($file) {
        fwrite($file, $classContent);
    }
    fclose($file);
}

