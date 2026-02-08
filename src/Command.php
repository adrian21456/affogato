<?php

namespace Zchted\Affogato;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Command
{
    private static $special_columns = ["created_at", "updated_at", "deleted_at"];

    public static function boot()
    {
        if (!is_dir(base_path("core"))) {
            mkdir(base_path("core"), 0777, true);
        }
        if (!is_dir(base_path("core/backups"))) {
            mkdir(base_path("core/backups"), 0777, true);
        }
        if (!is_dir(base_path("core/logs"))) {
            mkdir(base_path("core/logs"), 0777, true);
        }

        if (!is_dir(base_path("app/Http/Controllers/Mods"))) {
            mkdir(base_path("app/Http/Controllers/Mods"), 0777, true);
        }

        if (!is_dir(base_path("app/Http/Controllers/Api"))) {
            mkdir(base_path("app/Http/Controllers/Api"), 0777, true);
        }

        if (!is_dir(base_path("app/Models/Mods"))) {
            mkdir(base_path("app/Models/Mods"), 0777, true);
        }

        if (!is_dir(base_path("tests/Feature/CRUD"))) {
            mkdir(base_path("tests/Feature/CRUD"), 0777, true);
        }

        if (!file_exists(base_path("core/__ignored_configs.json"))) {
            file_put_contents(base_path("core/__ignored_configs.json"), "[]");
        }
        if (!file_exists(base_path("core/__roles.json"))) {
            file_put_contents(base_path("core/__roles.json"), '["Administrator"]');
        }

        if (!file_exists(base_path("routes/mods.php"))) {
            file_put_contents(base_path("routes/mods.php"), "<?php

use Illuminate\Support\Facades\Route;");
        }

        if (!file_exists(base_path("database/migrations/___create_audit_log___.php"))) {
            copy(__DIR__ . "/mods/___create_audit_log___.php", base_path("database/migrations/___create_audit_log___.php"));
        }

        if (!file_exists(base_path("database/migrations/___update_users___.php"))) {
            copy(__DIR__ . "/mods/___update_users___.php", base_path("database/migrations/___update_users___.php"));
        }
    }


    public static function makeConfig($config_name, $columns = null, $type = 'int'): array
    {
        $comments = [];
        try {
            if (file_exists(base_path("core/$config_name.json"))) {
                throw new \Exception("Config: $config_name.json already exists.");
            }

            $columns = !empty($columns) ? self::cleanColumns($columns) : [];
            $config = json_decode(file_get_contents(__DIR__ . "/mods/config.json"), true);

            //Change Contents
            $id = $config_name . "_id";
            $proper_name = properName($config_name);
            $plural_name = pluralize($proper_name);

            $config['name'] = $config_name;
            $config['representative_value'] = $id;
            $config['ui']['page_name'] = $plural_name;

            foreach ($config['urls'] as $key => $url) {
                $config['urls'][$key] = str_replace('demo', $config_name, $url);
            }

            //Create Primary Key
            $cols = [];
            $cols[] = self::columnGenerator($id, $type, 'primary');

            foreach ($columns as $column) {
                $col_data = self::columnAnalyzer($column);
                $cols[] = self::columnGenerator($col_data['column_name'], $col_data['type'], $col_data['key']);
            }

            $config['columns'] = $cols;

            self::backupConfig($config_name);
            $file = fopen(base_path("core/$config_name.json"), "w");
            fwrite($file, json_encode($config, JSON_PRETTY_PRINT));

            echo "Config: $config_name.json has been created in core/" . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to create config: $config_name. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    public static function deleteColumns($config_name, $columns): array
    {
        $comments = [];
        try {
            $columns = self::cleanColumns($columns);
            $config = json_decode(file_get_contents(base_path("core/$config_name.json")), true);

            $cols = $config['columns'];

            foreach ($columns as $column) {
                foreach ($cols as $key => $col) {
                    if ($col['name'] === $column) {
                        unset($cols[$key]);
                    }
                }
            }

            $cols = array_values($cols);

            $config['columns'] = $cols;

            self::backupConfig($config_name);
            $file = fopen(base_path("core/$config_name.json"), "w");
            fwrite($file, json_encode($config, JSON_PRETTY_PRINT));

            echo "Config Column: " . implode(",", $columns) . " has been removed from $config_name.json" . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to remove column: $config_name. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    private static function columnGenerator($column_name, $type = 'string', $key = '')
    {
        try {
            $column = json_decode(file_get_contents(__DIR__ . ("/mods/column.json")), true);
            $proper_name = properName($column_name);

            $column['backend']['type'] = $type;
            $column['backend']['name'] = $column_name;
            $column['frontend']['table']['label'] = rtrim($proper_name, " Id");
            $column['frontend']['table']['key'] = $column_name;
            $column['frontend']['text']['label'] = rtrim($proper_name, " Id");
            $column['frontend']['view']['label'] = rtrim($proper_name, " Id");
            $column['frontend']['view']['key'] = $column_name;
            $column['name'] = $column_name;

            if ($key === 'primary') {
                $column['backend']['primary'] = true;
                $column['backend']['fillable'] = false;
                $column['backend']['nullable'] = false;
                $column['frontend']['display']['form'] = false;
                $column['frontend']['display']['table'] = false;
                $column['frontend']['display']['view'] = false;
                $column['backend']['type'] = 'int';
            }

            if ($key === 'foreign') {
                $column['backend']['foreign'] = true;
                $column['backend']['nullable'] = false;
                if (($column['frontend']['form_control'] ?? '') === 'multiselect') {
                    $column['backend']['type'] = 'json';
                } else {
                    $column['backend']['type'] = 'int';
                }
            }

            // File controls
            if ($type === 'file') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'file';
                $column['frontend']['controlSettings']['file']['fileMultiple'] = false;
            }

            if ($type === 'file_multiple') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'file';
                $column['frontend']['controlSettings']['file']['fileMultiple'] = true;
            }

            // Text input controls
            if ($type === 'email') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'email';
            }

            if ($type === 'password') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'password';
            }

            if ($type === 'textarea') {
                $column['backend']['type'] = 'text';
                $column['frontend']['form_control'] = 'textarea';
            }

            // Numeric controls
            if ($type === 'number') {
                $column['backend']['type'] = 'int';
                $column['frontend']['form_control'] = 'number';
            }

            if ($type === 'range') {
                $column['backend']['type'] = 'int';
                $column['frontend']['form_control'] = 'range';
            }

            // Date/Time controls
            if ($type === 'daterange') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'daterange';
            }

            // Selection controls
            if ($type === 'select') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'select';
            }

            if ($type === 'multiselect') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'multiselect';
            }

            if ($type === 'radio') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'radio';
            }

            if ($type === 'checkbox') {
                $column['backend']['type'] = 'boolean';
                $column['frontend']['form_control'] = 'checkbox';
            }

            if ($type === 'checkbox_group') {
                $column['backend']['type'] = 'string';
                $column['frontend']['form_control'] = 'checkbox_group';
            }

            if ($type === 'toggle') {
                $column['backend']['type'] = 'boolean';
                $column['frontend']['form_control'] = 'toggle';
            }

            if (in_array($column_name, self::$special_columns)) {
                $column['backend']['required'] = false;
                $column['backend']['nullable'] = false;
                $column['backend']['fillable'] = false;
            }

            if (in_array($column_name, ['created_at', 'updated_at'])) {
                $column['frontend']['display']['form'] = false;
                $column['frontend']['display']['table'] = false;
                $column['frontend']['display']['view'] = false;
            }
            return $column;
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function columnAnalyzer($column_name)
    {
        $response = [
            "column_name" => $column_name,
            "type" => "string",
            "key" => ""
        ];

        try {
            $columns_names = explode("_", $column_name);
            if (count($columns_names) > 1) {
                if (end($columns_names) === 'id') {
                    $response['key'] = 'foreign';
                }
                $response['type'] = match (strtolower($columns_names[0])) {
                    // Backend types
                    'i' => 'int',
                    'b' => 'boolean',
                    's' => 'string',
                    // Date/Time controls
                    'd' => 'date',
                    't' => 'time',
                    'dt' => 'datetime',
                    'dr' => 'daterange',
                    // File controls
                    'f' => 'file',
                    'fm' => 'file_multiple',
                    // Text input controls
                    'e' => 'email',
                    'p' => 'password',
                    'ta' => 'textarea',
                    // Numeric controls
                    'n' => 'number',
                    'rng' => 'range',
                    // Selection controls
                    'sel' => 'select',
                    'msel' => 'multiselect',
                    'r' => 'radio',
                    'cb' => 'checkbox',
                    'cbg' => 'checkbox_group',
                    'tgl' => 'toggle',
                    default => 'none',
                };

                if ($response['type'] === 'none') {
                    $response['type'] = 'string';
                } else {
                    $response['column_name'] = preg_replace('/' . preg_quote($columns_names[0] . "_", '/') . '/', "", $column_name, 1);
                }
                return $response;
            } else {
                return $response;
            }
        } catch (\Exception $e) {
            return [
                "column_name" => $column_name,
                "type" => "string",
                "key" => ""
            ];
        }
    }

    public static function makeMod($name, $data = "", $force = false): array
    {
        $comments = [];
        try {
            $commandName = $name;
            $lowerName = strtolower($commandName);
            $name = str_replace("_", " ", $commandName);
            $modName = "Mod" . ucwords($name);
            $modName = str_replace(" ", "", $modName);
            $fileName = $modName . ".php";

            //Create Mod Model
            if (file_exists(app_path("Models/Mods/$fileName")) && !$force) {
                //Do nothing
            } else {
                $file = empty($data) ? file_get_contents(__DIR__ . ("/mods/ModModel.php.txt")) : $data; // Example path
                $file = str_replace("demo", $lowerName, $file);
                $file = str_replace("Demo", $modName, $file);
                $file = str_ireplace("ModMod", "Mod", $file);

                file_put_contents(app_path("Models/Mods/$fileName"), $file);
                echo "MOD: $modName has been created in app/Models/Mods" . PHP_EOL;
            }

            //Create Main Model
            $lowerName = strtolower($commandName);
            $name = str_replace("_", " ", $commandName);
            $modName = ucwords($name);
            $modName = str_replace(" ", "", $modName);
            $fileName = $modName . ".php";

            if (file_exists(app_path("Models/$fileName"))) {
                //                echo "Model: $modName already exists" . PHP_EOL;
            } else {
                $file = file_get_contents(__DIR__ . ("/mods/Model.php.txt")); // Example path
                $file = str_replace("demo", $lowerName, $file);
                $file = str_replace("Demo", $modName, $file);
                $file = str_ireplace("ModMod", "Mod", $file);

                file_put_contents(app_path("Models/$fileName"), $file);
                echo "Model: $modName has been created in app/Models/" . PHP_EOL;
            }

            //Create Mod Controller
            $lowerName = strtolower($commandName);
            $name = str_replace("_", " ", $commandName);
            $modName = "Mod" . ucwords($name);
            $modName = str_replace(" ", "", $modName);
            $properName = ucwords($name);
            $properName = str_replace(" ", "", $properName);
            $modName = str_replace(" ", "", $modName);
            $controllerName = $modName . "Controller";
            $fileName = $controllerName . ".php";

            $file = file_get_contents(__DIR__ . ("/mods/ModController.php.txt")); // Example path
            $file = str_replace("demo", $lowerName, $file);
            $file = str_replace("ModDemo", $modName, $file);
            $file = str_replace("Demo", $properName, $file);
            $file = str_ireplace("ModMod", "Mod", $file);

            file_put_contents(app_path("Http/Controllers/Mods/$fileName"), $file);
            echo "MOD: $controllerName has been created in app/Http/Controllers/Mods" . PHP_EOL;

            //Create Main Controller
            $lowerName = strtolower($commandName);
            $name = str_replace("_", " ", $commandName);
            $modName = ucwords($name);
            $modName = str_replace(" ", "", $modName);
            $properName = ucwords($name);
            $properName = str_replace(" ", "", $properName);
            $modName = str_replace(" ", "", $modName);
            $controllerName = $modName . "Controller";
            $fileName = $controllerName . ".php";
            $extraModName = "Mod" . ucwords($name);
            $extraModName = str_replace(" ", "", $extraModName);

            if (file_exists(app_path("Http/Controllers/api/$fileName"))) {
                //                echo "MOD: $controllerName already exists" . PHP_EOL;
            } else {
                $file = file_get_contents(__DIR__ . ("/mods/Controller.php.txt")); // Example path
                $file = str_replace("demo", $lowerName, $file);
                $file = str_replace("ModDemo", $extraModName, $file);
                $file = str_replace("Demo", $properName, $file);
                $file = str_ireplace("ModMod", "Mod", $file);

                file_put_contents(app_path("Http/Controllers/api/$fileName"), $file);
                echo "MOD: $controllerName has been created in app/Http/Controllers/" . PHP_EOL;
            }

            //Create Test
            $lowerName = strtolower($commandName);
            $name = str_replace("_", " ", $commandName);
            $modName = ucwords($name);
            $modName = str_replace(" ", "", $modName);
            $fileName = $modName . "Test.php";

            $file = file_get_contents(__DIR__ . ("/mods/ModTest.php.txt")); // Example path
            $file = str_replace("demo", $lowerName, $file);
            $file = str_replace("Demo", $modName, $file);
            $file = str_ireplace("ModMod", "Mod", $file);

            file_put_contents(base_path("tests/Feature/CRUD/$fileName"), $file);
            echo "Model: $modName has been created in tests/Feature/CRUD/" . PHP_EOL;


            //Create Routes
            $routes = file_get_contents(__DIR__ . ("/mods/Routes.txt"));
            $routes = str_replace("demo", $lowerName, $routes);
            $routes = str_replace("Demo", $properName, $routes);
            $routes_file = file_get_contents(base_path("routes/mods.php"));
            if (str_contains($routes_file, $routes)) {
                //                echo "Routes: API Routes already exists" . PHP_EOL;
            } else {
                $route_file = fopen(base_path("routes/mods.php"), "a");
                fwrite($route_file, PHP_EOL . PHP_EOL . $routes);
                fclose($route_file);
                echo "Routes: API Routes created" . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "Failed to create mod: $name. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    public static function configurator(): array
    {
        $comments = [];
        try {
            self::copyFakerFiles();
            self::fillAllRelationships();
            self::removeMigrationFiles();

            self::separator();

            $jsonFiles = self::getSortedConfigurations();
            foreach ($jsonFiles as $file) {
                $config = json_decode(file_get_contents(base_path("core/") . $file . ".json"), true);
                $config = self::cleanConfig($config);
                self::createModel($config);
                self::createFactory($config);
                self::createMigration($config);

                self::separator();
            }
        } catch (\Exception $e) {
            echo "Failed to clean config. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    public static function fillAllRelationships(): void
    {
        try {
            $path = base_path('core');
            $jsonFiles = glob($path . '/*.json');
            $ignored_configs = json_decode(file_get_contents("$path/__ignored_configs.json"), true);


            foreach ($jsonFiles as $file) {
                if (str_contains($file, "__")) continue;
                $config = json_decode(file_get_contents($file), true);
                if (!in_array($config['name'], $ignored_configs)) {
                    $config = self::cleanConfig($config);

                    foreach ($config['columns'] as $column) {
                        if ($column['backend']['foreign']) {
                            self::fillRelationship($config['name'] . "_id", $column['name']);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            dd("Failed to fill relationships: " . $e->getMessage());
        }
    }

    public static function fillRelationship($pk, $fk)
    {
        //PK will have HasMany by Default and FK will have BelongsTo (one) by Default
        $pk_table = str_replace("_id", "", $pk);
        $fk_table = str_replace("_id", "", $fk);

        $pk_file_path = base_path("core/$pk_table.json");
        if (file_exists($pk_file_path)) {
            $config = json_decode(file_get_contents($pk_file_path), true);
            if (!isset($config['relationship'][$fk_table])) {
                $config['relationship'][$fk_table] = 'one';
                file_put_contents($pk_file_path, json_encode($config, JSON_PRETTY_PRINT));
            }
        }

        $fk_file_path = base_path("core/$fk_table.json");
        if (file_exists($fk_file_path)) {
            $config = json_decode(file_get_contents($fk_file_path), true);
            if (!isset($config['relationship'][$pk_table])) {
                $config['relationship'][$pk_table] = 'many';
                file_put_contents($fk_file_path, json_encode($config, JSON_PRETTY_PRINT));
            }
        }
    }

    public static function createModel(array $config): array
    {
        $log = [];
        try {
            $file = file_get_contents(__DIR__ . ("/mods/ModModel.php.txt"));

            if (!$config['soft_delete']) {
                $file = str_replace("use SoftDeletes;", "", $file);
                $file = str_replace("use Illuminate\Database\Eloquent\SoftDeletes;", "", $file);
            }

            //Get Primary Key
            $primary_key = "";
            foreach ($config['columns'] as $column) {
                if ($column['backend']['primary']) {
                    $primary_key = $column['name'];
                    break;
                }
            }
            if (empty($primary_key)) throw new \Exception("Primary key not found.");
            $file = str_replace("{{pk_column}}", $primary_key, $file);
            $file = str_replace("{{table}}", $config['name'], $file);
            $file = str_replace("{{timestamps}}", $config['timestamps'] ? "true" : "false", $file);

            $fillables = [];
            $nullables = [];
            $casts = [];
            $enumerables = [];
            $files = [];
            $lengths = [];
            $defaults = [];

            foreach ($config['columns'] as $column) {
                if ($column['backend']['fillable']) $fillables[] = $column['name'];
                if ($column['backend']['nullable']) $nullables[] = $column['name'];
                if (!empty($column['backend']['options'])) $enumerables[$column['name']] = $column['backend']['options'];
                if (str_contains($column['frontend']['form_control'], 'file')) $files[$column['name']] = $column['backend']['file_types'];
                if (!empty($column['backend']['length'])) $lengths[$column['name']] = $column['backend']['length'];
                if (!empty($column['backend']['default'])) $defaults[$column['name']] = $column['backend']['default'];
                if (!in_array($column["name"], self::$special_columns)) {
                    $cast_type = $column["backend"]["type"];
                    // Add precision for decimal types
                    if ($cast_type === "decimal") {
                        $cast_type = "decimal:2";
                    }
                    $casts[$column["name"]] = $cast_type;
                }
                if (str_contains($column['frontend']['form_control'], 'file')) $casts[$column['name']] = "array";
            }

            $fillables_code = "";
            foreach ($fillables as $fillable) {
                $fillables_code .= "\t\t'$fillable', " . PHP_EOL;
            }
            $fillables_code = rtrim($fillables_code, ", " . PHP_EOL);
            $fillables_code = trim($fillables_code);
            $file = str_replace("{{fillables}}", $fillables_code, $file);

            $nullables_code = "";
            foreach ($nullables as $nullable) {
                $nullables_code .= "\t\t'$nullable', " . PHP_EOL;
            }
            $nullables_code = rtrim($nullables_code, ", " . PHP_EOL);
            $nullables_code = trim($nullables_code);
            $file = str_replace("{{nullables}}", $nullables_code, $file);

            $files_code = "";

            foreach ($files as $key => $file1) {
                $files_code .= "\t\t'$key' => '$file1', " . PHP_EOL;
            }
            $files_code = rtrim($files_code, ", " . PHP_EOL);
            $files_code = trim($files_code);
            $file = str_replace("{{files}}", $files_code, $file);

            $casts_code = "";
            foreach ($casts as $key => $cast) {
                $casts_code .= "\t\t'$key' => '$cast', " . PHP_EOL;
            }
            $casts_code = rtrim($casts_code, ", " . PHP_EOL);
            $casts_code = trim($casts_code);
            $file = str_replace("{{casts}}", $casts_code, $file);

            $lengths_code = "";
            foreach ($lengths as $key => $length) {
                $lengths_code .= "\t\t'$key' => '$length', " . PHP_EOL;
            }
            $lengths_code = rtrim($lengths_code, ", " . PHP_EOL);
            $lengths_code = trim($lengths_code);
            $file = str_replace("{{lengths}}", $lengths_code, $file);

            $defaults_code = "";
            foreach ($defaults as $key => $default) {
                $defaults_code .= "\t\t'$key' => '$default', " . PHP_EOL;
            }
            $defaults_code = rtrim($defaults_code, ", " . PHP_EOL);
            $defaults_code = trim($defaults_code);
            $file = str_replace("{{defaults}}", $defaults_code, $file);

            $enumerables_code = "";
            foreach ($enumerables as $key => $enumerable) {
                $enumerables_code .= "\t\t'$key' => " . json_encode($enumerable) . ", " . PHP_EOL;
            }
            $enumerables_code = rtrim($enumerables_code, ", " . PHP_EOL);
            $enumerables_code = trim($enumerables_code);
            $file = str_replace("{{enumerables}}", $enumerables_code, $file);



            $attributes_code = "";
            $appends = "\tpublic \$appends = [";

            $attributes = $config['attributes'] ?? [];
            foreach ($attributes as $key => $attribute) {
                $methodName = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
                
                $script = $attribute;
                
                // Process all placeholders in a single pass to avoid conflicts
                // First pass: Replace relationship references (e.g., {student.full_name})
                $script = preg_replace_callback('/\{([a-zA-Z_]+)\.([a-zA-Z_]+)\}/', function($matches) {
                    $relationName = $matches[1];
                    $relationField = $matches[2];
                    return "{\$this->{$relationName}?->{$relationField}}";
                }, $script);
                
                // Second pass: Replace direct property references (e.g., {semester}, {school_year})
                // Only match simple {property} patterns (no $ or ? characters after the opening brace)
                $script = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function($matches) {
                    return "{\$this->{$matches[1]}}";
                }, $script);
                
                $script = str_replace('"', '\"', $script); // escape double quotes
                $attributes_code .= "\tpublic function get{$methodName}Attribute(): string\n\t{\n\t\treturn \"{$script}\";\n\t}\n\n\t";
                
                $appends .= "'{$key}', ";
            }

            $appends = rtrim($appends, ", ");
            $appends .= "];";
            $attributes_code .= $appends;

            $file = str_replace("{{attributes}}", $attributes_code, $file);

            $imports = "";
            $relationship_code = "";
            $joins = "\tpublic array \$join = [";

            $relationships = $config['relationship'] ?? [];
            foreach ($relationships as $key => $relationship) {
                $fk = "{$key}_id";
                $found = false;
                foreach ($config['columns'] as $column) {
                    if ($column['name'] === $fk) {
                        $found = true;
                        break;
                    }
                }

                $codeName = str_replace(' ', '', properName($key));

                $joins .= "'{$key}', ";
                $imports .= "use App\Models\\" . $codeName . ";\n";

                if ($found) {
                    //Swap to belong to
                    $relationship_code .= "public function {$key}(): BelongsTo {\n\t\treturn \$this->belongsTo(" . $codeName . "::class, '$fk', '$fk');\n\t}\n\n\t";
                } else {
                    //Explicate Relationship
                    if ($relationship === 'one') {
                        $relationship_code .= "public function {$key}(): HasOne {\n\t\treturn \$this->hasOne(" . $codeName . "::class, '" . $config['name'] . "_id" . "', '" . $config['name'] . "_id" . "');\n\t}\n\n\t";
                    } else {
                        $relationship_code .= "public function {$key}(): HasMany {\n\t\treturn \$this->hasMany(" . $codeName . "::class, '" . $config['name'] . "_id" . "', '" . $config['name'] . "_id" . "');\n\t}\n\n\t";
                        //                        $relationship_code .= "public function " . pluralize($key) . "(): HasMany {\n\t\treturn \$this->hasMany(" . properName($key) . "::class, '" . $config['name'] . "_id" . "', '" . $config['name'] . "_id" . "');\n\t}\n\n\t";
                    }
                }
            }

            $relationship_code = rtrim($relationship_code, "\t");
            $joins = rtrim($joins, ", ");
            $joins .= "];";
            $relationship_code .= $joins;

            $imports = rtrim($imports, "\n");

            $file = str_replace("{{relationship}}", $relationship_code, $file);
            $file = str_replace("{{imports}}", $imports, $file);
            $file = removeLinesWithBrackets($file);

            $file = str_replace("\n\n\n\n\n\n", "\n\n", $file);
            $file = str_replace("\n\n\n\n\n", "\n\n", $file);
            $file = str_replace("\n\n\n\n", "\n\n", $file);
            $file = str_replace("\n\n\n", "\n\n", $file);

            $file = str_replace("['", "[\n\t\t'", $file);
            $file = str_replace("']", "'\n\t]", $file);


            self::makeMod($config['name'], $file, true);
        } catch (\Exception $e) {
            $log[] = "Error: " . $e->getMessage(); // Add to log array first
            Log::error($e->getMessage());
        } finally {
            if (!empty($log)) {
                file_put_contents(base_path("core/logs/" . date("Ymd_his") . "_" . $config['name'] . ".log"), implode(PHP_EOL, $log));
            }
            file_put_contents(base_path('core/' . $config['name'] . '.json'), json_encode($config, JSON_PRETTY_PRINT));
            return $config;
        }
    }

    public static function makeColumns($config_name, $columns): array
    {
        $comments = [];
        try {
            $columns = self::cleanColumns($columns);
            $config = json_decode(file_get_contents(base_path("core/$config_name.json")), true);

            $cols = $config['columns'];

            foreach ($columns as $column) {
                $col_data = self::columnAnalyzer($column);

                foreach ($cols as $key => $col) {
                    if ($col['name'] === $col_data['column_name']) {
                        throw new \Exception("Column " . $col['name'] . " already exists.");
                    }
                }

                $cols[] = self::columnGenerator($col_data['column_name'], $col_data['type'], $col_data['key']);
            }

            $config['columns'] = $cols;

            self::backupConfig($config_name);
            $file = fopen(base_path("core/$config_name.json"), "w");
            fwrite($file, json_encode($config, JSON_PRETTY_PRINT));

            echo "Config Column: " . implode(",", $columns) . " has been added to $config_name.json" . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to create column: $config_name. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    public static function cleanConfig(array $config): array
    {
        $log = [];
        try {
            //Ensure URLs are properly configured
            $template = json_decode(file_get_contents(__DIR__ . "/mods/config.json"), true);
            if (!isset($config['urls']) || empty($config['urls'])) {
                $config['urls'] = $template['urls'];
                $log[] = "URLs section was missing and has been added from template.";
            }

            // Replace 'demo' with actual config name in all URLs
            foreach ($config['urls'] as $key => $url) {
                $config['urls'][$key] = str_replace('demo', $config['name'], $url);
            }

            // Ensure all URL keys from template exist
            foreach ($template['urls'] as $key => $url) {
                if (!isset($config['urls'][$key])) {
                    $config['urls'][$key] = str_replace('demo', $config['name'], $url);
                    $log[] = "Missing URL key '$key' has been added.";
                }
            }

            //Check if it supports timestamp
            if ($config['timestamps']) {
                $created_at = false;
                $updated_at = false;
                foreach ($config['columns'] as $column) {
                    if ($column['name'] === 'created_at') $created_at = true;
                    if ($column['name'] === 'updated_at') $updated_at = true;
                }

                if (!$created_at) {
                    $log[] = "created_at column has been created since timestamp is enabled.";
                    $config['columns'][] = self::columnGenerator('created_at', 'datetime');
                }
                if (!$updated_at) {
                    $log[] = "updated_at column has been created since timestamp is enabled.";
                    $config['columns'][] = self::columnGenerator('updated_at', 'datetime');
                }
            } else {
                foreach ($config['columns'] as $key => $column) {
                    if ($column['name'] === 'created_at' || $column['name'] === 'updated_at') {
                        unset($config['columns'][$key]); // This modifies the original array
                    }
                }
            }
            $config['columns'] = array_values($config['columns']);

            //Check if supports soft delete
            if ($config['soft_delete']) {
                $deleted_at = false;
                foreach ($config['columns'] as $column) {
                    if ($column['name'] === 'deleted_at') $deleted_at = true;
                }

                if (!$deleted_at) {
                    $log[] = "deleted_at column has been created since soft delete is enabled.";
                    $config['columns'][] = self::columnGenerator('deleted_at', 'datetime');
                }
            } else {
                foreach ($config['columns'] as $key => $column) {
                    if ($column['name'] === 'deleted_at') {
                        unset($config['columns'][$key]); // This modifies the original array
                    }
                }
            }

            //Auto-tag columns ending with "_id" (excluding primary key) as foreign fields
            foreach ($config['columns'] as $key => $column) {
                if (!isset($column['name'])) continue;

                // Skip if already marked as primary or foreign
                if ($column['backend']['primary'] ?? false) continue;

                // Check if column name ends with "_id"
                if (str_ends_with($column['name'], '_id')) {
                    // Auto-tag as foreign field
                    $config['columns'][$key]['backend']['foreign'] = true;
                    $config['columns'][$key]['backend']['nullable'] = false;

                    if (($column['frontend']['form_control'] ?? '') === 'multiselect') {
                        $config['columns'][$key]['backend']['type'] = 'json';
                    } else {
                        $config['columns'][$key]['backend']['type'] = 'int';
                        $config['columns'][$key]['frontend']['form_control'] = 'select';
                    }

                    $log[] = "Auto-tagged {$column['name']} as foreign field.";
                }
            }

            //Auto-configure foreign columns
            foreach ($config['columns'] as $key => $column) {
                if (!isset($column['name'])) continue;

                // Check if column is marked as foreign
                if (isset($column['backend']['foreign']) && $column['backend']['foreign'] === true) {
                    if (($column['frontend']['form_control'] ?? '') === 'multiselect') {
                        $config['columns'][$key]['backend']['type'] = 'json';
                    } else {
                        $config['columns'][$key]['backend']['type'] = 'int';
                        $config['columns'][$key]['frontend']['form_control'] = 'select';
                    }
                }
            }

            //Auto-prettify and configure column frontend properties
            foreach ($config['columns'] as $key => $column) {
                if (!isset($column['name'])) continue;

                $prettified = properName($column['name']);
                $prettified = rtrim($prettified, ' Id');

                // Auto-set table.key if it contains "column"
                if (isset($column['frontend']['table']['key']) &&
                    str_contains(strtolower($column['frontend']['table']['key']), 'column')) {
                    $config['columns'][$key]['frontend']['table']['key'] = $column['name'];
                    $log[] = "Auto-set table.key for {$column['name']}.";
                }

                // Auto-set view.key if it contains "column"
                if (isset($column['frontend']['view']['key']) &&
                    str_contains(strtolower($column['frontend']['view']['key']), 'column')) {
                    $config['columns'][$key]['frontend']['view']['key'] = $column['name'];
                    $log[] = "Auto-set view.key for {$column['name']}.";
                }

                // Auto-prettify table.label if it contains "column"
                if (isset($column['frontend']['table']['label']) &&
                    str_contains(strtolower($column['frontend']['table']['label']), 'column')) {
                    $config['columns'][$key]['frontend']['table']['label'] = $prettified;
                    $log[] = "Auto-prettified table.label for {$column['name']} to '{$prettified}'.";
                }

                // Auto-prettify view.label if it contains "column"
                if (isset($column['frontend']['view']['label']) &&
                    str_contains(strtolower($column['frontend']['view']['label']), 'column')) {
                    $config['columns'][$key]['frontend']['view']['label'] = $prettified;
                    $log[] = "Auto-prettified view.label for {$column['name']} to '{$prettified}'.";
                }

                // Auto-prettify text.label if it contains "Column"
                if (isset($column['frontend']['text']['label']) &&
                    str_contains($column['frontend']['text']['label'], 'Column')) {
                    $config['columns'][$key]['frontend']['text']['label'] = $prettified;
                    $log[] = "Auto-prettified text.label for {$column['name']} to '{$prettified}'.";
                }
            }

            //Check for duplicated columns
            $names = [];
            $duplicates = [];

            foreach ($config['columns'] as $item) {
                if (!isset($item['name'])) continue;

                $name = $item['name'];

                if (isset($names[$name])) {
                    $duplicates[] = $name;
                } else {
                    $names[$name] = true;
                }
            }
            if (!empty($duplicates)) {
                throw new \Exception("Duplicate columns found: " . implode(', ', array_unique($duplicates)));
            }

            //Check for roles existence
            if (!self::checkRolesExistence($config['ui']['context']))
                throw new \Exception("Roles not found: " . implode(', ', $config['ui']['context']));

            foreach (self::$special_columns as $special_column) {
                foreach ($config['columns'] as $key => $column) {
                    if ($column['name'] === $special_column) {
                        unset($config['columns'][$key]);

                        $column['backend']['fillable'] = false;
                        $column['backend']['nullable'] = false;
                        $config['columns'][] = $column;
                    }
                }
            }

            $config['columns'] = array_values($config['columns']);
        } catch (\Exception $e) {
            dd("Failed to clean config (" . $config['name'] . "): " . $e->getMessage());
        } finally {
            if (!empty($log)) {
                file_put_contents(base_path("core/logs/" . date("Ymd_his") . "_" . $config['name'] . ".log"), implode(PHP_EOL, $log));
            }
            file_put_contents(base_path('core/' . $config['name'] . '.json'), json_encode($config, JSON_PRETTY_PRINT));
            return $config;
        }
    }

    private static function backupConfig($config)
    {
        if (file_exists(base_path("core/$config.json"))) {
            copy(base_path("core/$config.json"), base_path("core/backups/$config" . "-" . date("YmdHis") . ".json"));
        }
    }

    public static function rollbackConfig($config): array
    {
        $corePath = base_path("core");
        $backupPath = "$corePath/backups";

        $comments = [];
        try {
            $pattern = "$backupPath/$config-*.json";
            $backupFiles = glob($pattern);

            if (!empty($backupFiles)) {
                usort($backupFiles, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                $latestBackup = $backupFiles[0];
                copy($latestBackup, "$corePath/$config.json");
                unlink($latestBackup);
                echo "Config: $config.json has been rolled back to the latest backup." . PHP_EOL;
            } else {
                echo "Config: $config.json has no backups.";
            }
        } catch (\Exception $e) {
            echo "Failed to rollback config: $config. Error: " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    private static function cleanColumns($columns): array
    {
        try {
            // Check if $columns is already an array, if so, return it as is
            if (is_array($columns)) {
                return array_map('trim', $columns);  // If it's already an array, trim each element
            }

            // Make sure $columns is a string before calling explode
            if (is_string($columns)) {
                $columns = explode(',', $columns);
                foreach ($columns as $key => $column) {
                    $columns[$key] = trim($column); // Trim each column
                }
            } else {
                throw new \Exception("Expected a string or array for columns.");
            }

            return $columns;
        } catch (\Exception $e) {
            Log::debug($e->getMessage()); // Log the exception message
            return []; // Return an empty array in case of an error
        }
    }

    private static function checkRolesExistence($roles_array): bool
    {
        $roles = getRoles();
        foreach ($roles_array as $role) {
            if (!in_array($role, $roles)) {
                return false;
            }
        }
        return true;
    }

    public static function separator()
    {
        for ($i = 0; $i < 150; $i++)
            echo "=";
        echo PHP_EOL;
    }

    private static function copyFakerFiles(): void
    {
        $source = __DIR__ . '/faker';
        $destination = public_path('storage/files/faker');

        // Check if faker files already exist by looking for a specific file
        if (!file_exists($destination . '/fake.pdf')) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }
            self::recursiveCopy($source, $destination);
            echo "Faker files copied to public/storage/files/faker" . PHP_EOL;
        }
    }

    private static function recursiveCopy($source, $destination): void
    {
        $dir = opendir($source);
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($srcPath)) {
                self::recursiveCopy($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }

    private static function createFactory($config)
    {
        $json = $config;

        $modelName = ucfirst($json['name']);
        $columns = $json['columns'];

        $modelName = properName($modelName);
        $modelName = str_replace(" ", "", $modelName);

        $factoryClass = "{$modelName}Factory";

        $stub = "<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\\$modelName;

/**
 * @extends \\Illuminate\\Database\\Eloquent\\Factories\\Factory<\\App\\Models\\$modelName>
 */
class $factoryClass extends Factory
{
    protected \$model = $modelName::class;

    public function definition(): array
    {
        return [
{{FIELDS}}
        ];
    }
}
";

        $fieldMappings = [];

        foreach ($columns as $column) {
            if ($column['backend']['primary'] || in_array($column['name'], ['created_at', 'updated_at'])) {
                continue;
            }

            $field = $column['name'];
            $type = $column['backend']['type'];
            $formControl = $column['frontend']['form_control'] ?? '';

            /**
             * Handle file controls (single or multiple based on controlSettings.file.fileMultiple)
             */
            if ($formControl === 'file') {
                $isMultiple = $column['frontend']['controlSettings']['file']['fileMultiple'] ?? false;
                if ($isMultiple) {
                    $fieldMappings[] = "            '$field' => [],";
                } else {
                    $fieldMappings[] = "            '$field' => null,";
                }
                continue;
            }

            // Handle number control with number settings
            if ($formControl === 'number' || $formControl === 'range') {
                $min = $column['frontend']['controlSettings']['number']['numberMin'] ?? 0;
                $max = $column['frontend']['controlSettings']['number']['numberMax'] ?? 100;
                $step = $column['frontend']['controlSettings']['number']['numberStep'] ?? 1;
                
                // Determine if we need decimal precision
                if ($step < 1 || in_array($type, ['float', 'double', 'decimal'])) {
                    $decimals = 2; // default
                    if ($step < 1) {
                        $stepStr = (string)$step;
                        if (strpos($stepStr, '.') !== false) {
                            $decimals = strlen($stepStr) - strpos($stepStr, '.') - 1;
                        }
                    }
                    $faker = "->randomFloat($decimals, $min, $max)";
                } else {
                    $faker = "->numberBetween($min, $max)";
                }
                
                if (!$column['backend']['foreign']) {
                    $fieldMappings[] = "            '$field' => \$this->faker$faker,";
                    continue;
                }
            }

            // Handle option-based controls (select, multiselect, radio, checkbox, checkbox_group, toggle)
            // Only for fields that don't end with "_id" (which are FK/PK)
            if (!str_ends_with($field, '_id') && in_array($formControl, ['select', 'multiselect', 'radio', 'checkbox_group', 'toggle'])) {
                $options = [];
                
                // Extract options based on control type
                if ($formControl === 'select') {
                    $options = $column['frontend']['controlSettings']['select']['selectOptions'] ?? [];
                } elseif ($formControl === 'multiselect') {
                    $options = $column['frontend']['controlSettings']['multiselect']['multiSelectOptions'] ?? [];
                } elseif ($formControl === 'radio') {
                    $options = $column['frontend']['controlSettings']['radio']['radioOptions'] ?? [];
                } elseif ($formControl === 'checkbox_group') {
                    $options = $column['frontend']['controlSettings']['checkbox_group']['checkboxGroupOptions'] ?? [];
                } elseif ($formControl === 'toggle') {
                    // Toggle typically has on/off values from toggleLabels
                    $toggleLabels = $column['frontend']['controlSettings']['toggle']['toggleLabels'] ?? ['on' => 'On', 'off' => 'Off'];
                    $options = [
                        ['value' => true, 'label' => $toggleLabels['on']],
                        ['value' => false, 'label' => $toggleLabels['off']]
                    ];
                }
                
                // Filter out null or placeholder options
                $validOptions = array_filter($options, function($opt) {
                    return isset($opt['value']) && $opt['value'] !== null && $opt['label'] !== 'Please select';
                });
                
                if (!empty($validOptions)) {
                    // For multiselect and checkbox_group, return array of random selections
                    if (in_array($formControl, ['multiselect', 'checkbox_group'])) {
                        $minSelections = 1;
                        $maxSelections = min(3, count($validOptions));
                        
                        if ($formControl === 'multiselect') {
                            $minSelections = $column['frontend']['controlSettings']['multiselect']['multiSelectMinSelections'] ?? 1;
                            $maxSelections = $column['frontend']['controlSettings']['multiselect']['multiSelectMaxSelections'] ?? min(5, count($validOptions));
                        } elseif ($formControl === 'checkbox_group') {
                            $minSelections = $column['frontend']['controlSettings']['checkbox_group']['checkboxGroupMinSelections'] ?? 1;
                            $maxSelections = $column['frontend']['controlSettings']['checkbox_group']['checkboxGroupMaxSelections'] ?? min(3, count($validOptions));
                        }
                        
                        $valuesList = array_map(function($opt) {
                            $val = $opt['value'];
                            return is_bool($val) ? ($val ? 'true' : 'false') : (is_numeric($val) ? $val : "'$val'");
                        }, array_values($validOptions));
                        
                        $faker = "->randomElements([" . implode(', ', $valuesList) . "], \$this->faker->numberBetween($minSelections, $maxSelections))";
                    } else {
                        // For select, radio, toggle - return single value
                        $valuesList = array_map(function($opt) {
                            $val = $opt['value'];
                            return is_bool($val) ? ($val ? 'true' : 'false') : (is_numeric($val) ? $val : "'$val'");
                        }, array_values($validOptions));
                        
                        $faker = "->randomElement([" . implode(', ', $valuesList) . "])";
                    }
                    
                    $fieldMappings[] = "            '$field' => \$this->faker$faker,";
                    continue;
                }
            }

            // Handle checkbox control (single checkbox with checked/unchecked values)
            if ($formControl === 'checkbox' && !str_ends_with($field, '_id')) {
                $checkedValue = $column['frontend']['controlSettings']['checkbox']['checkboxCheckedValue'] ?? true;
                $uncheckedValue = $column['frontend']['controlSettings']['checkbox']['checkboxUncheckedValue'] ?? false;
                
                $checkedStr = is_bool($checkedValue) ? ($checkedValue ? 'true' : 'false') : (is_numeric($checkedValue) ? $checkedValue : "'$checkedValue'");
                $uncheckedStr = is_bool($uncheckedValue) ? ($uncheckedValue ? 'true' : 'false') : (is_numeric($uncheckedValue) ? $uncheckedValue : "'$uncheckedValue'");
                
                $faker = "->randomElement([$checkedStr, $uncheckedStr])";
                $fieldMappings[] = "            '$field' => \$this->faker$faker,";
                continue;
            }

            // Standard faker logic
            switch ($type) {
                case 'string':
                    if (!empty($column['backend']['options'])) {
                        $options = array_map(fn($v) => "'$v'", $column['backend']['options']);
                        $faker = '->randomElement([' . implode(', ', $options) . '])';
                        break;
                    }

                    if (str_contains($field, 'name') && str_contains($field, 'first')) {
                        $faker = '->firstName()';
                    } elseif (str_contains($field, 'name') && str_contains($field, 'last')) {
                        $faker = '->lastName()';
                    } elseif (str_contains($field, 'name') && str_contains($field, 'middle')) {
                        $faker = '->lastName()';
                    } elseif (str_contains($field, 'name')) {
                        $faker = '->name()';
                    } elseif (str_contains($field, 'email')) {
                        $faker = '->unique()->safeEmail()';
                    } elseif (str_contains($field, 'address')) {
                        $faker = '->address()';
                    } else {
                        $faker = '->word()';
                    }
                    break;

                case 'int':
                case 'integer':
                    // Use number settings from column if available
                    $min = $column['frontend']['controlSettings']['number']['numberMin'] ?? 1;
                    $max = $column['frontend']['controlSettings']['number']['numberMax'] ?? 100;
                    $faker = "->numberBetween($min, $max)";
                    break;

                case 'float':
                case 'double':
                case 'decimal':
                    // Use number settings from column if available
                    $min = $column['frontend']['controlSettings']['number']['numberMin'] ?? 0;
                    $max = $column['frontend']['controlSettings']['number']['numberMax'] ?? 100;
                    $step = $column['frontend']['controlSettings']['number']['numberStep'] ?? 1;
                    
                    // Determine decimal places from step
                    $decimals = 2; // default
                    if ($step < 1) {
                        $stepStr = (string)$step;
                        if (strpos($stepStr, '.') !== false) {
                            $decimals = strlen($stepStr) - strpos($stepStr, '.') - 1;
                        }
                    }
                    
                    $faker = "->randomFloat($decimals, $min, $max)";
                    break;

                case 'date':
                    $faker = '->date()';
                    break;

                case 'datetime':
                    $faker = '->dateTime()';
                    break;

                case 'boolean':
                    $faker = '->boolean()';
                    break;

                default:
                    $faker = '->word()';
            }

            if ($column['backend']['foreign']) {
                $fieldProper = properName(ucfirst(str_replace('_id', '', $field)));
                $fieldCode = str_replace(' ', '', properName($fieldProper));
                $faker = '=> \\App\\Models\\' . $fieldCode . '::get([\'' . $field . '\'])->random()';
                $fieldMappings[] = "            '$field' $faker,";
            } else {
                $fieldMappings[] = "            '$field' => \$this->faker$faker,";
            }
        }

        $filledStub = str_replace('{{FIELDS}}', implode("\n", $fieldMappings), $stub);

        $outputDir = base_path('database/factories');
        $filePath = "$outputDir/{$factoryClass}.php";
        file_put_contents($filePath, $filledStub);

        echo "Factory generated at: $filePath" . PHP_EOL;
    }


    public static function createMigration($config)
    {
        $json = $config;

        $table = $json['name'];
        $className = 'Create' . ucfirst($table) . 'Table';
        $columns = $json['columns'];
        $timestamps = $json['timestamps'] ?? false;
        $softDelete = $json['soft_delete'] ?? false;

        $fieldsCode = '';

        foreach ($columns as $column) {
            $name = $column['name'];
            $type = $column['backend']['type'];
            $isNullable = $column['backend']['nullable'];
            $isPrimary = $column['backend']['primary'];

            // Skip timestamps (handled separately)
            if (in_array($name, ['created_at', 'updated_at']) && $timestamps) continue;

            // Determine column type
            switch ($type) {
                case 'int':
                case 'integer':
                    $line = "\$table->integer('$name')";
                    break;

                case 'string':
                    $line = "\$table->string('$name')";
                    break;

                case 'text':
                    $line = "\$table->text('$name')";
                    break;

                case 'boolean':
                    $line = "\$table->boolean('$name')";
                    break;

                case 'date':
                    $line = "\$table->date('$name')";
                    break;

                case 'datetime':
                    $line = "\$table->dateTime('$name')";
                    break;

                case 'float':
                case 'double':
                    $line = "\$table->float('$name')";
                    break;

                default:
                    $line = "\$table->string('$name')";
            }

            if ($isPrimary) {
                $line = "\$table->id('$name')";
            }

            if ($isNullable) {
                $line .= "->nullable()";
            }

            $fieldsCode .= "            $line;\n";
        }

        if ($timestamps) {
            $fieldsCode .= "            \$table->timestamps();\n";
        }

        if ($softDelete) {
            $fieldsCode .= "            \$table->softDeletes();\n";
        }

        $migration = "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('$table', function (Blueprint \$table) {
$fieldsCode
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('$table');
    }
};
";

        // Ensure directory exists
        $outputDir = base_path('database/migrations');
        $timestamp = date('Y_m_d_His');
        $fileName = "___create_{$table}_table___.php";
        file_put_contents("$outputDir/$fileName", $migration);

        echo "Migration created: $outputDir/$fileName\n";
    }

    private static function removeMigrationFiles(): void
    {
        $directory = base_path('database/migrations');
        $files = scandir($directory);

        foreach ($files as $file) {
            // Skip . and ..
            if ($file === '.' || $file === '..') continue;

            $fullPath = $directory . DIRECTORY_SEPARATOR . $file;

            // Check if it's a file and contains "___"
            if (is_file($fullPath) && str_contains($file, '___') && !str_contains($file, '___create_audit_log___')) {
                if (!unlink($fullPath)) {
                    echo "Failed to delete: $file\n";
                }
            }
        }
    }

    public static function getSortedConfigurations(): array
    {
        $dependencies = [];

        $path = base_path('core');
        $jsonFiles = glob($path . '/*.json');
        $ignored_configs = json_decode(file_get_contents("$path/__ignored_configs.json"), true);

        foreach ($jsonFiles as $file) {

            if (str_contains($file, "__")) continue;
            $config = json_decode(file_get_contents($file), true);
            if (!in_array($config['name'], $ignored_configs)) {
                $dependency = [];
                $columns = $config['columns'];
                foreach ($columns as $column) {
                    if ($column['backend']['foreign']) {
                        $dependency[] = str_replace("_id", "", $column['name']);
                    }
                }
                $dependencies[$config['name']] = $dependency;
            }
        }

        $resolved = [];
        $seen = [];

        $visit = function ($model) use (&$visit, &$resolved, &$seen, $dependencies) {
            if (isset($seen[$model])) {
                if ($seen[$model] === 'visiting') {
                    throw new \Exception("Cyclic dependency detected at $model");
                }
                return;
            }

            $seen[$model] = 'visiting';

            foreach ($dependencies[$model] ?? [] as $dep) {
                $visit($dep);
            }

            $seen[$model] = 'visited';
            $resolved[] = $model;
        };

        foreach (array_keys($dependencies) as $model) {
            $visit($model);
        }

        return $resolved;
    }

    public static function runSeeder($command): void
    {
        $sortedConfigs = Command::getSortedConfigurations();

        foreach ($sortedConfigs as $configName) {
            $modelClass = 'App\\Models\\' . Str::studly($configName);
            $config = json_decode(file_get_contents(base_path("core/$configName.json")), true);
            $seed = env('SEEDER_COUNT', 10); // Default seed count
            if (array_key_exists('seed', $config)) {
                $seed = intval($config['seed']);
            }

            if (class_exists($modelClass)) {
                if ($modelClass::count() === 0) {
                    $modelClass::factory()->count($seed)->create();
                    $command->info("✅Seeded $seed entries: $modelClass");
                } else {
                    $command->info("ℹ️Already seeded: $modelClass");
                }
            } else {
                $command->warn("⚠️Model not found for config: $configName");
            }
        }
    }
}
