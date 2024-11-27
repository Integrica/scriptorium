<?php

namespace Integrica\Scriptorium\Console;

use Illuminate\Support\Facades\File;
use Integrica\Scriptorium\Stringer;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class EnvUpdater
{
    protected string $filePath;

    protected array $values = [];

    public static function for(string $filePath): static
    {
        return app(static::class, ['filePath' => $filePath]);
    }

    public function __construct(string $filePath)
    {
        if (!app()->runningInConsole()) {
            throw new \Exception('This class can only be used in console.');
        }
        
        $this->filePath = $filePath;
    }

    public function text(string $key, string $label, string $default = null, bool $required = true): static
    {
        $value = text(
            label: $label,
            default: $default,
            required: $required);

        $this->values[$key] = $value;

        return $this;
    }

    public function bool(string $key, string $label, bool $default = null): static
    {
        $value = (select(
            label: $label,
            options: [
                'yes' => 'Yes', 
                'no' => 'No',
            ],
            default: $default ? 'yes' : 'no'
        ) === 'yes');

        $this->values[$key] = ($value ? 'true' : 'false');

        return $this;
    }

    public function selectOne(string $key, string $label, array $options, string $default = null, bool $required = true, string $customLabel = null): static
    {
        if (!array_key_exists($default, $options)) {
            $options[$default] = $default;
        }
        
        if (!blank($customLabel)) {
            $options['integrica-custom'] = 'Custom';
        }

        $value = select(
            label: $label,
            options: $options,
            default: $default,
            required: $required
        );

        if ($value === 'integrica-custom') {
            $value = text(
                label: $customLabel,
                default: $default,
                required: $required,
            );

            // TODO: add validation logic for entered value, maybe callback
        }

        $this->values[$key] = $value;

        return $this;
    }

    public function setChanges(array $changes): static
    {
        $this->values = $changes;

        return $this;
    }

    public function appendChanges(array $changes): static
    {
        $this->values = array_merge($this->values, $changes);

        return $this;
    }

    public function getChanges(): array
    {
        return $this->values;
    }

    public function dumpChanges(): static
    {
        dump($this->values);

        return $this;
    }

    public function clearChanges(): static
    {
        $this->values = [];

        return $this;
    }

    public function save(): bool
    {
        $stringer = Stringer::for($this->filePath);

        foreach ($this->values as $key => $value) {
            $stringer->replace($key . '=', $key . '=' . $this->ensureQuotedValue($value));
        }

        return $stringer->save();
    }
    
    protected function ensureQuotedValue(string $value): string
    {
        // Characters or conditions that require quoting
        $specialChars = [' ', '#', '=', '$', '\n', '"'];

        // Check if the value contains any special characters
        foreach ($specialChars as $char) {
            if (strpos($value, $char) !== false) {
                // Escape any existing double quotes in the value
                $escapedValue = str_replace('"', '\"', $value);
                return "\"$escapedValue\"";
            }
        }

        // Check for leading or trailing whitespace
        if (trim($value) !== $value) {
            $escapedValue = str_replace('"', '\"', $value);
            return "\"$escapedValue\"";
        }

        // If the value does not need quoting, return it as is
        return $value;
    }
}
