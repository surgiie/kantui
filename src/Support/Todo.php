<?php

namespace Kantui\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Enums\TodoUrgency;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\HorizontalAlignment;
use PhpTui\Tui\Widget\Widget;

class Todo implements Arrayable
{
    /**
     * RGB color constants.
     */
    private const COLOR_WHITE = [255, 255, 255];

    private const COLOR_DARK_BG = [33, 37, 41];

    private const COLOR_URGENT = [220, 53, 69];

    private const COLOR_IMPORTANT = [255, 193, 7];

    private const COLOR_NORMAL = [46, 197, 70];

    private const COLOR_LOW = [164, 208, 216];

    /**
     * Layout percentage constants.
     */
    private const LAYOUT_TITLE_PERCENTAGE = 30;

    private const LAYOUT_CONTENT_PERCENTAGE = 70;

    private const LAYOUT_HALF_PERCENTAGE = 50;

    /**
     * Create a new todo item instance.
     */
    public function __construct(
        protected Context $context,
        public TodoType $type,
        public string $title,
        public string $id,
        public string $description,
        public TodoUrgency $urgency = TodoUrgency::NORMAL,
        public string $created_at = '',
    ) {}

    /**
     * Get the widget for the todo item.
     */
    public function widget(bool $active = false): Widget
    {
        $style = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));
        $urgencyStyle = $this->getUrgencyStyle();

        if ($active) {
            $style = $style->bg(RgbColor::fromRgb(...self::COLOR_DARK_BG));
            $urgencyStyle = $urgencyStyle->bg(RgbColor::fromRgb(...self::COLOR_DARK_BG));
        }

        $createdAt = Carbon::parse($this->created_at)->setTimezone($this->context->getTimezone());

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->widget(
                GridWidget::default()
                    ->direction(Direction::Vertical)
                    ->constraints(
                        Constraint::percentage(self::LAYOUT_TITLE_PERCENTAGE),
                        Constraint::percentage(self::LAYOUT_CONTENT_PERCENTAGE),
                    )->widgets(
                        GridWidget::default()
                            ->direction(Direction::Horizontal)
                            ->constraints(
                                Constraint::percentage(self::LAYOUT_HALF_PERCENTAGE),
                                Constraint::percentage(self::LAYOUT_HALF_PERCENTAGE),
                            )->widgets(
                                ParagraphWidget::fromText(
                                    Text::fromString(
                                        $this->urgency->label()
                                    )
                                )->style($urgencyStyle),
                                ParagraphWidget::fromText(
                                    Text::fromString(
                                        'Created: '.($this->context->config('human_readable_date', true)
                                            ? $createdAt->diffForHumans()
                                            : $createdAt->toDateTimeString())
                                    )
                                )->style($style)->alignment(HorizontalAlignment::Right)
                            ),
                        ParagraphWidget::fromText(
                            Text::fromString(
                                $this->title.PHP_EOL.PHP_EOL.
                                $this->description
                            )
                        )->style($style)
                    )
            );

    }

    /**
     * Get the urgency style.
     */
    protected function getUrgencyStyle(): Style
    {
        $style = \Kantui\default_style();

        return match ($this->urgency) {
            TodoUrgency::URGENT => $style->fg(RgbColor::fromRgb(...self::COLOR_URGENT)),
            TodoUrgency::IMPORTANT => $style->fg(RgbColor::fromRgb(...self::COLOR_IMPORTANT)),
            TodoUrgency::NORMAL => $style->fg(RgbColor::fromRgb(...self::COLOR_NORMAL)),
            TodoUrgency::LOW => $style->fg(RgbColor::fromRgb(...self::COLOR_LOW)),
        };
    }

    /**
     * Convert the item to an array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'urgency' => $this->urgency->value,
            'created_at' => $this->created_at,
        ];
    }
}
