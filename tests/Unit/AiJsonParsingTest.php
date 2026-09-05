<?php

namespace Tests\Unit;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use Tests\TestCase;

class AiJsonParsingTest extends TestCase
{
    public function test_parses_clean_json(): void
    {
        $result = app(AiService::class)->parseJson('{"gaps": ["a"], "verdict": "pass"}');

        $this->assertEquals(['gaps' => ['a'], 'verdict' => 'pass'], $result);
    }

    public function test_parses_json_in_markdown_fence(): void
    {
        $result = app(AiService::class)->parseJson("```json\n{\"ok\": true}\n```");

        $this->assertEquals(['ok' => true], $result);
    }

    public function test_parses_json_with_prose_around_it(): void
    {
        $result = app(AiService::class)->parseJson('Here is my analysis: {"gaps": ["x"], "verdict": "needs_work"} hope this helps!');

        $this->assertEquals(['x'], $result['gaps']);
        $this->assertEquals('needs_work', $result['verdict']);
    }

    public function test_parses_json_after_reasoning_prose(): void
    {
        // Reasoning models open with thinking prose before emitting JSON
        $result = app(AiService::class)->parseJson(
            'Let me analyze this brief. The user wants a gym tracker { hmm stray brace } with plateau detection. Final answer: {"problem": "plateau", "core_features": ["logging"]}',
        );

        $this->assertEquals('plateau', $result['problem']);
        $this->assertEquals(['logging'], $result['core_features']);
    }

    public function test_parses_truncated_json_with_repair(): void
    {
        // Stream cut mid-array: repair closes the structure
        $result = app(AiService::class)->parseJson('{"gaps": ["a", "b"');

        $this->assertNotNull($result);
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
