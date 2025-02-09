<?php

namespace Hybridly\Testing;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;

class Assertable extends AssertableJson
{
    protected string $view;
    protected string $url;
    protected array $payload;
    protected array $properties;
    protected ?string $version;
    protected ?string $dialog;

    public static function fromTestResponse(TestResponse $response): self
    {
        try {
            $response->assertViewHas('payload');
            $payload = json_decode(json_encode($response->viewData('payload')), true);

            PHPUnit::assertIsArray($payload);
            PHPUnit::assertArrayHasKey('view', $payload);
            PHPUnit::assertArrayHasKey('name', $payload['view']);
            PHPUnit::assertArrayHasKey('properties', $payload['view']);
            PHPUnit::assertArrayHasKey('dialog', $payload);
            PHPUnit::assertArrayHasKey('url', $payload);
            PHPUnit::assertArrayHasKey('version', $payload);
        } catch (AssertionFailedError) {
            PHPUnit::fail('Not a valid hybrid response.');
        }

        $instance = static::fromArray($payload);
        $instance->payload = $payload;
        $instance->view = $payload['view']['name'];
        $instance->properties = $payload['view']['properties'];
        $instance->dialog = $payload['dialog'];
        $instance->url = $payload['url'];
        $instance->version = $payload['version'];

        return $instance;
    }

    public function view(string $value = null, $shouldExist = null): self
    {
        PHPUnit::assertSame($value, $this->view, 'Unexpected Hybridly page view.');

        if ($shouldExist || (\is_null($shouldExist) && config('hybridly.testing.ensure_pages_exist', true))) {
            try {
                app('hybridly.testing.view_finder')->find($value);
            } catch (InvalidArgumentException) {
                PHPUnit::fail(sprintf('Hybridly page view file [%s] does not exist.', $value));
            }
        }

        return $this;
    }

    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Hybridly page url.');

        return $this;
    }

    public function version(string $value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Hybridly asset version.');

        return $this;
    }

    public function hasProperties(array $keys, string $scope = null): self
    {
        $scope ??= 'view.properties';

        foreach ($keys as $key => $value) {
            // ['property_name' => 'property_value'] -> assert that it has the given value
            if (\is_string($key) && \is_string($value)) {
                $this->where($scope . '.' . $key, $value);

                continue;
            }

            // ['property_name'] -> assert that it exists
            if (\is_int($key) && \is_string($value)) {
                $this->has($scope . '.' . $value);

                continue;
            }

            // ['property_name' => 10] -> assert that it exists and has the given count
            if (\is_string($key) && \is_int($value)) {
                $this->has($scope . '.' . $key, length: $value);

                continue;
            }

            // ['property_name' => fn () => ...] -> assert using a callback
            if (\is_string($key) && \is_callable($value)) {
                $this->has($scope . '.' . $key, $value);

                continue;
            }

            // ['property_name' => ['foo']] -> assert using an array
            if (\is_string($key) && \is_array($value)) {
                $this->hasProperties($value, scope: $scope . '.' . $key);

                continue;
            }

            throw new \LogicException("Unknown syntax [{$key} => {$value}]");
        }

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getValue(string $key): mixed
    {
        return $this->prop($key);
    }

    public function getProperty(string $key): mixed
    {
        return $this->prop('view.properties.' . $key);
    }

    public function toArray(): array
    {
        return $this->getPayload();
    }
}
