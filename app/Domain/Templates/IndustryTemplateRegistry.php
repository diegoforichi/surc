<?php

namespace App\Domain\Templates;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use JsonException;

class IndustryTemplateRegistry
{
    /** @var array<string, IndustryTemplateData>|null */
    protected ?array $packs = null;

    /**
     * @return array<string, IndustryTemplateData>
     */
    public function all(): array
    {
        return $this->packs ??= $this->load();
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (IndustryTemplateData $pack) => [$pack->key => $pack->name])
            ->sort()
            ->all();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function find(?string $key): IndustryTemplateData
    {
        $packs = $this->all();

        if ($key !== null && $key !== '' && isset($packs[$key])) {
            return $packs[$key];
        }

        if (isset($packs['generic'])) {
            return $packs['generic'];
        }

        throw new InvalidIndustryTemplateException('No existe el pack genérico en el directorio de plantillas.');
    }

    public function flush(): void
    {
        $this->packs = null;
    }

    public function path(): string
    {
        return (string) config('surc.industry_packs_path', database_path('industry-packs'));
    }

    /**
     * @return array<string, IndustryTemplateData>
     */
    protected function load(): array
    {
        $directory = $this->path();

        if (! File::isDirectory($directory)) {
            throw new InvalidIndustryTemplateException("No se encontró el directorio de packs: {$directory}");
        }

        $pattern = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.json';
        $files = File::glob($pattern) ?: [];
        sort($files);

        $packs = [];
        $keys = [];

        foreach ($files as $file) {
            $pack = $this->loadFile($file);
            $filename = basename($file);

            if (isset($keys[$pack->key])) {
                throw new InvalidIndustryTemplateException(
                    "El pack '{$filename}' duplica la clave '{$pack->key}' ya definida en '{$keys[$pack->key]}'."
                );
            }

            $keys[$pack->key] = $filename;
            $packs[$pack->key] = $pack;
        }

        return $packs;
    }

    protected function loadFile(string $file): IndustryTemplateData
    {
        $filename = basename($file);

        try {
            $payload = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidIndustryTemplateException(
                "El pack '{$filename}' no es JSON válido: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new InvalidIndustryTemplateException("El pack '{$filename}' debe ser un objeto JSON.");
        }

        if ($payload !== [] && array_is_list($payload)) {
            throw new InvalidIndustryTemplateException("El pack '{$filename}' debe ser un objeto JSON.");
        }

        $missing = $this->firstMissingField($payload);

        if ($missing !== null) {
            throw new InvalidIndustryTemplateException(
                "El pack '{$filename}' no tiene el campo obligatorio '{$missing}'."
            );
        }

        return IndustryTemplateData::fromArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function firstMissingField(array $payload): ?string
    {
        foreach (['key', 'name'] as $field) {
            if (! is_string($payload[$field] ?? null) || trim((string) $payload[$field]) === '') {
                return $field;
            }
        }

        foreach (['terminology', 'actor_types'] as $field) {
            if (! is_array($payload[$field] ?? null)) {
                return $field;
            }
        }

        if (! is_array($payload['workflow'] ?? null)) {
            return 'workflow';
        }

        if (! is_string(Arr::get($payload, 'workflow.name')) || trim((string) Arr::get($payload, 'workflow.name')) === '') {
            return 'workflow.name';
        }

        if (! is_array(Arr::get($payload, 'workflow.stages'))) {
            return 'workflow.stages';
        }

        return null;
    }
}
