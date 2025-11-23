<?php

namespace Kantui\Widgets;

use Carbon\Carbon;
use Kantui\App;
use Kantui\Contracts\AppWidget;
use Kantui\Support\Todo;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\HorizontalAlignment;
use PhpTui\Tui\Widget\Widget;

class TodoDetailWidget implements AppWidget
{
    /**
     * RGB color constants.
     */
    private const COLOR_WHITE = [255, 255, 255];

    private const COLOR_LABEL = [100, 150, 200];

    /**
     * Tag color palette (same as Todo class).
     */
    private const TAG_COLORS = [
        [0, 150, 255],    // Blue
        [46, 197, 70],    // Green
        [255, 193, 7],    // Yellow
        [220, 53, 69],    // Red
        [138, 43, 226],   // Purple
        [255, 127, 80],   // Coral
        [32, 178, 170],   // Teal
        [255, 105, 180],  // Pink
    ];

    private Todo $todo;

    private Style $style;

    private ?App $app;

    public function __construct(Todo $todo, ?Style $style = null, ?App $app = null)
    {
        $this->todo = $todo;
        $this->style = $style ?? Style::default();
        $this->app = $app;
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

        // Center the detail dialog with margins on both sides
        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(10),  // Left margin
                Constraint::percentage(80),  // Detail content
                Constraint::percentage(10)   // Right margin
            )
            ->widgets(
                BlockWidget::default(),  // Empty left block
                $detailBlock,
                BlockWidget::default()   // Empty right block
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getFooterText(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function handleCharKey(CharKeyEvent $event): callable|false|null
    {
        if ($event->modifiers !== KeyModifiers::NONE) {
            return null;
        }

        // Close detail view on i
        if ($event->char === 'i') {
            if ($this->app !== null) {
                $this->app->returnToMainWidget();
            }
        }

        return false; // Continue event loop
    }

    /**
     * {@inheritdoc}
     */
    public function handleCodedKey(CodedKeyEvent $event): callable|false|null
    {
        // Close detail view on ESC
        if ($event->code == KeyCode::Esc) {
            if ($this->app !== null) {
                $this->app->returnToMainWidget();
            }
        }

        return false; // Continue event loop
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
        $dateStr = $createdAt->format('Y-m-d H:i:s').' ('.$createdAt->diffForHumans().')';
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

                $color = $this->getTagColorByName($tag);
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
        $lines[] = Line::fromString('  '.str_repeat('─', 70));

        // Split description into lines, preserving original line breaks
        $descriptionLines = explode("\n", $this->todo->description);
        foreach ($descriptionLines as $line) {
            // Wrap long lines at 70 characters
            $wrapped = wordwrap($line, 70, "\n", false);
            $wrappedLines = explode("\n", $wrapped);
            foreach ($wrappedLines as $wrappedLine) {
                $lines[] = Line::fromSpans(Span::styled('  '.$wrappedLine, $valueStyle));
            }
        }

        return Text::fromLines(...$lines);
    }

    /**
     * Generate a color for a tag based on its name hash.
     *
     * @param  string  $tag  The tag name
     * @return array RGB color array
     */
    protected function getTagColorByName(string $tag): array
    {
        $hash = crc32($tag);
        $index = abs($hash) % count(self::TAG_COLORS);

        return self::TAG_COLORS[$index];
    }
}
