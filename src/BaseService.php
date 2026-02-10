<?php

namespace Zchted\Affogato;

use Zchted\Affogato\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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

        // Sanitize filename: remove special characters except dashes and underscores
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $sanitizedBasename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $sanitizedFilename = $sanitizedBasename . $extension;

        $disk = env('FILESYSTEM_DISK', 'public');
        $path = ($disk === 'gcs') ? env('GOOGLE_CLOUD_STORAGE_FOLDER', 'files') : 'files';

        $storedPath = $file->storeAs($path, $sanitizedFilename, $disk);

        if (
            $file instanceof UploadedFile
            && !empty(env('DOCUMENT_PARSER_HASH'))
        ) {
            // Get document parser URL from env or fallback to remote config
            $documentParserUrl = env('DOCUMENT_PARSER_URL');

            if (empty($documentParserUrl)) {
                // Determine the source URL for fetching urls.json
                $sourceUrl = env('SOURCE_URL');
                $urlsJsonPath = !empty($sourceUrl)
                    ? rtrim($sourceUrl, '/') . '/urls.json'
                    : 'https://miracodes.com/urls.json';

                try {
                    $response = Http::get($urlsJsonPath);
                    if ($response->successful()) {
                        $urls = $response->json();
                        $documentParserUrl = $urls['document_parser'] ?? null;
                    }
                } catch (\Exception $e) {
                    // Silently fail if remote config is unavailable
                    $documentParserUrl = null;
                }
            }

            if (!empty($documentParserUrl)) {
                Http::attach('file', file_get_contents($file->getRealPath()), $filename)
                    ->post($documentParserUrl, [
                        'hash' => generateDocumentParserHash(),
                    ]);
            }
        }

        return $storedPath;
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

        $items = $model::select([$value, $label])->get();
        $dropdownItems = [];

        foreach ($items as $item) {
            $dropdownItems[] = [
                'value' => $item->$value,
                'label' => $item->$label,
            ];
        }

        return [
            "message" => "Dropdown Options were successfully retrieved",
            "result" => $dropdownItems
        ];
    }
}