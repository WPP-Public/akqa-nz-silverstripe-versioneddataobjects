<?php

/**
 * Class VersionedGridFieldOrderableRows
 *
 * Allows grid field rows to be re-ordered via drag and drop. Both normal data
 * lists and many many lists can be ordered.
 *
 * If the grid field has not been sorted, this component will sort the data by
 * the sort field.
 */
class VersionedGridFieldOrderableRows extends RequestHandler implements
    GridField_ColumnProvider,
    GridField_DataManipulator,
    GridField_HTMLProvider,
    GridField_URLHandler
{
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'handleReorder',
        'handleMoveToPage'
    );

    /**
     * The database field which specifies the sort, defaults to "Sort".
     *
     * @see setSortField()
     * @var string
     */
    protected $sortField;

    /**
     * @param string $sortField
     */
    public function __construct($sortField = 'Sort')
    {
        $this->sortField = $sortField;
    }

    /**
     * @return string
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * Sets the field used to specify the sort.
     *
     * @param string $field
     * @return VersionedGridFieldOrderableRows $this
     */
    public function setSortField($field)
    {
        $this->sortField = $field;
        return $this;
    }

    /**
     * Gets the table which contains the sort field.
     *
     * @param DataList $list
     * @return mixed
     * @throws Exception
     */
    public function getSortTable(DataList $list)
    {
        $field = $this->getSortField();

        if ($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $table = $list->getJoinTable();

            if ($extra && array_key_exists($field, $extra)) {
                return $table;
            }
        }

        $classes = ClassInfo::dataClassesFor($list->dataClass());

        foreach ($classes as $class) {
            if (singleton($class)->hasOwnTableDatabaseField($field)) {
                return $class;
            }
        }

        throw new Exception("Couldn't find the sort field '$field'");
    }

    /**
     * Return URLs to be handled by this grid field, in an array the same form
     * as $url_handlers.
     *
     * Handler methods will be called on the component, rather than the
     * {@link GridField}.
     */
    public function getURLHandlers($gridField)
    {
        return array(
            'POST reorder' => 'handleReorder',
            'POST movetopage' => 'handleMoveToPage'
        );
    }

    /**
     * Returns a map where the keys are fragment names and the values are
     * pieces of HTML to add to these fragments.
     *
     * Here are 4 built-in fragments: 'header', 'footer', 'before', and
     * 'after', but components may also specify fragments of their own.
     *
     * To specify a new fragment, specify a new fragment by including the
     * text "$DefineFragment(fragmentname)" in the HTML that you return.
     *
     * Fragment names should only contain alphanumerics, -, and _.
     *
     * If you attempt to return HTML for a fragment that doesn't exist, an
     * exception will be thrown when the {@link GridField} is rendered.
     *
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $moduleDir = basename(dirname(__DIR__));

        Requirements::css($moduleDir . '/css/GridFieldExtensions.css');
        Requirements::javascript($moduleDir . '/javascript/GridFieldExtensions.js');

        $gridField->addExtraClass('ss-versioned-gridfield-orderable');
        $gridField->setAttribute('data-url-reorder', $gridField->Link('reorder'));
        $gridField->setAttribute('data-url-movetopage', $gridField->Link('movetopage'));

        return array();
    }

    /**
     * Modify the list of columns displayed in the table.
     *
     * @see {@link GridFieldDataColumns->getDisplayFields()}
     * @see {@link GridFieldDataColumns}.
     *
     * @param GridField $gridField
     * @param array - List reference of all column names.
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Reorder', $columns) && $gridField->getState()->VersionedGridFieldOrderableRows->enabled) {
            array_unshift($columns, 'Reorder');
        }
    }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return array('Reorder');
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param  GridField $gridField
     * @param  DataObject $record - Record displayed in this row
     * @param  string $columnName
     * @return string - HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        return ViewableData::create()->renderWith('VersionedGridFieldOrderableRowsDragHandle');
    }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param  GridField $gridField
     * @param  DataObject $record displayed in this row
     * @param  string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return array('class' => 'col-reorder');
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        return array('title' => '');
    }

    /**
     * Manipulate the {@link DataList} as needed by this grid modifier.
     *
     * @param GridField
     * @param SS_List
     * @return DataList
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        $state = $gridField->getState();
        $sorted = (bool)((string)$state->GridFieldSortableHeader->SortColumn);

        // If the data has not been sorted by the user, then sort it by the
        // sort column, otherwise disable reordering.
        $state->VersionedGridFieldOrderableRows->enabled = !$sorted;

        if (!$sorted) {
            return $dataList->sort($this->getSortField());
        } else {
            return $dataList;
        }
    }

    /**
     * Handles requests to reorder a set of IDs in a specific order.
     *
     * @param $grid
     * @param $request
     * @return mixed
     */
    public function handleReorder($grid, $request)
    {
        if (!singleton($grid->getModelClass())->canEdit()) {
            $this->httpError(403);
        }

        $ids = $request->postVar('order');
        $list = $grid->getList();
        $field = $this->getSortField();

        if (!is_array($ids)) {
            $this->httpError(400);
        }

        $items = $list->byIDs($ids)->sort($field);

        // Ensure that each provided ID corresponded to an actual object.
        if (count($items) != count($ids)) {
            $this->httpError(404);
        }

        // Populate each object we are sorting with a sort value.
        $this->populateSortValues($items);

        // Generate the current sort values.
        $current = $items->map('ID', $field)->toArray();

        // Perform the actual re-ordering.
        $this->reorderItems($list, $current, $ids);

        return $grid->FieldHolder();
    }

    /**
     * Handles requests to move an item to the previous or next page.
     *
     * @param GridField $grid
     * @param $request
     * @return mixed
     */
    public function handleMoveToPage(GridField $grid, $request)
    {
        if (!$paginator = $grid->getConfig()->getComponentByType('GridFieldPaginator')) {
            $this->httpError(404, 'Paginator component not found');
        }

        $move = $request->postVar('move');
        $field = $this->getSortField();

        $list = $grid->getList();
        $manip = $grid->getManipulatedList();

        $existing = $manip->map('ID', $field)->toArray();
        $values = $existing;
        $order = array();

        $id = isset($move['id']) ? (int)$move['id'] : null;
        $to = isset($move['page']) ? $move['page'] : null;

        if (!isset($values[$id])) {
            $this->httpError(400, 'Invalid item ID');
        }

        $this->populateSortValues($list);

        $page = ((int)$grid->getState()->GridFieldPaginator->currentPage) ?: 1;
        $per = $paginator->getItemsPerPage();

        if ($to == 'prev') {
            $swap = $list->limit(1, ($page - 1) * $per - 1)->first();
            $values[$swap->ID] = $swap->$field;

            $order[] = $id;
            $order[] = $swap->ID;

            foreach ($existing as $_id => $sort) {
                if ($id != $_id) $order[] = $_id;
            }
        } elseif ($to == 'next') {
            $swap = $list->limit(1, $page * $per)->first();
            $values[$swap->ID] = $swap->$field;

            foreach ($existing as $_id => $sort) {
                if ($id != $_id) $order[] = $_id;
            }

            $order[] = $swap->ID;
            $order[] = $id;
        } else {
            $this->httpError(400, 'Invalid page target');
        }

        $this->reorderItems($list, $values, $order);

        return $grid->FieldHolder();
    }

    /**
     * @param $list
     * @param array $values
     * @param array $order
     * @throws Exception
     */
    protected function reorderItems($list, array $values, array $order)
    {
        // Get a list of sort values that can be used.
        $pool = array_values($values);
        sort($pool);
        $table = $this->getSortTable($list);
        $sortField = $this->getSortField();

        // Loop through each item, and update the sort values which do not
        // match to order the objects.
        foreach (array_values($order) as $pos => $id) {
            $where = $this->getSortTableClauseForIds($list, $id);
            $query = sprintf(
                'UPDATE "%s" SET "%s" = %d WHERE %s',
                $table,
                $sortField,
                $pos,
                $where
            );
            DB::query($query);
            if (!$list instanceof ManyManyList) { // if relevant, update Live table
                $liveQuery = sprintf(
                    'UPDATE "%s" SET "%s" = %d WHERE %s',
                    $table . '_Live',
                    $sortField,
                    $pos,
                    $where
                );
                DB::query($liveQuery);
            }
        }
        $this->invalidateCache($list->dataClass());
    }

    /**
     * Invalidate cache if the cache-include module is installed
     * @param $className
     */
    protected function invalidateCache($className)
    {
        $class = singleton($className);
        if ($class->has_extension('Heyday\CacheInclude\SilverStripe\InvalidationExtension')) {
            $dataobject = $class::get()->first();
            $dataobject->extend('onAfterReorder');
        }
    }

    /**
     * @param DataList $list
     * @throws Exception
     */
    protected function populateSortValues(DataList $list)
    {
        $list = clone $list;
        $field = $this->getSortField();
        $table = $this->getSortTable($list);
        $clause = sprintf('"%s"."%s" = 0', $table, $this->getSortField());

        foreach ($list->where($clause)->column('ID') as $id) {
            $max = DB::query(sprintf('SELECT MAX("%s") + 1 FROM "%s"', $field, $table));
            $max = $max->value();

            DB::query(sprintf(
                'UPDATE "%s" SET "%s" = %d WHERE %s',
                $table,
                $field,
                $max,
                $this->getSortTableClauseForIds($list, $id)
            ));
        }
    }

    /**
     * @param DataList $list
     * @param $ids
     * @return string
     */
    protected function getSortTableClauseForIds(DataList $list, $ids)
    {
        if (is_array($ids)) {
            $value = 'IN (' . implode(', ', array_map('intval', $ids)) . ')';
        } else {
            $value = '= ' . (int)$ids;
        }

        if ($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $key = $list->getLocalKey();
            $foreignKey = $list->getForeignKey();
            $foreignID = $list->getForeignID();

            if ($extra && array_key_exists($this->getSortField(), $extra)) {
                return sprintf(
                    '"%s" %s AND "%s" = %d',
                    $key,
                    $value,
                    $foreignKey,
                    $foreignID
                );
            }
        }

        return "\"ID\" $value";
    }

}
