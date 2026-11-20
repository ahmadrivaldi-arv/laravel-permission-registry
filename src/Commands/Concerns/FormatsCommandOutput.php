<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands\Concerns;

use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Shared presentation helpers for the package's rbac:* commands.
 *
 * Every helper writes human-readable output only; JSON output is rendered separately.
 */
trait FormatsCommandOutput
{
    /** Print a bold section title, optionally followed by muted context. */
    private function heading(string $title, ?string $context = null): void
    {
        $this->newLine();
        $line = '  <options=bold>'.$this->escape($title).'</>';
        if ($context !== null && $context !== '') {
            $line .= ' <fg=gray>'.$this->escape($context).'</>';
        }
        $this->line($line);
    }

    /** Print a dotted label/value row. Both sides may contain console style tags. */
    private function detail(string $label, string|int $value = ''): void
    {
        $this->components->twoColumnDetail($label, (string) $value);
    }

    /** Print an indented item prefixed by a styled marker. */
    private function entry(string $marker, string $text, ?string $note = null): void
    {
        $line = "  {$marker} ".$this->escape($text);
        if ($note !== null && $note !== '') {
            $line .= ' <fg=gray>'.$this->escape($note).'</>';
        }
        $this->line($line);
    }

    /** Print a muted, indented hint. */
    private function hint(string $text): void
    {
        $this->line('  <fg=gray>'.$this->escape($text).'</>');
    }

    /** Color a count only when it is non-zero. */
    private function tally(int $count, string $color): string
    {
        return $count === 0 ? '<fg=gray>0</>' : "<fg={$color};options=bold>{$count}</>";
    }

    private function quantity(int $count, string $noun): string
    {
        return $count.' '.Str::plural($noun, $count);
    }

    private function riskBadge(RiskLevel $risk): string
    {
        $style = match ($risk) {
            RiskLevel::LOW => 'fg=green',
            RiskLevel::MEDIUM => 'fg=yellow',
            RiskLevel::HIGH => 'fg=red',
            RiskLevel::CRITICAL => 'fg=red;options=bold',
        };

        return "<{$style}>".strtoupper($risk->value).'</>';
    }

    private function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
