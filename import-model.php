<?php

/* 
* Imports all files from /dump into the mongo db.
* Example: php import-model.php <dbname>
*/
$dbName = $argv[1];
$cwd = getcwd();
chdir($cwd . "/dump/data-08-06-2020-10-06");
$scanned_directory = array_diff(scandir($cwd . "/dump/data-08-06-2020-10-06"), array('..', '.'));
foreach($scanned_directory as $file)
{
    $fname = explode('.',$file);
    $collection_name = reset($fname);
    echo "========= Updating: " . $collection_name ."\n";
    exec('mongo '.$dbName.' --eval "db.'.$collection_name.'.createIndex({"id": -1})"'); //ensure there is some indexes.
    exec("mongoimport -v --upsertFields=id --db $dbName --collection $collection_name --file $file --jsonArray");
    echo "==========================:\n";
}
