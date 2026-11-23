<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Answers locally with fixed, clearly labelled texts - no provider, no costs. This makes the
 * interface, the limits and the history fully testable. Keywords in the question trigger test
 * cases: /xss (HTML and script as text), /markdown, /long, /context (returns the server data
 * that was sent along), /error, /timeout. The German aliases /lang, /kontext, /fehler work too.
 */
class DemoDriver implements Driver
{
    private const BLOCK = '/\[\[SERVER DATA[^\]]*BEGIN\]\]\n(.*?)\n\[\[SERVER DATA[^\]]*END\]\]/su';

    /** @param int $delay pause between two words in microseconds */
    public function __construct(private int $delay = 28000)
    {
    }

    public function chat(string $system, array $messages, int $maxTokens, callable $onText, callable $onIdle): Result
    {
        $last = (string) (end($messages)['content'] ?? '');
        // The question comes before the data block; keywords inside server data do not count.
        $question = mb_strtolower((string) preg_replace('/\[\[SERVER DATA.*$/su', '', $last));
        $has = static fn (string ...$words): bool => array_filter($words, static fn ($word) => str_contains($question, $word)) !== [];

        if ($has('/error', '/fehler')) {
            throw new ProviderException(ProviderException::UNAVAILABLE, 'demo: simulated provider outage', 503);
        }
        if ($has('/timeout')) {
            usleep(600000);
            throw new ProviderException(ProviderException::TIMEOUT, 'demo: simulated timeout');
        }

        $text = match (true) {
            $has('/xss') => self::xss(),
            $has('/markdown') => I18n::t('assistant.demo_title') . ' ' . I18n::t('assistant.demo_markdown'),
            $has('/long', '/lang') => I18n::t('assistant.demo_long_title') . "\n\n"
                . implode("\n\n", array_fill(0, 30, str_repeat(I18n::t('assistant.demo_long_paragraph'), 3))),
            $has('/context', '/kontext') => self::context($last),
            default => self::standard($last),
        };

        $result = new Result();
        $result->tokensIn = (int) ceil(mb_strlen($system . implode('', array_column($messages, 'content'))) / 4);
        $result->tokensOut = 0;

        foreach (preg_split('/(?<=\s)/u', $text) ?: [] as $piece) {
            if ($piece === '') {
                continue;
            }
            if ($result->tokensOut >= $maxTokens) {
                $result->stop = Result::LENGTH;
                break;
            }
            $result->text .= $piece;
            $result->tokensOut++;
            if (!$onText($piece)) {
                $result->stop = Result::ABORTED;
                break;
            }
            if ($this->delay > 0) {
                usleep($this->delay);
            }
        }

        return $result;
    }

    private static function standard(string $last): string
    {
        $lines = preg_match(self::BLOCK, $last, $match) === 1 ? substr_count($match[1], "\n") + 1 : 0;

        return I18n::t('assistant.demo_title') . ' ' . I18n::t('assistant.demo_intro') . "\n\n"
            . ($lines > 0 ? I18n::t('assistant.demo_with_context', ['lines' => $lines]) : I18n::t('assistant.demo_without_context')) . "\n\n"
            . I18n::t('assistant.demo_structure') . "\n\n"
            . I18n::t('assistant.demo_keywords');
    }

    private static function xss(): string
    {
        return I18n::t('assistant.demo_title') . ' ' . I18n::t('assistant.demo_xss_intro') . "\n\n"
            . "<script>window.techtylXss = 1; alert('xss')</script>\n\n"
            . "<img src=x onerror=\"window.techtylXss = 2\">\n\n"
            . "<a href=\"javascript:window.techtylXss = 3\">Click</a> [Markdown link](javascript:window.techtylXss=4)\n\n"
            . "![Image](https://example.org/tracking-pixel.png) <iframe src=\"https://example.org\"></iframe>\n\n"
            . "```html\n<script>window.techtylXss = 5</script>\n</code></pre><script>window.techtylXss = 6</script>\n```\n\n"
            . I18n::t('assistant.demo_xss_end') . ' &lt;b&gt;no HTML&lt;/b&gt; &amp;.';
    }

    private static function context(string $last): string
    {
        if (preg_match(self::BLOCK, $last, $match) !== 1) {
            return I18n::t('assistant.demo_title') . ' ' . I18n::t('assistant.demo_without_context');
        }

        return I18n::t('assistant.demo_title') . ' ' . I18n::t('assistant.demo_context_intro') . "\n\n```\n"
            . str_replace('```', "'''", $match[1]) . "\n```";
    }
}
