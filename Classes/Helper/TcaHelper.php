<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/sync-crop-areas.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\SyncCropAreas\Helper;

use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Use this helper to get a full merged TCA base configuration for a specific TCA column
 */
class TcaHelper
{
    /**
     * To differ the column configuration and the array key "config" I use "BaseConfiguration" as wording for the
     * column root configuration ($GLOBALS['TCA'][$table]['columns'][$column])
     */
    public function getMergedColumnConfiguration(
        string $table,
        string $column,
        int $pageUid = 0,
        string $type = ''
    ): array {
        $tableConfiguration = $this->getTableConfiguration($table);
        if ($tableConfiguration === []) {
            return [];
        }

        $columnBaseConfiguration = $this->getColumnBaseConfiguration($table, $column);
        if ($columnBaseConfiguration === []) {
            return [];
        }

        $this->mergeWithTypeSpecificConfig(
            $type,
            $column,
            $tableConfiguration,
            $columnBaseConfiguration
        );

        $this->mergeWithPageTsConfig(
            $table,
            $column,
            $type,
            $pageUid,
            $columnBaseConfiguration
        );

        return $columnBaseConfiguration;
    }

    protected function mergeWithPageTsConfig(
        string $table,
        string $column,
        string $type,
        int $pageUid,
        &$columnBaseConfiguration
    ): void {
        $pageTsConfig = BackendUtility::getPagesTSconfig($pageUid);

        // FormEngineUtility::overrideFieldConf checks against "type" which is available within the "config"-part only
        $columnTsConfig = $pageTsConfig['TCEFORM.'][$table . '.'][$column . '.'] ?? [];
        $columnBaseConfiguration['config'] = FormEngineUtility::overrideFieldConf(
            $columnBaseConfiguration['config'],
            $columnTsConfig
        );

        $columnTypeTsConfig = $pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['types.'][$type . '.'] ?? [];
        $columnBaseConfiguration['config'] = FormEngineUtility::overrideFieldConf(
            $columnBaseConfiguration['config'],
            $columnTypeTsConfig
        );
    }

    public function getMergedCropVariants(string $table, string $column, int $pageUid = 0, string $type = '', int $contentElementUid = 0): array
    {
        $enabledFields = $this->getEnabledTcaFieldsForCType($type);

        // Check for flex form
        if (!in_array($column, $enabledFields, true)) {
            $record = BackendUtility::getRecord($table, $contentElementUid);

            foreach ($enabledFields as $fieldName => $fieldTca) {
                if ('flex' === $fieldTca['config']['type']) {
                    $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
                    $dataStructureIdentifier = $flexFormTools->getDataStructureIdentifier(
                        $fieldTca,
                        $table,
                        $fieldName,
                        $record
                    );
                    $dataStructure = $flexFormTools->parseDataStructureByIdentifier($dataStructureIdentifier);

                    foreach ($dataStructure['sheets'] as $sheet) {
                        $element = $sheet['ROOT']['el'][$column] ?? null;

                        if (null !== $element && 'file' === $element['config']['type']) {
                            return array_replace_recursive(
                                $this->getCropVariants(
                                    'sys_file_reference',
                                    'crop',
                                    'config/cropVariants',
                                    $pageUid
                                ),
                                (arraY)ArrayUtility::getValueByPath(
                                    $element,
                                    'config/overrideChildTca/columns/crop/config/cropVariants'
                                )
                            );
                        }
                    }
                }
            }
        }

        return array_replace_recursive(
            $this->getCropVariants(
                'sys_file_reference',
                'crop',
                'config/cropVariants',
                $pageUid
            ),
            $this->getCropVariants(
                $table,
                $column,
                'config/overrideChildTca/columns/crop/config/cropVariants',
                $pageUid,
                $type
            )
        );
    }

    protected function getCropVariants(string $table, string $column, string $path, int $pageUid = 0, string $type = ''): array
    {
        try {
            $cropVariants = (array)ArrayUtility::getValueByPath(
                $this->getMergedColumnConfiguration(
                    $table,
                    $column,
                    $pageUid,
                    $type
                ),
                $path
            );
        } catch (MissingArrayPathException $missingArrayPathException) {
            // Segment of path could not be found in array
            return [];
        } catch (\RuntimeException $runtimeException) {
            // $path is empty
            return [];
        } catch (\InvalidArgumentException $invalidArgumentException) {
            // $path is not string or array
            return [];
        }

        return $cropVariants;
    }

    /**
     * Checks TCA of $table for ctrl->type and uses this value to get entry from $record
     */
    public function getTypeOfRecord(array $record, string $table): string
    {
        $tableConfiguration = $this->getTableConfiguration($table);
        if ($tableConfiguration === []) {
            return '';
        }

        // It also checks, if key "type" is not empty
        if (!$this->tableHasTypeConfiguration($tableConfiguration)) {
            return is_array($tableConfiguration['types'] ?? null) ? (string)key($tableConfiguration['types']) : '';
        }

        $typeColumn = $tableConfiguration['ctrl']['type'];

        if (!array_key_exists($typeColumn, $record)) {
            return '';
        }

        // In case of "pages" column "doktype" can be int. Cast to string
        return $record[$typeColumn] ? (string)$record[$typeColumn] : '';
    }

    /**
     * Merge TCA config of a column with a specific TCA type configuration
     *
     * @param string $type The type like "textmedia", "tables", "images", ...
     * @param string $column The column of the type specific table configuration
     * @param array $tableConfiguration The TCA config of $GLOBALS['TCA'][$table]
     * @param array $columnBaseConfiguration The TCA config of $GLOBALS['TCA'][$table]['columns'][$column]
     */
    protected function mergeWithTypeSpecificConfig(
        string $type,
        string $column,
        array $tableConfiguration,
        array &$columnBaseConfiguration
    ): void {
        if ($type === '') {
            return;
        }

        $typeSpecificBaseConfigurationForColumn = $this->getTableTypeBaseConfigurationForColumn(
            $column,
            $type,
            $tableConfiguration
        );

        if ($typeSpecificBaseConfigurationForColumn === []) {
            return;
        }

        if (!array_key_exists('config', $typeSpecificBaseConfigurationForColumn)) {
            return;
        }

        ArrayUtility::mergeRecursiveWithOverrule(
            $columnBaseConfiguration,
            $typeSpecificBaseConfigurationForColumn,
            true,
            true,
            false
        );
    }

    protected function tableHasTypeConfiguration(array $tableConfiguration): bool
    {
        if (!array_key_exists('ctrl', $tableConfiguration)) {
            return false;
        }

        if (!is_array($tableConfiguration['ctrl'])) {
            return false;
        }

        if (!array_key_exists('type', $tableConfiguration['ctrl'])) {
            return false;
        }

        return !empty($tableConfiguration['ctrl']['type']);
    }

    protected function getColumnBaseConfiguration(string $table, string $column): array
    {
        $tableConfiguration = $this->getTableConfiguration($table);

        if (!array_key_exists('columns', $tableConfiguration)) {
            return [];
        }

        if (!array_key_exists($column, $tableConfiguration['columns'])) {
            return [];
        }

        return is_array($tableConfiguration['columns'][$column]) ? $tableConfiguration['columns'][$column] : [];
    }

    /**
     * Returns just the type-individual BaseConfiguration for table and column.
     * $GLOBALS['TCA'][$table]['types'][$type]['columnsOverrides'][$column]
     */
    protected function getTableTypeBaseConfigurationForColumn(
        string $column,
        string $type,
        array $tableConfiguration
    ): array {
        $tableTypeConfiguration = $this->getTableTypeConfigurationForType($type, $tableConfiguration);
        if ($tableTypeConfiguration === []) {
            return [];
        }

        if (!array_key_exists('columnsOverrides', $tableTypeConfiguration)) {
            return [];
        }

        if (!array_key_exists($column, $tableTypeConfiguration['columnsOverrides'])) {
            return [];
        }

        return is_array($tableTypeConfiguration['columnsOverrides'][$column])
            ? $tableTypeConfiguration['columnsOverrides'][$column]
            : [];
    }

    protected function getTableTypeConfigurationForType(string $type, array $tableConfiguration): array
    {
        $tableTypesConfiguration = $this->getTableTypesConfiguration($tableConfiguration);
        if (!array_key_exists($type, $tableTypesConfiguration)) {
            return [];
        }

        return is_array($tableTypesConfiguration[$type]) ? $tableTypesConfiguration[$type] : [];
    }

    protected function getTableTypesConfiguration(array $tableConfiguration): array
    {
        if (!array_key_exists('types', $tableConfiguration)) {
            return [];
        }

        return is_array($tableConfiguration['types']) ? $tableConfiguration['types'] : [];
    }

    protected function getTableConfiguration(string $table): array
    {
        if (!array_key_exists('TCA', $GLOBALS)) {
            return [];
        }

        if (!array_key_exists($table, $GLOBALS['TCA'])) {
            return [];
        }

        return is_array($GLOBALS['TCA'][$table]) ? $GLOBALS['TCA'][$table] : [];
    }

    public function getEnabledTcaFieldsForCType(string $CType): array
    {
        $tca = $GLOBALS['TCA']['tt_content'] ?? [];
        if (!isset($tca['types'][$CType])) {
            return [];
        }

        $fields = [];
        $typeConfig = $tca['types'][$CType];
        $showitem = $typeConfig['showitem'] ?? '';

        $items = array_filter(array_map('trim', explode(',', $showitem)));

        foreach ($items as $item) {
            // Skip dividers
            if ('--div--' === $item) {
                continue;
            }

            // Split by semicolon: fieldName;Label;Palette;Extra
            $parts = array_map('trim', explode(';', $item));
            $field = $parts[0] ?? null;
            $paletteName = $parts[2] ?? null;

            // Add the field if it exists in TCA
            if ($field && isset($tca['columns'][$field])) {
                $fields[$field] = $tca['columns'][$field];
            }

            // If palette exists, expand it
            if ($paletteName && isset($tca['palettes'][$paletteName]['showitem'])) {
                $paletteItems = array_filter(array_map('trim', explode(',', $tca['palettes'][$paletteName]['showitem'])));
                foreach ($paletteItems as $pItem) {
                    $pParts = array_map('trim', explode(';', $pItem));
                    $pField = $pParts[0] ?? null;
                    if ($pField && isset($tca['columns'][$pField])) {
                        $fields[$pField] = $tca['columns'][$pField];
                    }
                }
            }
        }

        return $fields;
    }
}
