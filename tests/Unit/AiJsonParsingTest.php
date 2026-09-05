<?php

namespace Tests\Unit;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use Tests\TestCase;

class AiJsonParsingTest extends TestCase
{
    public function test_parses_clean_json(): void
    {
        $service = app(AiService::class);
        $result = $service->parseJson('{"gaps": ["a"], "verdict": "pass"}');

        $this->assertEquals(['gaps' => ['a'], 'verdict' => 'pass'], $result);
    }

    public function test_parses_json_in_markdown_fence(): void
    {
        $service = app(AiService::class);
        $result = $service->parseJson("```json\n{\"ok\": true}\n```");

        $this->assertEquals(['ok' => true], $result);
    }

    public function test_parses_json_with_prose_around_it(): void
    {
        $service = app(AiService::class);
        $result = $service->parseJson('Here is my analysis: {"gaps": ["x"], "verdict": "needs_work"} hope this helps!');

        $this->assertEquals(['x'], $result['gaps']);
        $this->assertEquals('needs_work', $result['verdict']);
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(AiProviderException::class);

        app(AiService::class)->parseJson('no json here at all');
    }

    public function test_rejects_empty(): void
    {
        $this->expectException(AiProviderException::class);

        app(AiService::class)->parseJson('');
    }
}
