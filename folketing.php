<?php
ini_set("allow_url_fopen", 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 0);
error_reporting(E_ALL);
class folketing_client
{
    private $service_url = "https://oda.ft.dk/api/";
    /*
     * Extract meta data
     */
    public function GetEntityTypes()
    {
    }
    /*
     * Get all elements of odata feed.
     */
    public function GetAll($entity_type, $limit = -1,$last_query = null)
    {
        $Result = [];
        $pagecount = 0;
        $failed_count = 0;
        $ctx = stream_context_create(array(
            'http' =>
            array(
                'timeout' => 1200,  //1200 Seconds is 20 Minutes
            )
        ));
        try {
            $next_url = $this->service_url . urlencode($entity_type);
            if($last_query != null)
            {
                $filter_date = date('Y-m-d',$last_query);
                $next_url .= '?$filter=opdateringsdato%20gt%20datetime\''.$filter_date.'\'';
            }
            echo "retriving: " . $next_url ."\r\n";
   
            do {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
                curl_setopt($ch, CURLOPT_URL, $next_url);
                $tmp = curl_exec($ch);

                $data = null;
                if (is_string($tmp)) {
                    $data = json_decode($tmp);
                }
   
                if (!is_object($data)) {
                    //retry , sometimes service is slow.
                    echo "Retrying data fetch\n";
                    sleep(5);
                    $tmp = @file_get_contents($next_url);
                    $data = json_decode($tmp);
                }
                echo "Page:" . $pagecount . "\n";
                if (is_object($data)) {
                    $Result = array_merge($Result, $data->value);
                    $next_url = isset($data->{"odata.nextLink"}) ? $data->{"odata.nextLink"} : null;
                    $pagecount++;
                }
            } while ($next_url != null);
        } catch (Exception $ex) {
            echo "Failed to retrive objects: " . $entity_type;
            return null;
        }
        return $Result;
    }
    /*
     * Get an element by id.
     
    private function GetById($entity_type, $key_value)
    {
        $request_url = $this->service_url . $entity_type . "({key_value)";
        $tmp = json_decode(file_get_contents($next_url));
        return $tmp;
    }*/
}
