<?php

namespace Zchted\Affogato;

use App\Http\Controllers\Controller;

class ConfiguratorController extends Controller
{

    public function getConfig($config)
    {
        try {
            $response = getResponseObject();
            $response['result'] = json_decode(file_get_contents(base_path('core/' . $config . '.json')), true);
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = getResponseObject();
            $response['status'] = false;
            $response['error'] = $e->getMessage();
            return response()->json($response, 500);
        }
    }
}
