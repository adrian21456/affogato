<?php

namespace Zchted\Affogato;

use Zchted\Affogato\AuditLog;
use Illuminate\Http\Request;

class BaseService
{

    public static function writeAuditLog($module, $action, $user_id, $data_before = '', $data_after = '')
    {
        AuditLog::create([
            'module' => getModuleName(__CLASS__),
            'action' => getActionName(__FUNCTION__),
            'user_id' => auth()->id() ?? 1,
        ]);
    }

    public static function requestValidator(Request $request, array $rules)
    {
        return $request->validate($rules);
    }

    public static function saveFile($file, $filename)
    {
        $disk = env('FILESYSTEM_DISK', 'public');
        $path = ($disk === 'gcs') ? env('GOOGLE_CLOUD_STORAGE_FOLDER', 'files') : 'files';

        return $file->storeAs($path, $filename, $disk);
    }
}
