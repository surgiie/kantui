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
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
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
    private const LAYOUT_HEADER_PERCENTAGE = 30;

    private const LAYOUT_CONTENT_PERCENTAGE = 70;

    private const LAYOUT_HALF_PERCENTAGE = 50;

    /**
     * Maximum description length before truncation.
     */
    private const MAX_DESCRIPTION_LENGTH = 100;

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

        // Build text content with styled tag badges
        $contentText = $this->buildContentText($active, $style);

        // Create a background-only style for the content paragraph (no foreground to preserve span colors)
        $contentBgStyle = Style::default();
        if ($active) {
            $contentBgStyle = $contentBgStyle->bg(RgbColor::fromRgb(...self::COLOR_DARK_BG));
        }

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->widget(
                GridWidget::default()
                    ->direction(Direction::Vertical)
                    ->constraints(
                        Constraint::percentage(self::LAYOUT_HEADER_PERCENTAGE),
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
                        ParagraphWidget::fromText($contentText)->style($contentBgStyle)
                    )
            );

    }

    /**
     * Build content text with styled tag badges.
     *
     * @param  bool  $active  Whether this todo is currently active
     * @param  Style  $style  The base style for the text
     * @return Text The formatted content with styled tags
     */
    protected function buildContentText(bool $active, Style $style): Text
    {
        $lines = [];

        // First line: description with white text (no background, applied at paragraph level)
        // Replace newlines with spaces to keep description on a single line
        $descriptionStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));
        $cleanDescription = trim(preg_replace('/\s+/', ' ', $this->description));

        // Truncate description if it exceeds max length
        if (mb_strlen($cleanDescription) > self::MAX_DESCRIPTION_LENGTH) {
            $cleanDescription = mb_substr($cleanDescription, 0, self::MAX_DESCRIPTION_LENGTH - 3) . '...';
        }

        $lines[] = Line::fromSpans(Span::styled($cleanDescription, $descriptionStyle));

        // Add empty line for spacing
        $emptyStyle = Style::default();
        $lines[] = Line::fromSpans(Span::styled('', $emptyStyle));

        // Third line: styled tag badges with "Tags: " prefix
        $tagSpans = [];

        // Add "Tags: " prefix (no background, applied at paragraph level)
        $prefixStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));
        $tagSpans[] = Span::styled('Tags: ', $prefixStyle);

        if (empty($this->tags)) {
            $noTagsStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));
            $tagSpans[] = Span::styled('[No Tags]', $noTagsStyle);
        } else {
            foreach ($this->tags as $index => $tag) {
                // Add space between tags
                if ($index > 0) {
                    $spaceStyle = Style::default()->fg(RgbColor::fromRgb(...self::COLOR_WHITE));
                    $tagSpans[] = Span::styled(' ', $spaceStyle);
                }

                // Tag with color (no background, applied at paragraph level)
                $color = $this->getTagColorByName($tag);
                $tagStyle = Style::default()->fg(RgbColor::fromRgb(...$color));

                $tagSpans[] = Span::styled("[{$tag}]", $tagStyle);
            }
        }

        $lines[] = Line::fromSpans(...$tagSpans);

        return Text::fromLines(...$lines);
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
     * Get the context instance.
     *
     * @return Context The application context
     */
    public function getContext(): Context
    {
        return $this->context;
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
