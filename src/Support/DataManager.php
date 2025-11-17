<?php

namespace Kantui\Support;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Enums\TodoUrgency;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class DataManager
{
    /**
     * The number of items to paginate by.
     */
    public const PAGINATE_BY = 6;

    /**
     * The date format used for created_at timestamps.
     */
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * The filename for the data file.
     */
    private const DATA_FILE_NAME = 'data.json';

    /**
     * Layout percentage for small item count (2 or less).
     */
    private const SMALL_ITEM_CONSTRAINT_PERCENTAGE = 30;

    /**
     * The todos with status "todo".
     */
    protected array $todos;

    /**
     * The todo that is currently active by cursor.
     */
    protected Todo $activeTodo;

    /**
     * The active todo index in its collection.
     */
    protected int $activeIndex;

    public function __construct(protected Context $context)
    {
        $this->todos = $this->loadTodos();
    }

    /**
     * Get the default data for the todos.
     */
    public static function defaultData(): array
    {
        return [
            TodoType::TODO->value => [],
            TodoType::IN_PROGRESS->value => [],
            TodoType::DONE->value => [],
        ];
    }

    /**
     * Load the todo items from the data file.
     */
    public function loadTodos(): array
    {
        $todos = static::defaultData();

        $todosPath = $this->context->path(self::DATA_FILE_NAME);

        if (is_file($todosPath)) {
            $contents = file_get_contents($todosPath);

            if ($contents === false) {
                throw new \RuntimeException('Failed to read todos from data file.');
            }

            $todos = json_decode($contents, true);

            if (json_last_error()) {
                throw new \RuntimeException(
                    'Failed to load todos from the data file. Malformed JSON.'
                );
            }

            foreach ($todos as $type => $typeTodos) {
                $todos[$type] = array_map(
                    function ($todo) use ($type) {
                        unset($todo['type']);
                        $todo['urgency'] = TodoUrgency::from($todo['urgency']);

                        return new Todo(
                            $this->context,
                            TodoType::from($type),
                            ...$todo
                        );
                    },
                    $typeTodos
                );
            }
        }

        return $todos;
    }

    /**
     * Get the last page of items for a given todo type.
     */
    public function getLastPageItems(TodoType $type): LengthAwarePaginator
    {
        $paginated = $this->getByType($type, new Cursor(Cursor::INACTIVE, 0));
        $lastPage = $paginated->lastPage();

        return $this->getByType($type, new Cursor(Cursor::INACTIVE, $lastPage));
    }

    /**
     * Get the active todo item index.
     */
    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    /**
     * Reposition active item by the given number of index counts.
     */
    public function repositionActiveItem(int $offset): void
    {
        $activeTodo = $this->getActiveTodo();
        $type = $activeTodo->type->value;
        if (! $activeTodo) {
            return;
        }

        $typeTodos = &$this->todos[$type];
        $currentIndex = array_search($activeTodo->id, array_column($typeTodos, 'id'));

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + ($offset);
        if ($newIndex < 0 || $newIndex >= count($typeTodos)) {
            return;
        }
        // Swap the items
        [$typeTodos[$currentIndex], $typeTodos[$newIndex]] = [$typeTodos[$newIndex], $typeTodos[$currentIndex]];
        // reindex the items array so we save new positions
        $this->todos[$activeTodo->type->value] = array_values($typeTodos);
        $this->activeIndex = $newIndex;
        $this->writeTodos();
    }

    /**
     * Edit the active todo item interactively using prompts.
     */
    public function editInteractively(): void
    {
        $activeTodo = $this->getActiveTodo();
        if (! $activeTodo) {
            return;
        }
        info('Edit the todo:');
        $title = text('Title:', default: $activeTodo->title);
        $description = textarea('Description:', default: $activeTodo->description, required: true);
        $urgency = select(
            label: 'Urgency:',
            options: [
                TodoUrgency::LOW->value => TodoUrgency::LOW->label(),
                TodoUrgency::NORMAL->value => TodoUrgency::NORMAL->label(),
                TodoUrgency::IMPORTANT->value => TodoUrgency::IMPORTANT->label(),
                TodoUrgency::URGENT->value => TodoUrgency::URGENT->label(),
            ],
            default: $activeTodo->urgency->value
        );
        $activeTodo->title = $title;
        $activeTodo->description = $description;
        $activeTodo->urgency = TodoUrgency::from($urgency);
        $this->writeTodos();
    }

    /**
     * Create a new todo item and save it to the data file.
     */
    public function createInteractively(): Todo
    {
        info('Create a new todo:');

        $title = text('Title:');

        $description = textarea('Description:', required: true);

        $urgency = select(
            label: 'Urgency:',
            options: [
                TodoUrgency::LOW->value => TodoUrgency::LOW->label(),
                TodoUrgency::NORMAL->value => TodoUrgency::NORMAL->label(),
                TodoUrgency::IMPORTANT->value => TodoUrgency::IMPORTANT->label(),
                TodoUrgency::URGENT->value => TodoUrgency::URGENT->label(),
            ]
        );

        $created_at = Carbon::now($this->context->getTimezone())->format(self::DATE_FORMAT);

        $todo = new Todo(
            $this->context,
            TodoType::TODO,
            id: Str::uuid()->toString(),
            title: $title,
            description: $description,
            urgency: TodoUrgency::from($urgency),
            created_at: $created_at
        );

        $this->todos[TodoType::TODO->value][] = $todo;

        $this->writeTodos();

        return $todo;
    }

    /**
     * Write the todos array to the data file as JSON.
     */
    public function writeTodos(): void
    {
        $result = file_put_contents(
            $this->context->path(self::DATA_FILE_NAME),
            json_encode($this->todos, JSON_PRETTY_PRINT)
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to write todos to data file.');
        }
    }

    /**
     * Get todos by type and paginate them appropriately.
     */
    public function getByType(TodoType $type, Cursor $cursor): LengthAwarePaginator
    {
        $page = $cursor->page();

        $todos = Collection::make($this->todos[$type->value])->paginate(static::PAGINATE_BY, $page);

        return $todos;
    }

    /**
     * Move a todo item to the given type.
     */
    public function move(Todo $todo, TodoType $type): void
    {
        $this->delete($todo);

        $todo->type = $type;

        $this->todos[$type->value][] = $todo;

        $this->writeTodos();
    }

    /**
     * Delete a todo item from the collection.
     */
    public function delete(Todo $todo): void
    {
        $id = $todo->id;

        $index = array_search($id, array_column($this->todos[$todo->type->value], 'id'));

        if ($index === false) {
            return;
        }

        unset($this->todos[$todo->type->value][$index]);

        // reindex the array
        $this->todos[$todo->type->value] = array_values($this->todos[$todo->type->value]);

        $this->writeTodos();
    }

    /**
     * Get the active todo item.
     */
    public function getActiveTodo(): ?Todo
    {
        return $this->activeTodo;
    }

    /**
     * Calculate constraint percentage based on todo count.
     */
    protected function calculateConstraintPercentage(int $count): ?int
    {
        if ($count == 0) {
            return null;
        }

        if ($count <= 2) {
            return self::SMALL_ITEM_CONSTRAINT_PERCENTAGE;
        }

        return intval(floor(100 / $count));
    }

    /**
     * Build todo widgets and constraints from paginated todos.
     */
    protected function buildTodoWidgets(LengthAwarePaginator $todos, int $currentIndex): array
    {
        $widgets = [];
        $constraints = [];
        $count = $todos->count();

        $constraintPercentage = $this->calculateConstraintPercentage($count);

        if ($constraintPercentage === null) {
            $constraints[] = Constraint::percentage(100);

            return ['widgets' => $widgets, 'constraints' => $constraints];
        }

        foreach ($todos as $index => $todo) {
            $constraints[] = Constraint::percentage($constraintPercentage);

            if ($index === $currentIndex) {
                $widgets[] = $todo->widget(active: true);
                $this->activeTodo = $todo;
                $this->activeIndex = $index;
            } else {
                $widgets[] = $todo->widget();
            }
        }

        // insert an empty block widget to take up the remaining space if there are less than 2 todos.
        if ($count <= 2) {
            $constraints[] = Constraint::percentage(100);
            $widgets[] = BlockWidget::default();
        }

        return ['widgets' => $widgets, 'constraints' => $constraints];
    }

    /**
     * Format the type title with pagination info if needed.
     */
    protected function formatTypeTitle(TodoType $type, Cursor $cursor, LengthAwarePaginator $todos): string
    {
        $title = Str::replace('_', ' ', $type->name);

        if ($cursor->index() != Cursor::INACTIVE) {
            $current = ($cursor->index() + 1) + (($cursor->page() - 1) * static::PAGINATE_BY);
            $title = "$title - {$current}/{$todos->total()}";
        }

        return $title;
    }

    /**
     * Get the widget for the given todo collection.
     */
    public function makeWidget(TodoType $type, LengthAwarePaginator $todos, Cursor $cursor): Widget
    {
        $result = $this->buildTodoWidgets($todos, $cursor->index());
        $title = $this->formatTypeTitle($type, $cursor, $todos);

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString($title))
            ->style(\Kantui\default_style())->widget(
                GridWidget::default()
                    ->direction(Direction::Vertical)
                    ->constraints(...$result['constraints'])
                    ->widgets(...$result['widgets'])
            );
    }
}
