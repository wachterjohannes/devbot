<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Bottom status bar showing model name and session info.
 */
final class StatusBarWidget extends TextWidget
{
    private string $model = 'kimi-k2';
    private int $tokenCount = 0;

    public function __construct()
    {
        parent::__construct($this->buildText(), truncate: true);
        $this->addStyleClass('bg-gray-800');
        $this->addStyleClass('text-gray-300');
        $this->addStyleClass('p-1');
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
        $this->setText($this->buildText());
    }

    public function setTokenCount(int $count): void
    {
        $this->tokenCount = $count;
        $this->setText($this->buildText());
    }

    private function buildText(): string
    {
        return sprintf(' Model: %s | Tokens: %d | DevBot v0.1', $this->model, $this->tokenCount);
    }
}
