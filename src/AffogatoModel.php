<?php

namespace Zchted\Affogato;

use Illuminate\Database\Eloquent\Model;

class AffogatoModel extends Model
{
    public array $nullable = [];

    public array $enumerable = [];

    public array $files = [];

    public array $lengths = [];

    public array $defaults = [];

    public array $joins = [];


    public function generateStoreRule(array $additional_rules = []): array
    {
        $rules = [];

        foreach ($this->fillable as $field) {
            // For Store: Treat nullable fields like files as nullable
            if (in_array($field, $this->nullable)) {
                $rules[$field] = 'nullable';
            } else {
                // For non-nullable fields, enforce required validation
                $rules[$field] = 'required';
            }

            // Add length constraints if defined
            if (isset($this->lengths[$field])) {
                $rules[$field] .= '|max:' . $this->lengths[$field];
            }

            // Apply 'in' rule for enumerable fields
            if (isset($this->enumerable[$field])) {
                $rules[$field] .= '|in:' . implode(',', $this->enumerable[$field]);
            }

            // For file fields, apply mimes validation only if a file is provided (nullable)
            if (isset($this->files[$field])) {
                // Check if file formats are specified or if it's a wildcard '*'
                if ($this->files[$field] === '*' || empty($this->files[$field])) {
                    $rules[$field] .= '|nullable';
                } else {
                    $rules[$field] .= '|nullable|mimes:' . $this->files[$field];
                }
            }
        }

        foreach ($additional_rules as $key => $aRule) {
            $rules[$key] = $aRule;
        }
        return $rules;
    }

    public function generateUpdateRule(array $additional_rules = []): array
    {
        $rules = [];

        foreach ($this->fillable as $field) {
            // For Update: Treat nullable fields as nullable (not required unless provided)
            if (in_array($field, $this->nullable)) {
                $rules[$field] = 'nullable';
            } else {
                // For non-nullable fields, validate only if present (optional, but validated if provided)
                $rules[$field] = 'sometimes';
            }

            // Add length constraints if defined
            if (isset($this->lengths[$field])) {
                $rules[$field] .= '|max:' . $this->lengths[$field];
            }

            // Apply 'in' rule for enumerable fields
            if (isset($this->enumerable[$field])) {
                $rules[$field] .= '|in:' . implode(',', $this->enumerable[$field]);
            }

            // For file fields, apply mimes validation only if a file is provided (nullable)
            if (isset($this->files[$field])) {
                // Check if file formats are specified or if it's a wildcard '*'

                $column = getColumn($this->table, $field);

                if ($column['frontend']['form_control'] === 'file') {
                    if ($this->files[$field] === '*' || empty($this->files[$field])) {
                        $rules[$field] .= '|nullable';
                    } else {
                        $rules[$field] .= '|nullable|mimes:' . $this->files[$field];
                    }
                }

                if ($column['frontend']['form_control'] === 'file_multiple') {
                    if ($this->files[$field] === '*' || empty($this->files[$field])) {
                        $rules[$field . ".*"] = 'sometimes';
                    } else {
                        $rules[$field . ".*"] = 'sometimes|mimes:' . $this->files[$field];
                    }
                }
            }
        }

        foreach ($additional_rules as $key => $aRule) {
            $rules[$key] = $aRule;
        }
        //        dd($rules);
        return $rules;
    }

    public function generateFakeData(): array
    {
        $faker = \Faker\Factory::create();
        $fakeData = [];

        foreach ($this->fillable as $field) {
            $type = $this->casts[$field] ?? 'string';

            switch ($type) {
                case 'int':
                case 'integer':
                    $fakeData[$field] = $faker->numberBetween(1, 100);
                    break;

                case 'float':
                case 'double':
                    $fakeData[$field] = $faker->randomFloat(2, 1, 1000);
                    break;

                case 'boolean':
                    $fakeData[$field] = $faker->boolean;
                    break;

                case 'date':
                case 'datetime':
                    $fakeData[$field] = $faker->dateTime()->format('Y-m-d H:i:s');
                    break;

                case 'array':
                    $fakeData[$field] = [$faker->word, $faker->word];
                    break;

                case 'json':
                    $fakeData[$field] = json_encode(['key' => $faker->word]);
                    break;

                default: // Treat as string
                    if (isset($this->enumerable[$field]) && is_array($this->enumerable[$field])) {
                        $fakeData[$field] = $faker->randomElement($this->enumerable[$field]);
                    } else {
                        $fakeData[$field] = $faker->words(2, true);
                    }
                    break;
            }
        }

        return $fakeData;
    }
}
