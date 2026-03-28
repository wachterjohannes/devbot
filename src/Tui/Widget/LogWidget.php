<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use App\EventListener\ToolExecutionLogger;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * Tool execution log viewer.
 * Shows recent tool calls with status, arguments, and results.
 */
final class LogWidget extends ContainerWidget implements VerticallyExpandableInterface
{
    private TextWidget $content;

    public function __construct(
        private readonly ToolExecutionLogger $logger,
    ) {
        parent::__construct();

        $this->content = new TextWidget(' No tool calls yet.');
        $this->content->setId('log-content');
        $this->content->addStyleClass('p-1');

        $this->add($this->content);
    }

    public function refresh(): void
    {
        $entries = $this->logger->getRecent(50);

        if ($entries === []) {
            $this->content->setText(' No tool calls yet.');

            return;
        }

        $ok = "\033[32m";
        $err = "\033[31m";
        $cyan = "\033[36m";
        $gray = "\033[90m";
        $bold = "\033[1m";
        $r = "\033[0m";

        $lines = [" {$bold}Tool Execution Log{$r} ({$this->logger->count()} total)\n"];

        foreach (array_reverse($entries) as $entry) {
            $status = $entry['status'] === 'ok'
                ? "{$ok}OK{$r}"
                : "{$err}ERR{$r}";

            $args = $entry['args'] !== []
                ? $gray . json_encode($entry['args'], \JSON_UNESCAPED_UNICODE) . $r
                : $gray . '()' . $r;

            // Truncate args display
            if (mb_strlen($args) > 80) {
                $args = mb_substr($args, 0, 77) . '...';
            }

            $result = $entry['result'];
            if (mb_strlen($result) > 120) {
                $result = mb_substr($result, 0, 117) . '...';
            }

            $lines[] = " {$gray}{$entry['time']}{$r}  [{$status}]  {$cyan}{$bold}{$entry['tool']}{$r}  {$args}";
            $lines[] = "          {$result}";
            $lines[] = '';
        }

        $this->content->setText(implode("\n", $lines));
    }
}
