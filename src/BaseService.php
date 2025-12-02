<?php

namespace Zchted\Affogato;

use Zchted\Affogato\AuditLog;
use Illuminate\Http\Request;

class BaseService
{

    public static function writeAuditLog($module, $action, $user_id, $data_before = '', $data_after = '')
    {
        if (!empty($user_id)) {
            AuditLog::create([
                'module' => getModuleName(__CLASS__),
                'action' => getActionName(__FUNCTION__),
                'user_id' => auth()->id() ?? 1,
            ]);
        }
    }

    public static function requestValidator(Request $request, array $rules)
    {
        return $request->validate($rules);
    }

    public static function saveFile($file, $filename)
    {
        if (!$file) {
            return null;
        }

        $disk = env('FILESYSTEM_DISK', 'public');
        $path = ($disk === 'gcs') ? env('GOOGLE_CLOUD_STORAGE_FOLDER', 'files') : 'files';

        return $file->storeAs($path, $filename, $disk);
    }

    public function getConfig($config)
    {
        if(file_exists(base_path('core/' . $config . '.json')) === false){
            return json_decode(file_get_contents(base_path('core/' . $config . '.json')), true);
        }
        
        throw new \Exception("Configuration file not found");
    }

    public static function getDropdownItems($Request, $model)
    {
        $config = getConfig($model->table);
        $value = $Request->input('value', $model->table . "_id");
        $label = $Request->input('label', $config['representative_value']);

        $items = $model::query([$value, $label])->get();
        $dropdownItems = [];

        foreach ($items as $item) {
            $dropdownItems[] = [
                'value' => $item->$value,
                'label' => $item->$label,
            ];
        }

        return $dropdownItems;
    }
}