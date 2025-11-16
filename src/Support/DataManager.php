<?php

namespace Kantui\Support;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Kantui\Support\Enums\TodoType;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
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

        $todosPath = $this->context->path('data.json');

        if (is_file($todosPath)) {

            $todos = json_decode(file_get_contents($todosPath), true);

            if (json_last_error()) {
                throw new \RuntimeException(
                    'Failed to load todos from the data file. Malformed JSON.'
                );
            }

            foreach ($todos as $type => $typeTodos) {
                $todos[$type] = array_map(
                    function ($todo) use ($type) {
                        unset($todo['type']);

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

    /** Get last page of given todo type. */
    public function getLastPageItems(TodoType $type): LengthAwarePaginator
    {
        $paginated = $this->getByType($type, new Cursor(-1, 0));
        $lastPage = $paginated->lastPage();

        return $this->getByType($type, new Cursor(-1, $lastPage));
    }

    /** Get active index. */
    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    /**Reposition active item by the given number of index counts. */
    public function repositionActiveItem(int $offset): void
    {
        $activeTodo = $this->getActiveTodo();
        $type = $activeTodo->type->value;
        if (! $activeTodo) {
            return;
        }

        $typeTodos = &$this->todos[$type];
        $currentIndex = array_search($activeTodo->id, array_column($typeTodos, 'id'));
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

    /** Edit the active todo item interactively. */
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
                'low' => 'LOW',
                'normal' => 'NORMAL',
                'important' => 'IMPORTANT',
                'urgent' => 'URGENT',
            ],
            default: $activeTodo->urgency
        );
        $activeTodo->title = $title;
        $activeTodo->description = $description;
        $activeTodo->urgency = $urgency;
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
                'low' => 'LOW',
                'normal' => 'NORMAL',
                'important' => 'IMPORTANT',
                'urgent' => 'URGENT',
            ]
        );

        $created_at = Carbon::now(
            $this->context->config('timezone', date_default_timezone_get())
        )->toDateTimeString();

        $todo = new Todo(
            $this->context,
            TodoType::TODO,
            id: Str::uuid()->toString(),
            title: $title,
            description: $description,
            urgency: $urgency,
            created_at: $created_at
        );

        $this->todos[TodoType::TODO->value][] = $todo;

        $this->writeTodos();

        return $todo;
    }

    /** Write the todos to the data file. */
    public function writeTodos(): void
    {
        file_put_contents(
            $this->context->path('data.json'),
            json_encode($this->todos, JSON_PRETTY_PRINT)
        );
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

    /** Delete a todo item. */
    public function delete(Todo $todo): void
    {
        $id = $todo->id;

        $index = array_search($id, array_column($this->todos[$todo->type->value], 'id'));

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
     * Get the widget for the given todo collection.
     */
    public function makeWidget(TodoType $type, LengthAwarePaginator $todos, Cursor $cursor): Widget
    {
        $widgets = [];
        $constraints = [];
        $currentIndex = $cursor->index();

        if (($count = $todos->count()) == 0) {
            $constraints[] = Constraint::percentage(100);
        } elseif ($count <= 2) {
            // If there are only 2 todos, use a smaller percentage so the todos are not too big on the screen.
            $constraintPercentage = 30;
        } else {
            $constraintPercentage = intval(floor(100 / $count));
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

        $title = Str::replace('_', ' ', $type->name);

        if ($currentIndex != -1) {
            $current = ($currentIndex + 1) + (($cursor->page() - 1) * static::PAGINATE_BY);
            $title = "$title - {$current}/{$todos->total()}";
        }

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString($title))
            ->style(\Kantui\default_style())->widget(
                GridWidget::default()
                    ->direction(Direction::Vertical)
                    ->constraints(
                        ...$constraints
                    )
                    ->widgets(
                        ...$widgets
                    )
            );
    }
}
