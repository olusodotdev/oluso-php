<?php

declare(strict_types=1);

namespace Oluso\Laravel;

use Illuminate\Http\Request;
use Oluso\Client;
use Oluso\RequestContext;
use Oluso\Scope;

final class RequestContextBuilder
{
    public static function fromRequest(Client $client, Request $request): RequestContext
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = implode(', ', $values);
        }

        $responseTimeMs = null;
        $start = Scope::current()?->requestStart();
        if ($start !== null) {
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
        }

        return new RequestContext(
            url: $request->path(),
            method: $request->method(),
            headers: $client->sanitizer()->sanitizeHeaders($headers),
            query: $client->sanitizer()->sanitizeQuery($request->query->all()),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            responseTimeMs: $responseTimeMs,
        );
    }
}
