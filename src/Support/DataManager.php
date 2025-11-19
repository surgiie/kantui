<?php

namespace Kantui\Support;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Kantui\Contracts\DataManagerInterface;
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

/**
 * Manages todo data operations and persistence.
 *
 * The DataManager class handles all CRUD operations for todo items,
 * including loading from and saving to JSON files, pagination,
 * widget rendering, and interactive editing.
 */
class DataManager implements DataManagerInterface
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

    /**
     * Cache of indexed todos for faster lookups.
     */
    private array $todoIndexCache = [];

    /**
     * Whether the todo index cache needs to be rebuilt.
     */
    private bool $cacheNeedsRebuild = true;

    /**
     * The search and filter state (static to persist across app restarts).
     */
    protected static ?SearchFilter $searchFilter = null;

    /**
     * Create a new DataManager instance.
     *
     * @param  Context  $context  The application context
     */
    public function __construct(protected Context $context)
    {
        $this->todos = $this->loadTodos();
        $this->rebuildIndexCache();

        // Initialize static search filter if not already set
        if (static::$searchFilter === null) {
            static::$searchFilter = new SearchFilter;
        }
    }

    /**
     * Get the default data structure for the todos.
     *
     * Returns an empty data structure with all three todo type categories.
     *
     * @return array Empty todo collections indexed by type
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
     *
     * Reads the JSON data file and deserializes it into Todo objects.
     * If the file doesn't exist, returns the default empty structure.
     *
     * @return array Array of Todo objects indexed by type
     *
     * @throws \RuntimeException if file reading or JSON parsing fails
     */
    public function loadTodos(): array
    {
        $todos = static::defaultData();

        $todosPath = $this->context->path(self::DATA_FILE_NAME);

        if (is_file($todosPath)) {
            $contents = file_get_contents($todosPath);

            if ($contents === false) {
                throw new \RuntimeException("Failed to read todos from data file: {$todosPath}");
            }

            $todos = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

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
     *
     * Calculates and returns the items on the last page for a specific todo type.
     *
     * @param  TodoType  $type  The todo type to get the last page for
     * @return LengthAwarePaginator Paginated items from the last page
     */
    public function getLastPageItems(TodoType $type): LengthAwarePaginator
    {
        $paginated = $this->getByType($type, new Cursor(Cursor::INACTIVE, 0));
        $lastPage = $paginated->lastPage();

        return $this->getByType($type, new Cursor(Cursor::INACTIVE, $lastPage));
    }

    /**
     * Get the active todo item index.
     *
     * @return int The index of the currently active todo in its collection
     */
    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    /**
     * Reposition active item by the given number of index counts.
     *
     * Moves the currently active todo item up or down in its list by
     * swapping it with an adjacent item. Changes are persisted to disk.
     *
     * @param  int  $offset  The number of positions to move (positive = down, negative = up)
     */
    public function repositionActiveItem(int $offset): void
    {
        $activeTodo = $this->getActiveTodo();
        if (! $activeTodo) {
            return;
        }

        $type = $activeTodo->type->value;
        $typeTodos = &$this->todos[$type];

        // Use cached index for faster lookup
        $currentIndex = $this->findTodoIndex($activeTodo->id, $type);

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + $offset;
        if ($newIndex < 0 || $newIndex >= count($typeTodos)) {
            return;
        }

        // Swap the items
        [$typeTodos[$currentIndex], $typeTodos[$newIndex]] = [$typeTodos[$newIndex], $typeTodos[$currentIndex]];

        // reindex the items array so we save new positions
        $this->todos[$type] = array_values($typeTodos);
        $this->activeIndex = $newIndex;
        $this->cacheNeedsRebuild = true;
        $this->writeTodos();
    }

    /**
     * Edit the active todo item interactively using prompts.
     *
     * Displays interactive prompts for editing the title, description,
     * and urgency of the currently active todo. Saves changes to disk.
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
     *
     * Displays interactive prompts for creating a new todo with title,
     * description, and urgency. The todo is added to the TODO column.
     *
     * @return Todo The newly created todo item
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
     *
     * Serializes all todos to JSON and writes them to the data file.
     *
     * @throws \RuntimeException if file writing fails
     */
    public function writeTodos(): void
    {
        $result = file_put_contents(
            $this->context->path(self::DATA_FILE_NAME),
            json_encode($this->todos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        if ($result === false) {
            $dataPath = $this->context->path(self::DATA_FILE_NAME);
            throw new \RuntimeException("Failed to write todos to data file: {$dataPath}");
        }

        // Rebuild cache after write
        if ($this->cacheNeedsRebuild) {
            $this->rebuildIndexCache();
        }
    }

    /**
     * Get todos by type and paginate them appropriately.
     *
     * Retrieves todos of a specific type and returns them as a paginated collection.
     * Applies active search and filter criteria.
     *
     * @param  TodoType  $type  The type of todos to retrieve
     * @param  Cursor  $cursor  The cursor containing pagination information
     * @return LengthAwarePaginator Paginated collection of todos
     */
    public function getByType(TodoType $type, Cursor $cursor): LengthAwarePaginator
    {
        $page = $cursor->page();

        $todos = $this->todos[$type->value];

        // Apply search and filter
        if (static::$searchFilter->isActive()) {
            $todos = array_filter($todos, fn ($todo) => static::$searchFilter->matches($todo));
        }

        $todos = Collection::make($todos)->paginate(static::PAGINATE_BY, $page);

        return $todos;
    }

    /**
     * Move a todo item to the given type.
     *
     * Removes the todo from its current type and adds it to the specified type.
     * Changes are persisted to disk.
     *
     * @param  Todo  $todo  The todo item to move
     * @param  TodoType  $type  The destination type
     */
    public function move(Todo $todo, TodoType $type): void
    {
        $this->delete($todo);

        $todo->type = $type;

        $this->todos[$type->value][] = $todo;

        $this->cacheNeedsRebuild = true;
        $this->writeTodos();
    }

    /**
     * Delete a todo item from the collection.
     *
     * Removes the todo from its type collection and re-indexes the array.
     * Changes are persisted to disk.
     *
     * @param  Todo  $todo  The todo item to delete
     */
    public function delete(Todo $todo): void
    {
        $id = $todo->id;
        $type = $todo->type->value;

        // Use cached index for faster lookup
        $index = $this->findTodoIndex($id, $type);

        if ($index === false) {
            return;
        }

        unset($this->todos[$type][$index]);

        // reindex the array
        $this->todos[$type] = array_values($this->todos[$type]);

        $this->cacheNeedsRebuild = true;
        $this->writeTodos();
    }

    /**
     * Get the active todo item.
     *
     * @return Todo|null The currently active todo, or null if none selected
     */
    public function getActiveTodo(): ?Todo
    {
        return $this->activeTodo;
    }

    /**
     * Calculate constraint percentage based on todo count.
     *
     * Determines the layout percentage for each todo based on the total count.
     * Returns null for empty lists, fixed percentage for small lists,
     * or evenly distributed percentage for larger lists.
     *
     * @param  int  $count  The number of todos to display
     * @return int|null The percentage constraint, or null if count is zero
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
     *
     * Constructs widget representations for each todo in the paginated collection
     * and calculates layout constraints for proper vertical distribution.
     *
     * @param  LengthAwarePaginator  $todos  The paginated todo collection
     * @param  int  $currentIndex  The index of the currently selected todo
     * @return array Array with 'widgets' and 'constraints' keys
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
     *
     * Creates a formatted title for the todo type column, including
     * pagination information when a cursor is active.
     *
     * @param  TodoType  $type  The todo type
     * @param  Cursor  $cursor  The current cursor state
     * @param  LengthAwarePaginator  $todos  The paginated todos
     * @return string The formatted title string
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
     *
     * Creates a complete widget representation for a todo type column,
     * including all todos and appropriate layout constraints.
     *
     * @param  TodoType  $type  The type of todos in this widget
     * @param  LengthAwarePaginator  $todos  The paginated todos to display
     * @param  Cursor  $cursor  The current cursor state for highlighting
     * @return Widget The complete column widget for the TUI
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

    /**
     * Rebuild the todo index cache for faster lookups.
     *
     * Creates an indexed map of todo IDs to their positions within each type.
     */
    private function rebuildIndexCache(): void
    {
        $this->todoIndexCache = [];

        foreach ($this->todos as $type => $typeTodos) {
            $this->todoIndexCache[$type] = [];
            foreach ($typeTodos as $index => $todo) {
                $this->todoIndexCache[$type][$todo->id] = $index;
            }
        }

        $this->cacheNeedsRebuild = false;
    }

    /**
     * Find a todo's index by ID using the cache.
     *
     * Uses the index cache for O(1) lookup instead of O(n) array_search.
     *
     * @param  string  $id  The todo ID to find
     * @param  string  $type  The todo type
     * @return int|false The index if found, false otherwise
     */
    private function findTodoIndex(string $id, string $type): int|false
    {
        if ($this->cacheNeedsRebuild) {
            $this->rebuildIndexCache();
        }

        return $this->todoIndexCache[$type][$id] ?? false;
    }

    /**
     * Get the search filter instance.
     *
     * @return SearchFilter The search filter instance
     */
    public function getSearchFilter(): SearchFilter
    {
        return static::$searchFilter;
    }
}
