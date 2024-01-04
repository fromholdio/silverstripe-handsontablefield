<?php

namespace Fromholdio\HandsOnTableField\Forms;

use SilverStripe\Forms\TextareaField;
use SilverStripe\View\Requirements;

class HandsOnTableField extends TextareaField
{
    protected array $options = [];
    protected array $columns = [];
    protected int $minColumns = 0;

    public function Field($properties = [])
    {
        Requirements::javascript('fromholdio/silverstripe-handsontablefield: client/dist/js/handsontablefield.js');
        Requirements::css('fromholdio/silverstripe-handsontablefield: client/dist/css/handsontable.css');
        return parent::Field($properties);
    }


    public function addColumn(?string $title, array $config = [], bool $doSave = true): self
    {
        $config['title'] = $title ?? 'Untitled';
        $config['doSave'] = $doSave;
        $this->columns[] = $config;
        return $this;
    }

    public function getColumns(): array
    {
        $columns = $this->columns;
        return array_values($columns);
    }

    public function clearColumns(): self
    {
        $this->columns = [];
        return $this;
    }


    public function getColumnsTitles(): ?array
    {
        $titles = null;
        foreach ($this->getColumns() as $column) {
            $titles[] = $column['title'];
        }
        return $titles;
    }

    public function getColumnsOptions(): ?array
    {
        $options = null;
        foreach ($this->columns as $column) {
            unset($column['title']);
            unset($column['data']);
            unset($column['doSave']);
            $options[] = $column;
        }
        return $options;
    }


    public function getDefaultOptions(): array
    {
        $options = [
            'rowHeaders' => true,
            'colHeaders' => true,
            'colWidths' => 84,
            'autoColumnSize' => true,
            'manualColumnResize' => true,
            'autoRowSize' => true,
            'persistentState' => true,
            'width' => '100%',
            'minCols' => 10,
            'minRows' => 10,
            'height' => 'auto',
            'copyPaste' => true,
            'licenseKey' => 'non-commercial-and-evaluation'
//            'allowRemoveColumn' => true,
//            'manualRowMove' => true,
//            'contextMenu' => ['row_below', 'col_right', 'remove_col'],
        ];
        $configOptions = static::config()->get('default_options');
        if (!empty($configOptions)) {
            $options = self::mergeDeep($options, $configOptions);
        }
        return $options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        $options = $this->getDefaultOptions();

        $colTitles = $this->getColumnsTitles();
        $colOptions = $this->getColumnsOptions();

        if (!empty($colTitles) && !empty($colOptions)) {
            $options['colHeaders'] = $colTitles;
            $options['columns'] = $colOptions;
        }

        $instanceOptions = $this->options;
        if (!empty($instanceOptions)) {
            $options = self::mergeDeep($options, $instanceOptions);
        }

        $data = $this->getDataAsArray();
        $options['data'] = empty($data) ? [[]] : $data;

        return $options;
    }

    public function getOptionsAsJSON(): ?string
    {
        return json_encode($this->getOptions());
    }


    public function getDataAsJSON(): ?string
    {
        $data = $this->getDataAsArray();
        return empty($data) ? null : json_encode($data);
    }

    public function getDataAsArray(): array
    {
        $value = $this->Value();
        $data = empty($value) ? [] : json_decode($value, true);
        return $this->applyUnsavedColumnData($data);
    }

    public function getDataForSaveValue(): ?string
    {
        $value = $this->value;
        $data = empty($value) ? [[]] : json_decode($value, true);
        $data = $this->pruneUnsavedColumnData($data);
        $data = $this->pruneEmptyValues($data);
        return empty($data) ? null : json_encode($data);
    }


    public function dataValue(): ?string
    {
        return $this->getDataForSaveValue();
    }

    protected function applyUnsavedColumnData(array $data): array
    {
        $columns = $this->getColumns();
        if (empty($columns)) {
            return $data;
        }

        /**
         * $data is an array of arrayed values, per table Row. We want to merge
         * in data (or null values) for columns that have been preconfigured,
         * while filling in columns with $data values for columns setup with
         * 'doSave' = true.
         *
         * First we will create a new array of arrayed values, per table Column,
         * then rearrange that data to return an updated array of arrayed values
         * per table Row.
         */

        $newPerColumnData = [];
        $currDataRowIndex = 0;
        foreach ($columns as $colIndex => $columnConfig)
        {
            $doSaveColValues = $columnConfig['doSave'];
            if ($doSaveColValues) {
                $columnData = [];
                foreach ($data as $dataRowValues) {
                    $columnData[] = $dataRowValues[$currDataRowIndex] ?? null;
                }
                $currDataRowIndex++;
            }
            else {
                $columnData = $columnConfig['data'] ?? [];
            }
            $newPerColumnData[$colIndex] = $columnData;
        }

        $maxDataColumnCount = 0;
        foreach ($data as $dataRowValues) {
            $dataRowColumnsCount = count($dataRowValues);
            if ($dataRowColumnsCount > $maxDataColumnCount) {
                $maxDataColumnCount = $dataRowColumnsCount;
            }
        }

        $newColumnsCount = count($newPerColumnData);
        if ($newColumnsCount < $maxDataColumnCount) {
            for ($i = $newColumnsCount; $i < $maxDataColumnCount; $i++) {
                $newPerColumnData[$i] = $data[$i];
            }
        }

        $newPerRowData = [];
        $newColumnsCount = count($newPerColumnData);
        for ($newColumnIndex = 0; $newColumnIndex < $newColumnsCount; $newColumnIndex++)
        {
            $newColumnData = $newPerColumnData[$newColumnIndex];
            if (empty($newColumnData)) {
                $newPerRowData[0][$newColumnIndex] = null;
            }
            else {
                foreach ($newColumnData as $newRowIndex => $cell) {
                    $newPerRowData[$newRowIndex][$newColumnIndex] = $cell ?? null;
                }
            }
        }
        return $newPerRowData;
    }

    protected function pruneUnsavedColumnData(array $data): array
    {
        $columns = $this->getColumns();
        if (empty($columns)) {
            return $data;
        }

        /**
         * $data is an array of arrayed values, per Row, as submitted by the handsontable field.
         * This includes data from all columns. We need to remove the values for any
         * columns that have been preconfigured with 'doSave' = false.
         */

        foreach ($columns as $columnIndex => $column) {
            if (!$column['doSave']) {
                foreach ($data as $rowIndex => $cells) {
                    unset($data[$rowIndex][$columnIndex]);
                }
            }
        }
        foreach ($data as $rowIndex => $cells) {
            $data[$rowIndex] = array_values($cells);
        }
        return $data;
    }

    protected function pruneEmptyValues(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        /**
         * Submitted $data is an array of arrayed values per Row. These values
         * include null values for each extra cell in the table, even when the
         * full rows and/or columns are entirely empty. These values should be
         * removed prior to saving/further processing the submitted values.
         */

        $rowsMaxIndex = -1;
        foreach ($data as $row)
        {
            $rowMaxIndex = -1;
            foreach ($row as $cell) {
                if (!is_null($cell)) {
                    $rowMaxIndex++;
                }
            }
            if ($rowMaxIndex > $rowsMaxIndex) {
                $rowsMaxIndex = $rowMaxIndex;
            }
        }
        foreach ($data as $i => $row) {
            for ($j = $rowsMaxIndex + 1; $j <= count($row) + 1; $j++) {
                unset($data[$i][$j]);
            }
        }
        if ($rowsMaxIndex > 0) {
            foreach ($data as $i => $row) {
                foreach ($row as $key => $value) {
                    if (is_null($value) || $value === '') {
                        unset($row[$key]);
                    }
                }
                $isEmptyRow = count($row) === 0;
                if ($isEmptyRow) {
                    unset($data[$i]);
                }
            }
        }
        elseif ($rowsMaxIndex === 0)
        {
            $pruneFromIndex = false;
            foreach ($data as $i => $row) {
                foreach ($row as $cell) {
                    if (is_null($cell) || $cell === '') {
                        if ($pruneFromIndex === false) {
                            $pruneFromIndex = $i;
                        }
                    } else {
                        $pruneFromIndex = false;
                    }
                }
            }
            if ($pruneFromIndex !== false) {
                foreach ($data as $i => $row) {
                    if ($i >= $pruneFromIndex) {
                        unset($data[$i]);
                    }
                }
            }
        }
        return $data;
    }


    public static function getRowValues(array $data, ?int $rowIndex = null): array
    {
        $values = $data;
        if (!is_null($rowIndex)) {
            $values = $values[$rowIndex] ?? [];
        }
        return $values;
    }

    public static function getColumnValues(array $data, ?int $colIndex = null): array
    {
        $values = [];
        foreach ($data as $cells) {
            foreach ($cells as $i => $value) {
                $values[$i][] = $value;
            }
        }
        if (!is_null($colIndex)) {
            $values = $values[$colIndex] ?? [];
        }
        return $values;
    }

    public static function convertToData(?string $value): array
    {
        return empty($value) ? [] : json_decode($value, true);
    }

    public static function convertColumnToValue(array $data): ?string
    {
        $value = null;
        foreach ($data as $cellValue) {
            $value[] = [$cellValue];
        }
        return empty($value) ? null : json_encode($value);
    }

    public static function convertColumnsToValue(array $columnsData): ?string
    {
        $rows = [];
        foreach ($columnsData as $columnIndex => $column) {
            if (!is_array($column)) {
                continue;
            }
            foreach ($column as $rowIndex => $cell) {
                $rows[$rowIndex][$columnIndex] = $cell;
            }
        }
        foreach ($rows as $i => $row) {
            $rows[$i] = array_values($row);
        }
        return empty($rows) ? null : json_encode($rows);
    }


    /**
     * Merges multiple arrays, recursively, and returns the merged array.
     *
     * This function is similar to PHP's array_merge_recursive() function, but it
     * handles non-array values differently. When merging values that are not both
     * arrays, the latter value replaces the former rather than merging with it.
     *
     * Example:
     * @code
     * $link_options_1 = array('fragment' => 'x', 'attributes' => array('title' => t('X'), 'class' => array('a', 'b')));
     * $link_options_2 = array('fragment' => 'y', 'attributes' => array('title' => t('Y'), 'class' => array('c', 'd')));
     *
     * // This results in array('fragment' => array('x', 'y'), 'attributes' => array('title' => array(t('X'), t('Y')), 'class' => array('a', 'b', 'c', 'd'))).
     * $incorrect = array_merge_recursive($link_options_1, $link_options_2);
     *
     * // This results in array('fragment' => 'y', 'attributes' => array('title' => t('Y'), 'class' => array('a', 'b', 'c', 'd'))).
     * $correct = NestedArray::mergeDeep($link_options_1, $link_options_2);
     * @endcode
     *
     * @param array ...
     *   Arrays to merge.
     *
     * @return array
     *   The merged array.
     *
     * @see self::mergeDeepArray()
     */
    public static function mergeDeep(): array
    {
        return static::mergeDeepArray(func_get_args());
    }

    /**
     * Merges multiple arrays, recursively, and returns the merged array.
     *
     * This function is equivalent to NestedArray::mergeDeep(), except the
     * input arrays are passed as a single array parameter rather than a variable
     * parameter list.
     *
     * The following are equivalent:
     * - NestedArray::mergeDeep($a, $b);
     * - NestedArray::mergeDeepArray(array($a, $b));
     *
     * The following are also equivalent:
     * - call_user_func_array('self::mergeDeep', $arrays_to_merge);
     * - self::mergeDeepArray($arrays_to_merge);
     *
     * @param array $arrays
     *   An arrays of arrays to merge.
     * @param bool $preserve_integer_keys
     *   (optional) If given, integer keys will be preserved and merged instead of
     *   appended. Defaults to FALSE.
     *
     * @return array
     *   The merged array.
     *
     * @see self::mergeDeep()
     */
    public static function mergeDeepArray(array $arrays, $preserve_integer_keys = false): array
    {
        $result = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                // Renumber integer keys as array_merge_recursive() does unless
                // $preserve_integer_keys is set to TRUE. Note that PHP automatically
                // converts array keys that are integer strings (e.g., '1') to integers.
                if (is_int($key) && !$preserve_integer_keys) {
                    $result[] = $value;
                }
                // Recurse when both values are arrays.
                elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                    $result[$key] = self::mergeDeepArray([$result[$key], $value], $preserve_integer_keys);
                }
                // Otherwise, use the latter value, overriding any previous value.
                else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}
