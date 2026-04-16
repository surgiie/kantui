<?php

namespace Kantui\Widgets;

use Carbon\Carbon;
use Kantui\App;
use Kantui\Support\TagColors;
use Kantui\Support\Todo;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\HorizontalAlignment;
use PhpTui\Tui\Widget\Widget;

class TodoDetailWidget extends OverlayWidget
{
    private const COLOR_WHITE = [255, 255, 255];

    private const COLOR_LABEL = [100, 150, 200];

    private Todo $todo;

    public function __construct(Todo $todo, ?Style $style = null, ?App $app = null)
    {
        parent::__construct($style, $app);
        $this->todo = $todo;
    }

    /**
     * {@inheritdoc}
     */
    protected function closeKeys(): array
    {
        return ['i'];
    }

    /**
     * {@inheritdoc}
     */
    public function render(): Widget
    {
        $detailText = $this->buildDetailText();

        $detailBlock = BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString('Todo Details - Press i or ESC to close'))
            ->style($this->style)
            ->widget(
                ParagraphWidget::fromText($detailText)
                    ->alignment(HorizontalAlignment::Left)
            );

        return $this->centeredDialog($detailBlock);
    }

    /**
     * Build the detail text content.
     */
    private function buildDetailText(): Text
    {
        $lines = [];
        $labelStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_LABEL));
        $valueStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));

        // Add top padding
        $lines[] = Line::fromString('');

        // Urgency
        $lines[] = Line::fromSpans(
            Span::styled('  Urgency:     ', $labelStyle),
            Span::styled($this->todo->urgency->label(), $valueStyle)
        );

        // Created date
        $createdAt = Carbon::parse($this->todo->created_at)->setTimezone($this->todo->getContext()->getTimezone());
        $dateStr = $createdAt->format('Y-m-d H:i:s') . ' (' . $createdAt->diffForHumans() . ')';
        $lines[] = Line::fromSpans(
            Span::styled('  Created:     ', $labelStyle),
            Span::styled($dateStr, $valueStyle)
        );

        // Type
        $lines[] = Line::fromSpans(
            Span::styled('  Type:        ', $labelStyle),
            Span::styled($this->todo->type->value, $valueStyle)
        );

        // ID
        $lines[] = Line::fromSpans(
            Span::styled('  ID:          ', $labelStyle),
            Span::styled($this->todo->id, $valueStyle)
        );

        // Empty line before tags
        $lines[] = Line::fromString('');

        // Tags
        $tagSpans = [Span::styled('  Tags:        ', $labelStyle)];

        if (empty($this->todo->tags)) {
            $tagSpans[] = Span::styled('[No Tags]', $valueStyle);
        } else {
            foreach ($this->todo->tags as $index => $tag) {
                if ($index > 0) {
                    $tagSpans[] = Span::styled(' ', $valueStyle);
                }

                $color = TagColors::forTag($tag);
                $tagStyle = Style::default()->fg(RgbColor::fromRgb(...$color));
                $tagSpans[] = Span::styled("[{$tag}]", $tagStyle);
            }
        }

        $lines[] = Line::fromSpans(...$tagSpans);

        // Empty line before description
        $lines[] = Line::fromString('');
        $lines[] = Line::fromString('');

        // Description label
        $lines[] = Line::fromSpans(Span::styled('  Description:', $labelStyle));
        $lines[] = Line::fromString('  ' . str_repeat('─', 70));

        // Split description into lines, preserving original line breaks
        $descriptionLines = explode("\n", $this->todo->description);
        foreach ($descriptionLines as $line) {
            // Wrap long lines at 70 characters
            $wrapped = wordwrap($line, 70, "\n", false);
            $wrappedLines = explode("\n", $wrapped);
            foreach ($wrappedLines as $wrappedLine) {
                $lines[] = Line::fromSpans(Span::styled('  ' . $wrappedLine, $valueStyle));
            }
        }

        return Text::fromLines(...$lines);
    }
}
