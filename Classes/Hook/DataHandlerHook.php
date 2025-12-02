<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/sync-crop-areas.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\SyncCropAreas\Hook;

use JWeiland\SyncCropAreas\Helper\TcaHelper;
use JWeiland\SyncCropAreas\Service\UpdateCropVariantsService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Imaging\ImageManipulation\InvalidConfigurationException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Copy first found CropArea to all other CropVariants as long as selectedRatio matches
 */
class DataHandlerHook
{
    protected UpdateCropVariantsService $updateCropVariantsService;

    protected TcaHelper $tcaHelper;

    public function __construct(UpdateCropVariantsService $updateCropVariantsService, TcaHelper $tcaHelper)
    {
        $this->updateCropVariantsService = $updateCropVariantsService;
        $this->tcaHelper = $tcaHelper;
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        foreach ($dataHandler->datamap as $table => $records) {
            if ('sys_file_reference' !== $table) {
                continue;
            }

            foreach ($records as $uid => $record) {
                $sysFileReferenceIdentifier = array_key_exists($uid, $dataHandler->substNEWwithIDs)
                    ? (int) $dataHandler->substNEWwithIDs[$uid]
                    : (int) $uid;

                $sysFileReferenceRecord = BackendUtility::getRecord('sys_file_reference', $sysFileReferenceIdentifier);

                if ([] === $sysFileReferenceRecord) {
                    continue;
                }

                if (null === $sysFileReferenceRecord) {
                    continue;
                }

                if (empty($sysFileReferenceRecord['crop'])) {
                    continue;
                }

                if (empty($sysFileReferenceRecord['sync_crop_area'])) {
                    continue;
                }

                try {
                    $updatedSysFileReferenceRecord = $this->updateCropVariantsService->synchronizeCropVariants(
                        $sysFileReferenceRecord
                    );
                } catch (InvalidConfigurationException $invalidConfigurationException) {
                    continue;
                }

                if ([] === $updatedSysFileReferenceRecord) {
                    continue;
                }

                if ($sysFileReferenceRecord !== $updatedSysFileReferenceRecord) {
                    $this->updateSysFileReferenceRecord($updatedSysFileReferenceRecord);
                }
            }
        }
    }

    protected function updateSysFileReferenceRecord(array $sysFileReferenceRecord): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_reference');
        $connection->update(
            'sys_file_reference',
            [
                'crop' => $sysFileReferenceRecord['crop'],
            ],
            [
                'uid' => $sysFileReferenceRecord['uid'],
            ]
        );
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
