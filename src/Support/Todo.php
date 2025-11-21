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

/**
 * Represents a single todo item in the kanban board.
 *
 * This class encapsulates all data and presentation logic for a todo item,
 * including its tags, description, urgency level, creation timestamp, and
 * the visual widget representation in the terminal UI with color-coded tag badges.
 */
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
     * Tag color palette (cycles through for multiple tags).
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

    /**
     * Layout percentage constants.
     */
    private const LAYOUT_TITLE_PERCENTAGE = 30;

    private const LAYOUT_CONTENT_PERCENTAGE = 70;

    private const LAYOUT_HALF_PERCENTAGE = 50;

    /**
     * Create a new todo item instance.
     *
     * @param  Context  $context  The application context for configuration access
     * @param  TodoType  $type  The current state of the todo (TODO, IN_PROGRESS, DONE)
     * @param  array  $tags  Array of tag strings for categorizing the todo
     * @param  string  $id  Unique identifier (UUID) for the todo
     * @param  string  $description  Detailed description of the todo
     * @param  TodoUrgency  $urgency  The urgency level (defaults to NORMAL)
     * @param  string  $created_at  ISO timestamp of when the todo was created
     */
    public function __construct(
        protected Context $context,
        public TodoType $type,
        public array $tags,
        public string $id,
        public string $description,
        public TodoUrgency $urgency = TodoUrgency::NORMAL,
        public string $created_at = '',
    ) {}

    /**
     * Get the widget for the todo item.
     *
     * Creates a visual representation of the todo using PhpTui widgets.
     * The widget displays the urgency label, creation date, tag badges, and description.
     * When active, the widget has a highlighted background.
     *
     * @param  bool  $active  Whether this todo is currently selected/active
     * @return Widget The rendered widget for display in the TUI
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

        // Build tag badges string
        $tagBadges = $this->buildTagBadges($active);

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
                                $tagBadges.PHP_EOL.
                                $this->description
                            )
                        )->style($style)
                    )
            );

    }

    /**
     * Build colored tag badges for display.
     *
     * @param  bool  $active  Whether this todo is currently active
     * @return string The formatted tag badges string
     */
    protected function buildTagBadges(bool $active): string
    {
        if (empty($this->tags)) {
            return '[No Tags]';
        }

        $badges = [];
        foreach ($this->tags as $tag) {
            $color = $this->getTagColorByName($tag);
            $badges[] = "\033[38;2;{$color[0]};{$color[1]};{$color[2]}m[{$tag}]\033[0m";
        }

        return implode(' ', $badges);
    }

    /**
     * Get the urgency style based on the todo's urgency level.
     *
     * Returns a styled color representation for the urgency:
     * - URGENT: Red
     * - IMPORTANT: Yellow/Amber
     * - NORMAL: Green
     * - LOW: Light Blue
     *
     * @return Style The styled color for the urgency level
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
     * Convert the item to an array for serialization.
     *
     * Converts the todo object into an array format suitable for JSON
     * serialization and storage in the data file.
     *
     * @return array The todo data as an associative array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'tags' => $this->tags,
            'description' => $this->description,
            'urgency' => $this->urgency->value,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Get color for a specific tag based on its index.
     *
     * @param  int  $index  The index of the tag
     * @return array RGB color array
     */
    protected function getTagColor(int $index): array
    {
        return self::TAG_COLORS[$index % count(self::TAG_COLORS)];
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
