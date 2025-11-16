<?php

namespace Kantui\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Kantui\Support\Enums\TodoType;
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
     * Create a new todo item instance.
     */
    public function __construct(
        protected Context $context,
        public TodoType $type,
        public string $title,
        public string $id,
        public string $description,
        public string $urgency = 'normal',
        public string $created_at = '',
    ) {}

    /**
     * Get the widget for the todo item.
     */
    public function widget(bool $active = false): Widget
    {
        $style = Style::default()->fg(RgbColor::fromRgb(255, 255, 255));
        $urgencyStyle = $this->getUrgencyStyle();

        if ($active) {
            $style = $style->bg(RgbColor::fromRgb(33, 37, 41));
            $urgencyStyle = $urgencyStyle->bg(RgbColor::fromRgb(33, 37, 41));
        }

        $createdAt = Carbon::parse($this->created_at)->setTimezone($this->context->config('timezone', date_default_timezone_get()));

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->widget(
                GridWidget::default()
                    ->direction(Direction::Vertical)
                    ->constraints(
                        Constraint::percentage(30),
                        Constraint::percentage(70),
                    )->widgets(
                        GridWidget::default()
                            ->direction(Direction::Horizontal)
                            ->constraints(
                                Constraint::percentage(50),
                                Constraint::percentage(50),
                            )->widgets(
                                ParagraphWidget::fromText(
                                    Text::fromString(
                                        mb_strtoupper($this->urgency)
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

        if ($this->urgency === 'urgent') {
            return $style->fg(RgbColor::fromRgb(220, 53, 69));
        }

        if ($this->urgency === 'important') {
            return $style->fg(RgbColor::fromRgb(255, 193, 7));
        }

        if ($this->urgency === 'normal') {
            return $style->fg(RgbColor::fromRgb(46, 197, 70));
        }

        return $style->fg(RgbColor::fromRgb(164, 208, 216));
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
            'urgency' => $this->urgency,
            'created_at' => $this->created_at,
        ];
    }
}
