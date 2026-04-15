<?php

namespace Kantui\Widgets\Concerns;

use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

trait RendersAsDialog
{
    /**
     * Wrap a widget in a centered dialog with margins on both sides.
     */
    protected function centeredDialog(Widget $content): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(10),
                Constraint::percentage(80),
                Constraint::percentage(10)
            )
            ->widgets(
                BlockWidget::default(),
                $content,
                BlockWidget::default()
            );
    }
}
