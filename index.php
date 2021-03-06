<?php
/*
* This gets the data from the opendata api from the danish folketing.
* It dumps the data as json files in /dump for later processing and import.
* The Api is very unstable and will often block the scrape.
* It is neccesary to resume from time to time
*/
include_once 'folketing.php';
ini_set("memory_limit", "-1");
set_time_limit (0);

if(!is_dir("dump"))
{
    mkdir("dump");
}

$target_folder = "dump/data-".date('d-m-Y-H-m',time());
if(!is_dir($target_folder))
{
    mkdir($target_folder);
}

$client = new folketing_client();
$json = file_get_contents("model.json");
$model = json_decode($json);


$json = file_get_contents("done_model.json");
$done_model = json_decode($json);

$json = file_get_contents("status.json");
// get settings and stuff like last complete run for the objects.
//the last update time for the models are needed to optimize querying.
$status = json_decode($json); 
if(!is_object($status))
{
    $status = new stdClass();
}
$target_db ="folketing";

foreach($model as $id)
{
    if(in_array($id,$done_model))
    {
        echo "Skipping: " . $id . "\n";
        continue;
    }
    echo "Getting Objects for: " . $id . "\n";
    $model_data = array();
    $date_filter = null;
    $limit = null;
    
    if(isset($status->modelUpdates->{$id}))
    {
        $date_filter = $status->modelUpdates->{$id}; //get last timestamp for finished parse.
    }
    $model_data[$id] = $client->GetAll($id, $limit,$date_filter);
    if($model_data[$id] != null)
    {
        if($id == "Aktør")
        {
            //convert biografy xml to json.
            convertAktørXmlToJson($model_data[$id]);
        }
        echo "Objects: " . sizeof($model_data[$id]) . "\n";
        $fp = fopen($target_folder."/".$id.'.json', 'w+');
        fwrite($fp, json_encode($model_data[$id]));
        fclose($fp);
        unset($model_data);
        updateDoneModels($id);
        gc_collect_cycles(); //force garbage collection..else script wiiiil grooooooow.
    }
}
//are we done downloading the models ?
if(sizeof($model) == sizeof($done_model))
{
    //importModelOnComplete();
    reset_done_models();
}

//helper functions.
function updateDoneModels($id)
{
    global $done_model;
    global $status;
    if(!isset($status->modelUpdates))
    {
        $status->modelUpdates = new stdClass();
    }
 
    $status->modelUpdates->{$id} = time();
    array_push($done_model,$id);
    
    $fp = fopen('done_model.json', 'w+');
    fwrite($fp, json_encode($done_model));
    fclose($fp);

    $fp = fopen('status.json', 'w+');
    fwrite($fp, json_encode($status));
    fclose($fp);

    return true;
}
function reset_done_models()
{
    $fp = fopen('done_model.json', 'w+');
    fwrite($fp, json_encode(array()));
    fclose($fp);
}
function importModelOnComplete()
{
    global $target_db;
    exec('php import-model.php '.$target_db);
}
function convertAktørXmlToJson(&$data)
{
    foreach($data as $index => $aktør)
    {
        if(isset($aktør->biografi) && !empty($aktør->biografi))
        {
            $aktør->biografi = preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $aktør->biografi); 
            $xml = simplexml_load_string($aktør->biografi);
            $aktør->biografi = json_decode(json_encode($xml));

            //Fix image urls.
            //var_dump($aktør->biografi->pictureMiRes);
            //exit(1);
            if(isset($aktør->biografi) && !empty($aktør->biografi))
            {
                $aktør->biografi->pictureMiRes = fixmemberimgurl($aktør->biografi->pictureMiRes);
            }
            
        }
    }
}
function fixmemberimgurl($url)
{
    if(is_string($url))
    {
        $url = str_replace('https://master-ft.ft.dk:443/','https://www.ft.dk/',$url);
        $url = str_replace('https://ft-webstage.ft.dk:443/sitecore/shell/','https://www.ft.dk/',$url);
        $url = str_replace('https://ft-webstage.ft.dk:443/','https://www.ft.dk/',$url);
        $url = str_replace('https://master-eu.ft.dk:443/','https://www.ft.dk/',$url);
        $url = str_replace('https://ft-webmaster.ft.dk:443/sitecore/shell/','https://www.ft.dk/',$url);
        $url = str_replace('https://ft-webmaster.ft.dk:443/','https://www.ft.dk/',$url);
       
    }
    return $url;
}