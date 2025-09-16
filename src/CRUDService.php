<?php

namespace Zchted\Affogato;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zchted\Affogato\CRUDEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CRUDService
{

    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = new $model;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel(Model $model)
    {
        $this->model = new $model;
    }

    public function generateStoreRule(array $rules = [])
    {
        return $this->model->generateStoreRule($rules);
    }

    public function generateUpdateRule(array $rules = [])
    {
        return $this->model->generateUpdateRule($rules);
    }

    public function index()
    {
        $query = $this->model->query();

        if (!empty($this->model->join ?? [])) {
            $query->with($this->model->join);
        }

        return [
            "message" => "Records were successfully retrieved",
            "result" => $query->get()
        ];
    }

    public function show($item)
    {
        $query = $this->model->query();

        if (!empty($this->model->join ?? [])) {
            $query->with($this->model->join);
        }

        $result = $query->find($item);
        return [
            "message" => !empty($result) ? "Records were successfully retrieved" : "Record was not found",
            "result" => $result
        ];
    }

    public function search(Request $request)
    {
        $query = $this->model->query();

        if (!empty($this->model->join ?? [])) {
            $query->with($this->model->join);
        }

        foreach ($request->all() as $key => $value) {
            if (in_array($key, ['page', 'limit', 'order_by', 'order_direction'])) continue;
            
            if ($key === 'queryText' && !empty($value)) {
                $fields = $this->model->getFillable();
                foreach ($fields as $field) {
                    $query->orWhereRaw("CAST({$field} AS TEXT) LIKE ?", ['%' . $value . '%']);
                }
                continue;
            }

            if (!in_array($key, $this->model->getFillable())) continue;

            if (is_string($value)) {
                $query->where($key, 'like', '%' . $value . '%');
            } else {
                $query->where($key, $value);
            }
        }

        $orderBy = $request->input('order_by');
        $orderDirection = strtolower($request->input('order_direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $fillable = ($this->model->getFillable());
        $primaryKey = ($this->model->getKeyName());

        if ($orderBy && (in_array($orderBy, $fillable) || $orderBy === $primaryKey)) {
            $query->orderBy($orderBy, $orderDirection);
        }

        $limit = (int) $request->input('limit', 0);
        $page = (int) $request->input('page', 1);

        if ($limit > 0) {
            $results = $query->paginate($limit, ['*'], 'page', $page);
        } else {
            $results = $query->get();
        }

        return [
            "message" => $results->isNotEmpty() ? "Records retrieved successfully" : "No matching records found",
            "result" => $results
        ];
    }

    public function store(Request $request, $validated = [])
    {
        BaseService::writeAuditLog(getModuleName(__CLASS__), getActionName(__FUNCTION__), auth()->id() ?? null, $request->all());
        $validated = $this->handleFiles($request, $validated);
        $result = $this->model->create($validated);

        event(new CRUDEvent([
            "action" => "add",
            "data" => makeSerializable($request->all())
        ]));

        return [
            "message" => "Record has been saved",
            "result" => $result
        ];
    }

    public function update(Request $request, $item, $validated = [])
    {

        $item = $this->model->find($item);

        if (!$item) {
            return [
                "error" => "Failed to update record: Record Not found"
            ];
        }

        event(new CRUDEvent([
            "action" => "edit",
            "data" => makeSerializable($request->all())
        ]));

        $data_before = $item->toArray();
        $validated = $this->handleFiles($request, $validated, true);

        $item->fill($validated);
        $item->save();

        $data_after = $item->toArray();
        BaseService::writeAuditLog(getModuleName(__CLASS__), getActionName(__FUNCTION__), auth()->id() ?? null, $data_before, $data_after);

        return [
            "message" => "Record has been updated",
            "result" => $item
        ];
    }

    public function destroy($item)
    {
        $item = $this->model->find($item);

        if (!$item) {
            return [
                "error" => "Record to be removed was not found",
            ];
        }

        event(new CRUDEvent([
            "action" => "delete",
            "data" => makeSerializable($item->toArray())
        ]));

        BaseService::writeAuditLog(getModuleName(__CLASS__), getActionName(__FUNCTION__), auth()->id() ?? null, $item);
        $item->delete();

        return [
            "message" => "Record has been removed"
        ];
    }

    private function handleFiles(Request $request, $validated = [], $updateLogic = false)
    {
        $uploadedFiles = [];

        $modelFiles = $this->model->files;

        foreach ($modelFiles as $key => $value) {
            if ($request->has($key)) {

                $config = json_decode(file_get_contents(base_path('core/' . $this->model->table . '.json')), true);
                $columns = $config['columns'];
                $column = [];

                foreach ($columns as $col) {
                    if ($col['name'] === $key) {
                        $column = $col;
                    }
                }

                if ($updateLogic && $column['control'] === 'file_multiple') {
                    $uploadedFiles = $this->model->findOrFail($request->item)[$key];
                    if (is_array($request[$key]) && count($request[$key]) === 0) {
                        $uploadedFiles = [];
                    }
                }

                $files = $request->file($key);

                if (empty($files)) {
                    $files = $request->input($key);
                }

                if (!is_array($files)) {
                    $files = [$files];
                }

                if ($updateLogic && count($files) > 0) {
                    if (is_string($files[0])) {
                        $uploadedFiles = [];
                    }
                }

                foreach ($files as $file) {
                    if (is_string($file)) {
                        if ($updateLogic) { //file array is to be updated anew
                            $uploadedFiles[] = $file;
                            $validated[$key] = $uploadedFiles;
                            continue;
                        }
                    }

                    $extension = null;
                    $file_name = null;


                    if ($file instanceof UploadedFile) {
                        $extension = $file->getClientOriginalExtension();
                        $file_name = $file->getClientOriginalName();
                    }

                    if (is_string($file)) {
                        $file_parts = explode('.', $file);
                        if (count($file_parts) > 1) {
                            $extension = end($file_parts);
                        } else {
                            throw new \Exception('Invalid file extension');
                        }
                        $file_name = basename($file);
                        $uploadedFiles[] = $file;
                        continue;
                    }

                    $filename = str_replace('.' . $extension, "", $file_name) . getFileSuffix() . '.' . $extension;
                    $filePath = BaseService::saveFile($file, $filename);
                    $uploadedFiles[] = $filePath;
                }

                $validated[$key] = $uploadedFiles;
                $uploadedFiles = [];
            }
        }

        return $validated;
    }
}
