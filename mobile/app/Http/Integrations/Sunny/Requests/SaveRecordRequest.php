<?php

namespace App\Http\Integrations\Sunny\Requests;

use GuzzleHttp\Psr7\LazyOpenStream;
use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Repositories\Body\JsonBodyRepository;
use Saloon\Repositories\Body\MultipartBodyRepository;

class SaveRecordRequest extends Request implements HasBody
{
    protected JsonBodyRepository|MultipartBodyRepository $body;

    public function __construct(
        private readonly string $teamSlug,
        private readonly string $resource,
        private readonly array $payload,
        private readonly ?int $id = null,
        private readonly ?string $photoPath = null,
    ) {
        $this->method = $id !== null && $photoPath === null ? Method::PATCH : Method::POST;
    }

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/'.$this->resource.($this->id !== null ? '/'.$this->id : '');
    }

    public function body(): JsonBodyRepository|MultipartBodyRepository
    {
        if (isset($this->body)) {
            return $this->body;
        }

        if ($this->photoPath === null) {
            return $this->body = new JsonBodyRepository($this->payload);
        }

        $values = [];
        foreach ($this->payload as $key => $value) {
            if ($key === 'metadata') {
                $values[] = new MultipartValue($key, json_encode($value, JSON_THROW_ON_ERROR));
            } elseif (is_array($value) && $value !== []) {
                foreach ($value as $index => $entry) {
                    $values[] = new MultipartValue($key.'['.$index.']', (string) $entry);
                }
            } else {
                $values[] = new MultipartValue($key, is_array($value) ? '' : (is_bool($value) ? (string) (int) $value : (string) $value));
            }
        }
        if ($this->id !== null) {
            $values[] = new MultipartValue('_method', 'PATCH');
        }
        $values[] = new MultipartValue('photo', new LazyOpenStream($this->photoPath, 'r'), basename($this->photoPath));

        return $this->body = new MultipartBodyRepository($values);
    }

    protected function defaultHeaders(): array
    {
        return ['Content-Type' => $this->photoPath === null ? 'application/json' : 'multipart/form-data; boundary='.$this->body()->getBoundary()];
    }
}
