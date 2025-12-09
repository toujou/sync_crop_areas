<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/sync-crop-areas.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\SyncCropAreas\Service;

use JWeiland\SyncCropAreas\Helper\TcaHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariant;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Imaging\ImageManipulation\InvalidConfigurationException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Use this service to synchronize first found cropVariants to the other defined cropVariants
 */
class UpdateCropVariantsService
{
    protected TcaHelper $tcaHelper;

    /**
     * Default element configuration
     * SF: Copied from ImageManipulationElement
     *
     * @var array
     */
    protected static $defaultConfig = [
        'file_field' => 'uid_local',
        'allowedExtensions' => null, // default: $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        'cropVariants' => [
            'default' => [
                'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.crop_variant.default',
                'allowedAspectRatios' => [
                    '16:9' => [
                        'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.ratio.16_9',
                        'value' => 16 / 9,
                    ],
                    '3:2' => [
                        'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.ratio.3_2',
                        'value' => 3 / 2,
                    ],
                    '4:3' => [
                        'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.ratio.4_3',
                        'value' => 4 / 3,
                    ],
                    '1:1' => [
                        'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.ratio.1_1',
                        'value' => 1.0,
                    ],
                    'NaN' => [
                        'title' => 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard.ratio.free',
                        'value' => 0.0,
                    ],
                ],
                'selectedRatio' => 'NaN',
                'cropArea' => [
                    'x' => 0.0,
                    'y' => 0.0,
                    'width' => 1.0,
                    'height' => 1.0,
                ],
            ],
        ],
    ];

    public function __construct(TcaHelper $tcaHelper)
    {
        $this->tcaHelper = $tcaHelper;
    }

    /**
     * Copy first found CropArea to all other CropVariants as long as selectedRatio matches
     *
     * @param array $sysFileReference The full sys_file_reference record to update the crop column for.
     * @return array The sys_file_reference record with updated crop column. It's up to you to store this record now or not.
     * @throws InvalidConfigurationException
     */
    public function synchronizeCropVariants(array $sysFileReference): array
    {
        // After that we can be sure that tablenames, fieldname, crop and pid are available and filled with data
        if (!$this->isValidSysFileReference($sysFileReference)) {
            return $sysFileReference;
        }

        // Get TCA and PageTSConfig merged CropVariants
        $mergedCropVariants = $this->tcaHelper->getMergedCropVariants(
            $sysFileReference['tablenames'],
            $sysFileReference['fieldname'],
            $sysFileReference['pid'],
            $this->tcaHelper->getTypeOfRecord(
                $this->getForeignRecord($sysFileReference['tablenames'], (int) $sysFileReference['uid_foreign']),
                $sysFileReference['tablenames']
            ),
            (int) $sysFileReference['uid_foreign']
        );

        try {
            $persistedCropVariants = CropVariantCollection::create(
                $sysFileReference['crop'],
                $this->populateConfiguration(['cropVariants' => $mergedCropVariants])['cropVariants']
            )->asArray();

            if (count($persistedCropVariants) <= 1) {
                return $sysFileReference;
            }
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            return $sysFileReference;
        }

        $firstPersistedCropVariantConfiguration = current($persistedCropVariants);

        $updatedCropVariants = [];
        foreach ($persistedCropVariants as $name => $persistedCropVariantConfiguration) {
            if (
                $persistedCropVariantConfiguration !== $firstPersistedCropVariantConfiguration &&
                $this->isSelectedRatioAvailableInCurrentCropVariantConfiguration(
                    $persistedCropVariantConfiguration,
                    $firstPersistedCropVariantConfiguration
                )
            ) {
                $persistedCropVariantConfiguration['selectedRatio'] = $firstPersistedCropVariantConfiguration['selectedRatio'];
                $persistedCropVariantConfiguration['cropArea'] = $firstPersistedCropVariantConfiguration['cropArea'];
            }

            $updatedCropVariants[] = CropVariant::createFromConfiguration($name, $persistedCropVariantConfiguration);
        }

        $updatedCropVariantCollection = GeneralUtility::makeInstance(
            CropVariantCollection::class,
            $updatedCropVariants
        );

        $sysFileReference['crop'] = (string) $updatedCropVariantCollection;

        return $sysFileReference;
    }

    /**
     * SF: This is a copy from ImageManipulationElement, because I can't access the method as it is declared
     * as protected.
     *
     * @throws InvalidConfigurationException
     */
    protected function populateConfiguration(array $baseConfiguration): array
    {
        $defaultConfig = self::$defaultConfig;

        // If ratios are set do not add default options
        if (isset($baseConfiguration['cropVariants'])) {
            unset($defaultConfig['cropVariants']);
        }

        $config = array_replace_recursive($defaultConfig, $baseConfiguration);

        if (!is_array($config['cropVariants'])) {
            throw new InvalidConfigurationException('Crop variants configuration must be an array', 1485377267);
        }

        $cropVariants = [];
        foreach ($config['cropVariants'] as $id => $cropVariant) {
            // Filter allowed aspect ratios
            $cropVariant['allowedAspectRatios'] = array_filter($cropVariant['allowedAspectRatios'] ?? [], static function ($aspectRatio) {
                return !(bool)($aspectRatio['disabled'] ?? false);
            });

            // Ignore disabled crop variants
            if (!empty($cropVariant['disabled'])) {
                continue;
            }

            if (empty($cropVariant['allowedAspectRatios'])) {
                throw new InvalidConfigurationException('Crop variants configuration ' . $id . ' contains no allowed aspect ratios', 1620147893);
            }

            // Enforce a crop area (default is full image)
            if (empty($cropVariant['cropArea'])) {
                $cropVariant['cropArea'] = Area::createEmpty()->asArray();
            }

            $cropVariants[$id] = $cropVariant;
        }

        $config['cropVariants'] = $cropVariants;

        // By default, we allow all image extensions that can be handled by the GFX functionality
        $config['allowedExtensions'] ??= $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];

        return $config;
    }

    protected function isValidSysFileReference(array $sysFileReference): bool
    {
        if (!array_key_exists('sync_crop_area', $sysFileReference)) {
            return false;
        }

        if ((int)$sysFileReference['sync_crop_area'] === 0) {
            return false;
        }

        if (!array_key_exists('crop', $sysFileReference)) {
            return false;
        }

        if ($sysFileReference['crop'] === '') {
            return false;
        }

        if (!array_key_exists('tablenames', $sysFileReference)) {
            return false;
        }

        if ($sysFileReference['tablenames'] === '') {
            return false;
        }

        if (!array_key_exists('fieldname', $sysFileReference)) {
            return false;
        }

        if ($sysFileReference['fieldname'] === '') {
            return false;
        }

        if (!array_key_exists('uid_foreign', $sysFileReference)) {
            return false;
        }

        if ((int)$sysFileReference['uid_foreign'] === 0) {
            return false;
        }

        if (!array_key_exists('pid', $sysFileReference)) {
            return false;
        }

        if ((int)$sysFileReference['pid'] === 0) {
            return false;
        }

        return true;
    }

    protected function getForeignRecord(string $table, int $uid): array
    {
        return BackendUtility::getRecord($table, $uid) ?? [];
    }

    protected function isSelectedRatioAvailableInCurrentCropVariantConfiguration(
        array $currentPersistedCropVariantConfiguration,
        array $firstPersistedCropVariantConfiguration
    ): bool {
        return array_key_exists(
            $firstPersistedCropVariantConfiguration['selectedRatio'],
            $currentPersistedCropVariantConfiguration['allowedAspectRatios']
        );
    }
}
