# TYPO3 Extension `sync_crop_areas`

[![Packagist][packagist-logo-stable]][extension-packagist-url]
[![Latest Stable Version][extension-build-shield]][extension-ter-url]
[![Total Downloads][extension-downloads-badge]][extension-packagist-url]
[![Monthly Downloads][extension-monthly-downloads]][extension-packagist-url]
[![TYPO3 13.4][TYPO3-shield]][TYPO3-13-url]

![Build Status](https://github.com/jweiland-net/sync_crop_areas/actions/workflows/ci.yml/badge.svg)
## What does it do?
If you want to copy the crop area of first crop variant over to all other crop
variants. E.g. you have defined 4 crop variants "Desktop", "Landscape", "Tablet" and "Mobile". If you move
the crop area of "Desktop", you have to do so with "Landscape", "Tablet" and "Mobile", too, which is a lot of work
for your editors. Further it is hard to match the exact position (cropArea) in all other cropVariants.

## Installation

### Installation using Composer

Run the following command within your Composer based TYPO3 project:

```
composer require jweiland/sync-crop-areas
```

### Installation using Extension Manager

Login into TYPO3 Backend of your project and click on `Extensions` in the left menu.
Press the `Retrieve/Update` button and search for the extension key `sync_crop_areas`.
Import the extension from TER (TYPO3 Extension Repository)

## Configuration

There is no configuration for this extension.
Just save the content (tt_content) record to sync the cropAreas over all cropVariants

## Support

Free Support is available via [GitHub Issue Tracker](https://github.com/jweiland-net/sync_crop_areas/issues).

For commercial support, please contact us at [support@jweiland.net](support@jweiland.net).

<!-- MARKDOWN LINKS & IMAGES -->

[extension-build-shield]: https://poser.pugx.org/jweiland/sync-crop-areas/v/stable.svg?style=for-the-badge

[extension-downloads-badge]: https://poser.pugx.org/jweiland/sync-crop-areas/d/total.svg?style=for-the-badge

[extension-monthly-downloads]: https://poser.pugx.org/jweiland/sync-crop-areas/d/monthly?style=for-the-badge

[extension-ter-url]: https://extensions.typo3.org/extension/sync_crop_areas/

[extension-packagist-url]: https://packagist.org/packages/jweiland/sync-crop-areas/

[packagist-logo-stable]: https://img.shields.io/badge/--grey.svg?style=for-the-badge&logo=packagist&logoColor=white

[TYPO3-13-url]: https://get.typo3.org/version/13

[TYPO3-shield]: https://img.shields.io/badge/TYPO3-13.4-green.svg?style=for-the-badge&logo=typo3
