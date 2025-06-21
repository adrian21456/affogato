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
    }


    public static function makeConfig($config_name, $columns, $type = 'int'): array
    {
        $comments = [];
        try {
            if (file_exists(base_path("core/$config_name.json"))) {
                throw new \Exception("Config: $config_name.json already exists.");
            }

            $columns = self::cleanColumns($columns);
            $config = json_decode(file_get_contents(__DIR__ . "/mods/config.json"), true);

            //Change Contents
            $id = $config_name . "_id";
            $proper_name = properName($config_name);
            $plural_name = pluralize($proper_name);

            $config['name'] = $config_name;
            $config['representative_value'] = $id;
            $config['ui']['page_name'] = $plural_name;

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

    public static function makeAttribute($config_name, $__column): array
    {
        $comments = [];
        try {
            $path = base_path("core/$config_name.json");
            $config = json_decode(file_get_contents($path), true);
            $cols = $config['columns'];
            $attribute_template = json_decode(file_get_contents(__DIR__ . ("mods/column_attributes.json")), true);

            $found = false;

            foreach ($cols as $index => $column) {
                if ($column['name'] === $__column) {
                    $found = true;

                    // Ensure nesting exists
                    if (!isset($column['ui'])) $column['ui'] = [];
                    if (!isset($column['ui']['default'])) $column['ui']['default'] = [];
                    if (!isset($column['ui']['default']['attributes'])) $column['ui']['default']['attributes'] = [];

                    $attributes = $column['ui']['default']['attributes'];

                    if (in_array($attribute_template, $attributes)) {
                        throw new \Exception("An attribute template already exists.");
                    }

                    // Append attribute
                    $column['ui']['default']['attributes'][] = $attribute_template;
                    $cols[$index] = $column;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("Column '$__column' not found in configuration.");
            }

            $config['columns'] = $cols;

            self::backupConfig($config_name);
            file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));

            echo "Attribute template added to '{$__column}' in '{$config_name}.json'." . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to update column '{$__column}' in '{$config_name}.json': " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }

    public static function makeClass($config_name, $__column): array
    {
        $comments = [];
        try {
            $configPath = base_path("core/$config_name.json");
            $config = json_decode(file_get_contents($configPath), true);
            $cols = $config['columns'];
            $class_template = json_decode(file_get_contents(__DIR__ . ("/mods/column_class.json")), true);

            $found = false;

            foreach ($cols as $index => $column) {
                if ($column['name'] === $__column) {
                    $found = true;

                    // Ensure nesting exists
                    if (!isset($column['ui'])) $column['ui'] = [];
                    if (!isset($column['ui']['default'])) $column['ui']['default'] = [];
                    if (!isset($column['ui']['default']['classes'])) $column['ui']['default']['classes'] = [];

                    $classes = $column['ui']['default']['classes'];

                    // Prevent duplicate
                    if (in_array($class_template, $classes)) {
                        throw new \Exception("A class template already exists.");
                    }

                    // Add the class template
                    $column['ui']['default']['classes'][] = $class_template;
                    $cols[$index] = $column;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("Column '$__column' not found in configuration.");
            }

            $config['columns'] = $cols;

            // Backup and save
            self::backupConfig($config_name);
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

            echo "Class template added to '{$__column}' in '{$config_name}.json'." . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to update column '{$__column}' in '{$config_name}.json': " . $e->getMessage() . PHP_EOL;
        } finally {
            return $comments;
        }
    }


    public static function makeValidation($config_name, $__column): array
    {
        $comments = [];
        try {
            $path = base_path("core/$config_name.json");
            $config = json_decode(file_get_contents($path), true);
            $cols = $config['columns'];

            $validation_template = json_decode(file_get_contents(__DIR__ . ("/mods/column_validation.json")), true);

            $found = false;

            foreach ($cols as $index => $column) {
                if ($column['name'] === $__column) {
                    $found = true;

                    // Ensure the 'ui.default.validations' path exists
                    if (!isset($column['ui'])) $column['ui'] = [];
                    if (!isset($column['ui']['default'])) $column['ui']['default'] = [];
                    if (!isset($column['ui']['default']['validations'])) $column['ui']['default']['validations'] = [];

                    // Prevent duplicate validation templates
                    if (in_array($validation_template, $column['ui']['default']['validations'])) {
                        throw new \Exception("A validation template already exists.");
                    }

                    // Append the validation
                    $column['ui']['default']['validations'][] = $validation_template;

                    $cols[$index] = $column;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("Column '$__column' not found in configuration.");
            }

            $config['columns'] = $cols;

            // Backup and save
            self::backupConfig($config_name);
            file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));

            echo "Validation template added to '{$__column}' in '{$config_name}.json'." . PHP_EOL;
        } catch (\Exception $e) {
            echo "Failed to update column '{$__column}' in '{$config_name}.json': " . $e->getMessage() . PHP_EOL;
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

            $column['type'] = $type;
            $column['ui']['default']['table_label'] = rtrim($proper_name, " Id");
            $column['ui']['default']['form_label'] =  rtrim($proper_name, " Id");
            $column['name'] = $column_name;

            if ($key === 'primary') {
                $column['primary'] = true;
                $column['fillable'] = false;
                $column['nullable'] = false;
                $column['ui']['default']['hidden'] = true;
                $column['type'] = 'int';
            }

            if ($key === 'foreign') {
                $column['foreign'] = true;
                $column['nullable'] = false;
                $column['type'] = 'int';
            }

            if ($type === 'file') {
                $column['type'] = 'string';
                $column['control'] = 'file';
            }

            if ($type === 'file_multiple') {
                $column['type'] = 'string';
                $column['control'] = 'file_multiple';
            }

            if (in_array($column_name, self::$special_columns)) {
                $column['required'] = false;
                $column['nullable'] = false;
                $column['fillable'] = false;
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
                    'i' => 'int',
                    'd' => 'date',
                    't' => 'time',
                    'b' => 'boolean',
                    'dt' => 'datetime',
                    's' => 'string',
                    'f' => 'file',
                    'fm' => 'file_multiple',
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
                echo "MOD: $modName already exists" . PHP_EOL;
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

            if (file_exists(app_path("Http/Controllers/Mods/$fileName"))) {
                //                echo "MOD: $controllerName already exists" . PHP_EOL;
            } else {
                $file = file_get_contents(__DIR__ . ("/mods/ModController.php.txt")); // Example path
                $file = str_replace("demo", $lowerName, $file);
                $file = str_replace("ModDemo", $modName, $file);
                $file = str_replace("Demo", $properName, $file);
                $file = str_ireplace("ModMod", "Mod", $file);

                file_put_contents(app_path("Http/Controllers/Mods/$fileName"), $file);
                echo "MOD: $controllerName has been created in app/Http/Controllers/Mods" . PHP_EOL;
            }

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
                        if ($column['foreign']) {
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
                if ($column['primary']) {
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
                if ($column['fillable']) $fillables[] = $column['name'];
                if ($column['nullable']) $nullables[] = $column['name'];
                if (!empty($column['options'])) $enumerables[$column['name']] = $column['options'];
                if (str_contains($column['control'], 'file')) $files[$column['name']] = $column['file_types'];
                if (!empty($column['length'])) $lengths[$column['name']] = $column['length'];
                if (!empty($column['default'])) $defaults[$column['name']] = $column['default'];
                if (!in_array($column['name'], self::$special_columns)) $casts[$column['name']] = $column['type'];
                if (str_contains($column['control'], 'file')) $casts[$column['name']] = "array";
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
                $script = str_replace('{', '{$this->', $attribute);
                $script = str_replace('"', '\"', $script); // escape double quotes

                $attributes_code .= "public function get{$methodName}Attribute(): string\n\t{\n\t\treturn \"{$script}\";\n\t}\n\n";
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
                throw new \Exception("Roles not found: " . implode(', ', $config['context']));
            foreach ($config['columns'] as $column) {
                if (!self::checkRolesExistence($column['ui']['default']['table_context']))
                    throw new \Exception("Roles not found: " . implode(', ', $column['table_context']));
                if (!self::checkRolesExistence($column['ui']['default']['form_context']))
                    throw new \Exception("Roles not found: " . implode(', ', $column['form_context']));

                foreach ($column['ui']['default']['validations'] as $validation) {
                    if (!self::checkRolesExistence($validation['context']))
                        throw new \Exception("Roles not found: " . implode(', ', $validation['context']));
                }
                foreach ($column['ui']['default']['classes'] as $class) {
                    if (!self::checkRolesExistence($class['context']))
                        throw new \Exception("Roles not found: " . implode(', ', $class['context']));
                }
                foreach ($column['ui']['default']['attributes'] as $attribute) {
                    if (!self::checkRolesExistence($attribute['context']))
                        throw new \Exception("Roles not found: " . implode(', ', $attribute['context']));
                }
            }

            foreach (self::$special_columns as $special_column) {
                foreach ($config['columns'] as $key => $column) {
                    if ($column['name'] === $special_column) {
                        unset($config['columns'][$key]);

                        $column['fillable'] = false;
                        $column['nullable'] = false;
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
            if ($column['primary'] || in_array($column['name'], ['created_at', 'updated_at'])) {
                continue;
            }

            $field = $column['name'];
            $type = $column['type'];

            // Faker logic
            switch ($type) {
                case 'string':
                    // Use enum values if available
                    if (!empty($column['options'])) {
                        $options = array_map(fn($v) => "'$v'", $column['options']); // quote each string
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
                    $faker = '->numberBetween(1, 100)';
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

            // Foreign key factory relationship
            if ($column['foreign']) {
                $fieldProper = properName(ucfirst(str_replace('_id', '', $field)));
                $fieldCode = str_replace(' ', '', properName($fieldProper));
                $faker = '=> \\App\\Models\\' . $fieldCode . '::get([\'' . $field . '\'])->random()';
                $fieldMappings[] = "            '$field' $faker,";
            } else {
                $fieldMappings[] = "            '$field' => \$this->faker$faker,";
            }
        }

        $filledStub = str_replace('{{FIELDS}}', implode("\n", $fieldMappings), $stub);

        // Save the file
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
            $type = $column['type'];
            $isNullable = $column['nullable'];
            $isPrimary = $column['primary'];

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
            if (is_file($fullPath) && str_contains($file, '___')) {
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
                    if ($column['foreign']) {
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

            if (class_exists($modelClass)) {
                if ($modelClass::count() === 0) {
                    $modelClass::factory()->count(intval(env("SEEDER_COUNT", "10")))->create();
                    $command->info("✅Seeded: $modelClass");
                } else {
                    $command->info("ℹ️Already seeded: $modelClass");
                }
            } else {
                $command->warn("⚠️Model not found for config: $configName");
            }
        }
    }
}
